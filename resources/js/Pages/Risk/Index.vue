<script setup>
import { ref, computed } from 'vue'
import { usePage, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    contracts: { type: Array, default: () => [] },
    summary:   { type: Object, default: () => ({}) },
})

const page  = usePage()
const flash = computed(() => page.props.flash)

// Selección local — arranca con el primer contrato
const selectedId = ref(props.contracts[0]?.id ?? null)
const selected   = computed(() => props.contracts.find(c => c.id === selectedId.value) ?? null)

// Recalcular score
const recalculating = ref(false)
function recalculate(contract) {
    recalculating.value = true
    router.post(route('risk.recalculate', contract.id), {}, {
        preserveScroll: true,
        onFinish: () => { recalculating.value = false },
    })
}

// Factores del contrato seleccionado como array
const factors = computed(() => {
    const f = selected.value?.risk_score?.factors
    if (!f) return []
    return Object.entries(f).map(([key, v]) => ({ key, ...v }))
})

// Stats del contrato seleccionado (KPI row)
const scoreStats = computed(() => {
    const s = selected.value?.risk_score
    if (!s) return null
    const activeFactors = Object.values(s.factors ?? {}).filter(f => f.points > 0).length
    return {
        score:         s.score_value,
        level_label:   s.level_label,
        level_color:   s.level_color,
        level_bg:      s.level_bg,
        active_factors: activeFactors,
        calculated_at: s.calculated_at
            ? new Date(s.calculated_at).toLocaleDateString('es-CL')
            : '—',
    }
})

function riskDotColor(contract) {
    const map = { bajo: '#22c55e', medio: '#eab308', alto: '#f97316', critico: '#ef4444' }
    return map[contract.risk_score?.score_level] ?? 'var(--color-text-muted)'
}
</script>

