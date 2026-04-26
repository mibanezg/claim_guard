<script setup>
import { ref, computed } from 'vue'
import { usePage, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ChangeOrderModal from '@/Components/ChangeOrderModal.vue'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
    contracts:        { type: Object, default: () => ({}) },
    selectedContract: { type: Object, default: null },
    changeOrders:     { type: Object, default: null },
    contractEvents:   { type: Array,  default: () => [] },
    filters:          { type: Object, default: () => ({}) },
    statusLabels:     { type: Object, default: () => ({}) },
    partyLabels:      { type: Object, default: () => ({}) },
})

const page  = usePage()
const flash = computed(() => page.props.flash)
const { confirmDelete } = useConfirm()

const can = computed(() => {
    const perms = page.props.auth?.user?.permissions ?? []
    return { create: perms.includes('change_orders.create') }
})

// Contrato
const selectedContractId = ref(props.selectedContract?.id ?? null)

function selectContract(id) {
    selectedContractId.value = id
    router.get(route('change-orders.index'), { contract_id: id }, {
        preserveState: true, preserveScroll: true, replace: true,
    })
}

// Filtros
const filterStatus = ref(props.filters.status ?? '')
const filterParty  = ref(props.filters.requested_by_party ?? '')

function applyFilters() {
    router.get(route('change-orders.index'), {
        contract_id:         selectedContractId.value,
        status:              filterStatus.value  || undefined,
        requested_by_party:  filterParty.value   || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function clearFilters() {
    filterStatus.value = ''
    filterParty.value  = ''
    applyFilters()
}

// Modal crear/editar
const showModal = ref(false)
const editOrder = ref(null)

function openCreate() { editOrder.value = null; showModal.value = true }
function openEdit(o)  { editOrder.value = o;    showModal.value = true }
function closeModal()  { showModal.value = false }

// Modal aprobar
const showApproveModal  = ref(false)
const approvingOrder    = ref(null)
const approveForm = useForm({ partial: false, cost_impact: 0, schedule_impact_days: 0 })

function openApprove(order) {
    approvingOrder.value          = order
    approveForm.partial            = false
    approveForm.cost_impact        = order.cost_impact
    approveForm.schedule_impact_days = order.schedule_impact_days
    showApproveModal.value         = true
}

function submitApprove() {
    approveForm.patch(route('change-orders.approve', {
        contract:    props.selectedContract.id,
        changeOrder: approvingOrder.value.id,
    }), { onSuccess: () => { showApproveModal.value = false } })
}

// Modal rechazar
const showRejectModal  = ref(false)
const rejectingOrder   = ref(null)
const rejectForm = useForm({ rejection_notes: '' })

function openReject(order) {
    rejectingOrder.value       = order
    rejectForm.rejection_notes = ''
    showRejectModal.value      = true
}

function submitReject() {
    rejectForm.patch(route('change-orders.reject', {
        contract:    props.selectedContract.id,
        changeOrder: rejectingOrder.value.id,
    }), { onSuccess: () => { showRejectModal.value = false } })
}

// Eliminar
async function deleteOrder(order) {
    if (!await confirmDelete(order.request_number)) return
    router.delete(route('change-orders.destroy', {
        contract:    props.selectedContract.id,
        changeOrder: order.id,
    }))
}

// Datos
const orders = computed(() => props.changeOrders?.data ?? [])

const stats = computed(() => {
    const all = orders.value
    return {
        total:      all.length,
        pendientes: all.filter(o => o.is_pending).length,
        aprobadas:  all.filter(o => o.status === 'aprobada' || o.status === 'aprobada_parcialmente').length,
        rechazadas: all.filter(o => o.status === 'rechazada').length,
    }
})

function statusStyle(status) {
    const map = {
        solicitada:            'background: rgba(59,130,246,0.12); color: #3b82f6;',
        evaluacion:            'background: rgba(234,179,8,0.12); color: #eab308;',
        aprobada:              'background: rgba(34,197,94,0.12); color: #22c55e;',
        rechazada:             'background: rgba(239,68,68,0.12); color: #ef4444;',
        aprobada_parcialmente: 'background: rgba(168,85,247,0.12); color: #a855f7;',
    }
    return map[status] ?? ''
}

function formatMoney(val, currency) {
    if (!val && val !== 0) return '—'
    return new Intl.NumberFormat('es-CL', {
        style: 'currency', currency: currency ?? 'CLP', maximumFractionDigits: 0,
    }).format(val)
}
</script>

<template>
    <AppLayout title="Órd. de Cambio">
        <div class="flex gap-6 h-full">

            <!-- Panel lateral -->
            <div class="w-72 flex-shrink-0 flex flex-col gap-4">
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-3"
                        style="color: var(--color-text-secondary); font-family: var(--font-body);">Contrato</h3>
                    <div class="space-y-1">
                        <button v-for="c in contracts.data" :key="c.id"
                                @click="selectContract(c.id)"
                                class="w-full text-left px-3 py-2.5 rounded-xl text-sm transition-all"
                                :style="selectedContractId === c.id
                                    ? 'background: var(--color-primary); color: var(--color-on-primary); font-weight: 600;'
                                    : 'color: var(--color-text-primary);'"
                                :onMouseover="e => selectedContractId !== c.id && (e.currentTarget.style.background = 'var(--color-bg-hover)')"
                                :onMouseout="e => selectedContractId !== c.id && (e.currentTarget.style.background = '')">
                            <div class="font-semibold truncate">{{ c.name }}</div>
                            <div class="text-xs opacity-70">{{ c.number }}</div>
                        </button>
                    </div>
                </div>

                <!-- Filtros -->
                <div v-if="selectedContract" class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-3"
                        style="color: var(--color-text-secondary); font-family: var(--font-body);">Filtros</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider mb-1"
                                   style="color: var(--color-text-secondary);">Estado</label>
                            <select v-model="filterStatus" @change="applyFilters"
                                    class="w-full px-3 py-2 rounded-xl text-sm border-none outline-none"
                                    style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                <option value="">Todos</option>
                                <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider mb-1"
                                   style="color: var(--color-text-secondary);">Parte solicitante</label>
                            <select v-model="filterParty" @change="applyFilters"
                                    class="w-full px-3 py-2 rounded-xl text-sm border-none outline-none"
                                    style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                <option value="">Todas</option>
                                <option v-for="(label, key) in partyLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <button v-if="filterStatus || filterParty" @click="clearFilters"
                                class="w-full py-2 rounded-xl text-xs font-bold"
                                style="background: var(--color-bg-elevated); color: var(--color-text-secondary); border: none; cursor: pointer;">
                            Limpiar filtros
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panel principal -->
            <div class="flex-1 flex flex-col gap-6 min-w-0">

                <!-- Flash -->
                <div v-if="flash.success" class="flex items-center gap-3 px-5 py-3 rounded-2xl"
                     style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);">
                    <span class="material-symbols-outlined" style="color: #22c55e; font-size: 20px;">check_circle</span>
                    <span class="text-sm font-medium" style="color: #22c55e;">{{ flash.success }}</span>
                </div>
                <div v-if="flash.error" class="flex items-center gap-3 px-5 py-3 rounded-2xl"
                     style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);">
                    <span class="material-symbols-outlined" style="color: #ef4444; font-size: 20px;">error</span>
                    <span class="text-sm font-medium" style="color: #ef4444;">{{ flash.error }}</span>
                </div>

                <template v-if="selectedContract">

                    <!-- Header -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-extrabold" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                Órdenes de cambio
                            </h2>
                            <p class="text-sm mt-1" style="color: var(--color-text-secondary);">
                                {{ selectedContract.name }} — {{ selectedContract.number }}
                                <span class="ml-3 font-semibold" style="color: var(--color-primary);">
                                    Monto vigente: {{ formatMoney(selectedContract.current_amount, selectedContract.currency) }}
                                </span>
                            </p>
                        </div>
                        <button v-if="can.create" @click="openCreate"
                                class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">add</span>
                            Nueva OC
                        </button>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">Total</p>
                            <p class="text-3xl font-extrabold" style="color: var(--color-text-primary); font-family: var(--font-headline);">{{ stats.total }}</p>
                        </div>
                        <div class="rounded-2xl p-5"
                             :style="stats.pendientes > 0 ? 'background: rgba(234,179,8,0.08); border: 1px solid rgba(234,179,8,0.2);' : 'background: var(--color-bg-card);'">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">Pendientes</p>
                            <p class="text-3xl font-extrabold"
                               :style="stats.pendientes > 0 ? 'color: #eab308;' : 'color: var(--color-text-primary);'"
                               style="font-family: var(--font-headline);">{{ stats.pendientes }}</p>
                        </div>
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">Aprobadas</p>
                            <p class="text-3xl font-extrabold" style="color: #22c55e; font-family: var(--font-headline);">{{ stats.aprobadas }}</p>
                        </div>
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">Rechazadas</p>
                            <p class="text-3xl font-extrabold" style="color: #ef4444; font-family: var(--font-headline);">{{ stats.rechazadas }}</p>
                        </div>
                    </div>

                    <!-- Tabla -->
                    <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                        <div v-if="orders.length === 0" class="py-16 flex flex-col items-center gap-3">
                            <span class="material-symbols-outlined" style="font-size: 48px; color: var(--color-text-muted);">swap_horiz</span>
                            <p class="text-sm font-medium" style="color: var(--color-text-muted);">No hay órdenes de cambio registradas</p>
                            <button v-if="can.create" @click="openCreate"
                                    class="mt-2 px-5 py-2 rounded-full text-sm font-bold"
                                    style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer;">
                                Registrar primera OC
                            </button>
                        </div>

                        <table v-else class="w-full text-sm" style="font-family: var(--font-body); table-layout: fixed;">
                            <colgroup>
                                <col style="width: 160px;" />  <!-- N° OC -->
                                <col style="width: 110px;" />  <!-- Solicitante -->
                                <col />                        <!-- Descripción (flexible) -->
                                <col style="width: 160px;" />  <!-- Impactos -->
                                <col style="width: 140px;" />  <!-- Estado -->
                                <col style="width: 108px;" />  <!-- Acciones -->
                            </colgroup>
                            <thead>
                                <tr style="border-bottom: 1px solid var(--color-border-variant);">
                                    <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">N° OC</th>
                                    <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Solicitante</th>
                                    <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Descripción</th>
                                    <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Impactos</th>
                                    <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Estado</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="order in orders" :key="order.id"
                                    style="border-bottom: 1px solid var(--color-border-variant);"
                                    :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                    :onMouseout="e => e.currentTarget.style.background = ''">

                                    <!-- N° OC -->
                                    <td class="px-4 py-3">
                                        <span class="font-mono text-xs font-bold block truncate" style="color: var(--color-primary);">{{ order.request_number }}</span>
                                        <span class="text-xs" style="color: var(--color-text-muted);">{{ order.created_at }}</span>
                                    </td>

                                    <!-- Solicitante -->
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-bold"
                                              :style="order.requested_by_party === 'mandante'
                                                  ? 'background: rgba(59,130,246,0.12); color: #3b82f6;'
                                                  : 'background: rgba(168,85,247,0.12); color: #a855f7;'">
                                            {{ order.party_label }}
                                        </span>
                                    </td>

                                    <!-- Descripción -->
                                    <td class="px-4 py-3" style="min-width: 0;">
                                        <p class="truncate text-sm" style="color: var(--color-text-primary);">{{ order.description }}</p>
                                        <p v-if="order.approved_at" class="text-xs truncate" style="color: var(--color-text-muted);">
                                            Procesada {{ order.approved_at }}
                                        </p>
                                    </td>

                                    <!-- Impactos: plazo + costo apilados -->
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-0.5">
                                            <span v-if="order.schedule_impact_days !== 0" class="text-xs font-bold"
                                                  :style="order.schedule_impact_days > 0 ? 'color: #ef4444;' : 'color: #22c55e;'">
                                                {{ order.schedule_impact_days > 0 ? '+' : '' }}{{ order.schedule_impact_days }}d plazo
                                            </span>
                                            <span v-if="order.cost_impact !== 0" class="text-xs font-bold"
                                                  :style="order.cost_impact > 0 ? 'color: #ef4444;' : 'color: #22c55e;'">
                                                {{ order.cost_impact > 0 ? '+' : '' }}{{ formatMoney(order.cost_impact, selectedContract.currency) }}
                                            </span>
                                            <span v-if="order.schedule_impact_days === 0 && order.cost_impact === 0"
                                                  style="color: var(--color-text-muted);" class="text-xs">—</span>
                                        </div>
                                    </td>

                                    <!-- Estado -->
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-bold" :style="statusStyle(order.status)">
                                            {{ order.status_label }}
                                        </span>
                                        <div v-if="order.rejection_notes" class="text-xs mt-1 italic truncate"
                                             :title="order.rejection_notes" style="color: var(--color-text-muted);">
                                            {{ order.rejection_notes }}
                                        </div>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="px-4 py-3">
                                        <div v-if="can.create" class="flex items-center gap-0.5">
                                            <!-- Aprobar -->
                                            <button v-if="order.is_pending"
                                                    @click="openApprove(order)"
                                                    title="Aprobar"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg transition-all"
                                                    style="color: #22c55e; background: none; border: none; cursor: pointer;"
                                                    :onMouseover="e => e.currentTarget.style.background = 'rgba(34,197,94,0.1)'"
                                                    :onMouseout="e => e.currentTarget.style.background = ''">
                                                <span class="material-symbols-outlined" style="font-size: 18px;">check_circle</span>
                                            </button>
                                            <!-- Rechazar -->
                                            <button v-if="order.is_pending"
                                                    @click="openReject(order)"
                                                    title="Rechazar"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg transition-all"
                                                    style="color: #ef4444; background: none; border: none; cursor: pointer;"
                                                    :onMouseover="e => e.currentTarget.style.background = 'rgba(239,68,68,0.1)'"
                                                    :onMouseout="e => e.currentTarget.style.background = ''">
                                                <span class="material-symbols-outlined" style="font-size: 18px;">cancel</span>
                                            </button>
                                            <!-- Editar -->
                                            <button v-if="order.is_pending"
                                                    @click="openEdit(order)"
                                                    title="Editar"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg transition-all"
                                                    style="color: var(--color-text-muted); background: none; border: none; cursor: pointer;"
                                                    :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                                    :onMouseout="e => e.currentTarget.style.background = ''">
                                                <span class="material-symbols-outlined" style="font-size: 18px;">edit</span>
                                            </button>
                                            <!-- Eliminar -->
                                            <button v-if="order.is_pending"
                                                    @click="deleteOrder(order)"
                                                    title="Eliminar"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg transition-all"
                                                    style="color: var(--color-text-muted); background: none; border: none; cursor: pointer;"
                                                    :onMouseover="e => { e.currentTarget.style.background = 'rgba(239,68,68,0.1)'; e.currentTarget.style.color = '#ef4444' }"
                                                    :onMouseout="e => { e.currentTarget.style.background = ''; e.currentTarget.style.color = 'var(--color-text-muted)' }">
                                                <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Paginación -->
                        <div v-if="props.changeOrders?.links && props.changeOrders.links.length > 3"
                             class="flex items-center justify-center gap-2 px-5 py-4"
                             style="border-top: 1px solid var(--color-border-variant);">
                            <template v-for="link in props.changeOrders.links" :key="link.label">
                                <button v-if="link.url"
                                        @click="router.get(link.url, {}, { preserveScroll: true })"
                                        class="px-3 py-1.5 rounded-lg text-sm font-medium"
                                        :style="link.active
                                            ? 'background: var(--color-primary); color: var(--color-on-primary);'
                                            : 'background: var(--color-bg-elevated); color: var(--color-text-secondary);'"
                                        v-html="link.label">
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <div v-else class="flex-1 flex flex-col items-center justify-center py-24 rounded-2xl"
                     style="background: var(--color-bg-card);">
                    <span class="material-symbols-outlined mb-4" style="font-size: 56px; color: var(--color-text-muted);">swap_horiz</span>
                    <p class="font-semibold" style="color: var(--color-text-secondary);">Selecciona un contrato para ver sus órdenes de cambio</p>
                </div>
            </div>
        </div>

        <!-- Modal crear/editar -->
        <ChangeOrderModal
            :show="showModal"
            :contract="selectedContract ?? {}"
            :order="editOrder"
            :party-labels="partyLabels"
            :contract-events="contractEvents"
            @close="closeModal"
        />

        <!-- Modal aprobar -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100">
                <div v-if="showApproveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style="background: rgba(0,0,0,0.5);" @click.self="showApproveModal = false">
                    <div class="w-full max-w-md rounded-3xl p-8 shadow-2xl" style="background: var(--color-bg-card);">
                        <h3 class="text-lg font-extrabold mb-1" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                            Aprobar OC
                        </h3>
                        <p class="text-sm mb-5 font-mono" style="color: var(--color-text-secondary);">
                            {{ approvingOrder?.request_number }}
                        </p>

                        <!-- Toggle parcial -->
                        <label class="flex items-center gap-3 mb-5 cursor-pointer">
                            <input type="checkbox" v-model="approveForm.partial" class="w-4 h-4" />
                            <span class="text-sm font-medium" style="color: var(--color-text-primary);">Aprobación parcial (ajustar montos)</span>
                        </label>

                        <div v-if="approveForm.partial" class="grid grid-cols-2 gap-4 mb-5">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Costo aprobado</label>
                                <input v-model.number="approveForm.cost_impact" type="number" step="0.01"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                       style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Días aprobados</label>
                                <input v-model.number="approveForm.schedule_impact_days" type="number"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                       style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                            </div>
                        </div>

                        <p class="text-xs mb-5 p-3 rounded-xl" style="background: rgba(34,197,94,0.08); color: var(--color-text-secondary);">
                            Al aprobar, el monto vigente del contrato se actualizará automáticamente.
                        </p>

                        <div class="flex justify-end gap-3">
                            <button type="button" @click="showApproveModal = false"
                                    class="px-5 py-2.5 rounded-full text-sm font-bold"
                                    style="background: var(--color-bg-elevated); color: var(--color-text-secondary); border: none; cursor: pointer;">
                                Cancelar
                            </button>
                            <button @click="submitApprove" :disabled="approveForm.processing"
                                    class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95 disabled:opacity-60"
                                    style="background: #22c55e; color: white; border: none; cursor: pointer;">
                                Confirmar aprobación
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Modal rechazar -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100">
                <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style="background: rgba(0,0,0,0.5);" @click.self="showRejectModal = false">
                    <div class="w-full max-w-md rounded-3xl p-8 shadow-2xl" style="background: var(--color-bg-card);">
                        <h3 class="text-lg font-extrabold mb-1" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                            Rechazar OC
                        </h3>
                        <p class="text-sm mb-5 font-mono" style="color: var(--color-text-secondary);">
                            {{ rejectingOrder?.request_number }}
                        </p>

                        <div class="mb-5">
                            <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                   style="color: var(--color-text-secondary);">Motivo del rechazo *</label>
                            <textarea v-model="rejectForm.rejection_notes" rows="3"
                                      placeholder="Explica el motivo del rechazo..."
                                      class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none resize-none"
                                      style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"></textarea>
                            <p v-if="rejectForm.errors.rejection_notes" class="text-xs mt-1" style="color: var(--color-error);">
                                {{ rejectForm.errors.rejection_notes }}
                            </p>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" @click="showRejectModal = false"
                                    class="px-5 py-2.5 rounded-full text-sm font-bold"
                                    style="background: var(--color-bg-elevated); color: var(--color-text-secondary); border: none; cursor: pointer;">
                                Cancelar
                            </button>
                            <button @click="submitReject" :disabled="rejectForm.processing"
                                    class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95 disabled:opacity-60"
                                    style="background: #ef4444; color: white; border: none; cursor: pointer;">
                                Confirmar rechazo
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AppLayout>
</template>
