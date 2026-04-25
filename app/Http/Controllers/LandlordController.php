<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LandlordController extends Controller
{
    // ── Dashboard / lista de tenants ─────────────────────────────────────────

    public function index(): Response
    {
        $tenants = Tenant::with('subscription.plan')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Tenant $t) => [
                'id'         => $t->id,
                'name'       => $t->name,
                'slug'       => $t->slug,
                'email'      => $t->email,
                'is_active'  => $t->is_active,
                'created_at' => $t->created_at?->format('d/m/Y'),
                'user_count' => DB::connection('landlord')->table('users')->where('tenant_id', $t->id)->whereNull('deleted_at')->count(),
                'subscription' => $t->subscription ? [
                    'status'     => $t->subscription->status,
                    'plan_name'  => $t->subscription->plan?->name ?? 'Sin plan',
                    'trial_ends' => $t->subscription->trial_ends_at?->format('d/m/Y'),
                    'period_end' => $t->subscription->current_period_end?->format('d/m/Y'),
                ] : null,
            ]);

        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('price_clp')
            ->get(['id', 'name', 'slug', 'price_clp', 'max_contracts', 'max_users', 'has_ai_features', 'has_sharepoint']);

        $kpis = [
            'total'     => $tenants->count(),
            'activos'   => $tenants->where('is_active', true)->count(),
            'en_trial'  => $tenants->filter(fn ($t) => $t['subscription']['status'] ?? null === 'trial')->count(),
            'ingresos'  => SubscriptionPlan::whereHas('tenantSubscriptions', fn ($q) => $q->where('status', 'active'))
                ->join('tenant_subscriptions', 'subscription_plans.id', '=', 'tenant_subscriptions.subscription_plan_id')
                ->where('tenant_subscriptions.status', 'active')
                ->sum('subscription_plans.price_clp'),
        ];

        return Inertia::render('Landlord/Index', compact('tenants', 'plans', 'kpis'));
    }

    // ── Toggle activo / inactivo ──────────────────────────────────────────────

    public function toggleActive(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['is_active' => !$tenant->is_active]);

        return redirect()->route('landlord.index')->with('success', $tenant->is_active
            ? "Tenant \"{$tenant->name}\" activado."
            : "Tenant \"{$tenant->name}\" suspendido.");
    }

    // ── Asignar / cambiar suscripción ─────────────────────────────────────────

    public function updateSubscription(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'status'  => ['required', Rule::in(['trial', 'active', 'suspended', 'cancelled'])],
            'trial_ends_at'        => ['nullable', 'date'],
            'current_period_start' => ['nullable', 'date'],
            'current_period_end'   => ['nullable', 'date'],
        ]);

        TenantSubscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'subscription_plan_id' => $validated['plan_id'],
                'status'               => $validated['status'],
                'trial_ends_at'        => $validated['trial_ends_at'] ?? null,
                'current_period_start' => $validated['current_period_start'] ?? null,
                'current_period_end'   => $validated['current_period_end'] ?? null,
            ]
        );

        return redirect()->route('landlord.index')->with('success', "Suscripción de \"{$tenant->name}\" actualizada.");
    }

    // ── CRUD Planes ───────────────────────────────────────────────────────────

    public function plansIndex(): Response
    {
        $plans = SubscriptionPlan::withCount(['tenantSubscriptions as active_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('price_clp')
            ->get();

        return Inertia::render('Landlord/Plans', compact('plans'));
    }

    public function plansStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:80'],
            'slug'            => ['required', 'string', 'max:30', 'unique:subscription_plans,slug'],
            'description'     => ['nullable', 'string', 'max:500'],
            'price_clp'       => ['required', 'integer', 'min:0'],
            'max_contracts'   => ['required', 'integer', 'min:1'],
            'max_users'       => ['required', 'integer', 'min:1'],
            'has_ai_features' => ['boolean'],
            'has_sharepoint'  => ['boolean'],
        ]);

        SubscriptionPlan::create($validated);

        return back()->with('success', 'Plan creado correctamente.');
    }

    public function plansUpdate(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:80'],
            'description'     => ['nullable', 'string', 'max:500'],
            'price_clp'       => ['required', 'integer', 'min:0'],
            'max_contracts'   => ['required', 'integer', 'min:1'],
            'max_users'       => ['required', 'integer', 'min:1'],
            'has_ai_features' => ['boolean'],
            'has_sharepoint'  => ['boolean'],
            'is_active'       => ['boolean'],
        ]);

        $plan->update($validated);

        return back()->with('success', 'Plan actualizado correctamente.');
    }
}
