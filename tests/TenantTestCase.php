<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

abstract class TenantTestCase extends TestCase
{
    protected Tenant $tenant;
    protected User   $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->createTenantContext();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers públicos para tests que necesiten un segundo tenant
    // -------------------------------------------------------------------------

    protected function createExtraTenant(string $slug = 'otro'): Tenant
    {
        return Tenant::create([
            'name'      => "Empresa {$slug} S.A.",
            'slug'      => $slug,
            'domain'    => $slug,
            'database'  => "claimguard_{$slug}",
            'email'     => "admin@{$slug}.cl",
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Setup interno
    // -------------------------------------------------------------------------

    private function runMigrations(): void
    {
        // Migraciones landlord (tenants, users, planes)
        $this->artisan('migrate', [
            '--database' => 'landlord',
            '--path'     => 'database/migrations',
        ]);

        // Migraciones tenant (contratos, eventos, cartas, permisos, etc.)
        $this->artisan('migrate', [
            '--database' => 'tenant',
            '--path'     => 'database/migrations/tenant',
        ]);
    }

    private function createTenantContext(): void
    {
        $this->tenant = Tenant::create([
            'name'      => 'Empresa Test S.A.',
            'slug'      => 'test',
            'domain'    => 'test',
            'database'  => 'claimguard_test',
            'email'     => 'admin@test.cl',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Admin Test',
            'email'      => 'admin@test.cl',
            'password'   => Hash::make('password'),
        ]);

        // Activa el tenant para que los modelos del tenant tengan contexto
        $this->tenant->makeCurrent();

        // Crea roles y permisos en la DB del tenant
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->adminUser->assignRole('tenant_admin');
    }
}
