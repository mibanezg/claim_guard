<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractPriceItem extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'contract_id', 'code', 'description', 'unit',
        'unit_cost', 'category', 'is_active',
    ];

    protected $casts = [
        'unit_cost' => 'integer',
        'is_active' => 'boolean',
    ];

    const CATEGORY_LABELS = [
        'mano_obra'       => 'Mano de obra',
        'materiales'      => 'Materiales',
        'equipos'         => 'Equipos y maquinaria',
        'subcontratos'    => 'Subcontratos',
        'gastos_generales'=> 'Gastos generales',
        'otro'            => 'Otro',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function getUnitCostFormattedAttribute(): float
    {
        return $this->unit_cost / 100;
    }

    public function getDisplayLabelAttribute(): string
    {
        $prefix = $this->code ? "[{$this->code}] " : '';
        return "{$prefix}{$this->description} ({$this->unit})";
    }
}
