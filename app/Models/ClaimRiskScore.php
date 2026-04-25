<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimRiskScore extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'contract_id',
        'score_level',
        'score_value',
        'factors',
        'recommendations',
        'calculated_at',
    ];

    protected $casts = [
        'factors'         => 'array',
        'recommendations' => 'array',
        'calculated_at'   => 'datetime',
        'score_value'     => 'integer',
    ];

    const LEVEL_CONFIG = [
        'bajo'    => ['label' => 'Bajo',     'color' => '#22c55e', 'bg' => 'rgba(34,197,94,0.12)'],
        'medio'   => ['label' => 'Medio',    'color' => '#eab308', 'bg' => 'rgba(234,179,8,0.12)'],
        'alto'    => ['label' => 'Alto',     'color' => '#f97316', 'bg' => 'rgba(249,115,22,0.12)'],
        'critico' => ['label' => 'Crítico',  'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.12)'],
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVEL_CONFIG[$this->score_level]['label'] ?? $this->score_level;
    }

    public function getLevelColorAttribute(): string
    {
        return self::LEVEL_CONFIG[$this->score_level]['color'] ?? '#22c55e';
    }
}
