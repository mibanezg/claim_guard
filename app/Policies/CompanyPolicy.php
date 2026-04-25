<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['contracts.view', 'contracts.create']);
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasAnyPermission(['contracts.view', 'contracts.create']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('contracts.create');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('contracts.edit');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('contracts.edit');
    }
}
