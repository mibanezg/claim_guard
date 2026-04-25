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

class ImportMicrosoftProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        private Contract $contract,
        private string   $storagePath,   // ruta relativa en storage
        private int      $userId,
    ) {}

    public function handle(): void
    {
        $fullPath = Storage::path($this->storagePath);

        if (!file_exists($fullPath)) {
            Log::error("ImportMicrosoftProjectJob: archivo no encontrado en {$fullPath}");
            return;
        }

        $xml = simplexml_load_file($fullPath, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            Log::error("ImportMicrosoftProjectJob: XML inválido para contrato {$this->contract->id}");
            return;
        }

        $imported = 0;
        $updated  = 0;

        foreach ($xml->Tasks->Task ?? [] as $task) {
            // Ignorar la tarea raíz (UID=0) y tareas de resumen sin nombre real
            $uid  = (int)    $task->UID;
            $name = trim((string) $task->Name);

            if ($uid === 0 || $name === '') continue;

            // Solo importar hitos (Milestone=1) o todas las tareas según preferencia
            // Importamos todas para dar visibilidad completa del programa
            $isMilestone   = (int) $task->Milestone   === 1;
            $isSummary     = (int) $task->Summary      === 1;
            $isCritical    = (int) $task->Critical     === 1;
            $pctComplete   = (int) ($task->PercentComplete ?? 0);

            $plannedStart  = $this->parseDate((string) ($task->Start          ?? ''));
            $plannedFinish = $this->parseDate((string) ($task->Finish         ?? ''));
            $actualStart   = $this->parseDate((string) ($task->ActualStart    ?? ''));
            $actualFinish  = $this->parseDate((string) ($task->ActualFinish   ?? ''));

            // Usamos la fecha de fin como la fecha representativa del hito/tarea
            $plannedDate = $plannedFinish ?? $plannedStart;
            if (!$plannedDate) continue;

            $actualDate = $actualFinish ?? $actualStart;

            $status = $this->deriveStatus($pctComplete, $plannedDate, $actualDate);

            // updateOrCreate basado en external_id (UID) y contrato — NUNCA destruye datos manuales
            $existing = ContractMilestone::withTrashed()
                ->where('contract_id', $this->contract->id)
                ->where('external_id', (string) $uid)
                ->where('source', 'ms_project')
                ->first();

            if ($existing) {
                // Solo actualiza fechas, avance y estado — preserva datos contractuales
                $existing->restore(); // por si fue soft-deleted y vuelve a aparecer
                $existing->update([
                    'name'                => $name,
                    'planned_date'        => $plannedDate,
                    'actual_date'         => $actualDate,
                    'progress_percentage' => $pctComplete,
                    'is_critical'         => $isCritical,
                    'status'              => $status,
                ]);
                $updated++;
            } else {
                ContractMilestone::create([
                    'contract_id'            => $this->contract->id,
                    'name'                   => $name,
                    'planned_date'           => $plannedDate,
                    'actual_date'            => $actualDate,
                    'progress_percentage'    => $pctComplete,
                    'is_critical'            => $isCritical,
                    'generates_notification' => $isMilestone, // los hitos MS Project generan notificación por defecto
                    'status'                 => $status,
                    'source'                 => 'ms_project',
                    'external_id'            => (string) $uid,
                ]);
                $imported++;
            }
        }

        // Registra timestamp de importación en el contrato
        $this->contract->update(['ms_project_imported_at' => now()]);

        // Limpia el archivo temporal
        Storage::delete($this->storagePath);

        Log::info("ImportMicrosoftProjectJob completado: contrato {$this->contract->id} — {$imported} nuevos, {$updated} actualizados.");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ImportMicrosoftProjectJob falló para contrato {$this->contract->id}: {$e->getMessage()}");
        Storage::delete($this->storagePath);
    }

    private function parseDate(string $value): ?Carbon
    {
        if (empty($value) || str_starts_with($value, 'NA')) return null;
        try {
            return Carbon::parse($value);
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
