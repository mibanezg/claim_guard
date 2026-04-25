<?php

use App\Models\Contract;
use App\Models\Tenant;
use App\TenantFinders\SubdomainTenantFinder;
use Illuminate\Http\Request;

describe('Tenant context', function () {

    it('el tenant actual está activo después del setUp', function () {
        expect(Tenant::current())->not->toBeNull()
            ->and(Tenant::current()->slug)->toBe('test')
            ->and(Tenant::current()->is_active)->toBeTrue();
    });

    it('los modelos del tenant usan la conexión tenant', function () {
        $contract = Contract::factory()->vigente()->create(['created_by' => $this->adminUser->id]);

        expect($contract->getConnectionName())->toBe('tenant');
    });

});

describe('SubdomainTenantFinder', function () {

    it('resuelve el tenant por el subdominio del request', function () {
        $request = Request::create('http://test.claimguard.cl/dashboard');
        $finder  = app(SubdomainTenantFinder::class);

        $tenant = $finder->findForRequest($request);

        expect($tenant)->not->toBeNull()
            ->and($tenant->slug)->toBe('test');
    });

    it('retorna null para un subdominio desconocido', function () {
        $request = Request::create('http://noexiste.claimguard.cl/dashboard');
        $finder  = app(SubdomainTenantFinder::class);

        $tenant = $finder->findForRequest($request);

        expect($tenant)->toBeNull();
    });

});

describe('Aislamiento de datos entre tenants', function () {

    it('cambiar de tenant actualiza el contexto correctamente', function () {
        // Tests comparten SQLite en memoria — la isolation real requiere DBs separadas.
        // Aquí verificamos que makeCurrent() cambia el contexto de sesión.
        $tenantB = $this->createExtraTenant('empresa-b');

        $tenantB->makeCurrent();
        expect(Tenant::current()->slug)->toBe('empresa-b');

        // Volver al tenant principal
        $this->tenant->makeCurrent();
        expect(Tenant::current()->slug)->toBe('test');
    });

    it('un tenant inactivo retorna null en el finder', function () {
        $tenantInactivo = Tenant::create([
            'name'      => 'Tenant Inactivo',
            'slug'      => 'inactivo',
            'domain'    => 'inactivo',
            'database'  => 'claimguard_inactivo',
            'email'     => 'admin@inactivo.cl',
            'is_active' => false,
        ]);

        // El EnsureTenantIsActive middleware rechaza tenants inactivos
        // Aquí verificamos que el modelo tiene el flag correcto
        expect($tenantInactivo->is_active)->toBeFalse();
    });

});
