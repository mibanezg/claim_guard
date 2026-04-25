<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    contract:       { type: Object, required: true },
    events:         { type: Array,  default: () => [] },
    expiredLetters: { type: Array,  default: () => [] },
    summary:        { type: Object, required: true },
    flash:          { type: Object, default: () => ({}) },
})

const page  = usePage()
const flash = computed(() => page.props.flash)

const filterGap = ref('')  // 'quantum' | 'cpm' | 'rights' | 'notice' | ''

const filteredEvents = computed(() => {
    if (!filterGap.value) return props.events
    if (filterGap.value === 'quantum') return props.events.filter(e => !e.has_quantum)
    if (filterGap.value === 'cpm')     return props.events.filter(e => e.schedule_impact_days > 0 && !e.has_cpm)
    if (filterGap.value === 'rights')  return props.events.filter(e => e.rights_status === 'none')
    if (filterGap.value === 'notice')  return props.events.filter(e => e.notice_status === 'overdue')
    return props.events
})

// Indicador de completitud
function scoreColor(score, total) {
    const pct = total === 0 ? 1 : score / total
    if (pct === 1)  return { bg: 'var(--color-success-container)', text: 'var(--color-on-success-container)' }
    if (pct >= 0.5) return { bg: 'rgba(234,179,8,0.15)',           text: '#a16207' }
    return              { bg: 'var(--color-error-container)',       text: 'var(--color-on-error-container)' }
}

// Configs por estado
const RIGHTS_CONFIG = {
    formal:   { icon: 'verified',       color: '#1d4ed8', label: 'Formal' },
    informal: { icon: 'warning',        color: '#a16207', label: 'Sin carta' },
    none:     { icon: 'gpp_bad',        color: 'var(--color-error)', label: 'Sin reserva' },
    na:       { icon: 'remove_circle',  color: 'var(--color-text-muted)', label: 'N/A' },
}
const NOTICE_CONFIG = {
    ok:      { icon: 'check_circle',  color: 'var(--color-on-success-container)', label: 'Notificado' },
    overdue: { icon: 'alarm_off',     color: 'var(--color-error)', label: 'Vencido' },
    pending: { icon: 'schedule',      color: '#a16207', label: 'Pendiente' },
    na:      { icon: 'remove_circle', color: 'var(--color-text-muted)', label: 'N/A' },
}

function fmt(amount) {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: props.contract.currency ?? 'CLP',
        minimumFractionDigits: 0,
    }).format(amount)
}

function goToQuantum(eventId) {
    router.get(route('quantum.show', { contract: props.contract.id, event: eventId }))
}

function goToCpm(eventId) {
    router.get(route('delay-analysis.show', { contract: props.contract.id, event: eventId }))
}
</script>

