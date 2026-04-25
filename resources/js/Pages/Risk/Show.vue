<script setup>
import { computed, ref } from 'vue'
import { usePage, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    contract:     { type: Object, required: true },
    latest_score: { type: Object, default: null },
    history:      { type: Array,  default: () => [] },
})

const page  = usePage()
const flash = computed(() => page.props.flash)

const recalcForm = useForm({})
function recalculate() {
    recalcForm.post(route('risk.recalculate', props.contract.id), { preserveScroll: true })
}

// Recomendaciones como objetos normalizados
const recommendations = computed(() => {
    const raw = props.latest_score?.recommendations
    if (!Array.isArray(raw) || !raw.length) return []
    return raw.map(r => typeof r === 'string'
        ? { title: r, detail: '', action: 'events', action_label: 'Ver Eventos' }
        : r
    )
})

const openRec = ref(null)
function toggleRec(i) {
    openRec.value = openRec.value === i ? null : i
}

const ACTION_ROUTES = {
    letters:        'letters.index',
    events:         'events.index',
    'change-orders': 'change-orders.index',
    milestones:     'milestones.index',
    expediente:     'expediente.index',
}

const factors = computed(() => {
    const f = props.latest_score?.factors
    if (!f) return []
    return Object.entries(f).map(([key, v]) => ({ key, ...v }))
})

