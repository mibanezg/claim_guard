<?php

namespace App\Exports;

use App\Models\ContractLetter;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LettersExport implements
    FromCollection, WithHeadings, WithMapping,
    WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(private ?int $contractId = null) {}

    public function collection()
    {
        return ContractLetter::with(['contract', 'fromCompany', 'toCompany'])
            ->when($this->contractId, fn ($q) => $q->where('contract_id', $this->contractId))
            ->orderBy('issued_at')
            ->get();
    }

    public function title(): string { return 'Correspondencia'; }

    public function headings(): array
    {
        return [
            'N° Carta', 'Contrato', 'Tipo', 'Asunto',
            'De', 'Para',
            'Emitida', 'Recibida', 'Vencimiento', 'Días Plazo',
            'Estado', 'Generada por IA',
        ];
    }

    public function map($l): array
    {
        return [
            $l->letter_number,
            $l->contract?->number,
            ContractLetter::TYPE_LABELS[$l->type] ?? $l->type,
            $l->subject,
            $l->fromCompany?->name,
            $l->toCompany?->name,
            $l->issued_at?->format('d/m/Y'),
            $l->received_at?->format('d/m/Y'),
            $l->response_deadline?->format('d/m/Y'),
            $l->response_days,
            ucfirst($l->status),
            $l->ai_generated ? 'Sí' : 'No',
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
