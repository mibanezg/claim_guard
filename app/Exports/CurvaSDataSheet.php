<?php

namespace App\Exports;

use App\Models\Contract;
use App\Models\ContractMilestone;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CurvaSDataSheet implements
    FromCollection, WithHeadings, WithMapping,
    WithStyles, WithTitle, ShouldAutoSize
{
    private Contract $contract;

    public function __construct(private int $contractId)
    {
        $this->contract = Contract::findOrFail($contractId);
    }

    public function title(): string { return 'Curva S'; }

    public function collection()
    {
        return ContractMilestone::where('contract_id', $this->contractId)
            ->orderBy('planned_date')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Hito', 'Fecha Planificada', 'Fecha Real',
            '% Avance Plan', '% Avance Real',
            'Estado', 'Es Crítico', 'Fuente',
        ];
    }

    public function map($m): array
    {
        // Avance planificado: 100% en fecha planificada, 0% antes
        $planPct = $m->planned_date->isPast() ? 100 : 0;

        return [
            $m->name,
            $m->planned_date->format('d/m/Y'),
            $m->actual_date?->format('d/m/Y') ?? '—',
            $planPct,
            $m->progress_percentage,
            ContractMilestone::STATUS_LABELS[$m->status] ?? $m->status,
            $m->is_critical ? 'Sí' : 'No',
            ucfirst($m->source),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2A6496']],
            ],
        ];
    }
}
