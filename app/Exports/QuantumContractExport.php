<?php

namespace App\Exports;

use App\Models\Contract;
use App\Models\EventCostItem;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QuantumContractExport implements WithMultipleSheets
{
    public function __construct(private Contract $contract) {}

    public function sheets(): array
    {
        $contract = $this->contract->load([
            'mandante', 'contractor',
            'events' => fn ($q) => $q->has('costItems')->with('costItems')->orderByDesc('occurred_at'),
        ]);

        $sheets = [new QuantumSummarySheet($contract)];

        foreach ($contract->events as $event) {
            $sheets[] = new QuantumEventSheet($event, $contract->currency);
        }

        return $sheets;
    }
}

// ──────────────────────────────────────────────
// Hoja 1: Resumen del contrato
// ──────────────────────────────────────────────
class QuantumSummarySheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    private array $rows = [];

    public function __construct(private Contract $contract) {}

    public function title(): string { return 'Resumen'; }

    public function columnWidths(): array
    {
        return ['A' => 10, 'B' => 42, 'C' => 14, 'D' => 18, 'E' => 18, 'F' => 12];
    }

    public function array(): array { return []; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $sheet    = $e->sheet->getDelegate();
                $contract = $this->contract;
                $currency = $contract->currency;
                $row      = 1;

                // Título
                $sheet->mergeCells("A{$row}:F{$row}");
                $sheet->setCellValue("A{$row}", "RESUMEN QUANTUM — {$contract->number}");
                $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A5C']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(28);
                $row++;

                foreach ([
                    ['Contrato:',   $contract->name],
                    ['Mandante:',   $contract->mandante?->name ?? '—'],
                    ['Contratista:',$contract->contractor?->name ?? '—'],
                    ['Moneda:',     $currency],
                    ['Generado:',   now()->format('d/m/Y H:i')],
                ] as [$label, $value]) {
                    $sheet->setCellValue("A{$row}", $label);
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(9);
                    $sheet->mergeCells("B{$row}:F{$row}");
                    $sheet->setCellValue("B{$row}", $value);
                    $sheet->getStyle("A{$row}:F{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBF3FB');
                    $row++;
                }
                $row++;

                // Encabezados tabla
                $headers = ['N°', 'Evento / Descripción', 'Fecha', "Quantum ({$currency})", "Impacto ({$currency})", 'Conciliado'];
                foreach ($headers as $col => $h) {
                    $sheet->setCellValue(chr(65 + $col) . $row, $h);
                }
                $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2A6496']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $row++;

                // Filas por evento
                $grandQuantum = 0;
                $grandImpact  = 0;
                $n = 1;
                foreach ($contract->events as $event) {
                    $quantum    = $event->costItems->sum('amount') / 100;
                    $impact     = $event->cost_impact / 100;
                    $reconciled = abs($quantum - $impact) < 1;
                    $grandQuantum += $quantum;
                    $grandImpact  += $impact;

                    $sheet->setCellValue("A{$row}", $n++);
                    $sheet->setCellValue("B{$row}", "{$event->type_label}: {$event->description}");
                    $sheet->setCellValue("C{$row}", $event->occurred_at?->format('d/m/Y'));
                    $sheet->setCellValue("D{$row}", $quantum);
                    $sheet->setCellValue("E{$row}", $impact);
                    $sheet->setCellValue("F{$row}", $reconciled ? 'Sí ✓' : 'No');

                    $sheet->getStyle("D{$row}:E{$row}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("A{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("A{$row}:B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("F{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $reconciled ? '1A6B3C' : '8B3A00']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $reconciled ? 'D4EDDA' : 'FFF3CD']],
                    ]);
                    $bgColor = $n % 2 === 0 ? 'F5F9FD' : 'FFFFFF';
                    $sheet->getStyle("A{$row}:E{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bgColor);
                    $sheet->getStyle("A{$row}:F{$row}")->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CCDDEE');
                    $row++;
                }

                // Total
                $row++;
                $sheet->mergeCells("A{$row}:C{$row}");
                $sheet->setCellValue("A{$row}", 'TOTAL QUANTUM DEL CONTRATO');
                $sheet->setCellValue("D{$row}", $grandQuantum);
                $sheet->setCellValue("E{$row}", $grandImpact);
                $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A5C']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);
                $sheet->getStyle("D{$row}:E{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getRowDimension($row)->setRowHeight(22);

                $sheet->freezePane('A8');
            },
        ];
    }
}

