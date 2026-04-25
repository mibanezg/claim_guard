<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCostItem extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'contractual_event_id', 'contract_price_item_id',
        'description', 'unit', 'quantity', 'unit_cost', 'amount',
        'cost_category', 'notes',
    ];

    protected $casts = [
        'quantity'  => 'float',
        'unit_cost' => 'integer',
        'amount'    => 'integer',
    ];

    const CATEGORY_LABELS = [
        'mano_obra_directa' => 'Mano de obra directa',
        'materiales'        => 'Materiales',
        'equipos'           => 'Equipos y maquinaria',
        'subcontratos'      => 'Subcontratos',
        'gastos_obra'       => 'Gastos generales de obra',
        'overhead_sede'     => 'Overhead de sede',
        'profit'            => 'Utilidad',
        'otro'              => 'Otro',
    ];

    // Qué categorías son "costos directos"
    const DIRECT_CATEGORIES = [
        'mano_obra_directa', 'materiales', 'equipos', 'subcontratos',
    ];

    const INDIRECT_CATEGORIES = [
        'gastos_obra', 'overhead_sede',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ContractualEvent::class, 'contractual_event_id');
    }

    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(ContractPriceItem::class, 'contract_price_item_id');
    }

    public function getAmountFormattedAttribute(): float
    {
        return $this->amount / 100;
    }

    public function getUnitCostFormattedAttribute(): float
    {
        return $this->unit_cost / 100;
    }

    public function getIsFromCatalogAttribute(): bool
    {
        return !is_null($this->contract_price_item_id);
    }
}
