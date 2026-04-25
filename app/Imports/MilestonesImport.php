<?php

namespace App\Imports;

use App\Models\Contract;
use App\Models\ContractMilestone;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class MilestonesImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function __construct(private Contract $contract) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            if (empty($row['nombre'])) continue;

            $planned = $this->parseDate($row['fecha_planificada'] ?? null);
            if (!$planned) continue;

            ContractMilestone::updateOrCreate(
                [
                    'contract_id' => $this->contract->id,
                    'name'        => trim($row['nombre']),
                ],
                [
                    'description'            => $row['descripcion'] ?? null,
                    'planned_date'           => $planned,
                    'actual_date'            => $this->parseDate($row['fecha_real'] ?? null),
                    'progress_percentage'    => (int) ($row['avance_porcentaje'] ?? 0),
                    'is_critical'            => $this->parseBool($row['critico'] ?? false),
                    'generates_notification' => $this->parseBool($row['genera_notificacion'] ?? false),
                    'status'                 => $this->parseStatus($row['estado'] ?? 'pendiente'),
                    'source'                 => 'manual',
                ]
            );
        }
    }

    public function rules(): array
    {
        return [];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (empty($value)) return null;
        try {
            if (is_numeric($value)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value));
            }
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function parseBool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        return in_array(strtolower((string) $value), ['1', 'si', 'sí', 'yes', 'true', 'x']);
    }

    private function parseStatus(string $value): string
    {
        $map = [
            'pendiente'   => 'pendiente',
            'en progreso' => 'en_progreso',
            'en_progreso' => 'en_progreso',
            'completado'  => 'completado',
            'atrasado'    => 'atrasado',
        ];
        return $map[strtolower(trim($value))] ?? 'pendiente';
    }
}
