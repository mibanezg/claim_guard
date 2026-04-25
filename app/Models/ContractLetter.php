<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ContractLetter extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $connection = 'tenant';

    protected $fillable = [
        'contract_id',
        'contractual_event_id',
        'letter_number',
        'type',
        'subject',
        'from_company_id',
        'to_company_id',
        'issued_at',
        'received_at',
        'response_deadline',
        'response_days',
        'status',
        'clauses_referenced',
        'ai_generated',
        'content_draft',
        'created_by',
    ];

    protected $casts = [
        'issued_at'          => 'date',
        'received_at'        => 'date',
        'response_deadline'  => 'date',
        'clauses_referenced' => 'array',
        'ai_generated'       => 'boolean',
    ];

    const TYPE_LABELS = [
        'notificacion'     => 'Notificación',
        'reserva_derechos' => 'Reserva de Derechos',
        'respuesta'        => 'Respuesta',
        'cobranza'         => 'Cobranza',
        'acta_reunion'     => 'Acta de Reunión',
        'memorando'        => 'Memorando',
    ];

    const STATUS_LABELS = [
        'emitida'    => 'Emitida',
        'recibida'   => 'Recibida',
        'respondida' => 'Respondida',
        'vencida'    => 'Vencida',
    ];

    // Días hábiles de respuesta por defecto según tipo
    const DEFAULT_RESPONSE_DAYS = [
        'notificacion'     => 5,
        'reserva_derechos' => 5,
        'respuesta'        => 10,
        'cobranza'         => 15,
        'acta_reunion'     => 3,
        'memorando'        => 5,
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function fromCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'from_company_id');
    }

    public function toCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'to_company_id');
    }

    public function contractualEvent(): BelongsTo
    {
        return $this->belongsTo(ContractualEvent::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getDaysUntilDeadlineAttribute(): ?int
    {
        if (!$this->response_deadline) return null;
        return (int) now()->startOfDay()->diffInDays($this->response_deadline, false);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->response_deadline !== null
            && $this->response_deadline->isPast()
            && !in_array($this->status, ['respondida', 'vencida']);
    }
}
