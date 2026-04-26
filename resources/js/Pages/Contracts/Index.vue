<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
    contracts: Object,
    filters:   Object,
})

const page  = usePage()
const flash = computed(() => page.props.flash)
const { confirmDelete } = useConfirm()

const can = computed(() => {
    const perms = page.props.auth?.user?.permissions ?? []
    return {
        create: perms.includes('contracts.create'),
        edit:   perms.includes('contracts.edit'),
    }
})

const search       = ref(props.filters?.search ?? '')
const statusFilter = ref(props.filters?.status ?? '')
const typeFilter   = ref(props.filters?.type   ?? '')

let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => applyFilters(), 400)
})
watch([statusFilter, typeFilter], () => applyFilters())

function applyFilters() {
    router.get(route('contracts.index'), {
        search: search.value       || undefined,
        status: statusFilter.value || undefined,
        type:   typeFilter.value   || undefined,
    }, { preserveState: true, replace: true })
}

async function handleDelete(contract) {
    const confirmed = await confirmDelete(`${contract.number} — ${contract.name}`)
    if (confirmed) router.delete(route('contracts.destroy', contract.id))
}

const statusConfig = {
    borrador:   { bg: 'var(--color-bg-elevated)',         text: 'var(--color-text-secondary)', dot: 'var(--color-text-muted)' },
    vigente:    { bg: 'var(--color-success-container)',   text: 'var(--color-on-success-container)', dot: 'var(--color-on-success-container)' },
    suspendido: { bg: 'var(--color-error-container)',     text: 'var(--color-on-error-container)', dot: 'var(--color-on-error-container)' },
    terminado:  { bg: 'var(--color-bg-elevated)',         text: 'var(--color-text-muted)', dot: 'var(--color-text-muted)' },
    en_disputa: { bg: 'var(--color-primary-container)',   text: 'var(--color-on-primary-container)', dot: 'var(--color-on-primary-container)' },
}

function fmt(amount, currency) {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency', currency,
        minimumFractionDigits: currency === 'CLP' ? 0 : 2,
    }).format(amount)
}
</script>

