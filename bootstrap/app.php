<?php

use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Multitenancy\Http\Middleware\EnsureValidTenantSession;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware Inertia — comparte auth/tenant con todas las páginas Vue
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Alias para usar en rutas con ->middleware('nombre')
        $middleware->alias([
            'tenant'             => NeedsTenant::class,
            'tenant.active'      => EnsureTenantIsActive::class,
            'tenant.session'     => EnsureValidTenantSession::class,
            'tenant.settings'    => \App\Http\Middleware\TenantSettingsMiddleware::class,
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
