<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Models\ContractMilestone;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportPrimaveraJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        private Contract $contract,
        private string   $storagePath,
        private int      $userId,
    ) {}

    public function handle(): void
    {
        $fullPath = Storage::path($this->storagePath);

        if (!file_exists($fullPath)) {
            Log::error("ImportPrimaveraJob: archivo no encontrado en {$fullPath}");
            return;
        }

        $content  = file_get_contents($fullPath);
        $imported = 0;
        $updated  = 0;

        // Detectar formato: XER (texto tabulado) o XML
        if (str_starts_with(ltrim($content), '<?xml') || str_starts_with(ltrim($content), '<')) {
            [$imported, $updated] = $this->parseXml($content);
        } else {
            [$imported, $updated] = $this->parseXer($content);
        }

        $this->contract->update(['primavera_imported_at' => now()]);
        Storage::delete($this->storagePath);

        Log::info("ImportPrimaveraJob completado: contrato {$this->contract->id} — {$imported} nuevos, {$updated} actualizados.");
    }

    // -----------------------------------------------------------------------
    // Formato XER (texto plano con secciones tabuladas)
    // -----------------------------------------------------------------------
    private function parseXer(string $content): array
    {
        $imported = 0;
        $updated  = 0;
        $tasks    = [];
        $headers  = [];
        $inTask   = false;

        foreach (explode("\n", $content) as $line) {
            $line = rtrim($line, "\r");

            if (str_starts_with($line, '%T')) {
                $section = trim(substr($line, 2));
                $inTask  = ($section === 'TASK');
                $headers = [];
                continue;
            }

            if (!$inTask) continue;

            if (str_starts_with($line, '%F')) {
                $headers = explode("\t", trim(substr($line, 2)));
                continue;
            }

            if (str_starts_with($line, '%R') && !empty($headers)) {
                $values = explode("\t", trim(substr($line, 2)));
                // Normalize to header length to survive extra/missing trailing tabs
                $values = array_pad(array_slice($values, 0, count($headers)), count($headers), '');
                $tasks[] = array_combine($headers, $values);
            }
        }

        foreach ($tasks as $task) {
            $result = $this->upsertMilestone([
                'external_id'         => $task['task_id'] ?? ($task['task_code'] ?? null),
                'name'                => $task['task_name'] ?? 'Sin nombre',
                'planned_date'        => $this->parseDate($task['target_end_date'] ?? ($task['target_start_date'] ?? null)),
                'actual_date'         => $this->parseDate($task['act_end_date'] ?? null),
                'progress_percentage' => (int) round(($task['phys_complete_pct'] ?? $task['remain_drtn_hr_cnt'] ?? 0)),
                'is_critical'         => isset($task['driving_path_flag']) && $task['driving_path_flag'] === 'Y',
            ]);
            $result === 'created' ? $imported++ : $updated++;
        }

        return [$imported, $updated];
    }

    // -----------------------------------------------------------------------
    // Formato XML de Primavera P6
    // -----------------------------------------------------------------------
    private function parseXml(string $content): array
    {
        $imported = 0;
        $updated  = 0;

        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            Log::error("ImportPrimaveraJob: XML inválido para contrato {$this->contract->id}");
            return [0, 0];
        }

        // Primavera XML puede tener activities bajo Project/Activity o directamente
        $activities = $xml->xpath('//Activity') ?: [];

        foreach ($activities as $act) {
            $result = $this->upsertMilestone([
                'external_id'         => (string) ($act->ObjectId ?? $act->Id ?? ''),
                'name'                => (string) ($act->Name ?? 'Sin nombre'),
                'planned_date'        => $this->parseDate((string) ($act->PlannedFinishDate ?? $act->StartDate ?? '')),
                'actual_date'         => $this->parseDate((string) ($act->ActualFinishDate ?? '')),
                'progress_percentage' => (int) round((float) ($act->PercentComplete ?? 0)),
                'is_critical'         => strtolower((string) ($act->Critical ?? 'false')) === 'true',
            ]);
            $result === 'created' ? $imported++ : $updated++;
        }

        return [$imported, $updated];
    }

    // -----------------------------------------------------------------------
    // Upsert por external_id + source=primavera
    // -----------------------------------------------------------------------
    private function upsertMilestone(array $data): string
    {
        if (!$data['planned_date'] || empty($data['external_id'])) return 'skip';

        $status = $this->deriveStatus(
            $data['progress_percentage'],
            $data['planned_date'],
            $data['actual_date']
        );

        $existing = ContractMilestone::withTrashed()
            ->where('contract_id', $this->contract->id)
            ->where('external_id', $data['external_id'])
            ->where('source', 'primavera')
            ->first();

        if ($existing) {
            $existing->restore();
            $existing->update([
                'name'                => $data['name'],
                'planned_date'        => $data['planned_date'],
                'actual_date'         => $data['actual_date'],
                'progress_percentage' => $data['progress_percentage'],
                'is_critical'         => $data['is_critical'],
                'status'              => $status,
            ]);
            return 'updated';
        }

        ContractMilestone::create([
            'contract_id'            => $this->contract->id,
            'name'                   => $data['name'],
            'planned_date'           => $data['planned_date'],
            'actual_date'            => $data['actual_date'],
            'progress_percentage'    => $data['progress_percentage'],
            'is_critical'            => $data['is_critical'],
            'generates_notification' => false,
            'status'                 => $status,
            'source'                 => 'primavera',
            'external_id'            => $data['external_id'],
        ]);

        return 'created';
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ImportPrimaveraJob falló para contrato {$this->contract->id}: {$e->getMessage()}");
        Storage::delete($this->storagePath);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (empty($value) || $value === '' || str_contains((string) $value, 'NA')) return null;
        try {
            return Carbon::parse((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    private function deriveStatus(int $pct, Carbon $plannedDate, ?Carbon $actualDate): string
    {
        if ($pct >= 100) return 'completado';
        if ($pct > 0)    return 'en_progreso';
        if ($plannedDate->isPast() && !$actualDate) return 'atrasado';
        return 'pendiente';
    }
}
