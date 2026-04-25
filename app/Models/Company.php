<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'rut',
        'address',
        'contact_name',
        'contact_email',
        'type',
    ];

    protected $casts = [
        'type' => 'string',
    ];

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'mandante'    => 'Mandante',
            'contratista' => 'Contratista',
            'ambos'       => 'Mandante y Contratista',
            default       => $this->type,
        };
    }

    public function contractsAsMandante(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Contract::class, 'mandante_company_id');
    }

    public function contractsAsContractor(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Contract::class, 'contractor_company_id');
    }
}
