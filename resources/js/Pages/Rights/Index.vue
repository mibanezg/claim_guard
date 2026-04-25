<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    contracts:        { type: Object,  required: true },
    selectedContract: { type: Object,  default: null },
    events:           { type: Array,   default: () => [] },
    stats:            { type: Object,  default: () => ({ formal: 0, informal: 0, none: 0, na: 0 }) },
    flash:            { type: Object,  default: () => ({}) },
    typeLabels:       { type: Object,  default: () => ({}) },
    partyLabels:      { type: Object,  default: () => ({}) },
})

const page  = usePage()
const flash = computed(() => page.props.flash)

const filterStatus = ref('')

function selectContract(id) {
    router.get(route('rights.index'), { contract_id: id }, { preserveState: false })
}

const statusConfig = {
    formal:   {
        label: 'Reserva formalizada',
        icon:  'verified',
        bg:    'rgba(59,130,246,0.12)',
        text:  '#1d4ed8',
        badgeBg: 'rgba(59,130,246,0.12)',
    },
    informal: {
        label: 'Sin carta de reserva',
        icon:  'warning',
        bg:    'rgba(234,179,8,0.12)',
        text:  '#a16207',
        badgeBg: 'rgba(234,179,8,0.15)',
    },
    none: {
        label: 'Sin reserva registrada',
        icon:  'gpp_bad',
        bg:    'var(--color-error-container)',
        text:  'var(--color-on-error-container)',
        badgeBg: 'var(--color-error-container)',
    },
    na: {
        label: 'No aplica',
        icon:  'remove_circle',
        bg:    'var(--color-bg-elevated)',
        text:  'var(--color-text-muted)',
        badgeBg: 'var(--color-bg-elevated)',
    },
}

const partyConfig = {
    mandante:     { bg: 'var(--color-error-container)',   text: 'var(--color-on-error-container)' },
    contratista:  { bg: 'var(--color-primary-container)', text: 'var(--color-on-primary-container)' },
    fuerza_mayor: { bg: 'var(--color-bg-elevated)',       text: 'var(--color-text-secondary)' },
    tercero:      { bg: 'var(--color-bg-elevated)',       text: 'var(--color-text-secondary)' },
}

const filteredEvents = computed(() => {
    if (!filterStatus.value) return props.events
    return props.events.filter(e => e.rights_status === filterStatus.value)
})

// Eventos críticos: imputables a mandante/fuerza_mayor/tercero sin reserva
const criticalEvents = computed(() =>
    props.events.filter(e => e.rights_status === 'none')
)

function fmt(amount) {
    if (!amount) return null
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: props.selectedContract?.currency ?? 'CLP',
        minimumFractionDigits: 0,
    }).format(amount)
}

function goToEvents(eventId) {
    router.get(route('events.index'), { contract_id: props.selectedContract?.id })
}

function goToLetters() {
    router.get(route('letters.index'), { contract_id: props.selectedContract?.id })
}
</script>

