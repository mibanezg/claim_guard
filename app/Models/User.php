<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $connection = 'landlord';

    // HasRoles usa Role/Permission que tienen connection='tenant'.
    // Funciona porque ambas DBs están en el mismo servidor MySQL.
    protected string $guard_name = 'web';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'is_super_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_super_admin'    => 'boolean',
        ];
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Evita el cross-DB JOIN que genera HasRoles::role() en tests SQLite.
    // Resuelve roles en la conexión 'tenant' y luego busca users en 'landlord'.
    public static function notifiableManagers(int $tenantId): Collection
    {
        $roleIds = Role::whereIn('name', ['manager', 'tenant_admin'])->pluck('id');

        $userIds = DB::connection('tenant')
            ->table('model_has_roles')
            ->whereIn('role_id', $roleIds)
            ->where('model_type', self::class)
            ->pluck('model_id');

        return static::whereIn('id', $userIds)
            ->where('tenant_id', $tenantId)
            ->get();
    }
}
