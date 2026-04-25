<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractAiAnalysis extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'contract_id',
        'status',
        'exposure_assessment',
        'strong_points',
        'weak_points',
        'urgent_actions',
        'pattern_observations',
        'key_clauses',
        'estimated_exposure_days',
        'estimated_exposure_cost',
        'analysis_confidence',
        'confidence_note',
        'error_message',
        'requested_by',
        'completed_at',
    ];

    protected $casts = [
        'strong_points'       => 'array',
        'weak_points'         => 'array',
        'urgent_actions'      => 'array',
        'pattern_observations'=> 'array',
        'key_clauses'         => 'array',
        'completed_at'        => 'datetime',
    ];

    const CONFIDENCE_LABELS = [
        'alta'  => 'Alta',
        'media' => 'Media',
        'baja'  => 'Baja',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }
}