<template>
    <AppLayout title="Reserva de Derechos">

        <!-- Flash -->
        <div v-if="flash?.success" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-success-container); color: var(--color-on-success-container);">
            <span class="material-symbols-outlined">check_circle</span>{{ flash.success }}
        </div>

        <!-- Encabezado -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">Reserva de Derechos</h2>
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    Control de entitlement — qué eventos están cubiertos, cuáles sin formalizar y cuáles en riesgo de pérdida de derechos
                </p>
            </div>
            <button v-if="selectedContract" @click="goToLetters"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-full font-bold text-sm transition-all active:scale-95"
                    style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                <span class="material-symbols-outlined" style="font-size: 16px;">add</span>
                Registrar carta de reserva
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
                    </nav>
                </div>
            </div>

            <!-- Panel principal -->
            <div class="lg:col-span-3">

                <div v-if="!selectedContract"
                     class="flex flex-col items-center justify-center h-64 rounded-2xl"
                     style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <span class="material-symbols-outlined mb-3" style="font-size: 48px; color: var(--color-text-muted);">policy</span>
                    <p class="font-semibold" style="color: var(--color-text-secondary);">Selecciona un contrato</p>
                </div>

                <template v-else>

                    <!-- Alerta crítica: eventos sin reserva -->
                    <div v-if="criticalEvents.length > 0"
                         class="flex items-start gap-3 p-4 rounded-xl mb-4"
                         style="background: var(--color-error-container); border: 1px solid var(--color-error);">
                        <span class="material-symbols-outlined mt-0.5" style="font-size: 22px; color: var(--color-error);">gpp_bad</span>
                        <div class="flex-1">
                            <p class="text-sm font-bold" style="color: var(--color-on-error-container);">
                                {{ criticalEvents.length }} evento(s) sin reserva de derechos registrada
                            </p>
                            <p class="text-xs mt-0.5" style="color: var(--color-on-error-container); opacity: 0.85;">
                                Eventos imputables a mandante, fuerza mayor o terceros sin reserva pueden implicar renuncia tácita al derecho de reclamo.
                            </p>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-4 gap-4 mb-5">
                        <button @click="filterStatus = filterStatus === 'formal' ? '' : 'formal'"
                                class="p-4 rounded-2xl text-center cursor-pointer transition-all"
                                :style="filterStatus === 'formal'
                                    ? 'background: rgba(59,130,246,0.2); box-shadow: 0 0 0 2px #3b82f6;'
                                    : 'background: rgba(59,130,246,0.1); box-shadow: var(--shadow-card);'">
                            <p class="text-2xl font-extrabold" style="color: #1d4ed8;">{{ stats.formal }}</p>
                            <p class="text-xs font-bold mt-1" style="color: #1d4ed8; opacity: 0.8;">Formalizadas</p>
                        </button>
                        <button @click="filterStatus = filterStatus === 'informal' ? '' : 'informal'"
                                class="p-4 rounded-2xl text-center cursor-pointer transition-all"
                                :style="filterStatus === 'informal'
                                    ? 'background: rgba(234,179,8,0.3); box-shadow: 0 0 0 2px #eab308;'
                                    : 'background: rgba(234,179,8,0.15);'">
                            <p class="text-2xl font-extrabold" style="color: #a16207;">{{ stats.informal }}</p>
                            <p class="text-xs font-bold mt-1" style="color: #a16207; opacity: 0.8;">Sin formalizar</p>
                        </button>
                        <button @click="filterStatus = filterStatus === 'none' ? '' : 'none'"
                                class="p-4 rounded-2xl text-center cursor-pointer transition-all"
                                :style="filterStatus === 'none'
                                    ? 'background: var(--color-error-container); box-shadow: 0 0 0 2px var(--color-error);'
                                    : (stats.none > 0 ? 'background: var(--color-error-container);' : 'background: var(--color-bg-card); box-shadow: var(--shadow-card);')">
                            <p class="text-2xl font-extrabold"
                               :style="stats.none > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-text-primary);'">{{ stats.none }}</p>
                            <p class="text-xs font-bold mt-1"
                               :style="stats.none > 0 ? 'color: var(--color-on-error-container); opacity: 0.8;' : 'color: var(--color-text-muted);'">Sin reserva</p>
                        </button>
                        <button @click="filterStatus = filterStatus === 'na' ? '' : 'na'"
                                class="p-4 rounded-2xl text-center cursor-pointer transition-all"
                                style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-text-primary);">{{ stats.na }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-text-muted);">No aplica</p>
                        </button>
                    </div>

                    <!-- Leyenda -->
                    <div v-if="filterStatus" class="flex items-center gap-2 mb-4 px-1">
                        <span class="text-xs" style="color: var(--color-text-muted);">Filtrando:</span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold"
                              :style="`background: ${statusConfig[filterStatus]?.badgeBg}; color: ${statusConfig[filterStatus]?.text};`">
                            <span class="material-symbols-outlined" style="font-size: 12px;">{{ statusConfig[filterStatus]?.icon }}</span>
                            {{ statusConfig[filterStatus]?.label }}
                        </span>
                        <button @click="filterStatus = ''" class="text-xs font-semibold" style="color: var(--color-primary); background: none; border: none; cursor: pointer;">
                            Limpiar
                        </button>
                    </div>

                    <!-- Tabla de eventos -->
                    <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr style="background: var(--color-bg-sidebar);">
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Evento</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Responsable</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Impacto</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Estado reserva</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="e in filteredEvents" :key="e.id"
                                        class="transition-colors"
                                        style="border-top: 1px solid var(--color-border-variant);"
                                        :onMouseover="r => r.currentTarget.style.background = 'var(--color-bg-hover)'"
                                        :onMouseout="r => r.currentTarget.style.background = ''">

                                        <!-- Evento -->
                                        <td class="px-5 py-4">
                                            <div class="text-sm font-semibold" style="color: var(--color-text-primary);">{{ e.type_label }}</div>
                                            <div class="text-xs mt-0.5" style="color: var(--color-text-muted);">{{ e.occurred_at }}</div>
                                            <div class="text-xs mt-0.5 line-clamp-2 max-w-xs" style="color: var(--color-text-secondary);">{{ e.description }}</div>
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
                                                {{ fmt(e.cost_impact) }}
                                            </div>
                                            <span v-if="!e.schedule_impact_days && !e.cost_impact" style="color: var(--color-text-muted);">—</span>
                                        </td>

                                        <!-- Estado reserva -->
                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-1.5">
                                                <span class="material-symbols-outlined" style="font-size: 16px;"
                                                      :style="`color: ${statusConfig[e.rights_status]?.text};`">
                                                    {{ statusConfig[e.rights_status]?.icon }}
                                                </span>
                                                <span class="text-xs font-bold"
                                                      :style="`color: ${statusConfig[e.rights_status]?.text};`">
                                                    {{ statusConfig[e.rights_status]?.label }}
                                                </span>
                                            </div>
                                            <!-- Fecha de reserva -->
                                            <div v-if="e.rights_reserved_at" class="text-xs mt-1" style="color: var(--color-text-muted);">
                                                Reservado: {{ e.rights_reserved_at }}
                                            </div>
                                            <!-- Carta de reserva vinculada -->
                                            <div v-if="e.rights_letters_count > 0" class="text-xs mt-0.5 flex items-center gap-1" style="color: #1d4ed8;">
                                                <span class="material-symbols-outlined" style="font-size: 11px;">mail</span>
                                                {{ e.rights_letters_count }} carta(s) de reserva
                                            </div>
                                            <!-- Advertencia: sin carta registrada -->
                                            <div v-else-if="e.rights_status === 'informal'"
                                                 class="text-xs mt-1 flex items-center gap-1" style="color: #a16207;">
                                                <span class="material-symbols-outlined" style="font-size: 11px;">warning</span>
                                                Carta no registrada en el sistema
                                            </div>
                                        </td>

                                        <!-- Acciones -->
                                        <td class="px-5 py-4">
                                            <div class="flex flex-col gap-1.5">
                                                <!-- Si no tiene reserva: sugerir crearla -->
                                                <button v-if="e.rights_status === 'none'"
                                                        @click="goToEvents(e.id)"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold"
                                                        style="background: var(--color-error-container); color: var(--color-on-error-container); border: none; cursor: pointer;">
                                                    Registrar reserva
                                                </button>
                                                <!-- Si tiene reserva informal: recordar registrar la carta ya enviada -->
                                                <button v-if="e.rights_status === 'informal'"
                                                        @click="goToLetters"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-bold"
                                                        style="background: rgba(234,179,8,0.2); color: #a16207; border: none; cursor: pointer;">
                                                    Registrar carta enviada
                                                </button>
                                                <!-- Si ya está formal: solo ver -->
                                                <span v-if="e.rights_status === 'formal'"
                                                      class="px-3 py-1.5 rounded-lg text-xs font-bold inline-flex items-center gap-1"
                                                      style="background: rgba(59,130,246,0.1); color: #1d4ed8;">
                                                    <span class="material-symbols-outlined" style="font-size: 12px;">check</span>
                                                    Completo
                                                </span>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="filteredEvents.length === 0">
                                        <td colspan="5" class="px-6 py-16 text-center">
                                            <span class="material-symbols-outlined mb-3 block" style="font-size: 40px; color: var(--color-text-muted);">policy</span>
                                            <p class="font-semibold" style="color: var(--color-text-secondary);">Sin eventos para mostrar</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Guía de estados -->
                        <div class="px-6 py-4 flex flex-wrap items-center gap-4"
                             style="background: var(--color-bg-sidebar); border-top: 1px solid var(--color-border-variant);">
                            <p class="text-xs font-bold" style="color: var(--color-text-muted);">Estados:</p>
                            <div v-for="(cfg, key) in statusConfig" :key="key"
                                 class="flex items-center gap-1.5 text-xs" :style="`color: ${cfg.text};`">
                                <span class="material-symbols-outlined" style="font-size: 14px;">{{ cfg.icon }}</span>
                                {{ cfg.label }}
                            </div>
                        </div>
                    </div>

                    <!-- Instrucción -->
                    <div class="mt-4 p-4 rounded-xl flex items-start gap-2"
                         style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant);">
                        <span class="material-symbols-outlined flex-shrink-0 mt-0.5" style="font-size: 16px; color: var(--color-text-muted);">info</span>
                        <p class="text-xs" style="color: var(--color-text-muted);">
                            Para marcar la reserva en un evento, edita el evento desde el módulo de Eventos y activa el toggle <strong>Reserva de derechos</strong>.
                            Si ya enviaste una carta de reserva por tu sistema externo (LOD.CL u otro), regístrala en el módulo de Cartas con tipo <strong>Reserva de Derechos</strong> y vincúlala al evento.
                        </p>
                    </div>

                </template>
            </div>
        </div>

    </AppLayout>
</template>
