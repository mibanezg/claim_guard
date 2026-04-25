<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractDocument extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'contract_id',
        'contractual_event_id',
        'letter_id',
        'change_order_id',
        'name',
        'category',
        'is_constitutive',
        'extracted_text',
        'precedence_order',
        'sharepoint_id',
        'sharepoint_url',
        'local_path',
        'file_type',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'is_constitutive' => 'boolean',
    ];

    const CATEGORY_LABELS = [
        'carta_emitida'  => 'Carta Emitida',
        'carta_recibida' => 'Carta Recibida',
        'evento'         => 'Evento',
        'orden_cambio'   => 'Orden de Cambio',
        'programa'       => 'Programa',
        'expediente'     => 'Expediente',
        'otro'           => 'Otro',
    ];

    // Tipos de documentos que forman el cuerpo contractual
    const CONSTITUTIVE_LABELS = [
        'contrato_base'    => 'Contrato Base',
        'bases_tecnicas'   => 'Bases Técnicas (BT)',
        'bases_admin'      => 'Bases Administrativas Especiales (BAE)',
        'anexo'            => 'Anexo',
        'addenda'          => 'Addenda',
        'especificaciones' => 'Especificaciones Técnicas',
        'otro'             => 'Otro documento contractual',
    ];

    public function scopeConstitutive($query)
    {
        return $query->where('is_constitutive', true)->orderBy('precedence_order');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ContractualEvent::class, 'contractual_event_id');
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(ContractLetter::class, 'letter_id');
    }

    public function changeOrder(): BelongsTo
    {
        return $this->belongsTo(ChangeOrder::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withoutGlobalScopes();
    }

    public function getStorageUrlAttribute(): ?string
    {
        return $this->sharepoint_url ?? ($this->local_path
            ? asset('storage/' . $this->local_path)
            : null);
    }

    public function isStoredLocally(): bool
    {
        return is_null($this->sharepoint_id) && !is_null($this->local_path);
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024)       return $bytes . ' B';
        if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
