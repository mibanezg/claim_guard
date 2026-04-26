<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import { computed, ref, watch, onMounted, nextTick } from 'vue'

const props = defineProps({
    isOpen: { type: Boolean, default: false },
})
const emit = defineEmits(['close'])

const page   = usePage()
const user   = computed(() => page.props.auth.user)
const tenant = computed(() => page.props.tenant)
const navEl  = ref(null)

const userPerms = computed(() => user.value?.permissions ?? [])
const hasPermission = (perm) => userPerms.value.includes(perm)

const allNavItems = [
    { label: 'Dashboard',          icon: 'dashboard',      routeName: 'dashboard' },
    { label: 'Contratos',          icon: 'description',    routeName: 'contracts.index',       activePattern: 'contracts.*' },
    { label: 'Programa',           icon: 'calendar_month', routeName: 'milestones.index' },
    { label: 'Eventos',            icon: 'event_note',     routeName: 'events.index' },
    { label: 'Diario de Obra',     icon: 'book',           routeName: 'daily-reports.index' },
    { label: 'Cartas',             icon: 'mail',           routeName: 'letters.index' },
    { label: 'Órd. de Cambio',     icon: 'swap_horiz',     routeName: 'change-orders.index' },
    { label: 'Riesgo de Claim',    icon: 'shield',         routeName: 'risk.index',            permission: 'risk.view' },
    { label: 'Quantum',            icon: 'calculate',      routeName: 'quantum.index' },
    { label: 'Análisis CPM',       icon: 'account_tree',   routeName: 'delay-analysis.index' },
    { label: 'Reserva Derechos',   icon: 'policy',         routeName: 'rights.index' },
    { label: 'Análisis IA',        icon: 'psychology',     routeName: 'analysis.index' },
    { label: 'Expediente',         icon: 'folder_special', routeName: 'expediente.index',      permission: 'expediente.generate' },
    { label: 'Documentos',         icon: 'folder',         routeName: 'documents.index' },
    { label: 'Reportes',           icon: 'bar_chart',      routeName: 'reports.index' },
    { label: 'Empresas',           icon: 'business',       routeName: 'companies.index' },
    { label: 'Usuarios',           icon: 'group',          routeName: 'users.index',           permission: 'settings.tenant' },
]

const navItems = computed(() =>
    allNavItems.filter(item => !item.permission || hasPermission(item.permission))
)

function isActive(item) {
    if (item.activePattern) return route().current(item.activePattern)
    return route().current(item.routeName)
}

function logout() {
    router.post(route('logout'))
}

function handleNavClick() {
    emit('close')
}

// Scroll al ítem activo dentro del nav del sidebar
function scrollToActive() {
    nextTick(() => {
        const active = navEl.value?.querySelector('[data-active="true"]')
        active?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
    })
}

onMounted(scrollToActive)
watch(() => page.url, scrollToActive)
</script>

<template>
    <aside
        class="fixed left-0 top-0 z-40 h-screen flex flex-col p-6 space-y-2 transition-transform duration-300 ease-in-out"
        :class="isOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
        style="width: var(--sidebar-width); background: var(--color-bg-sidebar); font-family: var(--font-body); font-size: 14px; font-weight: 500;"
    >
        <!-- Logo / Tenant -->
        <div class="mb-8 px-2 flex items-center gap-3">
            <div
                class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                style="background: var(--gradient-primary);"
            >
                <span class="material-symbols-outlined text-white" style="font-size: 20px;">shield</span>
            </div>
            <div class="min-w-0">
                <h2
                    class="font-extrabold text-lg leading-tight truncate"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);"
                >
                    Claim Guard
                </h2>
                <p class="text-xs font-normal truncate" style="color: var(--color-text-secondary);">
                    {{ tenant?.name ?? 'Workspace' }}
                </p>
            </div>
        </div>

        <!-- Navegación principal -->
        <nav ref="navEl" class="flex-1 space-y-1 overflow-y-auto" style="scrollbar-width: none;">
            <template v-for="item in navItems" :key="item.routeName">
                <Link
                    v-if="route().has(item.routeName)"
                    :href="route(item.routeName)"
                    :data-active="isActive(item) ? 'true' : 'false'"
                    class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200"
                    :style="isActive(item)
                        ? 'background: var(--color-bg-card); color: var(--color-text-primary); font-weight: 600; box-shadow: var(--shadow-sidebar-item);'
                        : 'color: var(--color-text-secondary);'"
                    :onMouseover="e => !isActive(item) && (e.currentTarget.style.background = 'var(--color-bg-hover)')"
                    :onMouseout="e => !isActive(item) && (e.currentTarget.style.background = '')"
                    @click="handleNavClick"
                >
                    <span class="material-symbols-outlined shrink-0" style="font-size: 20px;">{{ item.icon }}</span>
                    <span class="truncate">{{ item.label }}</span>
                </Link>
                <span
                    v-else
                    class="flex items-center gap-3 px-4 py-3 rounded-lg cursor-not-allowed"
                    style="color: var(--color-text-muted); opacity: 0.6;"
                    :title="`${item.label} — próximamente`"
                >
                    <span class="material-symbols-outlined shrink-0" style="font-size: 20px;">{{ item.icon }}</span>
                    <span class="truncate">{{ item.label }}</span>
                </span>
            </template>
        </nav>

        <!-- Sección inferior -->
        <div class="pt-4 flex flex-col space-y-1 shrink-0" style="border-top: 1px solid var(--color-border-variant);">
            <button v-if="hasPermission('contracts.create')"
                class="w-full mb-4 py-3 px-4 rounded-full font-bold flex items-center justify-center gap-2 active:scale-95 transition-all"
                style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); font-family: var(--font-body);"
            >
                <span class="material-symbols-outlined" style="font-size: 18px;">add</span>
                Nuevo Contrato
            </button>

            <Link
                v-if="route().has('settings.tenant') && hasPermission('settings.tenant')"
                :href="route('settings.tenant')"
                class="flex items-center gap-3 px-4 py-2 rounded-lg transition-all"
                style="color: var(--color-text-secondary);"
                :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                :onMouseout="e => e.currentTarget.style.background = ''"
                @click="handleNavClick"
            >
                <span class="material-symbols-outlined" style="font-size: 20px;">settings</span>
                <span>Configuración</span>
            </Link>

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
