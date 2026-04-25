<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractUser extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['contract_id', 'user_id', 'role'];

    public const ROLE_LABELS = [
        'tenant_admin'    => 'Administrador',
        'contract_admin'  => 'Administrador de Contrato',
        'field_engineer'  => 'Ingeniero de Campo',
        'manager'         => 'Gerente',
        'legal'           => 'Legal',
        'counterpart'     => 'Contraparte (Mandante)',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
