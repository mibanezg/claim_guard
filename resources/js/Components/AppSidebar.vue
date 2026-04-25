<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const user = computed(() => page.props.auth.user)
const tenant = computed(() => page.props.tenant)

const navItems = [
    { label: 'Dashboard',          icon: 'dashboard',      routeName: 'dashboard' },
    { label: 'Contratos',          icon: 'description',    routeName: 'contracts.index',  activePattern: 'contracts.*' },
    { label: 'Programa',           icon: 'calendar_month', routeName: 'milestones.index' },
    { label: 'Eventos',            icon: 'event_note',     routeName: 'events.index' },
    { label: 'Diario de Obra',     icon: 'book',           routeName: 'daily-reports.index' },
    { label: 'Cartas',             icon: 'mail',           routeName: 'letters.index' },
    { label: 'Órd. de Cambio',     icon: 'swap_horiz',     routeName: 'change-orders.index' },
    { label: 'Riesgo de Claim',    icon: 'shield',         routeName: 'risk.index' },
    { label: 'Quantum',             icon: 'calculate',      routeName: 'quantum.index' },
    { label: 'Análisis CPM',       icon: 'account_tree',   routeName: 'delay-analysis.index' },
    { label: 'Reserva Derechos',   icon: 'policy',         routeName: 'rights.index' },
    { label: 'Análisis IA',        icon: 'psychology',     routeName: 'analysis.index' },
    { label: 'Expediente',         icon: 'folder_special', routeName: 'expediente.index' },
    { label: 'Documentos',         icon: 'folder',         routeName: 'documents.index' },
    { label: 'Reportes',           icon: 'bar_chart',      routeName: 'reports.index' },
    { label: 'Empresas',           icon: 'business',       routeName: 'companies.index' },
    { label: 'Usuarios',           icon: 'group',          routeName: 'users.index' },
]

function isActive(item) {
    if (item.activePattern) return route().current(item.activePattern)
    return route().current(item.routeName)
}

function logout() {
    router.post(route('logout'))
}
</script>

<template>
    <aside
        class="fixed left-0 top-0 z-40 h-screen flex flex-col p-6 space-y-2"
        style="width: var(--sidebar-width); background: var(--color-bg-sidebar); font-family: var(--font-body); font-size: 14px; font-weight: 500;"
    >
        <!-- Logo / Tenant -->
        <div class="mb-8 px-2 flex items-center gap-3">
            <div
                class="w-10 h-10 rounded-xl flex items-center justify-center"
                style="background: var(--gradient-primary);"
            >
                <span class="material-symbols-outlined text-white" style="font-size: 20px;">shield</span>
            </div>
            <div>
                <h2
                    class="font-extrabold text-lg leading-tight"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);"
                >
                    Claim Guard
                </h2>
                <p class="text-xs font-normal" style="color: var(--color-text-secondary);">
                    {{ tenant?.name ?? 'Workspace' }}
                </p>
            </div>
        </div>

        <!-- Navegación principal -->
        <nav class="flex-1 space-y-1 overflow-y-auto" style="scrollbar-width: none;">
            <template v-for="item in navItems" :key="item.routeName">
                <Link
                    v-if="route().has(item.routeName)"
                    :href="route(item.routeName)"
                    class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200"
                    :style="isActive(item)
                        ? 'background: var(--color-bg-card); color: var(--color-text-primary); font-weight: 600; box-shadow: var(--shadow-sidebar-item);'
                        : 'color: var(--color-text-secondary);'"
                    :onMouseover="e => !isActive(item) && (e.currentTarget.style.background = 'var(--color-bg-hover)')"
                    :onMouseout="e => !isActive(item) && (e.currentTarget.style.background = '')"
                >
                    <span class="material-symbols-outlined" style="font-size: 20px;">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                </Link>
                <!-- Ítem deshabilitado (ruta aún no creada) -->
                <span
                    v-else
                    class="flex items-center gap-3 px-4 py-3 rounded-lg cursor-not-allowed"
                    style="color: var(--color-text-muted); opacity: 0.6;"
                    :title="`${item.label} — próximamente`"
                >
                    <span class="material-symbols-outlined" style="font-size: 20px;">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                </span>
            </template>
        </nav>

        <!-- Sección inferior -->
        <div class="pt-4 flex flex-col space-y-1" style="border-top: 1px solid var(--color-border-variant);">
            <!-- Botón Nuevo Contrato -->
            <button
                class="w-full mb-4 py-3 px-4 rounded-full font-bold flex items-center justify-center gap-2 active:scale-95 transition-all"
                style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); font-family: var(--font-body);"
            >
                <span class="material-symbols-outlined" style="font-size: 18px;">add</span>
                Nuevo Contrato
            </button>

            <!-- Configuración -->
            <Link
                v-if="route().has('settings.tenant')"
                :href="route('settings.tenant')"
                class="flex items-center gap-3 px-4 py-2 rounded-lg transition-all"
                style="color: var(--color-text-secondary);"
                :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                :onMouseout="e => e.currentTarget.style.background = ''"
            >
                <span class="material-symbols-outlined" style="font-size: 20px;">settings</span>
                <span>Configuración</span>
            </Link>

            <!-- Cerrar sesión -->
            <button
                class="w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-all text-left"
                style="color: var(--color-text-secondary); font-family: var(--font-body); font-size: 14px; font-weight: 500; background: none; border: none; cursor: pointer;"
                :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                :onMouseout="e => e.currentTarget.style.background = ''"
                @click="logout"
            >
                <span class="material-symbols-outlined" style="font-size: 20px;">logout</span>
                <span>Cerrar sesión</span>
            </button>
        </div>
    </aside>
</template>
