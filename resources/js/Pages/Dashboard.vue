<script setup>
import { computed, ref, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Line } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, PointElement, LineElement,
    Tooltip, Filler,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler)

const props = defineProps({
    mode:                { type: String, default: 'executive' },
    // executive
    kpis:                { type: Object, default: () => ({}) },
    risk_dist:           { type: Object, default: () => ({}) },
    contract_list:       { type: Array,  default: () => [] },
    expired_letters:     { type: Array,  default: () => [] },
    pending_oc:          { type: Array,  default: () => [] },
    risk_history:        { type: Object, default: () => ({ labels: [], data: [] }) },
    // operative
    upcoming_milestones: { type: Array,  default: () => [] },
    pending_events:      { type: Array,  default: () => [] },
    letters_this_week:   { type: Array,  default: () => [] },
    flash:               { type: Object, default: () => ({}) },
})

const page      = usePage()
const flashData = computed(() => page.props.flash ?? props.flash)
const user      = computed(() => page.props.auth.user)

function greeting() {
    const h = new Date().getHours()
    if (h < 12) return 'Buenos días'
    if (h < 19) return 'Buenas tardes'
    return 'Buenas noches'
}

// ── Formateo ─────────────────────────────────────────────────────────────────
function formatAmount(cents, currency = 'CLP') {
    const n = Math.abs(cents / 100)
    if (currency === 'USD') return 'USD ' + n.toLocaleString('es-CL', { minimumFractionDigits: 0 })
    if (n >= 1_000_000_000) return 'CLP ' + (n / 1_000_000_000).toFixed(1) + ' MM'
    if (n >= 1_000_000)     return 'CLP ' + (n / 1_000_000).toFixed(1) + ' M'
    return 'CLP ' + n.toLocaleString('es-CL')
}

// ── Colores de riesgo ─────────────────────────────────────────────────────────
function riskColor(level) {
    return { bajo: '#22c55e', medio: '#eab308', alto: '#f97316', critico: '#ef4444' }[level] ?? 'var(--color-text-muted)'
}
function riskLabel(level) {
    return { bajo: 'Bajo', medio: 'Medio', alto: 'Alto', critico: 'Crítico' }[level] ?? '—'
}
function riskBg(level) {
    return {
        bajo:    'rgba(34,197,94,0.1)',
        medio:   'rgba(234,179,8,0.1)',
        alto:    'rgba(249,115,22,0.1)',
        critico: 'rgba(239,68,68,0.1)',
    }[level] ?? 'rgba(0,0,0,0.05)'
}

// ── Gráfico de riesgo histórico ───────────────────────────────────────────────
const chartData = computed(() => ({
    labels: props.risk_history.labels ?? [],
    datasets: [{
        label: 'Score de riesgo promedio',
        data:  props.risk_history.data ?? [],
        fill:  true,
        borderColor:     'var(--color-primary, #2a6496)',
        backgroundColor: 'rgba(42,100,150,0.08)',
        borderWidth: 2,
        tension: 0.4,
        pointRadius: 3,
        pointBackgroundColor: 'var(--color-primary, #2a6496)',
    }],
}))

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: {
            min: 0, max: 100,
            grid: { color: 'rgba(0,0,0,0.05)' },
            ticks: { font: { size: 11 }, color: '#7c7c9a' },
        },
        x: {
            grid: { display: false },
            ticks: { font: { size: 11 }, color: '#7c7c9a', maxTicksLimit: 10 },
        },
    },
}

// ── Distribución de riesgo como barras ───────────────────────────────────────
const riskLevels = computed(() => {
    const d   = props.risk_dist
    const tot = Object.values(d).reduce((s, v) => s + v, 0) || 1
    return [
        { key: 'critico', label: 'Crítico', count: d.critico ?? 0, color: '#ef4444', pct: ((d.critico ?? 0) / tot) * 100 },
        { key: 'alto',    label: 'Alto',    count: d.alto    ?? 0, color: '#f97316', pct: ((d.alto    ?? 0) / tot) * 100 },
        { key: 'medio',   label: 'Medio',   count: d.medio   ?? 0, color: '#eab308', pct: ((d.medio   ?? 0) / tot) * 100 },
        { key: 'bajo',    label: 'Bajo',    count: d.bajo    ?? 0, color: '#22c55e', pct: ((d.bajo    ?? 0) / tot) * 100 },
    ]
})
</script>

