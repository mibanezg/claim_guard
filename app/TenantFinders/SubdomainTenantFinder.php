<?php

namespace App\TenantFinders;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class SubdomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?Tenant
    {
        $host  = $request->getHost();
        $parts = explode('.', $host);

        // En local (localhost / 127.0.0.1) usamos el tenant configurado en .env
        if (count($parts) < 2 || $host === '127.0.0.1') {
            $defaultDomain = config('multitenancy.local_default_domain');
            if (!$defaultDomain) {
                return null;
            }
            return Tenant::query()
                ->where('domain', $defaultDomain)
                ->where('is_active', true)
                ->first();
        }

        $subdomain = $parts[0];

        if (in_array($subdomain, ['www', 'app'])) {
            return null;
        }

        return Tenant::query()
            ->where('domain', $subdomain)
            ->where('is_active', true)
            ->first();
    }
}
