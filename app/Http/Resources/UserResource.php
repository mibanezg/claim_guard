<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $this->roles->first()?->name,
            'role_label' => $this->roleLabel(),
            'created_at' => $this->created_at?->format('d/m/Y'),
        ];
    }

    private function roleLabel(): string
    {
        $labels = [
            'tenant_admin'   => 'Administrador',
            'contract_admin' => 'Admin. de Contratos',
            'field_engineer' => 'Ingeniero de Campo',
            'manager'        => 'Gerente',
            'legal'          => 'Legal',
            'counterpart'    => 'Contraparte',
        ];

        $roleName = $this->roles->first()?->name;
        return $labels[$roleName] ?? ($roleName ?? 'Sin rol');
    }
}
