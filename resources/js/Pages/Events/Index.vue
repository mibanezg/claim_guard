<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EventModal from '@/Components/EventModal.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
    contracts:          { type: Object,  required: true },
    selectedContract:   { type: Object,  default: null },
    events:             { type: Object,  default: null },
    filters:            { type: Object,  default: () => ({}) },
    flash:              { type: Object,  default: () => ({}) },
    typeLabels:         { type: Object,  default: () => ({}) },
    partyLabels:        { type: Object,  default: () => ({}) },
    resolutionLabels:   { type: Object,  default: () => ({}) },
    notificationLabels: { type: Object,  default: () => ({}) },
    basisDocLabels:     { type: Object,  default: () => ({}) },
})

const page  = usePage()
const flash = computed(() => page.props.flash)
const { confirmDelete } = useConfirm()

const showModal  = ref(false)
const editTarget = ref(null)

const typeFilter   = ref(props.filters?.type                ?? '')
const partyFilter  = ref(props.filters?.responsible_party   ?? '')
const resFilter    = ref(props.filters?.resolution_status   ?? '')
const notifFilter  = ref(props.filters?.notification_status ?? '')

function selectContract(id) {
    router.get(route('events.index'), { contract_id: id }, { preserveState: false })
}

function applyFilters() {
    router.get(route('events.index'), {
        contract_id:         props.selectedContract?.id,
        type:                typeFilter.value   || undefined,
        responsible_party:   partyFilter.value  || undefined,
        resolution_status:   resFilter.value    || undefined,
        notification_status: notifFilter.value  || undefined,
    }, { preserveState: true, replace: true })
}

function openCreate() {
    editTarget.value = null
    showModal.value  = true
}

function openEdit(event) {
    editTarget.value = event
    showModal.value  = true
}

function closeModal() {
    showModal.value  = false
    editTarget.value = null
}

async function handleDelete(event) {
    const confirmed = await confirmDelete(event.type_label)
    if (!confirmed) return
    router.delete(route('events.destroy', {
        contract: props.selectedContract.id,
        event:    event.id,
    }), { preserveScroll: true })
}

function fmt(amount, currency = 'CLP') {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency', currency,
        minimumFractionDigits: currency === 'CLP' ? 0 : 2,
    }).format(amount)
}

const partyConfig = {
    mandante:     { bg: 'var(--color-error-container)',     text: 'var(--color-on-error-container)' },
    contratista:  { bg: 'var(--color-primary-container)',   text: 'var(--color-on-primary-container)' },
    fuerza_mayor: { bg: 'var(--color-bg-elevated)',         text: 'var(--color-text-secondary)' },
    tercero:      { bg: 'var(--color-bg-elevated)',         text: 'var(--color-text-secondary)' },
}

const resConfig = {
    pendiente:   { bg: 'var(--color-error-container)',     text: 'var(--color-on-error-container)' },
    negociacion: { bg: 'var(--color-primary-container)',   text: 'var(--color-on-primary-container)' },
    resuelto:    { bg: 'var(--color-success-container)',   text: 'var(--color-on-success-container)' },
    escalado:    { bg: 'var(--color-bg-elevated)',         text: 'var(--color-text-secondary)' },
}

const notifConfig = {
    pendiente:           { bg: 'rgba(234,179,8,0.15)',   text: '#a16207' },
    notificado_a_tiempo: { bg: 'var(--color-success-container)', text: 'var(--color-on-success-container)' },
    notificado_tarde:    { bg: 'rgba(249,115,22,0.15)', text: '#c2410c' },
    no_aplica:           { bg: 'var(--color-bg-elevated)',        text: 'var(--color-text-muted)' },
}

const stats = computed(() => {
    const data = props.events?.data ?? []
    return {
        total:         data.length,
        pendiente:     data.filter(e => e.resolution_status === 'pendiente').length,
        mandante:      data.filter(e => e.responsible_party === 'mandante').length,
        notifVencidas: data.filter(e => e.is_notice_overdue).length,
    }
})
</script>

