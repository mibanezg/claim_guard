<?php

namespace App\Exports;

use App\Models\ContractualEvent;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EventsExport implements
    FromCollection, WithHeadings, WithMapping,
    WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(private ?int $contractId = null) {}

    public function collection()
    {
        return ContractualEvent::with('contract')
            ->when($this->contractId, fn ($q) => $q->where('contract_id', $this->contractId))
            ->orderBy('occurred_at')
            ->get();
    }

    public function title(): string { return 'Eventos Contractuales'; }

    public function headings(): array
    {
        return [
            'Contrato', 'N° Contrato', 'Fecha', 'Tipo',
            'Descripción', 'Responsable',
            'Impacto Días', 'Impacto Costo',
            'Estado Resolución', 'Notas Resolución',
        ];
    }

    public function map($e): array
    {
        return [
            $e->contract?->name,
            $e->contract?->number,
            $e->occurred_at->format('d/m/Y'),
            ContractualEvent::TYPE_LABELS[$e->type] ?? $e->type,
            $e->description,
            ucfirst($e->responsible_party),
            $e->schedule_impact_days,
            round(abs($e->cost_impact) / 100, 0),
            ucfirst($e->resolution_status),
            $e->resolution_notes,
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
