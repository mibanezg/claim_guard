<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Limpia caché de permisos antes de sembrar
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permisos granulares del sistema
        $permissions = [
            'contracts.view', 'contracts.create', 'contracts.edit',
            'events.create', 'events.edit',
            'letters.create', 'letters.emit',
            'change_orders.create', 'change_orders.approve',
            'documents.upload', 'documents.delete',
            'risk.view', 'expediente.generate',
            'settings.tenant', 'settings.integrations',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Roles con sus permisos
        $roles = [
            'tenant_admin'     => $permissions,
            'contract_admin'   => ['contracts.view', 'contracts.create', 'contracts.edit', 'events.create', 'events.edit', 'letters.create', 'letters.emit', 'change_orders.create', 'documents.upload', 'risk.view'],
            'field_engineer'   => ['contracts.view', 'events.create', 'documents.upload', 'risk.view'],
            'manager'          => ['contracts.view', 'risk.view', 'expediente.generate'],
            'legal'            => ['contracts.view', 'expediente.generate', 'risk.view'],
            'counterpart'      => ['contracts.view'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }

        $this->command?->info('Roles y permisos creados correctamente.');
    }
}
