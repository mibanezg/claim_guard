<?php

namespace App\Exports;

use App\Models\ContractualEvent;
use App\Models\EventCostItem;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QuantumEventExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    private array $rows = [];
    private array $meta = [];

    public function __construct(
        private ContractualEvent $event,
        private string $currency = 'CLP'
    ) {
        $this->build();
    }

    private function build(): void
    {
        $event    = $this->event->load(['contract.mandante', 'contract.contractor', 'costItems']);
        $contract = $event->contract;

        $this->meta = [
            'contract_number' => $contract->number,
            'contract_name'   => $contract->name,
            'mandante'        => $contract->mandante?->name ?? '—',
            'contratista'     => $contract->contractor?->name ?? '—',
            'event_type'      => $event->type_label,
            'event_date'      => $event->occurred_at?->format('d/m/Y'),
            'event_desc'      => $event->description,
            'cost_impact'     => $event->cost_impact / 100,
        ];

        $items         = $event->costItems()->orderBy('cost_category')->get();
        $directCats    = EventCostItem::DIRECT_CATEGORIES;
        $indirectCats  = EventCostItem::INDIRECT_CATEGORIES;
        $categoryLabels= EventCostItem::CATEGORY_LABELS;

        $directTotal   = 0;
        $indirectTotal = 0;
        $profitTotal   = 0;

        $currentCat = null;
        foreach ($items as $item) {
            if ($item->cost_category !== $currentCat) {
                $currentCat = $item->cost_category;
                $this->rows[] = ['__cat__', $categoryLabels[$currentCat] ?? $currentCat];
            }
            $this->rows[] = [
                'item',
                $item->description,
                $item->unit,
                $item->quantity,
                $item->unit_cost / 100,
                $item->amount / 100,
                $item->cost_category,
            ];

            if (in_array($item->cost_category, $directCats))   $directTotal   += $item->amount;
            elseif (in_array($item->cost_category, $indirectCats)) $indirectTotal += $item->amount;
            elseif ($item->cost_category === 'profit')          $profitTotal   += $item->amount;
        }

        $grandTotal = $items->sum('amount');

        $this->rows[] = ['__sep__'];
        $this->rows[] = ['__subtotal__', 'Costos directos',   $directTotal / 100];
        $this->rows[] = ['__subtotal__', 'Costos indirectos', $indirectTotal / 100];
        $this->rows[] = ['__subtotal__', 'Utilidad',          $profitTotal / 100];
        $this->rows[] = ['__total__',    'TOTAL QUANTUM',      $grandTotal / 100];
        $this->rows[] = ['__impact__',   'Impacto registrado en evento', $this->meta['cost_impact']];
        $diff = $grandTotal / 100 - $this->meta['cost_impact'];
        $this->rows[] = ['__diff__', abs($diff) < 1 ? 'Conciliado ✓' : 'Diferencia', $diff];
    }

    public function array(): array
    {
        // Solo devuelve filas de items reales (las demás se insertan via AfterSheet)
        return [];
    }

    public function title(): string
    {
        return 'Quantum';
    }

    public function columnWidths(): array
    {
        return ['A' => 8, 'B' => 48, 'C' => 12, 'D' => 12, 'E' => 18, 'F' => 18];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->writeSheet($sheet);
            },
        ];
    }

    private function writeSheet(Worksheet $sheet): void
    {
        $row = 1;
        $currency = $this->currency;

        // ── Encabezado del contrato ──
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", "QUANTUM DE COSTOS — {$this->meta['contract_number']}");
        $this->applyStyle($sheet, "A{$row}:F{$row}", [
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A5C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(28);
        $row++;

        // Datos del contrato
        foreach ([
            ['Contrato:', $this->meta['contract_name']],
            ['Mandante:', $this->meta['mandante']],
            ['Contratista:', $this->meta['contratista']],
        ] as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->mergeCells("B{$row}:F{$row}");
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}:F{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBF3FB');
            $row++;
        }

        $row++; // espacio

        // ── Encabezado del evento ──
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", "EVENTO: {$this->meta['event_type']} — {$this->meta['event_date']}");
        $this->applyStyle($sheet, "A{$row}:F{$row}", [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2A6496']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", $this->meta['event_desc']);
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '555555']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EBF3FB']],
        ]);
        $row++;
        $row++; // espacio

        // ── Encabezados de columna ──
        $headers = ['N°', 'Descripción', 'Unidad', 'Cantidad', "Precio unit. ({$currency})", "Total ({$currency})"];
        foreach ($headers as $col => $h) {
            $cell = chr(65 + $col) . $row;
            $sheet->setCellValue($cell, $h);
        }
        $this->applyStyle($sheet, "A{$row}:F{$row}", [
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A86AE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '2A6496']]],
        ]);
        $row++;

        // ── Filas de ítems ──
        $n = 1;
        foreach ($this->rows as $r) {
            $type = $r[0] ?? '';

            if ($type === '__cat__') {
                // Fila de categoría
                $sheet->mergeCells("A{$row}:F{$row}");
                $sheet->setCellValue("A{$row}", strtoupper($r[1]));
                $this->applyStyle($sheet, "A{$row}:F{$row}", [
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1A3A5C']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6E8F7']],
                ]);
                $row++;

            } elseif ($type === 'item') {
                $sheet->setCellValue("A{$row}", $n++);
                $sheet->setCellValue("B{$row}", $r[1]);
                $sheet->setCellValue("C{$row}", $r[2]);
                $sheet->setCellValue("D{$row}", $r[3]);
                $sheet->setCellValue("E{$row}", $r[4]);
                $sheet->setCellValue("F{$row}", $r[5]);
                $sheet->getStyle("D{$row}:F{$row}")->getNumberFormat()
                    ->setFormatCode('#,##0');
                $sheet->getStyle("D{$row}:F{$row}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$row}:F{$row}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB('CCDDEE');
                $bgColor = $row % 2 === 0 ? 'F5F9FD' : 'FFFFFF';
                $sheet->getStyle("A{$row}:F{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bgColor);
                $row++;

            } elseif ($type === '__sep__') {
                $row++;

            } elseif ($type === '__subtotal__') {
                $sheet->mergeCells("B{$row}:E{$row}");
                $sheet->setCellValue("B{$row}", $r[1]);
                $sheet->setCellValue("F{$row}", $r[2]);
                $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("F{$row}")->getFont()->setBold(true)->setSize(9);
                $row++;

            } elseif ($type === '__total__') {
                $sheet->mergeCells("A{$row}:E{$row}");
                $sheet->setCellValue("A{$row}", $r[1]);
                $sheet->setCellValue("F{$row}", $r[2]);
                $this->applyStyle($sheet, "A{$row}:F{$row}", [
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A5C']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getRowDimension($row)->setRowHeight(22);
                $row++;

            } elseif ($type === '__impact__') {
                $sheet->mergeCells("A{$row}:E{$row}");
                $sheet->setCellValue("A{$row}", $r[1]);
                $sheet->setCellValue("F{$row}", $r[2]);
                $sheet->getStyle("A{$row}:F{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBF3FB');
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("A{$row}:F{$row}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle("F{$row}")->getFont()->setBold(true)->setSize(9);
                $row++;

            } elseif ($type === '__diff__') {
                $isOk  = $r[1] === 'Conciliado ✓';
                $color = $isOk ? '1A6B3C' : '8B3A00';
                $bgRgb = $isOk ? 'D4EDDA' : 'FFF3CD';
                $sheet->mergeCells("A{$row}:E{$row}");
                $sheet->setCellValue("A{$row}", $r[1]);
                $sheet->setCellValue("F{$row}", $isOk ? '—' : $r[2]);
                $this->applyStyle($sheet, "A{$row}:F{$row}", [
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => $color]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgRgb]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);
                if (!$isOk) {
                    $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
                }
                $row++;
            }
        }

        // Pie de página
        $row++;
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", 'Generado por Claim Guard — ' . now()->format('d/m/Y H:i'));
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 8, 'color' => ['rgb' => 'AAAAAA']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        $sheet->freezePane('A8');
    }

    private function applyStyle(Worksheet $sheet, string $range, array $style): void
    {
        $sheet->getStyle($range)->applyFromArray($style);
    }
}
