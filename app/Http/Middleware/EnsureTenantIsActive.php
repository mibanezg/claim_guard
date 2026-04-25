<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = \Spatie\Multitenancy\Models\Tenant::current();

        if ($tenant && ! $tenant->is_active) {
            abort(503, 'Este espacio de trabajo está suspendido. Contacta a soporte.');
        }

        return $next($request);
    }
}
