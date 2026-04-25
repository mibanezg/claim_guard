<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $connection = 'landlord';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_clp',
        'max_contracts',
        'max_users',
        'has_ai_features',
        'has_sharepoint',
        'is_active',
    ];

    protected $casts = [
        'has_ai_features' => 'boolean',
        'has_sharepoint'  => 'boolean',
        'is_active'       => 'boolean',
    ];

    public function tenantSubscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }
}