<template>
    <AppLayout :title="`Estado del Claim — ${contract.number}`">

        <div v-if="flash?.success" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-success-container); color: var(--color-on-success-container);">
            <span class="material-symbols-outlined">check_circle</span>{{ flash.success }}
        </div>

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 mb-6 text-sm" style="color: var(--color-text-muted);">
            <Link :href="route('contracts.index')" class="hover:underline" style="color: var(--color-primary);">Contratos</Link>
            <span class="material-symbols-outlined" style="font-size: 16px;">chevron_right</span>
            <Link :href="route('contracts.show', contract.id)" class="hover:underline" style="color: var(--color-primary);">{{ contract.number }}</Link>
            <span class="material-symbols-outlined" style="font-size: 16px;">chevron_right</span>
            <span style="color: var(--color-text-primary); font-weight: 600;">Estado del Claim</span>
        </div>

        <!-- Encabezado -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">
                    Estado del Claim
                </h2>
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    {{ contract.name }} — completitud documental por evento
                </p>
            </div>
            <!-- Indicador global -->
            <div class="text-right flex-shrink-0 ml-6">
                <div class="text-3xl font-extrabold" style="color: var(--color-primary);">
                    {{ summary.fully_ready }}<span class="text-lg font-normal" style="color: var(--color-text-muted);">/{{ summary.total }}</span>
                </div>
                <p class="text-xs" style="color: var(--color-text-muted);">eventos completamente documentados</p>
            </div>
        </div>

        <!-- Alerta cartas vencidas -->
        <div v-if="expiredLetters.length > 0"
             class="flex items-start gap-3 p-4 rounded-xl mb-5"
             style="background: var(--color-error-container); border: 1px solid var(--color-error);">
            <span class="material-symbols-outlined mt-0.5 flex-shrink-0" style="font-size: 22px; color: var(--color-error);">alarm_off</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold" style="color: var(--color-on-error-container);">
                    {{ expiredLetters.length }} carta(s) vencidas sin respuesta
                </p>
                <div class="flex flex-wrap gap-2 mt-1">
                    <span v-for="l in expiredLetters" :key="l.id"
                          class="text-xs px-2 py-0.5 rounded font-mono"
                          style="background: rgba(0,0,0,0.1); color: var(--color-on-error-container);">
                        {{ l.letter_number }}
                    </span>
                </div>
            </div>
            <Link :href="route('letters.index', { contract_id: contract.id })"
                  class="text-xs font-bold px-3 py-1.5 rounded-lg flex-shrink-0"
                  style="background: var(--color-error); color: white;">
                Ver cartas
            </Link>
        </div>

        <!-- 4 stat cards clicables -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

            <!-- Sin quantum -->
            <button @click="filterGap = filterGap === 'quantum' ? '' : 'quantum'"
                    class="p-4 rounded-2xl text-left transition-all"
                    :style="filterGap === 'quantum'
                        ? 'background: var(--color-error-container); box-shadow: 0 0 0 2px var(--color-error);'
                        : summary.without_quantum > 0
                            ? 'background: var(--color-error-container); box-shadow: var(--shadow-card);'
                            : 'background: var(--color-success-container); box-shadow: var(--shadow-card);'">
                <div class="flex items-center justify-between mb-2">
                    <span class="material-symbols-outlined" style="font-size: 20px;"
                          :style="summary.without_quantum > 0 ? 'color: var(--color-error);' : 'color: var(--color-on-success-container);'">
                        {{ summary.without_quantum > 0 ? 'calculate' : 'check_circle' }}
                    </span>
                    <span class="text-2xl font-extrabold"
                          :style="summary.without_quantum > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-on-success-container);'">
                        {{ summary.without_quantum }}
                    </span>
                </div>
                <p class="text-xs font-bold"
                   :style="summary.without_quantum > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-on-success-container);'">
                    Sin quantum
                </p>
                <p class="text-xs mt-0.5 opacity-70"
                   :style="summary.without_quantum > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-on-success-container);'">
                    de {{ summary.total }} eventos
                </p>
            </button>

            <!-- Sin análisis CPM -->
            <button @click="filterGap = filterGap === 'cpm' ? '' : 'cpm'"
                    class="p-4 rounded-2xl text-left transition-all"
                    :style="filterGap === 'cpm'
                        ? 'background: var(--color-error-container); box-shadow: 0 0 0 2px var(--color-error);'
                        : (summary.cpm_required - summary.with_cpm) > 0
                            ? 'background: var(--color-error-container); box-shadow: var(--shadow-card);'
                            : 'background: var(--color-success-container); box-shadow: var(--shadow-card);'">
                <div class="flex items-center justify-between mb-2">
                    <span class="material-symbols-outlined" style="font-size: 20px;"
                          :style="(summary.cpm_required - summary.with_cpm) > 0 ? 'color: var(--color-error);' : 'color: var(--color-on-success-container);'">
                        {{ (summary.cpm_required - summary.with_cpm) > 0 ? 'account_tree' : 'check_circle' }}
                    </span>
                    <span class="text-2xl font-extrabold"
                          :style="(summary.cpm_required - summary.with_cpm) > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-on-success-container);'">
                        {{ summary.cpm_required - summary.with_cpm }}
                    </span>
                </div>
                <p class="text-xs font-bold"
                   :style="(summary.cpm_required - summary.with_cpm) > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-on-success-container);'">
                    Sin análisis CPM
                </p>
                <p class="text-xs mt-0.5 opacity-70"
                   :style="(summary.cpm_required - summary.with_cpm) > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-on-success-container);'">
                    de {{ summary.cpm_required }} con impacto plazo
                </p>
            </button>

            <!-- Sin reserva (crítico) -->
            <button @click="filterGap = filterGap === 'rights' ? '' : 'rights'"
                    class="p-4 rounded-2xl text-left transition-all"
                    :style="filterGap === 'rights'
                        ? 'background: var(--color-error-container); box-shadow: 0 0 0 2px var(--color-error);'
                        : summary.rights_critical > 0
                            ? 'background: var(--color-error-container); box-shadow: var(--shadow-card);'
                            : 'background: var(--color-success-container); box-shadow: var(--shadow-card);'">
                <div class="flex items-center justify-between mb-2">
                    <span class="material-symbols-outlined" style="font-size: 20px;"
                          :style="summary.rights_critical > 0 ? 'color: var(--color-error);' : 'color: var(--color-on-success-container);'">
                        {{ summary.rights_critical > 0 ? 'gpp_bad' : 'check_circle' }}
                    </span>
                    <span class="text-2xl font-extrabold"
                          :style="summary.rights_critical > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-on-success-container);'">
                        {{ summary.rights_critical }}
                    </span>
                </div>
                <p class="text-xs font-bold"
                   :style="summary.rights_critical > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-on-success-container);'">
                    Sin reserva de derechos
                </p>
                <p class="text-xs mt-0.5 opacity-70"
                   :style="summary.rights_critical > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-on-success-container);'">
                    riesgo de renuncia tácita
                </p>
            </button>

            <!-- Avisos vencidos -->
            <button @click="filterGap = filterGap === 'notice' ? '' : 'notice'"
                    class="p-4 rounded-2xl text-left transition-all"
                    :style="filterGap === 'notice'
                        ? 'background: var(--color-error-container); box-shadow: 0 0 0 2px var(--color-error);'
                        : summary.expired_letters > 0
                            ? 'background: rgba(234,179,8,0.15); box-shadow: var(--shadow-card);'
                            : 'background: var(--color-bg-card); box-shadow: var(--shadow-card);'">
                <div class="flex items-center justify-between mb-2">
                    <span class="material-symbols-outlined" style="font-size: 20px; color: #a16207;">alarm_off</span>
                    <span class="text-2xl font-extrabold" style="color: #a16207;">{{ summary.expired_letters }}</span>
                </div>
                <p class="text-xs font-bold" style="color: #a16207;">Cartas vencidas</p>
                <p class="text-xs mt-0.5 opacity-70" style="color: #a16207;">sin respuesta del mandante</p>
            </button>
        </div>

        <!-- Filtro activo -->
        <div v-if="filterGap" class="flex items-center gap-2 mb-4">
            <span class="text-xs" style="color: var(--color-text-muted);">Mostrando solo:</span>
            <span class="text-xs font-bold px-3 py-1 rounded-full"
                  style="background: var(--color-error-container); color: var(--color-on-error-container);">
                {{ filterGap === 'quantum' ? 'Sin quantum' : filterGap === 'cpm' ? 'Sin análisis CPM' : filterGap === 'rights' ? 'Sin reserva de derechos' : 'Avisos vencidos' }}
            </span>
            <button @click="filterGap = ''" class="text-xs font-semibold" style="color: var(--color-primary); background: none; border: none; cursor: pointer;">
                Limpiar filtro
            </button>
        </div>

        <!-- Tabla de eventos -->
        <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
            <div class="overflow-x-auto">
                <table class="w-full text-left" style="border-collapse: collapse; table-layout: fixed;">
                    <colgroup>
                        <col style="width: 28%;" />
                        <col style="width: 11%;" />
                        <col style="width: 14%;" />
                        <col style="width: 14%;" />
                        <col style="width: 14%;" />
                        <col style="width: 11%;" />
                        <col style="width: 8%;" />
                    </colgroup>
                    <thead>
                        <tr style="background: var(--color-bg-sidebar);">
                            <th class="px-5 py-4 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-muted);">Evento</th>
                            <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-muted);">Estado</th>
                            <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-muted);">
                                <span class="material-symbols-outlined align-middle" style="font-size: 14px;">calculate</span>
                                Quantum
                            </th>
                            <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-muted);">
                                <span class="material-symbols-outlined align-middle" style="font-size: 14px;">account_tree</span>
                                CPM
                            </th>
                            <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-muted);">
                                <span class="material-symbols-outlined align-middle" style="font-size: 14px;">policy</span>
                                Reserva
                            </th>
                            <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-muted);">
                                <span class="material-symbols-outlined align-middle" style="font-size: 14px;">notifications</span>
                                Aviso
                            </th>
                            <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider text-center" style="color: var(--color-text-muted);">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in filteredEvents" :key="e.id"
                            style="border-top: 1px solid var(--color-border-variant);"
                            :onMouseover="r => r.currentTarget.style.background = 'var(--color-bg-hover)'"
                            :onMouseout="r => r.currentTarget.style.background = ''">

                            <!-- Evento -->
                            <td class="px-5 py-4">
                                <div class="text-sm font-semibold truncate" style="color: var(--color-text-primary);">{{ e.type_label }}</div>
                                <div class="text-xs" style="color: var(--color-text-muted);">{{ e.occurred_at }}</div>
                                <div class="text-xs truncate mt-0.5" style="color: var(--color-text-secondary);">{{ e.description }}</div>
                                <div class="flex gap-2 mt-1 flex-wrap">
                                    <span v-if="e.cost_impact > 0" class="text-xs font-mono" style="color: var(--color-text-muted);">
                                        {{ fmt(e.cost_impact) }}
                                    </span>
                                    <span v-if="e.schedule_impact_days > 0" class="text-xs font-mono" style="color: var(--color-text-muted);">
                                        {{ e.schedule_impact_days }}d
                                    </span>
                                </div>
                            </td>

                            <!-- Responsable -->
                            <td class="px-4 py-4">
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                                      :style="e.responsible_party === 'mandante'
                                          ? 'background: var(--color-error-container); color: var(--color-on-error-container);'
                                          : e.responsible_party === 'contratista'
                                              ? 'background: var(--color-primary-container); color: var(--color-on-primary-container);'
                                              : 'background: var(--color-bg-elevated); color: var(--color-text-secondary);'">
                                    {{ e.party_label }}
                                </span>
                            </td>

                            <!-- Quantum -->
                            <td class="px-4 py-4">
                                <div v-if="e.has_quantum" class="flex flex-col gap-0.5">
                                    <div class="flex items-center gap-1">
                                        <span class="material-symbols-outlined" style="font-size: 16px; color: var(--color-on-success-container);">check_circle</span>
                                        <span class="text-xs font-bold" style="color: var(--color-on-success-container);">OK</span>
                                    </div>
                                    <span class="text-xs font-mono" style="color: var(--color-text-muted);">{{ fmt(e.quantum_total) }}</span>
                                    <button @click="goToQuantum(e.id)"
                                            class="text-xs font-semibold underline text-left"
                                            style="background: none; border: none; cursor: pointer; color: var(--color-primary);">
                                        Ver detalle
                                    </button>
                                </div>
                                <div v-else class="flex flex-col gap-0.5">
                                    <div class="flex items-center gap-1">
                                        <span class="material-symbols-outlined" style="font-size: 16px; color: var(--color-error);">pending</span>
                                        <span class="text-xs font-bold" style="color: var(--color-error);">Falta</span>
                                    </div>
                                    <button @click="goToQuantum(e.id)"
                                            class="text-xs font-bold px-2 py-1 rounded-lg"
                                            style="background: var(--color-error-container); color: var(--color-on-error-container); border: none; cursor: pointer;">
                                        Documentar
                                    </button>
                                </div>
                            </td>

                            <!-- CPM -->
                            <td class="px-4 py-4">
                                <div v-if="e.schedule_impact_days === 0" class="flex items-center gap-1">
                                    <span class="material-symbols-outlined" style="font-size: 15px; color: var(--color-text-muted);">remove_circle</span>
                                    <span class="text-xs" style="color: var(--color-text-muted);">N/A</span>
                                </div>
                                <div v-else-if="e.has_cpm" class="flex flex-col gap-0.5">
                                    <div class="flex items-center gap-1">
                                        <span class="material-symbols-outlined" style="font-size: 16px; color: var(--color-on-success-container);">check_circle</span>
                                        <span class="text-xs font-bold" style="color: var(--color-on-success-container);">OK</span>
                                    </div>
                                    <span class="text-xs" style="color: var(--color-text-muted);">{{ e.cpm_delay_type }}</span>
                                    <button @click="goToCpm(e.id)"
                                            class="text-xs font-semibold underline text-left"
                                            style="background: none; border: none; cursor: pointer; color: var(--color-primary);">
                                        Ver análisis
                                    </button>
                                </div>
                                <div v-else class="flex flex-col gap-0.5">
                                    <div class="flex items-center gap-1">
                                        <span class="material-symbols-outlined" style="font-size: 16px; color: var(--color-error);">pending</span>
                                        <span class="text-xs font-bold" style="color: var(--color-error);">Falta</span>
                                    </div>
                                    <button @click="goToCpm(e.id)"
                                            class="text-xs font-bold px-2 py-1 rounded-lg"
                                            style="background: var(--color-error-container); color: var(--color-on-error-container); border: none; cursor: pointer;">
                                        Analizar
                                    </button>
                                </div>
                            </td>

                            <!-- Reserva -->
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-1">
                                    <span class="material-symbols-outlined" style="font-size: 15px;"
                                          :style="`color: ${RIGHTS_CONFIG[e.rights_status]?.color};`">
                                        {{ RIGHTS_CONFIG[e.rights_status]?.icon }}
                                    </span>
                                    <span class="text-xs font-bold"
                                          :style="`color: ${RIGHTS_CONFIG[e.rights_status]?.color};`">
                                        {{ RIGHTS_CONFIG[e.rights_status]?.label }}
                                    </span>
                                </div>
                                <div v-if="e.rights_letters_count > 0" class="text-xs mt-0.5" style="color: var(--color-text-muted);">
                                    {{ e.rights_letters_count }} carta(s)
                                </div>
                            </td>

                            <!-- Aviso -->
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-1">
                                    <span class="material-symbols-outlined" style="font-size: 15px;"
                                          :style="`color: ${NOTICE_CONFIG[e.notice_status]?.color};`">
                                        {{ NOTICE_CONFIG[e.notice_status]?.icon }}
                                    </span>
                                    <span class="text-xs font-bold"
                                          :style="`color: ${NOTICE_CONFIG[e.notice_status]?.color};`">
                                        {{ NOTICE_CONFIG[e.notice_status]?.label }}
                                    </span>
                                </div>
                                <div v-if="e.notice_deadline" class="text-xs mt-0.5" style="color: var(--color-text-muted);">
                                    {{ e.notice_deadline }}
                                </div>
                            </td>

                            <!-- Score -->
                            <td class="px-4 py-4 text-center">
                                <div class="inline-flex items-center justify-center w-10 h-10 rounded-full text-xs font-extrabold"
                                     :style="`background: ${scoreColor(e.completeness_score, e.completeness_total).bg}; color: ${scoreColor(e.completeness_score, e.completeness_total).text};`">
                                    {{ e.completeness_score }}/{{ e.completeness_total }}
                                </div>
                            </td>
                        </tr>

                        <tr v-if="filteredEvents.length === 0">
                            <td colspan="7" class="px-6 py-16 text-center">
                                <span class="material-symbols-outlined mb-3 block" style="font-size: 40px; color: var(--color-text-muted);">task_alt</span>
                                <p class="font-semibold" style="color: var(--color-text-secondary);">
                                    {{ filterGap ? 'No hay eventos con ese gap' : 'Sin eventos registrados' }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Leyenda score -->
            <div class="px-6 py-3 flex flex-wrap items-center gap-6 text-xs"
                 style="background: var(--color-bg-sidebar); border-top: 1px solid var(--color-border-variant); color: var(--color-text-muted);">
                <span class="font-bold">Score:</span>
                <span class="flex items-center gap-1.5">
                    <span class="w-4 h-4 rounded-full inline-block" style="background: var(--color-success-container);"></span>
                    Completo
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-4 h-4 rounded-full inline-block" style="background: rgba(234,179,8,0.15);"></span>
                    Parcial ≥50%
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-4 h-4 rounded-full inline-block" style="background: var(--color-error-container);"></span>
                    Crítico &lt;50%
                </span>
                <span class="ml-auto">El score considera: quantum + análisis CPM (si aplica) + reserva de derechos + aviso al mandante (si aplica)</span>
            </div>
        </div>

        <!-- Volver -->
        <div class="mt-6">
            <Link :href="route('contracts.show', contract.id)"
                  class="text-sm font-semibold flex items-center gap-1"
                  style="color: var(--color-text-secondary);"
                  :onMouseover="e => e.currentTarget.style.color = 'var(--color-primary)'"
                  :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'">
                <span class="material-symbols-outlined" style="font-size: 16px;">arrow_back</span>
                Volver al contrato
            </Link>
        </div>

    </AppLayout>
</template>
