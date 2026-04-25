<?php

namespace App\Policies;

use App\Models\ContractDocument;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('contracts.view');
    }

    public function view(User $user, ContractDocument $document): bool
    {
        return $user->hasPermissionTo('contracts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('documents.upload');
    }

    public function delete(User $user, ContractDocument $document): bool
    {
        return $user->hasPermissionTo('documents.delete');
    }
}
