<?php

namespace App\Policies;

use App\Models\ChangeOrder;
use App\Models\User;

class ChangeOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('contracts.view');
    }

    public function view(User $user, ChangeOrder $oc): bool
    {
        return $user->hasPermissionTo('contracts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('change_orders.create');
    }

    public function update(User $user, ChangeOrder $oc): bool
    {
        // Aprobar o aprobar parcialmente requiere permiso especial
        if (in_array(request()->input('status'), ['aprobada', 'aprobada_parcialmente'])) {
            return $user->hasPermissionTo('change_orders.approve');
        }

        return $user->hasPermissionTo('change_orders.create');
    }

    public function delete(User $user, ChangeOrder $oc): bool
    {
        // Solo se pueden eliminar OC en estado solicitada
        return $user->hasPermissionTo('change_orders.create')
            && $oc->status === 'solicitada';
    }

    // Gate explícito para botón de aprobación en el frontend
    public function approve(User $user, ChangeOrder $oc): bool
    {
        return $user->hasPermissionTo('change_orders.approve')
            && in_array($oc->status, ['solicitada', 'evaluacion']);
    }
}