<template>
    <AppLayout title="Contratos">
        <!-- Encabezado -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">
                    Contratos
                </h2>
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    Gestión contractual del espacio de trabajo
                </p>
            </div>
            <Link v-if="can.create"
                :href="route('contracts.create')"
                class="flex items-center gap-2 px-6 py-3 rounded-full font-bold text-sm transition-all active:scale-95"
                style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary);"
            >
                <span class="material-symbols-outlined" style="font-size: 18px;">add</span>
                Nuevo Contrato
            </Link>
        </div>

        <!-- Flash -->
        <div v-if="flash?.success" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-success-container); color: var(--color-on-success-container);">
            <span class="material-symbols-outlined">check_circle</span>
            {{ flash.success }}
        </div>

        <!-- Filtros -->
        <div class="flex flex-wrap items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant);">
            <div class="flex items-center gap-2 flex-1 min-w-48 px-4 py-2 rounded-xl"
                 style="background: var(--color-bg-input);">
                <span class="material-symbols-outlined text-sm" style="color: var(--color-text-muted);">search</span>
                <input v-model="search" type="text" placeholder="Buscar por nombre o número..."
                       class="bg-transparent border-none outline-none flex-1 text-sm"
                       style="font-family: var(--font-body); color: var(--color-text-primary);">
            </div>
            <select v-model="statusFilter" class="px-4 py-2 rounded-xl text-sm border-none outline-none"
                    style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                <option value="">Todos los estados</option>
                <option value="borrador">Borrador</option>
                <option value="vigente">Vigente</option>
                <option value="suspendido">Suspendido</option>
                <option value="terminado">Terminado</option>
                <option value="en_disputa">En Disputa</option>
            </select>
            <select v-model="typeFilter" class="px-4 py-2 rounded-xl text-sm border-none outline-none"
                    style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                <option value="">Todos los tipos</option>
                <option value="obra">Obra</option>
                <option value="suministro">Suministro</option>
                <option value="servicios">Servicios</option>
                <option value="EPC">EPC</option>
                <option value="mixto">Mixto</option>
            </select>
        </div>

        <!-- Tabla -->
        <div class="rounded-3xl overflow-hidden" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr style="background: var(--color-bg-sidebar);">
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Contrato</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Tipo</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Partes</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Monto vigente</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Estado</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-right" style="color: var(--color-text-secondary);">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="c in contracts.data" :key="c.id"
                            class="transition-colors"
                            style="border-top: 1px solid var(--color-border-variant);"
                            :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                            :onMouseout="e => e.currentTarget.style.background = ''"
                        >
                            <!-- Contrato -->
                            <td class="px-6 py-5">
                                <Link :href="route('contracts.show', c.id)" class="block">
                                    <div class="font-bold text-sm mb-0.5" style="color: var(--color-text-primary);">{{ c.name }}</div>
                                    <div class="text-xs font-mono" style="color: var(--color-text-muted);">{{ c.number }}</div>
                                </Link>
                            </td>

                            <!-- Tipo -->
                            <td class="px-6 py-5 text-sm" style="color: var(--color-text-secondary);">{{ c.type_label }}</td>

                            <!-- Partes -->
                            <td class="px-6 py-5 text-xs" style="color: var(--color-text-secondary);">
                                <div class="font-semibold" style="color: var(--color-text-primary);">{{ c.mandante?.name }}</div>
                                <div style="color: var(--color-text-muted);">vs {{ c.contractor?.name }}</div>
                            </td>

                            <!-- Monto -->
                            <td class="px-6 py-5 text-sm font-semibold" style="color: var(--color-text-primary);">
                                {{ fmt(c.current_amount, c.currency) }}
                                <div v-if="c.current_amount !== c.original_amount" class="text-xs font-normal" style="color: var(--color-text-muted);">
                                    orig. {{ fmt(c.original_amount, c.currency) }}
                                </div>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-5">
                                <span
                                    class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-bold"
                                    :style="`background: ${statusConfig[c.status]?.bg}; color: ${statusConfig[c.status]?.text};`"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full" :style="`background: ${statusConfig[c.status]?.dot};`"></span>
                                    {{ c.status_label }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="route('contracts.show', c.id)"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                        style="color: var(--color-text-secondary);"
                                        :onMouseover="e => e.currentTarget.style.color = 'var(--color-primary)'"
                                        :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                                        title="Ver detalle"
                                    >
                                        <span class="material-symbols-outlined" style="font-size: 18px;">visibility</span>
                                    </Link>
                                    <Link v-if="can.edit"
                                        :href="route('contracts.edit', c.id)"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                        style="color: var(--color-text-secondary);"
                                        :onMouseover="e => e.currentTarget.style.color = 'var(--color-primary)'"
                                        :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                                        title="Editar"
                                    >
                                        <span class="material-symbols-outlined" style="font-size: 18px;">edit</span>
                                    </Link>
                                    <button v-if="can.edit"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                        style="color: var(--color-text-secondary); background: none; border: none; cursor: pointer;"
                                        :onMouseover="e => e.currentTarget.style.color = 'var(--color-error)'"
                                        :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                                        title="Eliminar"
                                        @click="handleDelete(c)"
                                    >
                                        <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="contracts.data.length === 0">
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="material-symbols-outlined" style="font-size: 48px; color: var(--color-text-muted);">description</span>
                                    <p class="font-semibold" style="color: var(--color-text-secondary);">No hay contratos registrados</p>
                                    <p class="text-sm" style="color: var(--color-text-muted);">
                                        {{ search || statusFilter || typeFilter ? 'Prueba con otros filtros' : 'Crea el primer contrato para comenzar' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="contracts.meta?.last_page > 1"
                 class="px-6 py-4 flex items-center justify-between"
                 style="background: var(--color-bg-sidebar); border-top: 1px solid var(--color-border-variant);">
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    Mostrando {{ contracts.meta.from }}–{{ contracts.meta.to }} de {{ contracts.meta.total }} contratos
                </p>
                <div class="flex items-center gap-2">
                    <a v-if="contracts.links.prev" :href="contracts.links.prev" class="px-3 py-1.5 rounded-lg text-sm font-semibold" style="color: var(--color-primary);">← Anterior</a>
                    <a v-if="contracts.links.next" :href="contracts.links.next" class="px-3 py-1.5 rounded-lg text-sm font-semibold" style="color: var(--color-primary);">Siguiente →</a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
