<?php

namespace App\Exports;

use App\Models\ContractPriceItem;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PriceItemsTemplateExport implements
    FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths, WithEvents
{
    public function __construct(private string $currency = 'CLP') {}

    public function title(): string
    {
        return 'CPU - Precios Unitarios';
    }

    public function headings(): array
    {
        return [
            'codigo',
            'descripcion',
            'unidad',
            'precio_unitario',
            'categoria',
        ];
    }

    public function array(): array
    {
        // Filas de ejemplo por categoría
        // Precios de ejemplo en la moneda del contrato (borrar y reemplazar con valores reales)
        $factor = $this->currency === 'USD' ? 1 : 1000;
        return [
            ['MO-001', 'Capataz',                          'HH',  25 * $factor, 'mano_obra'],
            ['MO-002', 'Operario especializado',            'HH',  20 * $factor, 'mano_obra'],
            ['MO-003', 'Ayudante',                         'HH',  15 * $factor, 'mano_obra'],
            ['MAT-001','Hormigón H-30',                    'm³',  120 * $factor, 'materiales'],
            ['MAT-002','Acero A630-420H',                  'kg',  1 * $factor,  'materiales'],
            ['EQ-001', 'Retroexcavadora 320 HP',           'HM',  80 * $factor, 'equipos'],
            ['EQ-002', 'Grúa horquilla 10 ton',            'HM',  60 * $factor, 'equipos'],
            ['SUB-001','Instalaciones eléctricas',         'gl',  500 * $factor,'subcontratos'],
            ['GG-001', 'Gastos generales de obra',         'mes', 2000 * $factor,'gastos_generales'],
            ['OT-001', 'Ítem adicional (borrar)',          'un',  10 * $factor, 'otro'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 45,
            'C' => 12,
            'D' => 22,
            'E' => 22,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Encabezados
            1 => [
                'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2A6496']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            // Filas de datos
            'A2:E11' => [
                'font' => ['size' => 10],
            ],
            // Columna precio: formato numérico
            'D2:D11' => [
                'numberFormat' => ['formatCode' => NumberFormat::FORMAT_NUMBER],
                'alignment'    => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Fila de instrucciones (fila 13)
                $sheet->mergeCells('A13:E13');
                $sheet->setCellValue('A13', 'INSTRUCCIONES DE LLENADO');
                $sheet->getStyle('A13')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '2A6496']],
                ]);

                $instrucciones = [
                    ['A14', 'codigo',          'B14', 'Código interno opcional (ej: MO-001). Puede quedar vacío.'],
                    ['A15', 'descripcion',     'B15', 'Nombre completo del recurso o ítem. OBLIGATORIO.'],
                    ['A16', 'unidad',          'B16', 'Unidad de medida (HH, HM, m³, kg, m², gl, un, mes…). OBLIGATORIO.'],
                    ['A17', 'precio_unitario', 'B17', 'Precio en ' . $this->currency . ' SIN puntos ni comas. Ej: 25000 para $25.000. OBLIGATORIO.'],
                    ['A18', 'categoria',       'B18', 'Debe ser exactamente uno de los valores de la columna F. OBLIGATORIO.'],
                ];

                foreach ($instrucciones as [$colA, $campo, $colB, $desc]) {
                    $sheet->setCellValue($colA, $campo);
                    $sheet->getStyle($colA)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 9],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EBF3FB']],
                    ]);
                    $sheet->setCellValue($colB, $desc);
                    $sheet->getStyle($colB)->applyFromArray([
                        'font' => ['size' => 9],
                    ]);
                    $sheet->mergeCells("{$colB}:" . 'E' . substr($colB, 1));
                }

                // Columna F: valores válidos para categoría
                $sheet->setCellValue('G1', 'CATEGORÍAS VÁLIDAS');
                $sheet->getStyle('G1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B8DB8']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getColumnDimension('G')->setWidth(22);
                $sheet->setCellValue('H1', 'ETIQUETA');
                $sheet->getStyle('H1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B8DB8']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getColumnDimension('H')->setWidth(28);

                $row = 2;
                foreach (ContractPriceItem::CATEGORY_LABELS as $key => $label) {
                    $sheet->setCellValue("G{$row}", $key);
                    $sheet->getStyle("G{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '2A6496']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F7FF']],
                    ]);
                    $sheet->setCellValue("H{$row}", $label);
                    $sheet->getStyle("H{$row}")->applyFromArray(['font' => ['size' => 9]]);
                    $row++;
                }

                // Borde externo a la tabla de datos
                $sheet->getStyle('A1:E11')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => 'C5D9E8'],
                        ],
                    ],
                ]);

                // Altura fila encabezado
                $sheet->getRowDimension(1)->setRowHeight(22);

                // Freeze header row
                $sheet->freezePane('A2');
            },
        ];
    }
}
