<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ChangeOrder extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $connection = 'tenant';

    protected $fillable = [
        'contract_id',
        'contractual_event_id',
        'request_number',
        'requested_by_party',
        'description',
        'schedule_impact_days',
        'cost_impact',
        'status',
        'approved_by',
        'approved_at',
        'rejection_notes',
        'sharepoint_id',
        'created_by',
    ];

    protected $casts = [
        'schedule_impact_days' => 'integer',
        'cost_impact'          => 'integer',
        'approved_at'          => 'datetime',
    ];

    const STATUS_LABELS = [
        'solicitada'           => 'Solicitada',
        'evaluacion'           => 'En Evaluación',
        'aprobada'             => 'Aprobada',
        'rechazada'            => 'Rechazada',
        'aprobada_parcialmente' => 'Aprobada Parcialmente',
    ];

    const PARTY_LABELS = [
        'mandante'    => 'Mandante',
        'contratista' => 'Contratista',
    ];

    // Umbrales por defecto en centavos (configurable en Paso 23)
    // threshold_1: 10.000.000 CLP → aprueba contract_admin o superior
    // threshold_2: 50.000.000 CLP → aprueba manager o superior
    // Sobre threshold_2 → solo tenant_admin
    const DEFAULT_THRESHOLD_1 = 1_000_000_000; // 10M CLP
    const DEFAULT_THRESHOLD_2 = 5_000_000_000; // 50M CLP

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function contractualEvent(): BelongsTo
    {
        return $this->belongsTo(ContractualEvent::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getPartyLabelAttribute(): string
    {
        return self::PARTY_LABELS[$this->requested_by_party] ?? $this->requested_by_party;
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['solicitada', 'evaluacion']);
    }

    public function transformAudit(array $data): array
    {
        foreach (['new_values', 'old_values'] as $key) {
            if (isset($data[$key]['cost_impact'])) {
                $data[$key]['cost_impact'] = $data[$key]['cost_impact'] / 100;
            }
        }
        return $data;
    }
}
