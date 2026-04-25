<?php

namespace App\Exports;

use App\Models\Contract;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ContractsExport implements
    FromCollection, WithHeadings, WithMapping,
    WithStyles, WithTitle, ShouldAutoSize
{
    public function collection()
    {
        return Contract::with(['mandante', 'contractor', 'latestRiskScore',
                               'events', 'letters', 'changeOrders'])
            ->whereIn('status', ['vigente', 'suspendido', 'en_disputa', 'terminado'])
            ->orderBy('number')
            ->get();
    }

    public function title(): string
    {
        return 'Estado Contratos';
    }

    public function headings(): array
    {
        return [
            'N° Contrato', 'Nombre', 'Tipo', 'Estado',
            'Mandante', 'Contratista',
            'Monto Original', 'Monto Vigente', 'Moneda',
            'Inicio Contractual', 'Término Contractual', 'Término Proyectado',
            'Días Desviación',
            'Eventos', 'Cartas', 'OC',
            'Riesgo', 'Score Riesgo',
        ];
    }

    public function map($c): array
    {
        $desvio = null;
        if ($c->projected_end_date && $c->contractual_end_date) {
            $desvio = $c->contractual_end_date->diffInDays($c->projected_end_date, false);
        }

        return [
            $c->number,
            $c->name,
            Contract::TYPE_LABELS[$c->type]     ?? $c->type,
            Contract::STATUS_LABELS[$c->status] ?? $c->status,
            $c->mandante?->name,
            $c->contractor?->name,
            round($c->original_amount / 100, 0),
            round($c->current_amount  / 100, 0),
            $c->currency,
            $c->contractual_start_date?->format('d/m/Y'),
            $c->contractual_end_date?->format('d/m/Y'),
            $c->projected_end_date?->format('d/m/Y'),
            $desvio,
            $c->events->count(),
            $c->letters->count(),
            $c->changeOrders->count(),
            $c->latestRiskScore?->score_level,
            $c->latestRiskScore?->score_value,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2A6496']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