<template>
    <AppLayout title="Eventos Contractuales">

        <!-- Flash -->
        <div v-if="flash?.success" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-success-container); color: var(--color-on-success-container);">
            <span class="material-symbols-outlined">check_circle</span>{{ flash.success }}
        </div>

        <!-- Encabezado -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">Eventos Contractuales</h2>
                <p class="text-sm" style="color: var(--color-text-secondary);">Registro de hechos relevantes durante la ejecución</p>
            </div>
            <button v-if="selectedContract" @click="openCreate"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-full font-bold text-sm transition-all active:scale-95"
                    style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                <span class="material-symbols-outlined" style="font-size: 16px;">add</span>
                Registrar evento
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            <!-- Panel de contratos -->
            <div class="lg:col-span-1">
                <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <div class="px-5 py-4" style="border-bottom: 1px solid var(--color-border-variant);">
                        <p class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Contratos</p>
                    </div>
                    <nav class="p-2">
                        <button v-for="c in contracts.data" :key="c.id" @click="selectContract(c.id)"
                                class="w-full text-left px-4 py-3 rounded-xl mb-1 transition-all text-sm"
                                :style="selectedContract?.id === c.id
                                    ? 'background: var(--color-primary-container); color: var(--color-on-primary-container); font-weight: 700;'
                                    : 'color: var(--color-text-secondary);'"
                                :onMouseover="e => selectedContract?.id !== c.id && (e.currentTarget.style.background = 'var(--color-bg-hover)')"
                                :onMouseout="e => selectedContract?.id !== c.id && (e.currentTarget.style.background = '')">
                            <div class="font-semibold truncate">{{ c.name }}</div>
                            <div class="text-xs font-mono opacity-70">{{ c.number }}</div>
                        </button>
                        <div v-if="contracts.data.length === 0" class="px-4 py-8 text-center">
                            <span class="material-symbols-outlined mb-2 block" style="font-size: 32px; color: var(--color-text-muted);">description</span>
                            <Link :href="route('contracts.create')" class="text-xs font-bold" style="color: var(--color-primary);">+ Crear contrato</Link>
                        </div>
                    </nav>
                </div>
            </div>

            <!-- Panel principal -->
            <div class="lg:col-span-3">

                <div v-if="!selectedContract"
                     class="flex flex-col items-center justify-center h-64 rounded-2xl"
                     style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <span class="material-symbols-outlined mb-3" style="font-size: 48px; color: var(--color-text-muted);">event_note</span>
                    <p class="font-semibold" style="color: var(--color-text-secondary);">Selecciona un contrato</p>
                </div>

                <template v-else>
                    <!-- Stats -->
                    <div class="grid grid-cols-4 gap-4 mb-6">
                        <div class="p-4 rounded-2xl text-center" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-text-primary);">{{ stats.total }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-text-muted);">Total</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center" style="background: var(--color-error-container);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-on-error-container);">{{ stats.pendiente }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-on-error-container); opacity: 0.8;">Pendientes</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center" style="background: var(--color-primary-container);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-on-primary-container);">{{ stats.mandante }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-on-primary-container); opacity: 0.8;">Imputables mandante</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center"
                             :style="stats.notifVencidas > 0 ? 'background: var(--color-error-container);' : 'background: var(--color-bg-card); box-shadow: var(--shadow-card);'">
                            <p class="text-2xl font-extrabold"
                               :style="stats.notifVencidas > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-text-primary);'">{{ stats.notifVencidas }}</p>
                            <p class="text-xs font-bold mt-1"
                               :style="stats.notifVencidas > 0 ? 'color: var(--color-on-error-container); opacity: 0.8;' : 'color: var(--color-text-muted);'">Avisos vencidos</p>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="flex flex-wrap gap-3 mb-4 p-3 rounded-xl"
                         style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant);">
                        <select v-model="typeFilter" @change="applyFilters"
                                class="px-3 py-2 rounded-xl text-xs border-none outline-none"
                                style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                            <option value="">Todos los tipos</option>
                            <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <select v-model="partyFilter" @change="applyFilters"
                                class="px-3 py-2 rounded-xl text-xs border-none outline-none"
                                style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                            <option value="">Todas las partes</option>
                            <option v-for="(label, key) in partyLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <select v-model="resFilter" @change="applyFilters"
                                class="px-3 py-2 rounded-xl text-xs border-none outline-none"
                                style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                            <option value="">Todos los estados</option>
                            <option v-for="(label, key) in resolutionLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <select v-model="notifFilter" @change="applyFilters"
                                class="px-3 py-2 rounded-xl text-xs border-none outline-none"
                                style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                            <option value="">Todas las notificaciones</option>
                            <option v-for="(label, key) in notificationLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>

                    <!-- Tabla -->
                    <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr style="background: var(--color-bg-sidebar);">
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Evento</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Fecha</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Responsable</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Impacto</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Resolución</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Notificación</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Vínculos</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest text-right" style="color: var(--color-text-secondary);">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="e in events?.data ?? []" :key="e.id"
                                        class="transition-colors"
                                        style="border-top: 1px solid var(--color-border-variant);"
                                        :onMouseover="r => r.currentTarget.style.background = 'var(--color-bg-hover)'"
                                        :onMouseout="r => r.currentTarget.style.background = ''">

                                        <!-- Evento -->
                                        <td class="px-5 py-4">
                                            <div class="text-sm font-semibold" style="color: var(--color-text-primary);">{{ e.type_label }}</div>
                                            <div class="text-xs mt-0.5 line-clamp-2 max-w-xs" style="color: var(--color-text-muted);">{{ e.description }}</div>
                                            <div v-if="e.contractual_basis_doc_label" class="text-xs mt-1 font-medium" style="color: var(--color-primary);">
                                                {{ e.contractual_basis_doc_label }}
                                            </div>
                                            <div v-if="e.rights_reserved" class="flex items-center gap-1 mt-1">
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-bold"
                                                      :style="e.rights_letters_count > 0
                                                          ? 'background: rgba(59,130,246,0.12); color: #1d4ed8;'
                                                          : 'background: rgba(234,179,8,0.15); color: #a16207;'"
                                                      :title="e.rights_letters_count > 0 ? 'Reserva formalizada con carta' : 'Reserva sin formalizar'">
                                                    <span class="material-symbols-outlined" style="font-size: 10px;">policy</span>
                                                    {{ e.rights_letters_count > 0 ? 'Reserva formal' : 'Reserva s/carta' }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Fecha + días abierto -->
                                        <td class="px-5 py-4">
                                            <div class="text-sm" style="color: var(--color-text-secondary);">{{ e.occurred_at }}</div>
                                            <div v-if="e.resolution_status !== 'resuelto' && e.days_open > 0"
                                                 class="text-xs mt-0.5"
                                                 :style="e.days_open > 15 ? 'color: var(--color-error); font-weight: 600;' : 'color: var(--color-text-muted);'">
                                                {{ e.days_open }} días abierto
                                            </div>
                                        </td>

                                        <!-- Responsable -->
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
                                                  :style="`background: ${partyConfig[e.responsible_party]?.bg}; color: ${partyConfig[e.responsible_party]?.text};`">
                                                {{ e.party_label }}
                                            </span>
                                        </td>

                                        <!-- Impacto -->
                                        <td class="px-5 py-4 text-xs" style="color: var(--color-text-secondary);">
                                            <div v-if="e.schedule_impact_days > 0">
                                                <span class="material-symbols-outlined align-middle" style="font-size: 12px;">schedule</span>
                                                {{ e.schedule_impact_days }} días
                                            </div>
                                            <div v-if="e.cost_impact > 0">
                                                <span class="material-symbols-outlined align-middle" style="font-size: 12px;">payments</span>
                                                {{ fmt(e.cost_impact, selectedContract.currency) }}
                                            </div>
                                            <span v-if="!e.schedule_impact_days && !e.cost_impact" style="color: var(--color-text-muted);">—</span>
                                        </td>

                                        <!-- Resolución -->
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
                                                  :style="`background: ${resConfig[e.resolution_status]?.bg}; color: ${resConfig[e.resolution_status]?.text};`">
                                                {{ e.resolution_label }}
                                            </span>
                                        </td>

                                        <!-- Notificación -->
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
                                                  :style="`background: ${notifConfig[e.notification_status]?.bg}; color: ${notifConfig[e.notification_status]?.text};`">
                                                {{ e.notification_label }}
                                            </span>
                                            <!-- Plazo de aviso -->
                                            <div v-if="e.notice_deadline && e.notification_status === 'pendiente'"
                                                 class="text-xs mt-1 flex items-center gap-1"
                                                 :style="e.is_notice_overdue ? 'color: var(--color-error); font-weight: 700;' : (e.notice_days_remaining !== null && e.notice_days_remaining <= 3 ? 'color: #c2410c; font-weight: 600;' : 'color: var(--color-text-muted);')">
                                                <span class="material-symbols-outlined" style="font-size: 11px;">{{ e.is_notice_overdue ? 'warning' : 'alarm' }}</span>
                                                <span v-if="e.is_notice_overdue">Plazo vencido ({{ e.notice_deadline }})</span>
                                                <span v-else>Vence {{ e.notice_deadline }} ({{ e.notice_days_remaining }}d)</span>
                                            </div>
                                        </td>

                                        <!-- Vínculos -->
                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-2">
                                                <span v-if="e.letters_count > 0"
                                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold"
                                                      style="background: rgba(59,130,246,0.12); color: #3b82f6;"
                                                      :title="`${e.letters_count} carta(s) vinculada(s)`">
                                                    <span class="material-symbols-outlined" style="font-size: 12px;">mail</span>
                                                    {{ e.letters_count }}
                                                </span>
                                                <span v-if="e.change_orders_count > 0"
                                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold"
                                                      style="background: rgba(168,85,247,0.12); color: #a855f7;"
                                                      :title="`${e.change_orders_count} OC vinculada(s)`">
                                                    <span class="material-symbols-outlined" style="font-size: 12px;">swap_horiz</span>
                                                    {{ e.change_orders_count }}
                                                </span>
                                                <span v-if="!e.letters_count && !e.change_orders_count"
                                                      style="color: var(--color-text-muted);" class="text-xs">—</span>
                                            </div>
                                        </td>

                                        <!-- Acciones -->
                                        <td class="px-5 py-4 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <button @click="openEdit(e)"
                                                        class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                                        style="color: var(--color-text-secondary); background: none; border: none; cursor: pointer;"
                                                        :onMouseover="r => r.currentTarget.style.color = 'var(--color-primary)'"
                                                        :onMouseout="r => r.currentTarget.style.color = 'var(--color-text-secondary)'"
                                                        title="Editar">
                                                    <span class="material-symbols-outlined" style="font-size: 18px;">edit</span>
                                                </button>
                                                <button @click="handleDelete(e)"
                                                        class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                                        style="color: var(--color-text-secondary); background: none; border: none; cursor: pointer;"
                                                        :onMouseover="r => r.currentTarget.style.color = 'var(--color-error)'"
                                                        :onMouseout="r => r.currentTarget.style.color = 'var(--color-text-secondary)'"
                                                        title="Eliminar">
                                                    <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="(events?.data ?? []).length === 0">
                                        <td colspan="8" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center gap-3">
                                                <span class="material-symbols-outlined" style="font-size: 48px; color: var(--color-text-muted);">event_note</span>
                                                <p class="font-semibold" style="color: var(--color-text-secondary);">Sin eventos registrados</p>
                                                <p class="text-sm" style="color: var(--color-text-muted);">Registra el primer evento contractual</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div v-if="events?.meta?.last_page > 1"
                             class="px-6 py-4 flex items-center justify-between"
                             style="background: var(--color-bg-sidebar); border-top: 1px solid var(--color-border-variant);">
                            <p class="text-sm" style="color: var(--color-text-secondary);">
                                Mostrando {{ events.meta.from }}–{{ events.meta.to }} de {{ events.meta.total }} eventos
                            </p>
                            <div class="flex items-center gap-2">
                                <a v-if="events.links.prev" :href="events.links.prev"
                                   class="px-3 py-1.5 rounded-lg text-sm font-semibold" style="color: var(--color-primary);">← Anterior</a>
                                <a v-if="events.links.next" :href="events.links.next"
                                   class="px-3 py-1.5 rounded-lg text-sm font-semibold" style="color: var(--color-primary);">Siguiente →</a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Modal -->
        <EventModal
            v-if="selectedContract"
            :show="showModal"
            :contract="selectedContract"
            :event="editTarget"
            :type-labels="typeLabels"
            :party-labels="partyLabels"
            :res-labels="resolutionLabels"
            :notification-labels="notificationLabels"
            :basis-doc-labels="basisDocLabels"
            @close="closeModal"
        />

    </AppLayout>
</template>
