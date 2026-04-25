<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TenantIntegration extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'service',
        'client_id',
        'tenant_azure_id',
        'client_secret_encrypted',
        'site_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function forService(string $service): ?self
    {
        return static::where('service', $service)->first();
    }

    public function setClientSecretAttribute(string $value): void
    {
        $this->attributes['client_secret_encrypted'] = Crypt::encryptString($value);
    }

    public function getClientSecretAttribute(): ?string
    {
        if (!$this->client_secret_encrypted) return null;
        try {
            return Crypt::decryptString($this->client_secret_encrypted);
        } catch (\Exception) {
            return null;
        }
    }
}
