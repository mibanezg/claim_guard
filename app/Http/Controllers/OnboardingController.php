<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(private TenantProvisioningService $provisioning) {}

    /**
     * Wizard de creación de nuevo tenant.
     * Accesible solo desde el dominio landlord (sin subdominio de tenant).
     */
    public function show(): Response
    {
        return Inertia::render('Onboarding/Index', [
            'existing_slugs' => Tenant::pluck('slug')->toArray(),
            'flash'          => session()->only(['success', 'error']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name'   => ['required', 'string', 'max:100'],
            'slug'           => ['required', 'string', 'max:30', 'regex:/^[a-z0-9\-]+$/', 'unique:landlord.tenants,slug'],
            'email'          => ['required', 'email', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'admin_name'     => ['required', 'string', 'max:100'],
            'admin_email'    => ['required', 'email', 'max:100', 'unique:landlord.users,email'],
            'admin_password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Genera el domain a partir del slug (en producción sería {slug}.claimguard.cl)
        $domain = $validated['slug'];

        try {
            $tenant = $this->provisioning->provision([
                'name'           => $validated['company_name'],
                'slug'           => $validated['slug'],
                'domain'         => $domain,
                'email'          => $validated['email'],
                'phone'          => $validated['phone'] ?? null,
                'admin_name'     => $validated['admin_name'],
                'admin_email'    => $validated['admin_email'],
                'admin_password' => $validated['admin_password'],
            ]);

            return redirect()
                ->route('onboarding.success', ['tenant' => $tenant->id])
                ->with('success', "Tenant \"{$tenant->name}\" creado correctamente.");

        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Error al crear el tenant: ' . $e->getMessage());
        }
    }

    public function success(int $tenant): Response
    {
        $t = Tenant::findOrFail($tenant);

        return Inertia::render('Onboarding/Success', [
            'tenant' => [
                'id'       => $t->id,
                'name'     => $t->name,
                'slug'     => $t->slug,
                'domain'   => $t->domain,
                'database' => $t->database,
                'email'    => $t->email,
            ],
        ]);
    }

    /**
     * Verifica disponibilidad del slug en tiempo real (AJAX).
     */
    public function checkSlug(Request $request): \Illuminate\Http\JsonResponse
    {
        $slug      = Str::slug($request->input('slug', ''), '-');
        $available = !Tenant::where('slug', $slug)->exists();

        return response()->json([
            'slug'      => $slug,
            'available' => $available,
        ]);
    }
}