// ──────────────────────────────────────────────
// Hoja por evento
// ──────────────────────────────────────────────
class QuantumEventSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    public function __construct(
        private $event,
        private string $currency
    ) {}

    public function title(): string
    {
        // Nombre de hoja: tipo + fecha (máx 31 chars para Excel)
        $label = substr($this->event->type_label, 0, 15) . ' ' . ($this->event->occurred_at?->format('d-m-Y') ?? '');
        return substr(preg_replace('/[\/\\\?\*\[\]:]/', '-', $label), 0, 31);
    }

    public function columnWidths(): array
    {
        return ['A' => 8, 'B' => 46, 'C' => 12, 'D' => 12, 'E' => 18, 'F' => 18];
    }

    public function array(): array { return []; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                // Reutiliza la misma lógica que QuantumEventExport
                $export = new QuantumEventExport($this->event, $this->currency);
                // Acceder al método privado via reflexión no es limpio;
                // en su lugar delegamos construyendo el export y copiando la hoja
                // Solución: duplicar la lógica de escritura aquí.
                // Para no duplicar código, construimos un export temporal y usamos su writeSheet
                $sheet = $e->sheet->getDelegate();
                $this->writeSheet($sheet);
            },
        ];
    }

    private function writeSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        // Misma lógica de escritura que QuantumEventExport::writeSheet
        // Cargamos el evento con sus relaciones
        $event    = $this->event;
        $contract = $event->contract;
        $currency = $this->currency;
        $row      = 1;

        // Encabezado evento
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", "QUANTUM — {$event->type_label}");
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2A6496']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(24);
        $row++;

        foreach ([
            ['Contrato:', $contract->number . ' — ' . $contract->name],
            ['Evento:',   $event->occurred_at?->format('d/m/Y') . ' — ' . $event->description],
        ] as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(9);
            $sheet->mergeCells("B{$row}:F{$row}");
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}:F{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBF3FB');
            $row++;
        }
        $row++;

        // Encabezados columna
        $headers = ['N°', 'Descripción', 'Unidad', 'Cantidad', "P. Unit. ({$currency})", "Total ({$currency})"];
        foreach ($headers as $col => $h) {
            $sheet->setCellValue(chr(65 + $col) . $row, $h);
        }
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A86AE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        $items          = $event->costItems->sortBy('cost_category');
        $categoryLabels = EventCostItem::CATEGORY_LABELS;
        $directCats     = EventCostItem::DIRECT_CATEGORIES;
        $indirectCats   = EventCostItem::INDIRECT_CATEGORIES;

        $directTotal = $indirectTotal = $profitTotal = 0;
        $n = 1; $currentCat = null;

        foreach ($items as $item) {
            if ($item->cost_category !== $currentCat) {
                $currentCat = $item->cost_category;
                $sheet->mergeCells("A{$row}:F{$row}");
                $sheet->setCellValue("A{$row}", strtoupper($categoryLabels[$currentCat] ?? $currentCat));
                $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1A3A5C']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6E8F7']],
                ]);
                $row++;
            }

            $sheet->setCellValue("A{$row}", $n++);
            $sheet->setCellValue("B{$row}", $item->description);
            $sheet->setCellValue("C{$row}", $item->unit);
            $sheet->setCellValue("D{$row}", $item->quantity);
            $sheet->setCellValue("E{$row}", $item->unit_cost / 100);
            $sheet->setCellValue("F{$row}", $item->amount / 100);
            $sheet->getStyle("D{$row}:F{$row}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("D{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $bgColor = $n % 2 === 0 ? 'F5F9FD' : 'FFFFFF';
            $sheet->getStyle("A{$row}:F{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bgColor);
            $sheet->getStyle("A{$row}:F{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CCDDEE');

            if (in_array($item->cost_category, $directCats))    $directTotal   += $item->amount;
            elseif (in_array($item->cost_category, $indirectCats)) $indirectTotal += $item->amount;
            elseif ($item->cost_category === 'profit')            $profitTotal   += $item->amount;
            $row++;
        }

        $grandTotal = $items->sum('amount');
        $costImpact = $event->cost_impact;

        $row++;
        foreach ([
            ['Costos directos',   $directTotal / 100],
            ['Costos indirectos', $indirectTotal / 100],
            ['Utilidad',          $profitTotal / 100],
        ] as [$label, $value]) {
            $sheet->mergeCells("B{$row}:E{$row}");
            $sheet->setCellValue("B{$row}", $label);
            $sheet->setCellValue("F{$row}", $value);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(9);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        // Total
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("A{$row}", 'TOTAL QUANTUM');
        $sheet->setCellValue("F{$row}", $grandTotal / 100);
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A5C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        // Conciliación
        $diff = $grandTotal - $costImpact;
        $reconciled = abs($diff) < 100;
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("A{$row}", 'Impacto registrado en evento');
        $sheet->setCellValue("F{$row}", $costImpact / 100);
        $sheet->getStyle("A{$row}:F{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBF3FB');
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("A{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $row++;

        $color = $reconciled ? '1A6B3C' : '8B3A00';
        $bg    = $reconciled ? 'D4EDDA' : 'FFF3CD';
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("A{$row}", $reconciled ? 'Conciliado ✓' : 'Diferencia');
        $sheet->setCellValue("F{$row}", $reconciled ? '—' : $diff / 100);
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => $color]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        if (!$reconciled) $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');

        $sheet->freezePane('A6');
    }
}
