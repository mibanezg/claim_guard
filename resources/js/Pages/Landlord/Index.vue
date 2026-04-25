<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
    tenants: { type: Array, default: () => [] },
    plans:   { type: Array, default: () => [] },
    kpis:    { type: Object, default: () => ({}) },
})

// ── Navegación entre secciones ────────────────────────────────────────────
const section = ref('tenants') // 'tenants' | 'plans'

// ── Flash ─────────────────────────────────────────────────────────────────
const flash = ref(null)

// ── Búsqueda y filtro ─────────────────────────────────────────────────────
const search    = ref('')
const filterActive = ref('all') // 'all' | 'active' | 'inactive'

const filteredTenants = computed(() => {
    return props.tenants.filter(t => {
        const matchSearch = !search.value ||
            t.name.toLowerCase().includes(search.value.toLowerCase()) ||
            t.slug.toLowerCase().includes(search.value.toLowerCase())
        const matchActive = filterActive.value === 'all' ||
            (filterActive.value === 'active'   &&  t.is_active) ||
            (filterActive.value === 'inactive' && !t.is_active)
        return matchSearch && matchActive
    })
})

// ── Toggle activo ──────────────────────────────────────────────────────────
async function toggleActive(tenant) {
    const suspending = tenant.is_active

    if (suspending) {
        const result = await Swal.fire({
            title:              '¿Suspender workspace?',
            html:               `El workspace <strong>${tenant.name}</strong> quedará inaccesible para sus usuarios hasta que lo reactives.`,
            icon:               'warning',
            showCancelButton:   true,
            confirmButtonText:  'Sí, suspender',
            cancelButtonText:   'Cancelar',
            confirmButtonColor: '#dc2626',
            cancelButtonColor:  '#6b7280',
            reverseButtons:     true,
        })
        if (!result.isConfirmed) return
    }

    router.patch(route('landlord.tenants.toggle', tenant.id), {}, {
        preserveScroll: true,
    })
}

// ── Modal suscripción ──────────────────────────────────────────────────────
const subModal   = ref(false)
const subTenant  = ref(null)
const subForm    = useForm({
    plan_id:               '',
    status:                'trial',
    trial_ends_at:         '',
    current_period_start:  '',
    current_period_end:    '',
})

function openSubModal(tenant) {
    subTenant.value = tenant
    const sub = tenant.subscription
    subForm.plan_id              = props.plans[0]?.id ?? ''
    subForm.status               = sub?.status ?? 'trial'
    subForm.trial_ends_at        = ''
    subForm.current_period_start = ''
    subForm.current_period_end   = ''
    subModal.value = true
}

function submitSub() {
    subForm.post(route('landlord.tenants.subscription', subTenant.value.id), {
        preserveScroll: true,
        onSuccess: () => { subModal.value = false },
    })
}

// ── Modal nuevo plan ───────────────────────────────────────────────────────
const planModal  = ref(false)
const editingPlan = ref(null)
const planForm   = useForm({
    name:            '',
    slug:            '',
    description:     '',
    price_clp:       0,
    max_contracts:   10,
    max_users:       5,
    has_ai_features: false,
    has_sharepoint:  false,
    is_active:       true,
})

function openPlanModal(plan = null) {
    editingPlan.value = plan
    if (plan) {
        planForm.name            = plan.name
        planForm.slug            = plan.slug
        planForm.description     = plan.description ?? ''
        planForm.price_clp       = plan.price_clp
        planForm.max_contracts   = plan.max_contracts
        planForm.max_users       = plan.max_users
        planForm.has_ai_features = plan.has_ai_features
        planForm.has_sharepoint  = plan.has_sharepoint
        planForm.is_active       = plan.is_active
    } else {
        planForm.reset()
    }
    planModal.value = true
}