function fmtDate(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString('es-CL', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}
</script>

<template>
    <AppLayout :title="`Riesgo — ${contract.number}`">
        <div class="flex flex-col gap-6" style="max-width: 900px;">

            <!-- Breadcrumb / back -->
            <div class="flex items-center gap-2">
                <button @click="router.get(route('risk.index'))"
                        class="flex items-center gap-2 text-sm font-medium transition-all"
                        style="color: var(--color-text-secondary); background: none; border: none; cursor: pointer; padding: 0;"
                        :onMouseover="e => e.currentTarget.style.color = 'var(--color-primary)'"
                        :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'">
                    <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span>
                    Riesgo de Claim
                </button>
                <span style="color: var(--color-text-muted);">/</span>
                <span class="text-sm font-medium" style="color: var(--color-text-primary);">
                    {{ contract.number }}
                </span>
            </div>

            <!-- Header contrato -->
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-2xl font-extrabold"
                        style="font-family: var(--font-headline); color: var(--color-text-primary);">
                        {{ contract.name }}
                    </h2>
                    <p class="text-sm mt-1" style="color: var(--color-text-secondary);">
                        {{ contract.mandante }} · {{ contract.contractor }}
                    </p>
                </div>
                <button @click="recalculate"
                        :disabled="recalcForm.processing"
                        class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                        style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                    <span class="material-symbols-outlined" style="font-size: 18px;">refresh</span>
                    Recalcular ahora
                </button>
            </div>

            <!-- Flash -->
            <div v-if="flash?.success" class="flex items-center gap-3 px-5 py-3 rounded-2xl"
                 style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);">
                <span class="material-symbols-outlined" style="color: #22c55e; font-size: 20px;">check_circle</span>
                <span class="text-sm font-medium" style="color: #22c55e;">{{ flash.success }}</span>
            </div>

            <!-- Sin score -->
            <div v-if="!latest_score"
                 class="flex flex-col items-center justify-center py-20 rounded-2xl"
                 style="background: var(--color-bg-card);">
                <span class="material-symbols-outlined mb-4"
                      style="font-size: 56px; color: var(--color-text-muted);">monitor_heart</span>
                <p class="font-semibold mb-4" style="color: var(--color-text-secondary);">
                    No se ha calculado el score de riesgo
                </p>
                <button @click="recalculate" :disabled="recalcForm.processing"
                        class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                        style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                    Calcular ahora
                </button>
            </div>

            <template v-else>
                <!-- Score actual -->
                <div class="rounded-2xl p-6"
                     :style="`background: var(--color-bg-card); border-left: 4px solid ${latest_score.level_color};`">
                    <div class="flex items-center gap-6 mb-6">
                        <!-- Badge numérico -->
                        <div class="rounded-2xl px-8 py-5 flex flex-col items-center flex-shrink-0"
                             :style="`background: ${latest_score.level_bg}; border: 1px solid ${latest_score.level_color}30;`">
                            <span class="text-5xl font-extrabold"
                                  :style="`color: ${latest_score.level_color}; font-family: var(--font-headline);`">
                                {{ latest_score.score_value }}
                            </span>
                            <span class="text-xs font-bold uppercase tracking-wider mt-1"
                                  :style="`color: ${latest_score.level_color};`">
                                {{ latest_score.level_label }}
                            </span>
                        </div>

                        <!-- Barra + meta -->
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-semibold"
                                      style="color: var(--color-text-secondary);">Nivel de riesgo (0–100)</span>
                                <span class="text-xs" style="color: var(--color-text-muted);">
                                    Calculado {{ fmtDate(latest_score.calculated_at) }}
                                </span>
                            </div>
                            <div class="w-full h-3 rounded-full overflow-hidden"
                                 style="background: var(--color-bg-elevated);">
                                <div class="h-3 rounded-full transition-all duration-500"
                                     :style="`width: ${latest_score.score_value}%; background: ${latest_score.level_color};`">
                                </div>
                            </div>
                            <div class="flex justify-between mt-1 px-1">
                                <span class="text-xs" style="color: var(--color-text-muted);">0</span>
                                <span class="text-xs" style="color: var(--color-text-muted);">25</span>
                                <span class="text-xs" style="color: var(--color-text-muted);">50</span>
                                <span class="text-xs" style="color: var(--color-text-muted);">75</span>
                                <span class="text-xs" style="color: var(--color-text-muted);">100</span>
                            </div>
                            <div class="flex items-center gap-2 mt-3">
                                <div class="w-2 h-2 rounded-full"
                                     :class="latest_score.score_level === 'critico' ? 'animate-pulse' : ''"
                                     :style="`background: ${latest_score.level_color};`"></div>
                                <span class="text-xs font-semibold"
                                      :style="`color: ${latest_score.level_color};`">
                                    Riesgo {{ latest_score.level_label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Factores -->
                <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                    <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-variant);">
                        <h3 class="text-sm font-extrabold"
                            style="font-family: var(--font-headline); color: var(--color-text-primary);">
                            Factores de riesgo
                        </h3>
                    </div>
                    <table class="w-full text-sm" style="font-family: var(--font-body);">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--color-border-variant);">
                                <th class="text-left px-6 py-3 text-xs font-bold uppercase tracking-wider"
                                    style="color: var(--color-text-secondary);">Factor</th>
                                <th class="text-left px-6 py-3 text-xs font-bold uppercase tracking-wider"
                                    style="color: var(--color-text-secondary);">Peso</th>
                                <th class="text-right px-6 py-3 text-xs font-bold uppercase tracking-wider"
                                    style="color: var(--color-text-secondary);">Puntos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="f in factors" :key="f.key"
                                style="border-bottom: 1px solid var(--color-border-variant);"
                                :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                :onMouseout="e => e.currentTarget.style.background = ''">
                                <td class="px-6 py-4 font-medium"
                                    style="color: var(--color-text-primary);">{{ f.label }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 rounded-full overflow-hidden" style="width: 160px; background: var(--color-bg-elevated);">
                                            <div class="h-2 rounded-full transition-all duration-500"
                                                 :style="`width: ${(f.points / f.max) * 100}%; background: ${f.points > 0 ? latest_score.level_color : 'var(--color-border-variant)'};`">
                                            </div>
                                        </div>
                                        <span class="text-xs" style="color: var(--color-text-muted);">
                                            máx {{ f.max }} pts
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="font-bold text-sm"
                                          :style="f.points > 0
                                              ? `color: ${latest_score.level_color};`
                                              : 'color: var(--color-text-muted);'">
                                        {{ f.points }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Recomendaciones IA -->
                <div v-if="recommendations.length"
                     class="rounded-2xl overflow-hidden"
                     style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant);">
                    <div class="flex items-center gap-3 px-6 py-4"
                         style="border-bottom: 1px solid var(--color-border-variant);">
                        <span class="material-symbols-outlined"
                              style="color: var(--color-secondary); font-variation-settings: 'FILL' 1; font-size: 20px;">auto_awesome</span>
                        <span class="font-extrabold text-sm"
                              style="font-family: var(--font-headline); color: var(--color-text-primary);">
                            Recomendaciones IA
                        </span>
                        <span class="ml-auto text-xs px-2 py-0.5 rounded-full font-semibold"
                              style="background: var(--color-bg-elevated); color: var(--color-text-secondary);">
                            {{ recommendations.length }}
                        </span>
                    </div>

                    <div class="divide-y" style="border-color: var(--color-border-variant);">
                        <div v-for="(rec, i) in recommendations" :key="i">
                            <!-- Cabecera clicable -->
                            <button
                                class="w-full text-left px-6 py-4 flex items-center gap-4 transition-all"
                                :style="openRec === i
                                    ? 'background: var(--color-bg-elevated);'
                                    : ''"
                                :onMouseover="e => openRec !== i && (e.currentTarget.style.background = 'var(--color-bg-hover)')"
                                :onMouseout="e => openRec !== i && (e.currentTarget.style.background = '')"
                                @click="toggleRec(i)">
                                <!-- Número -->
                                <span class="w-6 h-6 rounded-full text-xs font-bold flex items-center justify-center flex-shrink-0"
                                      :style="`background: ${latest_score.level_bg}; color: ${latest_score.level_color};`">
                                    {{ i + 1 }}
                                </span>
                                <!-- Título -->
                                <span class="flex-1 text-sm font-semibold"
                                      style="color: var(--color-text-primary);">
                                    {{ rec.title }}
                                </span>
                                <!-- Chevron -->
                                <span class="material-symbols-outlined transition-transform duration-200 flex-shrink-0"
                                      :style="`font-size: 18px; color: var(--color-text-muted); transform: rotate(${openRec === i ? 180 : 0}deg);`">
                                    expand_more
                                </span>
                            </button>

                            <!-- Detalle expandible -->
                            <div v-if="openRec === i && rec.detail"
                                 class="px-6 pb-5"
                                 style="background: var(--color-bg-elevated);">
                                <p class="text-sm leading-relaxed mb-4"
                                   style="color: var(--color-text-secondary); padding-left: 2.5rem;">
                                    {{ rec.detail }}
                                </p>
                                <div style="padding-left: 2.5rem;">
                                    <button
                                        @click="router.get(route(ACTION_ROUTES[rec.action] ?? 'events.index'))"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold transition-all active:scale-95"
                                        style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer; box-shadow: var(--shadow-primary);">
                                        <span class="material-symbols-outlined" style="font-size: 15px;">arrow_forward</span>
                                        {{ rec.action_label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historial de scores -->
                <div v-if="history.length > 1" class="rounded-2xl overflow-hidden"
                     style="background: var(--color-bg-card);">
                    <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-variant);">
                        <h3 class="text-sm font-extrabold"
                            style="font-family: var(--font-headline); color: var(--color-text-primary);">
                            Historial de scores
                        </h3>
                    </div>
                    <table class="w-full text-sm" style="font-family: var(--font-body);">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--color-border-variant);">
                                <th class="text-left px-6 py-3 text-xs font-bold uppercase tracking-wider"
                                    style="color: var(--color-text-secondary);">Fecha</th>
                                <th class="text-left px-6 py-3 text-xs font-bold uppercase tracking-wider"
                                    style="color: var(--color-text-secondary);">Score</th>
                                <th class="text-left px-6 py-3 text-xs font-bold uppercase tracking-wider"
                                    style="color: var(--color-text-secondary);">Nivel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="h in history" :key="h.id"
                                style="border-bottom: 1px solid var(--color-border-variant);"
                                :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                :onMouseout="e => e.currentTarget.style.background = ''">
                                <td class="px-6 py-3" style="color: var(--color-text-secondary);">
                                    {{ fmtDate(h.calculated_at) }}
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 rounded-full overflow-hidden" style="width: 80px; background: var(--color-bg-elevated);">
                                            <div class="h-2 rounded-full"
                                                 :style="`width: ${h.score_value}%; background: ${h.level_color};`">
                                            </div>
                                        </div>
                                        <span class="font-bold" :style="`color: ${h.level_color};`">
                                            {{ h.score_value }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-2 h-2 rounded-full"
                                             :style="`background: ${h.level_color};`"></div>
                                        <span class="text-xs font-semibold"
                                              :style="`color: ${h.level_color};`">
                                            {{ h.level_label }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

        </div>
    </AppLayout>
</template>
