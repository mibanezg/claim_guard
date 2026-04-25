<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventDelayAnalysis extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'contractual_event_id', 'affected_milestone_id',
        'delay_type', 'is_critical_path', 'analysis_method',
        'baseline_date', 'impacted_date',
        'float_consumed', 'concurrent_cause', 'narrative',
    ];

    protected $casts = [
        'baseline_date'   => 'date',
        'impacted_date'   => 'date',
        'is_critical_path'=> 'boolean',
    ];

    const DELAY_TYPE_LABELS = [
        'compensable'  => 'Compensable (tiempo + costo)',
        'excusable'    => 'Excusable / Fuerza mayor (solo tiempo)',
        'no_excusable' => 'No excusable (contratista)',
        'concurrente'  => 'Concurrente',
    ];

    const METHOD_LABELS = [
        'as_planned_vs_as_built' => 'As-Planned vs As-Built',
        'time_impact'            => 'Time Impact Analysis (TIA)',
        'collapsed_but_for'      => 'Collapsed But-For',
        'windows'                => 'Windows Analysis',
        'contemporaneo'          => 'Análisis contemporáneo (diarios)',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ContractualEvent::class, 'contractual_event_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ContractMilestone::class, 'affected_milestone_id');
    }

    public function getDelayDaysAttribute(): ?int
    {
        if (!$this->baseline_date || !$this->impacted_date) return null;
        return (int) $this->baseline_date->diffInDays($this->impacted_date, false);
    }

    public function getDelayTypeLabelAttribute(): string
    {
        return self::DELAY_TYPE_LABELS[$this->delay_type] ?? $this->delay_type;
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHOD_LABELS[$this->analysis_method] ?? $this->analysis_method;
    }
}
