<?php

namespace App\Exports;

use App\Models\ChangeOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ChangeOrdersExport implements
    FromCollection, WithHeadings, WithMapping,
    WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(private ?int $contractId = null) {}

    public function collection()
    {
        return ChangeOrder::with('contract')
            ->when($this->contractId, fn ($q) => $q->where('contract_id', $this->contractId))
            ->orderBy('created_at')
            ->get();
    }

    public function title(): string { return 'Órdenes de Cambio'; }

    public function headings(): array
    {
        return [
            'N° OC', 'Contrato', 'Solicitante',
            'Descripción',
            'Impacto Días', 'Impacto Costo', 'Moneda',
            'Estado', 'Aprobada',
        ];
    }

    public function map($oc): array
    {
        return [
            $oc->request_number,
            $oc->contract?->number,
            ucfirst($oc->requested_by_party),
            $oc->description,
            $oc->schedule_impact_days,
            round(abs($oc->cost_impact) / 100, 0),
            $oc->contract?->currency ?? 'CLP',
            ChangeOrder::STATUS_LABELS[$oc->status] ?? $oc->status,
            $oc->approved_at?->format('d/m/Y'),
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
