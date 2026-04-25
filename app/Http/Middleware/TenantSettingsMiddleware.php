<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class TenantSettingsMiddleware
{
    // Las 5 variables de color personalizables por tenant
    private const COLOR_KEYS = [
        'color_primary',
        'color_primary_dim',
        'color_secondary',
        'color_sidebar_bg',
        'color_text_primary',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = \Spatie\Multitenancy\Models\Tenant::current();

        if ($tenant) {
            $colors = $this->getTenantColors($tenant);

            // Comparte los colores con todas las páginas Inertia
            Inertia::share('tenant_colors', $colors);
        }

        return $next($request);
    }

    private function getTenantColors(\App\Models\Tenant $tenant): array
    {
        // Caché de 1 hora por tenant para evitar queries repetidas
        return Cache::remember(
            "tenant_{$tenant->id}_colors",
            3600,
            function () {
                $colors = [];

                foreach (self::COLOR_KEYS as $key) {
                    $setting = \Illuminate\Support\Facades\DB::connection('tenant')
                        ->table('tenant_settings')
                        ->where('key', $key)
                        ->value('value');

                    if ($setting) {
                        $colors[$key] = $setting;
                    }
                }

                return $colors;
            }
        );
    }
}