<template>
    <AppLayout title="Dashboard">

        <!-- Flash -->
        <div v-if="flashData?.success" class="mb-6 flex items-center gap-3 px-5 py-3 rounded-2xl"
             style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);">
            <span class="material-symbols-outlined" style="color: #22c55e; font-size: 20px;">check_circle</span>
            <span class="text-sm font-medium" style="color: #22c55e;">{{ flashData.success }}</span>
        </div>

        <!-- Saludo -->
        <div class="mb-8">
            <h2 class="text-3xl font-extrabold"
                style="font-family: var(--font-headline); color: var(--color-text-primary);">
                {{ greeting() }}, {{ user?.name?.split(' ')[0] }}.
            </h2>
            <p class="text-sm mt-1" style="color: var(--color-text-secondary);">
                {{ mode === 'executive' ? 'Vista ejecutiva — resumen general del portafolio' : 'Vista operativa — tus contratos asignados' }}
            </p>
        </div>

        <!-- ================================================================= -->
        <!-- VISTA EJECUTIVA                                                    -->
        <!-- ================================================================= -->
        <template v-if="mode === 'executive'">

            <!-- KPI top row -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <!-- Contratos activos -->
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider"
                           style="color: var(--color-text-secondary);">Contratos activos</p>
                        <span class="material-symbols-outlined"
                              style="font-size: 20px; color: var(--color-primary);">description</span>
                    </div>
                    <p class="text-3xl font-extrabold"
                       style="font-family: var(--font-headline); color: var(--color-text-primary);">
                        {{ kpis.contratos_activos ?? 0 }}
                    </p>
                    <p v-if="kpis.contratos_disputa > 0" class="text-xs mt-1" style="color: #ef4444;">
                        {{ kpis.contratos_disputa }} en disputa
                    </p>
                </div>

                <!-- Monto CLP -->
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider"
                           style="color: var(--color-text-secondary);">Monto vigente CLP</p>
                        <span class="material-symbols-outlined"
                              style="font-size: 20px; color: var(--color-primary);">payments</span>
                    </div>
                    <p class="text-2xl font-extrabold"
                       style="font-family: var(--font-headline); color: var(--color-text-primary);">
                        {{ formatAmount(kpis.monto_clp ?? 0, 'CLP') }}
                    </p>
                </div>

                <!-- OC Pendientes -->
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider"
                           style="color: var(--color-text-secondary);">OC pendientes</p>
                        <span class="material-symbols-outlined"
                              :style="`font-size: 20px; color: ${kpis.oc_pendientes > 0 ? '#f97316' : 'var(--color-primary)'}`">
                            swap_horiz
                        </span>
                    </div>
                    <p class="text-3xl font-extrabold"
                       :style="`font-family: var(--font-headline); color: ${kpis.oc_pendientes > 0 ? '#f97316' : 'var(--color-text-primary)'};`">
                        {{ kpis.oc_pendientes ?? 0 }}
                    </p>
                </div>

                <!-- Alertas -->
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider"
                           style="color: var(--color-text-secondary);">Alertas activas</p>
                        <span class="material-symbols-outlined"
                              :style="`font-size: 20px; color: ${(kpis.cartas_vencidas + kpis.eventos_sin_resolver) > 0 ? '#ef4444' : 'var(--color-primary)'}`">
                            warning
                        </span>
                    </div>
                    <p class="text-3xl font-extrabold"
                       :style="`font-family: var(--font-headline); color: ${(kpis.cartas_vencidas + kpis.eventos_sin_resolver) > 0 ? '#ef4444' : 'var(--color-text-primary)'};`">
                        {{ (kpis.cartas_vencidas ?? 0) + (kpis.eventos_sin_resolver ?? 0) }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--color-text-muted);">
                        {{ kpis.cartas_vencidas ?? 0 }} cartas · {{ kpis.eventos_sin_resolver ?? 0 }} eventos
                    </p>
                </div>
            </div>

            <!-- Segunda fila: gráfico + distribución riesgo -->
            <div class="grid grid-cols-3 gap-4 mb-6">

                <!-- Gráfico histórico riesgo (span 2) -->
                <div class="col-span-2 rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-sm font-bold"
                               style="color: var(--color-text-primary); font-family: var(--font-headline);">
                                Historial de riesgo
                            </p>
                            <p class="text-xs" style="color: var(--color-text-muted);">
                                Score promedio últimos 90 días
                            </p>
                        </div>
                    </div>
                    <div v-if="risk_history.labels?.length > 0" style="height: 180px;">
                        <Line :data="chartData" :options="chartOptions" />
                    </div>
                    <div v-else class="flex flex-col items-center justify-center gap-2"
                         style="height: 180px;">
                        <span class="material-symbols-outlined"
                              style="font-size: 36px; color: var(--color-text-muted);">show_chart</span>
                        <p class="text-xs" style="color: var(--color-text-muted);">
                            Sin historial aún — calcula el score de riesgo en contratos activos
                        </p>
                    </div>
                </div>

                <!-- Distribución de riesgo -->
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <p class="text-sm font-bold mb-4"
                       style="color: var(--color-text-primary); font-family: var(--font-headline);">
                        Distribución de riesgo
                    </p>
                    <div class="space-y-3">
                        <div v-for="r in riskLevels" :key="r.key">
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full" :style="`background: ${r.color}`"></div>
                                    <span class="text-xs font-medium"
                                          style="color: var(--color-text-secondary);">{{ r.label }}</span>
                                </div>
                                <span class="text-xs font-bold"
                                      :style="`color: ${r.count > 0 ? r.color : 'var(--color-text-muted)'}`">
                                    {{ r.count }}
                                </span>
                            </div>
                            <div class="w-full h-1.5 rounded-full overflow-hidden"
                                 style="background: var(--color-bg-elevated);">
                                <div class="h-1.5 rounded-full transition-all duration-500"
                                     :style="`width: ${r.pct}%; background: ${r.color};`"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Semáforo rápido total -->
                    <div class="mt-5 pt-4" style="border-top: 1px solid var(--color-border-variant);">
                        <p class="text-xs font-bold uppercase tracking-wider mb-2"
                           style="color: var(--color-text-secondary);">Contratos en alerta</p>
                        <p class="text-2xl font-extrabold"
                           :style="`font-family: var(--font-headline); color: ${((risk_dist.critico ?? 0) + (risk_dist.alto ?? 0)) > 0 ? '#ef4444' : '#22c55e'}`">
                            {{ (risk_dist.critico ?? 0) + (risk_dist.alto ?? 0) }}
                        </p>
                        <p class="text-xs" style="color: var(--color-text-muted);">nivel alto o crítico</p>
                    </div>
                </div>
            </div>

            <!-- Tercera fila: contratos + cartas vencidas + OC pendientes -->
            <div class="grid grid-cols-3 gap-4 mb-6">

                <!-- Contratos activos con semáforo -->
                <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                    <div class="px-5 py-4 flex items-center justify-between"
                         style="border-bottom: 1px solid var(--color-border-variant);">
                        <p class="text-sm font-bold"
                           style="color: var(--color-text-primary); font-family: var(--font-headline);">
                            Contratos activos
                        </p>
                        <button @click="router.get(route('contracts.index'))"
                                class="text-xs font-medium"
                                style="color: var(--color-primary); background: none; border: none; cursor: pointer;">
                            Ver todos →
                        </button>
                    </div>
                    <div v-if="contract_list.length === 0" class="px-5 py-8 text-center">
                        <p class="text-xs" style="color: var(--color-text-muted);">Sin contratos activos</p>
                    </div>
                    <div v-else class="divide-y" style="--tw-divide-opacity: 1;">
                        <div v-for="c in contract_list.slice(0, 5)" :key="c.id"
                             class="px-5 py-3 flex items-center gap-3 cursor-pointer transition-all"
                             :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                             :onMouseout="e => e.currentTarget.style.background = ''"
                             @click="router.get(route('risk.index'))">
                            <div class="w-2 h-2 rounded-full flex-shrink-0"
                                 :class="c.risk_level === 'critico' ? 'animate-pulse' : ''"
                                 :style="`background: ${c.risk_level ? riskColor(c.risk_level) : 'var(--color-text-muted)'}`">
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold truncate"
                                   style="color: var(--color-text-primary);">{{ c.name }}</p>
                                <p class="text-xs" style="color: var(--color-text-muted);">{{ c.number }}</p>
                            </div>
                            <span v-if="c.risk_level"
                                  class="text-xs font-bold flex-shrink-0"
                                  :style="`color: ${riskColor(c.risk_level)}`">
                                {{ riskLabel(c.risk_level) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Cartas vencidas -->
                <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                    <div class="px-5 py-4 flex items-center justify-between"
                         style="border-bottom: 1px solid var(--color-border-variant);">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-bold"
                               style="color: var(--color-text-primary); font-family: var(--font-headline);">
                                Cartas vencidas
                            </p>
                            <span v-if="expired_letters.length > 0"
                                  class="text-xs font-bold px-2 py-0.5 rounded-full"
                                  style="background: rgba(239,68,68,0.1); color: #ef4444;">
                                {{ expired_letters.length }}
                            </span>
                        </div>
                        <button @click="router.get(route('letters.index'))"
                                class="text-xs font-medium"
                                style="color: var(--color-primary); background: none; border: none; cursor: pointer;">
                            Ver todas →
                        </button>
                    </div>
                    <div v-if="expired_letters.length === 0" class="px-5 py-8 text-center">
                        <span class="material-symbols-outlined"
                              style="font-size: 28px; color: #22c55e;">check_circle</span>
                        <p class="text-xs mt-2" style="color: var(--color-text-muted);">Sin cartas vencidas</p>
                    </div>
                    <div v-else class="divide-y">
                        <div v-for="l in expired_letters" :key="l.id"
                             class="px-5 py-3 cursor-pointer transition-all"
                             :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                             :onMouseout="e => e.currentTarget.style.background = ''"
                             @click="router.get(route('letters.index', { contract_id: l.contract_id }))">
                            <p class="text-xs font-semibold"
                               style="color: #ef4444;">{{ l.letter_number }}</p>
                            <p class="text-xs truncate" style="color: var(--color-text-primary);">{{ l.subject }}</p>
                            <p class="text-xs" style="color: var(--color-text-muted);">
                                {{ l.contract_name }} · Venció: {{ l.response_deadline }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- OC pendientes -->
                <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                    <div class="px-5 py-4 flex items-center justify-between"
                         style="border-bottom: 1px solid var(--color-border-variant);">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-bold"
                               style="color: var(--color-text-primary); font-family: var(--font-headline);">
                                OC pendientes
                            </p>
                            <span v-if="pending_oc.length > 0"
                                  class="text-xs font-bold px-2 py-0.5 rounded-full"
                                  style="background: rgba(249,115,22,0.1); color: #f97316;">
                                {{ pending_oc.length }}
                            </span>
                        </div>
                        <button @click="router.get(route('change-orders.index'))"
                                class="text-xs font-medium"
                                style="color: var(--color-primary); background: none; border: none; cursor: pointer;">
                            Ver todas →
                        </button>
                    </div>
                    <div v-if="pending_oc.length === 0" class="px-5 py-8 text-center">
                        <span class="material-symbols-outlined"
                              style="font-size: 28px; color: #22c55e;">check_circle</span>
                        <p class="text-xs mt-2" style="color: var(--color-text-muted);">Sin OC pendientes</p>
                    </div>
                    <div v-else class="divide-y">
                        <div v-for="oc in pending_oc" :key="oc.id"
                             class="px-5 py-3 cursor-pointer transition-all"
                             :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                             :onMouseout="e => e.currentTarget.style.background = ''"
                             @click="router.get(route('change-orders.index', { contract_id: oc.contract_id }))">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold" style="color: #f97316;">{{ oc.request_number }}</p>
                                <span class="text-xs font-bold"
                                      style="color: var(--color-text-primary);">
                                    {{ formatAmount(oc.cost_impact, oc.currency) }}
                                </span>
                            </div>
                            <p class="text-xs truncate mt-0.5"
                               style="color: var(--color-text-primary);">{{ oc.description }}</p>
                            <p class="text-xs" style="color: var(--color-text-muted);">{{ oc.contract_name }}</p>
                        </div>
                    </div>
                </div>
            </div>

        </template>

        <!-- ================================================================= -->
        <!-- VISTA OPERATIVA                                                    -->
        <!-- ================================================================= -->
        <template v-else>

            <!-- KPIs operativos -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider"
                           style="color: var(--color-text-secondary);">Mis contratos</p>
                        <span class="material-symbols-outlined"
                              style="font-size: 20px; color: var(--color-primary);">description</span>
                    </div>
                    <p class="text-3xl font-extrabold"
                       style="font-family: var(--font-headline); color: var(--color-text-primary);">
                        {{ contract_list.length }}
                    </p>
                </div>
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider"
                           style="color: var(--color-text-secondary);">Hitos próximos</p>
                        <span class="material-symbols-outlined"
                              style="font-size: 20px; color: #f97316;">calendar_month</span>
                    </div>
                    <p class="text-3xl font-extrabold"
                       :style="`font-family: var(--font-headline); color: ${upcoming_milestones.length > 0 ? '#f97316' : 'var(--color-text-primary)'};`">
                        {{ upcoming_milestones.length }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--color-text-muted);">en los próximos 14 días</p>
                </div>
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-bold uppercase tracking-wider"
                           style="color: var(--color-text-secondary);">Cartas urgentes</p>
                        <span class="material-symbols-outlined"
                              :style="`font-size: 20px; color: ${letters_this_week.length > 0 ? '#ef4444' : 'var(--color-primary)'}`">
                            mail
                        </span>
                    </div>
                    <p class="text-3xl font-extrabold"
                       :style="`font-family: var(--font-headline); color: ${letters_this_week.length > 0 ? '#ef4444' : 'var(--color-text-primary)'};`">
                        {{ letters_this_week.length }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--color-text-muted);">respuesta esta semana</p>
                </div>
            </div>

            <!-- Fila: contratos + hitos próximos -->
            <div class="grid grid-cols-2 gap-4 mb-4">

                <!-- Mis contratos -->
                <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                    <div class="px-5 py-4" style="border-bottom: 1px solid var(--color-border-variant);">
                        <p class="text-sm font-bold"
                           style="color: var(--color-text-primary); font-family: var(--font-headline);">
                            Mis contratos
                        </p>
                    </div>
                    <div v-if="contract_list.length === 0" class="px-5 py-8 text-center">
                        <p class="text-xs" style="color: var(--color-text-muted);">No tienes contratos asignados</p>
                    </div>
                    <div v-else class="divide-y">
                        <div v-for="c in contract_list" :key="c.id"
                             class="px-5 py-3 flex items-center gap-3 cursor-pointer transition-all"
                             :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                             :onMouseout="e => e.currentTarget.style.background = ''"
                             @click="router.get(route('events.index', { contract_id: c.id }))">
                            <div class="w-2 h-2 rounded-full flex-shrink-0"
                                 :style="`background: ${c.risk_level ? riskColor(c.risk_level) : 'var(--color-text-muted)'}`"></div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold truncate"
                                   style="color: var(--color-text-primary);">{{ c.name }}</p>
                                <p class="text-xs" style="color: var(--color-text-muted);">
                                    {{ c.mandante }} · {{ c.status_label }}
                                </p>
                            </div>
                            <span v-if="c.risk_level" class="text-xs font-bold flex-shrink-0"
                                  :style="`color: ${riskColor(c.risk_level)}`">
                                {{ riskLabel(c.risk_level) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Hitos próximos -->
                <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                    <div class="px-5 py-4 flex items-center justify-between"
                         style="border-bottom: 1px solid var(--color-border-variant);">
                        <p class="text-sm font-bold"
                           style="color: var(--color-text-primary); font-family: var(--font-headline);">
                            Hitos próximos
                        </p>
                        <button @click="router.get(route('milestones.index'))"
                                class="text-xs font-medium"
                                style="color: var(--color-primary); background: none; border: none; cursor: pointer;">
                            Ver todos →
                        </button>
                    </div>
                    <div v-if="upcoming_milestones.length === 0" class="px-5 py-8 text-center">
                        <p class="text-xs" style="color: var(--color-text-muted);">Sin hitos en los próximos 14 días</p>
                    </div>
                    <div v-else class="divide-y">
                        <div v-for="m in upcoming_milestones" :key="m.id"
                             class="px-5 py-3 flex items-start gap-3 transition-all"
                             :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                             :onMouseout="e => e.currentTarget.style.background = ''">
                            <div class="mt-0.5 w-2 h-2 rounded-full flex-shrink-0"
                                 :style="`background: ${m.days_left <= 3 ? '#ef4444' : m.days_left <= 7 ? '#f97316' : '#eab308'}`"></div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold truncate"
                                   style="color: var(--color-text-primary);">
                                    {{ m.name }}
                                    <span v-if="m.is_critical" class="ml-1 text-xs" style="color: #ef4444;">●</span>
                                </p>
                                <p class="text-xs" style="color: var(--color-text-muted);">{{ m.contract_name }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-xs font-bold"
                                   :style="`color: ${m.days_left <= 3 ? '#ef4444' : m.days_left <= 7 ? '#f97316' : 'var(--color-text-secondary)'}`">
                                    {{ m.days_left <= 0 ? 'Hoy' : `${m.days_left}d` }}
                                </p>
                                <p class="text-xs" style="color: var(--color-text-muted);">{{ m.planned_date }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fila: eventos pendientes + cartas urgentes -->
            <div class="grid grid-cols-2 gap-4">

                <!-- Eventos pendientes -->
                <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                    <div class="px-5 py-4 flex items-center justify-between"
                         style="border-bottom: 1px solid var(--color-border-variant);">
                        <p class="text-sm font-bold"
                           style="color: var(--color-text-primary); font-family: var(--font-headline);">
                            Eventos pendientes
                        </p>
                        <button @click="router.get(route('events.index'))"
                                class="text-xs font-medium"
                                style="color: var(--color-primary); background: none; border: none; cursor: pointer;">
                            Ver todos →
                        </button>
                    </div>
                    <div v-if="pending_events.length === 0" class="px-5 py-8 text-center">
                        <p class="text-xs" style="color: var(--color-text-muted);">Sin eventos pendientes</p>
                    </div>
                    <div v-else class="divide-y">
                        <div v-for="e in pending_events" :key="e.id"
                             class="px-5 py-3 cursor-pointer transition-all"
                             :onMouseover="ev => ev.currentTarget.style.background = 'var(--color-bg-hover)'"
                             :onMouseout="ev => ev.currentTarget.style.background = ''"
                             @click="router.get(route('events.index', { contract_id: e.contract_id }))">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                      style="background: rgba(42,100,134,0.1); color: var(--color-primary);">
                                    {{ e.type_label }}
                                </span>
                                <span class="text-xs" style="color: var(--color-text-muted);">
                                    hace {{ e.days_old }}d
                                </span>
                            </div>
                            <p class="text-xs mt-1 truncate" style="color: var(--color-text-primary);">{{ e.description }}</p>
                            <p class="text-xs" style="color: var(--color-text-muted);">{{ e.contract_name }}</p>
                        </div>
                    </div>
                </div>

                <!-- Cartas urgentes -->
                <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                    <div class="px-5 py-4 flex items-center justify-between"
                         style="border-bottom: 1px solid var(--color-border-variant);">
                        <p class="text-sm font-bold"
                           style="color: var(--color-text-primary); font-family: var(--font-headline);">
                            Cartas — respuesta esta semana
                        </p>
                        <button @click="router.get(route('letters.index'))"
                                class="text-xs font-medium"
                                style="color: var(--color-primary); background: none; border: none; cursor: pointer;">
                            Ver todas →
                        </button>
                    </div>
                    <div v-if="letters_this_week.length === 0" class="px-5 py-8 text-center">
                        <span class="material-symbols-outlined"
                              style="font-size: 28px; color: #22c55e;">check_circle</span>
                        <p class="text-xs mt-2" style="color: var(--color-text-muted);">Sin cartas urgentes esta semana</p>
                    </div>
                    <div v-else class="divide-y">
                        <div v-for="l in letters_this_week" :key="l.id"
                             class="px-5 py-3 flex items-start gap-3 cursor-pointer transition-all"
                             :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                             :onMouseout="e => e.currentTarget.style.background = ''"
                             @click="router.get(route('letters.index', { contract_id: l.contract_id }))">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold"
                                   :style="`color: ${l.days_left !== null && l.days_left <= 2 ? '#ef4444' : 'var(--color-primary)'}`">
                                    {{ l.letter_number }}
                                </p>
                                <p class="text-xs truncate" style="color: var(--color-text-primary);">{{ l.subject }}</p>
                                <p class="text-xs" style="color: var(--color-text-muted);">{{ l.contract_name }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-xs font-bold"
                                   :style="`color: ${l.days_left !== null && l.days_left <= 2 ? '#ef4444' : l.days_left <= 5 ? '#f97316' : 'var(--color-text-secondary)'}`">
                                    {{ l.days_left !== null ? (l.days_left <= 0 ? 'Hoy' : `${l.days_left}d`) : '—' }}
                                </p>
                                <p class="text-xs" style="color: var(--color-text-muted);">{{ l.response_deadline }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </template>

    </AppLayout>
</template>
