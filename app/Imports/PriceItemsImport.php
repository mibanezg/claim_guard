<?php

namespace App\Imports;

use App\Models\ContractPriceItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PriceItemsImport implements ToCollection, WithHeadingRow
{
    private int $contractId;
    public int  $imported = 0;
    public array $errors  = [];

    public function __construct(int $contractId)
    {
        $this->contractId = $contractId;
    }

    public function collection(Collection $rows): void
    {
        $categoryMap = [
            'mano de obra'          => 'mano_obra',
            'mano_obra'             => 'mano_obra',
            'materiales'            => 'materiales',
            'equipos'               => 'equipos',
            'equipos y maquinaria'  => 'equipos',
            'maquinaria'            => 'equipos',
            'subcontratos'          => 'subcontratos',
            'gastos generales'      => 'gastos_generales',
            'gastos_generales'      => 'gastos_generales',
            'otro'                  => 'otro',
        ];

        foreach ($rows as $i => $row) {
            $description = trim((string) ($row['descripcion'] ?? $row['description'] ?? ''));
            $unitCostRaw = $row['precio_unitario'] ?? $row['unit_cost'] ?? $row['precio'] ?? 0;

            if (empty($description)) continue;

            $unitCost = (int) round((float) str_replace(['.', ','], ['', '.'], (string) $unitCostRaw) * 100);

            if ($unitCost <= 0) {
                $this->errors[] = "Fila " . ($i + 2) . ": precio unitario inválido para '{$description}'";
                continue;
            }

            $categoryRaw = strtolower(trim((string) ($row['categoria'] ?? $row['category'] ?? '')));
            $category    = $categoryMap[$categoryRaw] ?? 'otro';

            ContractPriceItem::create([
                'contract_id' => $this->contractId,
                'code'        => trim((string) ($row['codigo'] ?? $row['code'] ?? '')),
                'description' => $description,
                'unit'        => trim((string) ($row['unidad'] ?? $row['unit'] ?? 'gl')),
                'unit_cost'   => $unitCost,
                'category'    => $category,
                'is_active'   => true,
            ]);

            $this->imported++;
        }
    }
}
