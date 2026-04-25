<?php

namespace App\Policies;

use App\Models\ContractLetter;
use App\Models\User;

class LetterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('contracts.view');
    }

    public function view(User $user, ContractLetter $letter): bool
    {
        return $user->hasPermissionTo('contracts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('letters.create');
    }

    public function update(User $user, ContractLetter $letter): bool
    {
        // Cambiar status a 'emitida' requiere aprobación explícita (letters.emit)
        // Esta regla aplica tanto para el update normal como para requestDraft
        if (request()->input('status') === 'emitida') {
            return $user->hasPermissionTo('letters.emit');
        }

        return $user->hasPermissionTo('letters.create');
    }

    public function delete(User $user, ContractLetter $letter): bool
    {
        // Solo se pueden eliminar borradores
        return $user->hasPermissionTo('letters.create')
            && $letter->status === 'borrador';
    }

    // Gate explícito para cuando el frontend necesita verificar permisos de emisión
    public function emit(User $user, ContractLetter $letter): bool
    {
        return $user->hasPermissionTo('letters.emit')
            && $letter->status === 'borrador';
    }
}
