<?php

namespace App\Policies;

use App\Models\ContractualEvent;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('contracts.view');
    }

    public function view(User $user, ContractualEvent $event): bool
    {
        return $user->hasPermissionTo('contracts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('events.create');
    }

    public function update(User $user, ContractualEvent $event): bool
    {
        return $user->hasPermissionTo('events.edit');
    }

    public function delete(User $user, ContractualEvent $event): bool
    {
        return $user->hasPermissionTo('events.edit');
    }
}
