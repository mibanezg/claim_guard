<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Multitenancy\Models\Tenant;

class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasPermissionTo('settings.tenant');
    }

    public function view(User $authUser, User $user): bool
    {
        return $authUser->hasPermissionTo('settings.tenant')
            && $user->tenant_id === Tenant::current()?->id;
    }

    public function create(User $authUser): bool
    {
        return $authUser->hasPermissionTo('settings.tenant');
    }

    public function update(User $authUser, User $user): bool
    {
        // No puede editar su propia cuenta desde este panel ni a super admins
        return $authUser->hasPermissionTo('settings.tenant')
            && $user->tenant_id === Tenant::current()?->id
            && $user->id !== $authUser->id
            && !$user->is_super_admin;
    }

    public function delete(User $authUser, User $user): bool
    {
        return $authUser->hasPermissionTo('settings.tenant')
            && $user->tenant_id === Tenant::current()?->id
            && $user->id !== $authUser->id
            && !$user->is_super_admin;
    }
}