<template>
    <AppLayout title="Riesgo de Claim">
        <div class="flex gap-6 h-full">

            <!-- Panel lateral: selector de contrato -->
            <div class="w-72 flex-shrink-0 flex flex-col gap-4">
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-3"
                        style="color: var(--color-text-secondary); font-family: var(--font-body);">Contrato</h3>
                    <div v-if="contracts.length === 0" class="py-4">
                        <p class="text-xs" style="color: var(--color-text-muted);">No hay contratos activos</p>
                    </div>
                    <div v-else class="space-y-1">
                        <button v-for="c in contracts" :key="c.id"
                                @click="selectedId = c.id"
                                class="w-full text-left px-3 py-2.5 rounded-xl text-sm transition-all"
                                :style="selectedId === c.id
                                    ? 'background: var(--color-primary); color: var(--color-on-primary); font-weight: 600;'
                                    : 'color: var(--color-text-primary);'"
                                :onMouseover="e => selectedId !== c.id && (e.currentTarget.style.background = 'var(--color-bg-hover)')"
                                :onMouseout="e => selectedId !== c.id && (e.currentTarget.style.background = '')">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-semibold truncate">{{ c.name }}</div>
                                    <div class="text-xs opacity-70">{{ c.number }}</div>
                                </div>
                                <div class="w-2 h-2 rounded-full flex-shrink-0"
                                     :class="c.risk_score?.score_level === 'critico' ? 'animate-pulse' : ''"
                                     :style="`background: ${riskDotColor(c)}`"></div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panel principal -->
            <div class="flex-1 flex flex-col gap-6 min-w-0">

                <!-- Flash -->
                <div v-if="flash?.success" class="flex items-center gap-3 px-5 py-3 rounded-2xl"
                     style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);">
                    <span class="material-symbols-outlined" style="color: #22c55e; font-size: 20px;">check_circle</span>
                    <span class="text-sm font-medium" style="color: #22c55e;">{{ flash.success }}</span>
                </div>
                <div v-if="flash?.error" class="flex items-center gap-3 px-5 py-3 rounded-2xl"
                     style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);">
                    <span class="material-symbols-outlined" style="color: #ef4444; font-size: 20px;">error</span>
                    <span class="text-sm font-medium" style="color: #ef4444;">{{ flash.error }}</span>
                </div>

                <template v-if="selected">

                    <!-- Header -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-extrabold"
                                style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                Riesgo de Claim
                            </h2>
                            <p class="text-sm mt-1" style="color: var(--color-text-secondary);">
                                {{ selected.name }} — {{ selected.number }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="recalculate(selected)"
                                    :disabled="recalculating"
                                    class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                    style="background: var(--color-bg-card); color: var(--color-text-primary); border: 1px solid var(--color-border-variant); cursor: pointer;">
                                <span class="material-symbols-outlined" :class="recalculating ? 'animate-spin' : ''" style="font-size: 18px;">refresh</span>
                                {{ recalculating ? 'Calculando…' : 'Recalcular' }}
                            </button>
                            <button @click="router.get(route('risk.show', selected.id))"
                                    class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                    style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                                <span class="material-symbols-outlined" style="font-size: 18px;">analytics</span>
                                Historial
                            </button>
                        </div>
                    </div>

                    <!-- KPI stats -->
                    <div v-if="scoreStats" class="grid grid-cols-4 gap-4">
                        <!-- Score -->
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2"
                               style="color: var(--color-text-secondary);">Score</p>
                            <p class="text-3xl font-extrabold"
                               :style="`color: ${scoreStats.level_color}; font-family: var(--font-headline);`">
                                {{ scoreStats.score }}
                                <span class="text-base font-medium" style="color: var(--color-text-muted);">/100</span>
                            </p>
                        </div>
                        <!-- Nivel -->
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2"
                               style="color: var(--color-text-secondary);">Nivel</p>
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full flex-shrink-0"
                                     :class="selected.risk_score?.score_level === 'critico' ? 'animate-pulse' : ''"
                                     :style="`background: ${scoreStats.level_color};`"></div>
                                <p class="text-2xl font-extrabold"
                                   :style="`color: ${scoreStats.level_color}; font-family: var(--font-headline);`">
                                    {{ scoreStats.level_label }}
                                </p>
                            </div>
                        </div>
                        <!-- Factores activos -->
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2"
                               style="color: var(--color-text-secondary);">Factores activos</p>
                            <p class="text-3xl font-extrabold"
                               :style="`color: ${scoreStats.active_factors > 0 ? scoreStats.level_color : 'var(--color-text-primary)'}; font-family: var(--font-headline);`">
                                {{ scoreStats.active_factors }}
                            </p>
                        </div>
                        <!-- Fecha cálculo -->
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2"
                               style="color: var(--color-text-secondary);">Calculado</p>
                            <p class="text-base font-bold"
                               style="color: var(--color-text-primary); font-family: var(--font-headline);">
                                {{ scoreStats.calculated_at }}
                            </p>
                        </div>
                    </div>

                    <!-- Sin score aún -->
                    <div v-else class="rounded-2xl py-16 flex flex-col items-center gap-3"
                         style="background: var(--color-bg-card);">
                        <span class="material-symbols-outlined"
                              style="font-size: 48px; color: var(--color-text-muted);">monitor_heart</span>
                        <p class="text-sm font-medium" style="color: var(--color-text-muted);">
                            No se ha calculado el score de riesgo
                        </p>
                        <button @click="recalculate(selected)"
                                class="mt-2 px-5 py-2 rounded-full text-sm font-bold"
                                style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer;">
                            Calcular ahora
                        </button>
                    </div>

                    <!-- Tabla de factores -->
                    <div v-if="scoreStats" class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                        <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-variant);">
                            <!-- Barra de score -->
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold uppercase tracking-wider"
                                      style="color: var(--color-text-secondary);">Desglose de factores</span>
                                <span class="text-xs" style="color: var(--color-text-muted);">
                                    {{ scoreStats.score }} / 100 pts
                                </span>
                            </div>
                            <div class="w-full h-2 rounded-full overflow-hidden"
                                 style="background: var(--color-bg-elevated);">
                                <div class="h-2 rounded-full transition-all duration-500"
                                     :style="`width: ${scoreStats.score}%; background: ${scoreStats.level_color};`">
                                </div>
                            </div>
                        </div>

                        <table class="w-full text-sm" style="font-family: var(--font-body); table-layout: fixed;">
                            <colgroup>
                                <col />                         <!-- Factor -->
                                <col style="width: 220px;" />   <!-- Barra -->
                                <col style="width: 80px;" />    <!-- Pts -->
                                <col style="width: 80px;" />    <!-- Máx -->
                            </colgroup>
                            <thead>
                                <tr style="border-bottom: 1px solid var(--color-border-variant);">
                                    <th class="text-left px-6 py-3 text-xs font-bold uppercase tracking-wider"
                                        style="color: var(--color-text-secondary);">Factor</th>
                                    <th class="text-left px-6 py-3 text-xs font-bold uppercase tracking-wider"
                                        style="color: var(--color-text-secondary);">Peso</th>
                                    <th class="text-right px-6 py-3 text-xs font-bold uppercase tracking-wider"
                                        style="color: var(--color-text-secondary);">Pts</th>
                                    <th class="text-right px-6 py-3 text-xs font-bold uppercase tracking-wider"
                                        style="color: var(--color-text-secondary);">Máx</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="f in factors" :key="f.key"
                                    style="border-bottom: 1px solid var(--color-border-variant);"
                                    :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                    :onMouseout="e => e.currentTarget.style.background = ''">
                                    <td class="px-6 py-4 font-medium truncate"
                                        style="color: var(--color-text-primary);">{{ f.label }}</td>
                                    <td class="px-6 py-4">
                                        <div class="w-full h-2 rounded-full overflow-hidden"
                                             style="background: var(--color-bg-elevated);">
                                            <div class="h-2 rounded-full transition-all duration-500"
                                                 :style="`width: ${(f.points / f.max) * 100}%;
                                                          background: ${f.points > 0 ? scoreStats.level_color : 'var(--color-border-variant)'};`">
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-bold"
                                              :style="f.points > 0
                                                  ? `color: ${scoreStats.level_color};`
                                                  : 'color: var(--color-text-muted);'">
                                            {{ f.points }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right"
                                        style="color: var(--color-text-muted);">{{ f.max }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </template>

                <!-- Sin contrato seleccionado -->
                <div v-else class="flex-1 flex flex-col items-center justify-center py-24 rounded-2xl"
                     style="background: var(--color-bg-card);">
                    <span class="material-symbols-outlined mb-4"
                          style="font-size: 56px; color: var(--color-text-muted);">monitor_heart</span>
                    <p class="font-semibold" style="color: var(--color-text-secondary);">
                        Selecciona un contrato para ver su indicador de riesgo
                    </p>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