function submitPlan() {
    if (editingPlan.value) {
        planForm.put(route('landlord.plans.update', editingPlan.value.id), {
            preserveScroll: true,
            onSuccess: () => { planModal.value = false },
        })
    } else {
        planForm.post(route('landlord.plans.store'), {
            preserveScroll: true,
            onSuccess: () => { planModal.value = false },
        })
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────
const SUB_STATUS = {
    trial:     { label: 'Trial',      style: 'background: var(--color-warning-container); color: var(--color-warning);' },
    active:    { label: 'Activo',     style: 'background: var(--color-success-container); color: var(--color-success);' },
    suspended: { label: 'Suspendido', style: 'background: var(--color-error-container); color: var(--color-error);' },
    cancelled: { label: 'Cancelado',  style: 'background: var(--color-bg-elevated); color: var(--color-text-muted);' },
}

function formatClp(val) {
    if (!val) return 'CLP 0'
    if (val >= 1_000_000) return `CLP ${(val / 1_000_000).toFixed(1)} M`
    return `CLP ${Number(val).toLocaleString('es-CL')}`
}
</script>

<template>
    <Head title="Panel Landlord — Claim Guard" />

    <div class="flex min-h-screen" style="background: var(--color-bg-page); font-family: var(--font-body);">

        <!-- Sidebar landlord -->
        <aside class="w-56 flex flex-col flex-shrink-0"
               style="background: var(--color-bg-sidebar); border-right: 1px solid rgba(255,255,255,0.06);">

            <!-- Logo -->
            <div class="flex items-center gap-2.5 px-5 py-5"
                 style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background: var(--color-primary);">
                    <span class="material-symbols-outlined" style="font-size:18px; color:#fff;">shield</span>
                </div>
                <div>
                    <p class="text-sm font-bold leading-none" style="color: var(--color-text-sidebar); font-family: var(--font-headline);">Claim Guard</p>
                    <p class="text-xs mt-0.5" style="color: var(--color-text-sidebar-muted);">Panel Landlord</p>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 px-3 py-4 space-y-1">
                <button @click="section = 'tenants'"
                        class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all"
                        :style="section === 'tenants'
                            ? 'background: var(--color-primary); color: #fff;'
                            : 'color: var(--color-text-sidebar-muted); background: transparent;'">
                    <span class="material-symbols-outlined" style="font-size:18px;">business</span>
                    Workspaces
                </button>
                <button @click="section = 'plans'"
                        class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all"
                        :style="section === 'plans'
                            ? 'background: var(--color-primary); color: #fff;'
                            : 'color: var(--color-text-sidebar-muted); background: transparent;'">
                    <span class="material-symbols-outlined" style="font-size:18px;">sell</span>
                    Planes
                </button>
            </nav>

            <!-- Footer -->
            <div class="px-4 py-4" style="border-top: 1px solid rgba(255,255,255,0.06);">
                <a :href="route('onboarding.show')"
                   class="flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium transition-all w-full"
                   style="color: var(--color-text-sidebar-muted); background: rgba(255,255,255,0.05);">
                    <span class="material-symbols-outlined" style="font-size:16px;">add_circle</span>
                    Nuevo workspace
                </a>
            </div>
        </aside>

        <!-- Contenido principal -->
        <main class="flex-1 overflow-auto">

            <!-- ─── SECCIÓN WORKSPACES ──────────────────────────────────── -->
            <template v-if="section === 'tenants'">

                <!-- Header -->
                <div class="px-8 py-6" style="border-bottom: 1px solid var(--color-border);">
                    <h1 class="text-xl font-extrabold" style="color: var(--color-text-primary); font-family: var(--font-headline);">Workspaces</h1>
                    <p class="text-sm mt-0.5" style="color: var(--color-text-muted);">Gestión de tenants activos en la plataforma</p>
                </div>

                <!-- KPIs -->
                <div class="px-8 py-5 grid grid-cols-4 gap-4">
                    <div v-for="kpi in [
                            { label: 'Total workspaces', val: kpis.total,    icon: 'domain' },
                            { label: 'Activos',          val: kpis.activos,  icon: 'check_circle' },
                            { label: 'En trial',         val: kpis.en_trial, icon: 'hourglass_empty' },
                            { label: 'Ingresos / mes',   val: formatClp(kpis.ingresos), icon: 'payments' },
                         ]" :key="kpi.label"
                         class="rounded-2xl p-4 flex items-center gap-3"
                         style="background: var(--color-bg-card); border: 1px solid var(--color-border); box-shadow: var(--shadow-card);">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                             style="background: var(--color-primary-dim);">
                            <span class="material-symbols-outlined" style="font-size:20px; color: var(--color-primary);">{{ kpi.icon }}</span>
                        </div>
                        <div>
                            <p class="text-xl font-extrabold leading-none" style="color: var(--color-text-primary);">{{ kpi.val }}</p>
                            <p class="text-xs mt-1" style="color: var(--color-text-muted);">{{ kpi.label }}</p>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="px-8 pb-4 flex items-center gap-3">
                    <div class="relative flex-1 max-w-xs">
                        <span class="material-symbols-outlined absolute left-3 top-2.5"
                              style="font-size:18px; color: var(--color-text-muted);">search</span>
                        <input v-model="search"
                               type="text" placeholder="Buscar workspace…"
                               class="w-full h-9 pl-9 pr-4 rounded-xl text-sm border-0 outline-none"
                               style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                    </div>
                    <div class="flex items-center gap-1 rounded-xl p-1" style="background: var(--color-bg-input);">
                        <button v-for="f in [{k:'all',l:'Todos'},{k:'active',l:'Activos'},{k:'inactive',l:'Inactivos'}]"
                                :key="f.k"
                                @click="filterActive = f.k"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                                :style="filterActive === f.k
                                    ? 'background: var(--color-primary); color: #fff;'
                                    : 'color: var(--color-text-muted); background: transparent;'">
                            {{ f.l }}
                        </button>
                    </div>
                </div>

                <!-- Tabla de tenants -->
                <div class="px-8 pb-8">
                    <div class="rounded-2xl overflow-hidden"
                         style="background: var(--color-bg-card); border: 1px solid var(--color-border); box-shadow: var(--shadow-card);">
                        <table class="w-full text-sm">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--color-border); background: var(--color-bg-table-header);">
                                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide" style="color: var(--color-text-muted);">Workspace</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide" style="color: var(--color-text-muted);">Slug / URL</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide" style="color: var(--color-text-muted);">Plan</th>
                                    <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wide" style="color: var(--color-text-muted);">Usuarios</th>
                                    <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wide" style="color: var(--color-text-muted);">Estado</th>
                                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide" style="color: var(--color-text-muted);">Creado</th>
                                    <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wide" style="color: var(--color-text-muted);">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="filteredTenants.length === 0">
                                    <td colspan="7" class="py-12 text-center text-sm" style="color: var(--color-text-muted);">
                                        No se encontraron workspaces
                                    </td>
                                </tr>
                                <tr v-for="t in filteredTenants" :key="t.id"
                                    style="border-bottom: 1px solid var(--color-border);"
                                    class="transition-colors hover:bg-opacity-50">
                                    <!-- Nombre + email -->
                                    <td class="px-5 py-4">
                                        <p class="font-semibold" style="color: var(--color-text-primary);">{{ t.name }}</p>
                                        <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">{{ t.email }}</p>
                                    </td>
                                    <!-- Slug -->
                                    <td class="px-5 py-4">
                                        <span class="font-mono text-xs px-2 py-1 rounded-lg"
                                              style="background: var(--color-bg-input); color: var(--color-text-secondary);">
                                            {{ t.slug }}
                                        </span>
                                    </td>
                                    <!-- Plan + suscripción -->
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-medium" style="color: var(--color-text-primary);">
                                            {{ t.subscription?.plan_name ?? 'Sin plan' }}
                                        </p>
                                        <span v-if="t.subscription"
                                              class="text-xs px-2 py-0.5 rounded-full font-semibold"
                                              :style="SUB_STATUS[t.subscription.status]?.style">
                                            {{ SUB_STATUS[t.subscription.status]?.label }}
                                        </span>
                                    </td>
                                    <!-- Usuarios -->
                                    <td class="px-5 py-4 text-center">
                                        <span class="text-sm font-semibold" style="color: var(--color-text-primary);">{{ t.user_count }}</span>
                                    </td>
                                    <!-- Activo -->
                                    <td class="px-5 py-4 text-center">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold"
                                              :style="t.is_active
                                                  ? 'background: var(--color-success-container); color: var(--color-success);'
                                                  : 'background: var(--color-error-container); color: var(--color-error);'">
                                            <span class="material-symbols-outlined" style="font-size:13px;">
                                                {{ t.is_active ? 'check_circle' : 'cancel' }}
                                            </span>
                                            {{ t.is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <!-- Fecha -->
                                    <td class="px-5 py-4 text-sm" style="color: var(--color-text-muted);">{{ t.created_at }}</td>
                                    <!-- Acciones -->
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <button @click="openSubModal(t)"
                                                    class="p-1.5 rounded-lg transition-all"
                                                    style="color: var(--color-primary);"
                                                    title="Gestionar suscripción">
                                                <span class="material-symbols-outlined" style="font-size:18px;">sell</span>
                                            </button>
                                            <button @click="toggleActive(t)"
                                                    class="p-1.5 rounded-lg transition-all"
                                                    :style="t.is_active ? 'color: var(--color-error);' : 'color: var(--color-success);'"
                                                    :title="t.is_active ? 'Suspender' : 'Activar'">
                                                <span class="material-symbols-outlined" style="font-size:18px;">
                                                    {{ t.is_active ? 'block' : 'play_circle' }}
                                                </span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <!-- ─── SECCIÓN PLANES ──────────────────────────────────────── -->
            <template v-if="section === 'plans'">

                <!-- Header -->
                <div class="px-8 py-6 flex items-center justify-between"
                     style="border-bottom: 1px solid var(--color-border);">
                    <div>
                        <h1 class="text-xl font-extrabold" style="color: var(--color-text-primary); font-family: var(--font-headline);">Planes de suscripción</h1>
                        <p class="text-sm mt-0.5" style="color: var(--color-text-muted);">Define los planes disponibles para los workspaces</p>
                    </div>
                    <button @click="openPlanModal()"
                            class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition-all active:scale-95"
                            style="background: var(--color-primary); color: #fff; box-shadow: var(--shadow-primary);">
                        <span class="material-symbols-outlined" style="font-size:18px;">add</span>
                        Nuevo plan
                    </button>
                </div>

                <!-- Grid de planes -->
                <div class="px-8 py-6 grid grid-cols-3 gap-5">
                    <div v-for="plan in plans" :key="plan.id"
                         class="rounded-2xl p-5 flex flex-col gap-4"
                         style="background: var(--color-bg-card); border: 1px solid var(--color-border); box-shadow: var(--shadow-card);">

                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-extrabold text-base" style="color: var(--color-text-primary); font-family: var(--font-headline);">{{ plan.name }}</p>
                                <p class="text-xs mt-0.5 font-mono" style="color: var(--color-text-muted);">{{ plan.slug }}</p>
                            </div>
                            <button @click="openPlanModal(plan)"
                                    class="p-1.5 rounded-lg"
                                    style="color: var(--color-text-muted);">
                                <span class="material-symbols-outlined" style="font-size:18px;">edit</span>
                            </button>
                        </div>

                        <p class="text-2xl font-extrabold" style="color: var(--color-primary);">
                            {{ plan.price_clp === 0 ? 'Gratis' : formatClp(plan.price_clp) + ' / mes' }}
                        </p>

                        <div class="space-y-2 text-sm" style="color: var(--color-text-secondary);">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined" style="font-size:16px; color: var(--color-primary);">description</span>
                                Hasta {{ plan.max_contracts }} contratos
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined" style="font-size:16px; color: var(--color-primary);">group</span>
                                Hasta {{ plan.max_users }} usuarios
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined"
                                      :style="`font-size:16px; color: ${plan.has_ai_features ? 'var(--color-success)' : 'var(--color-text-muted)'};`">
                                    {{ plan.has_ai_features ? 'check_circle' : 'cancel' }}
                                </span>
                                Funciones de IA
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined"
                                      :style="`font-size:16px; color: ${plan.has_sharepoint ? 'var(--color-success)' : 'var(--color-text-muted)'};`">
                                    {{ plan.has_sharepoint ? 'check_circle' : 'cancel' }}
                                </span>
                                Integración SharePoint
                            </div>
                        </div>
                    </div>

                    <!-- Card vacía si no hay planes -->
                    <div v-if="plans.length === 0" class="col-span-3 py-16 text-center rounded-2xl"
                         style="background: var(--color-bg-card); border: 1px solid var(--color-border);">
                        <span class="material-symbols-outlined text-5xl block mb-3" style="color: var(--color-text-muted);">sell</span>
                        <p class="text-sm" style="color: var(--color-text-muted);">No hay planes configurados. Crea el primero.</p>
                    </div>
                </div>
            </template>
        </main>
    </div>

    <!-- ── Modal: gestionar suscripción ────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="subModal" class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background: rgba(0,0,0,0.5);" @click.self="subModal = false">
            <div class="w-full max-w-md rounded-2xl p-6"
                 style="background: var(--color-bg-card); box-shadow: 0 20px 60px rgba(0,0,0,0.3);">

                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-extrabold text-base" style="color: var(--color-text-primary); font-family: var(--font-headline);">
                        Suscripción — {{ subTenant?.name }}
                    </h3>
                    <button @click="subModal = false" style="color: var(--color-text-muted);">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">Plan</label>
                        <select v-model="subForm.plan_id"
                                class="w-full h-11 px-3 rounded-xl text-sm border-0 outline-none"
                                style="background: var(--color-bg-input); color: var(--color-text-primary);">
                            <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">Estado</label>
                        <select v-model="subForm.status"
                                class="w-full h-11 px-3 rounded-xl text-sm border-0 outline-none"
                                style="background: var(--color-bg-input); color: var(--color-text-primary);">
                            <option value="trial">Trial</option>
                            <option value="active">Activo</option>
                            <option value="suspended">Suspendido</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                    </div>
                    <div v-if="subForm.status === 'trial'">
                        <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">Fin del trial</label>
                        <input v-model="subForm.trial_ends_at" type="date"
                               class="w-full h-11 px-3 rounded-xl text-sm border-0 outline-none"
                               style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                    </div>
                    <div v-if="subForm.status === 'active'" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">Inicio período</label>
                            <input v-model="subForm.current_period_start" type="date"
                                   class="w-full h-11 px-3 rounded-xl text-sm border-0 outline-none"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">Fin período</label>
                            <input v-model="subForm.current_period_end" type="date"
                                   class="w-full h-11 px-3 rounded-xl text-sm border-0 outline-none"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button @click="subModal = false"
                            class="px-4 py-2.5 rounded-xl text-sm font-medium"
                            style="background: var(--color-bg-input); color: var(--color-text-secondary);">
                        Cancelar
                    </button>
                    <button @click="submitSub" :disabled="subForm.processing"
                            class="px-4 py-2.5 rounded-xl text-sm font-bold transition-all active:scale-95"
                            style="background: var(--color-primary); color: #fff;">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ── Modal: crear / editar plan ──────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="planModal" class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background: rgba(0,0,0,0.5);" @click.self="planModal = false">
            <div class="w-full max-w-lg rounded-2xl p-6"
                 style="background: var(--color-bg-card); box-shadow: 0 20px 60px rgba(0,0,0,0.3);">

                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-extrabold text-base" style="color: var(--color-text-primary); font-family: var(--font-headline);">
                        {{ editingPlan ? 'Editar plan' : 'Nuevo plan' }}
                    </h3>
                    <button @click="planModal = false" style="color: var(--color-text-muted);">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">Nombre *</label>
                            <input v-model="planForm.name" type="text" placeholder="Plan Profesional"
                                   class="w-full h-11 px-3 rounded-xl text-sm border-0 outline-none"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                            <p v-if="planForm.errors.name" class="text-xs mt-1" style="color: var(--color-error);">{{ planForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">Slug *</label>
                            <input v-model="planForm.slug" type="text" placeholder="profesional"
                                   class="w-full h-11 px-3 rounded-xl text-sm font-mono border-0 outline-none"
                                   :disabled="!!editingPlan"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">Descripción</label>
                        <input v-model="planForm.description" type="text"
                               class="w-full h-11 px-3 rounded-xl text-sm border-0 outline-none"
                               style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">Precio CLP / mes</label>
                            <input v-model.number="planForm.price_clp" type="number" min="0"
                                   class="w-full h-11 px-3 rounded-xl text-sm border-0 outline-none"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">Máx. contratos</label>
                            <input v-model.number="planForm.max_contracts" type="number" min="1"
                                   class="w-full h-11 px-3 rounded-xl text-sm border-0 outline-none"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">Máx. usuarios</label>
                            <input v-model.number="planForm.max_users" type="number" min="1"
                                   class="w-full h-11 px-3 rounded-xl text-sm border-0 outline-none"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                        </div>
                    </div>

                    <!-- Toggles de features -->
                    <div class="flex items-center gap-6 pt-1">
                        <label class="flex items-center gap-2.5 cursor-pointer select-none">
                            <div class="relative w-10 h-5 rounded-full transition-all"
                                 :style="planForm.has_ai_features ? 'background: var(--color-primary)' : 'background: var(--color-bg-input)'"
                                 @click="planForm.has_ai_features = !planForm.has_ai_features">
                                <div class="absolute top-0.5 w-4 h-4 rounded-full bg-white shadow transition-all"
                                     :style="planForm.has_ai_features ? 'left: calc(100% - 18px)' : 'left: 2px'"></div>
                            </div>
                            <span class="text-sm" style="color: var(--color-text-secondary);">Funciones IA</span>
                        </label>
                        <label class="flex items-center gap-2.5 cursor-pointer select-none">
                            <div class="relative w-10 h-5 rounded-full transition-all"
                                 :style="planForm.has_sharepoint ? 'background: var(--color-primary)' : 'background: var(--color-bg-input)'"
                                 @click="planForm.has_sharepoint = !planForm.has_sharepoint">
                                <div class="absolute top-0.5 w-4 h-4 rounded-full bg-white shadow transition-all"
                                     :style="planForm.has_sharepoint ? 'left: calc(100% - 18px)' : 'left: 2px'"></div>
                            </div>
                            <span class="text-sm" style="color: var(--color-text-secondary);">SharePoint</span>
                        </label>
                        <label v-if="editingPlan" class="flex items-center gap-2.5 cursor-pointer select-none">
                            <div class="relative w-10 h-5 rounded-full transition-all"
                                 :style="planForm.is_active ? 'background: var(--color-success)' : 'background: var(--color-bg-input)'"
                                 @click="planForm.is_active = !planForm.is_active">
                                <div class="absolute top-0.5 w-4 h-4 rounded-full bg-white shadow transition-all"
                                     :style="planForm.is_active ? 'left: calc(100% - 18px)' : 'left: 2px'"></div>
                            </div>
                            <span class="text-sm" style="color: var(--color-text-secondary);">Activo</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button @click="planModal = false"
                            class="px-4 py-2.5 rounded-xl text-sm font-medium"
                            style="background: var(--color-bg-input); color: var(--color-text-secondary);">
                        Cancelar
                    </button>
                    <button @click="submitPlan" :disabled="planForm.processing"
                            class="px-4 py-2.5 rounded-xl text-sm font-bold transition-all active:scale-95"
                            style="background: var(--color-primary); color: #fff;">
                        {{ editingPlan ? 'Guardar cambios' : 'Crear plan' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
