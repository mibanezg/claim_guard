<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class TenantProvisioningService
{
    /**
     * Crea un nuevo tenant completo:
     * 1. Registra en tabla landlord.tenants
     * 2. Crea la base de datos
     * 3. Ejecuta migraciones del tenant
     * 4. Crea los roles base
     * 5. Crea el usuario administrador
     *
     * Retorna el Tenant creado.
     * Lanza excepción en caso de fallo (con rollback de la DB si fue creada).
     */
    public function provision(array $data): Tenant
    {
        $slug     = $data['slug'];
        $dbName   = 'claimguard_' . $slug;
        $dbCreated = false;

        try {
            // 1 — Crear el registro del tenant
            $tenant = Tenant::create([
                'name'      => $data['name'],
                'slug'      => $slug,
                'domain'    => $data['domain'],
                'database'  => $dbName,
                'email'     => $data['email']     ?? null,
                'phone'     => $data['phone']     ?? null,
                'logo_url'  => $data['logo_url']  ?? null,
                'is_active' => true,
            ]);

            // 2 — Crear la base de datos del tenant
            $this->createDatabase($dbName);
            $dbCreated = true;

            // 3 — Ejecutar migraciones del tenant
            $tenant->makeCurrent();
            $this->runTenantMigrations();

            // 4 — Crear roles base en la DB del tenant
            $this->createBaseRoles();

            // 5 — Crear usuario admin en landlord DB (con tenant_id)
            $adminUser = $this->createAdminUser($tenant, $data);

            Tenant::forgetCurrent();

            Log::info('TenantProvisioningService: tenant creado correctamente', [
                'tenant_id' => $tenant->id,
                'slug'      => $slug,
            ]);

            return $tenant;

        } catch (\Throwable $e) {
            Tenant::forgetCurrent();

            // Rollback: elimina la DB si fue creada, elimina el registro
            if ($dbCreated) {
                $this->dropDatabase($dbName);
            }

            if (isset($tenant)) {
                $tenant->forceDelete();
            }

            Log::error('TenantProvisioningService: fallo en provisioning', [
                'slug'  => $slug,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ── Crear base de datos ───────────────────────────────────────────────────

    private function createDatabase(string $dbName): void
    {
        // Valida el nombre (solo letras, números, guiones bajos)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
            throw new \InvalidArgumentException("Nombre de base de datos inválido: {$dbName}");
        }

        DB::connection('landlord')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    private function dropDatabase(string $dbName): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) return;

        try {
            DB::connection('landlord')->statement("DROP DATABASE IF EXISTS `{$dbName}`");
        } catch (\Throwable $e) {
            Log::warning('TenantProvisioningService: no se pudo eliminar DB en rollback', [
                'db'    => $dbName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ── Migraciones del tenant ────────────────────────────────────────────────

    private function runTenantMigrations(): void
    {
        Artisan::call('migrate', [
            '--path'     => 'database/migrations/tenant',
            '--force'    => true,
        ]);
    }

    // ── Roles base ────────────────────────────────────────────────────────────

    private function createBaseRoles(): void
    {
        $roles = [
            'tenant_admin',
            'contract_admin',
            'field_engineer',
            'manager',
            'legal',
            'counterpart',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    // ── Usuario administrador ─────────────────────────────────────────────────

    private function createAdminUser(Tenant $tenant, array $data): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name'      => $data['admin_name'],
            'email'     => $data['admin_email'],
            'password'  => Hash::make($data['admin_password']),
        ]);

        // Asigna rol tenant_admin (Role existe en tenant DB, usuario en landlord DB)
        // Spatie resuelve esto porque ambas DBs están en el mismo servidor
        $tenant->makeCurrent();
        $user->assignRole('tenant_admin');

        return $user;
    }
}
