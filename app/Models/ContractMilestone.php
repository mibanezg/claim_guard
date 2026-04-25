<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractMilestone extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'contract_id',
        'name',
        'description',
        'planned_date',
        'actual_date',
        'progress_percentage',
        'is_critical',
        'generates_notification',
        'status',
        'source',
        'external_id',
    ];

    protected $casts = [
        'planned_date'           => 'date',
        'actual_date'            => 'date',
        'is_critical'            => 'boolean',
        'generates_notification' => 'boolean',
        'progress_percentage'    => 'integer',
    ];

    const STATUS_LABELS = [
        'pendiente'   => 'Pendiente',
        'en_progreso' => 'En Progreso',
        'completado'  => 'Completado',
        'atrasado'    => 'Atrasado',
    ];

    const SOURCE_LABELS = [
        'manual'     => 'Manual',
        'ms_project' => 'MS Project',
        'primavera'  => 'Primavera P6',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? $this->source;
    }

    public function isDelayed(): bool
    {
        if ($this->status === 'completado') return false;
        return $this->planned_date->isPast();
    }
}
