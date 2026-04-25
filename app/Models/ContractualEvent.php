<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ChangeOrder;
use App\Models\ContractLetter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ContractualEvent extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $connection = 'tenant';

    protected $fillable = [
        'contract_id',
        'type',
        'occurred_at',
        'description',
        'contractual_basis',
        'contractual_basis_doc',
        'responsible_party',
        'schedule_impact_days',
        'cost_impact',
        'resolution_status',
        'resolution_notes',
        'notice_deadline',
        'notified_at',
        'notification_status',
        'rights_reserved',
        'rights_reserved_at',
        'created_by',
    ];

    protected $casts = [
        'occurred_at'          => 'date',
        'notice_deadline'      => 'date',
        'notified_at'          => 'date',
        'rights_reserved_at'   => 'date',
        'rights_reserved'      => 'boolean',
        'schedule_impact_days' => 'integer',
        'cost_impact'          => 'integer',
    ];

    const TYPE_LABELS = [
        'orden_cambio'         => 'Orden de Cambio',
        'trabajo_adicional'    => 'Trabajo Adicional',
        'condicion_imprevista' => 'Condición Imprevista',
        'atraso_mandante'      => 'Atraso Mandante',
        'atraso_contratista'   => 'Atraso Contratista',
        'suspension'           => 'Suspensión',
        'entrega_frente'       => 'Entrega de Frente',
        'no_conformidad'       => 'No Conformidad',
        'disputa'              => 'Disputa',
        'otro'                 => 'Otro',
    ];

    const PARTY_LABELS = [
        'mandante'     => 'Mandante',
        'contratista'  => 'Contratista',
        'fuerza_mayor' => 'Fuerza Mayor',
        'tercero'      => 'Tercero',
    ];

    const RESOLUTION_LABELS = [
        'pendiente'   => 'Pendiente',
        'negociacion' => 'En Negociación',
        'resuelto'    => 'Resuelto',
        'escalado'    => 'Escalado',
    ];

    const NOTIFICATION_LABELS = [
        'pendiente'            => 'Sin notificar',
        'notificado_a_tiempo'  => 'Notificado a tiempo',
        'notificado_tarde'     => 'Notificado tarde',
        'no_aplica'            => 'No aplica',
    ];

    const BASIS_DOC_LABELS = [
        'contrato_base'  => 'Contrato Base',
        'bases_tecnicas' => 'Bases Técnicas (BT)',
        'bases_admin'    => 'Bases Administrativas (BAE)',
        'anexo'          => 'Anexo',
        'otro'           => 'Otro',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(ContractLetter::class, 'contractual_event_id');
    }

    public function rightsLetter(): HasMany
    {
        return $this->hasMany(ContractLetter::class, 'contractual_event_id')
            ->where('type', 'reserva_derechos');
    }

    /**
     * Estado de la reserva de derechos:
     * - 'formal'    → reservada y formalizada con carta emitida
     * - 'informal'  → marcada internamente pero sin carta
     * - 'none'      → sin reserva
     * - 'na'        → no aplica (imputables al propio contratista)
     */
    public function getRightsStatusAttribute(): string
    {
        if ($this->responsible_party === 'contratista') return 'na';
        if (!$this->rights_reserved) return 'none';

        // withCount('rightsLetter') produce rights_letter_count
        $count = $this->getAttribute('rights_letter_count');
        if ($count !== null && $count > 0) return 'formal';

        // Si la relación fue cargada con with()
        if ($this->relationLoaded('rightsLetter') && $this->rightsLetter->isNotEmpty()) return 'formal';

        return 'informal';
    }

    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class, 'contractual_event_id');
    }

    public function costItems(): HasMany
    {
        return $this->hasMany(EventCostItem::class);
    }

    public function delayAnalysis(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EventDelayAnalysis::class);
    }

    public function getQuantumTotalAttribute(): int
    {
        return $this->costItems()->sum('amount');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getPartyLabelAttribute(): string
    {
        return self::PARTY_LABELS[$this->responsible_party] ?? $this->responsible_party;
    }

    public function getResolutionLabelAttribute(): string
    {
        return self::RESOLUTION_LABELS[$this->resolution_status] ?? $this->resolution_status;
    }

    public function getNotificationLabelAttribute(): string
    {
        return self::NOTIFICATION_LABELS[$this->notification_status] ?? $this->notification_status;
    }

    public function getNoticeDaysRemainingAttribute(): ?int
    {
        if (!$this->notice_deadline) return null;
        if (in_array($this->notification_status, ['notificado_a_tiempo', 'notificado_tarde', 'no_aplica'])) return null;
        return (int) now()->startOfDay()->diffInDays($this->notice_deadline, false);
    }

    public function getIsNoticeOverdueAttribute(): bool
    {
        return $this->notice_deadline !== null
            && $this->notice_deadline->isPast()
            && $this->notification_status === 'pendiente';
    }

    public function getDaysOpenAttribute(): int
    {
        if ($this->resolution_status === 'resuelto') return 0;
        return (int) $this->occurred_at->diffInDays(now());
    }

    public function transformAudit(array $data): array
    {
        // Convierte centavos a pesos para el audit log
        if (isset($data['new_values']['cost_impact'])) {
            $data['new_values']['cost_impact'] = $data['new_values']['cost_impact'] / 100;
        }
        if (isset($data['old_values']['cost_impact'])) {
            $data['old_values']['cost_impact'] = $data['old_values']['cost_impact'] / 100;
        }
        return $data;
    }
}
