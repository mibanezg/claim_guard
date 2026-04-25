<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Datos compartidos con todas las páginas Inertia.
     */
    public function share(Request $request): array
    {
        $tenant = \Spatie\Multitenancy\Models\Tenant::current();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id'             => $request->user()->id,
                    'name'           => $request->user()->name,
                    'email'          => $request->user()->email,
                    'is_super_admin' => $request->user()->is_super_admin,
                    // Roles y permisos solo cuando hay tenant activo (Spatie usa DB tenant)
                    'roles'          => $tenant ? $request->user()->getRoleNames() : [],
                    'permissions'    => $tenant ? $request->user()->getAllPermissions()->pluck('name') : [],
                ] : null,
            ],
            'tenant' => $tenant ? [
                'name'   => $tenant->name,
                'slug'   => $tenant->slug,
                'domain' => $tenant->domain,
            ] : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'info'    => $request->session()->get('info'),
            ],
        ]);
    }
}
