<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    contracts:        { type: Object,  required: true },
    selectedContract: { type: Object,  default: null },
    analysis:         { type: Object,  default: null },
    isProcessing:     { type: Boolean, default: false },
    flash:            { type: Object,  default: () => ({}) },
})

const page  = usePage()
const flash = computed(() => page.props.flash)

let pollInterval = null

function selectContract(id) {
    router.get(route('analysis.index'), { contract_id: id }, { preserveState: false })
}

function requestAnalysis() {
    router.post(route('analysis.generate', { contract: props.selectedContract.id }))
}

// Cuando isProcessing pasa a false (job terminó), para el polling
watch(() => props.isProcessing, (processing) => {
    if (!processing) stopPolling()
})

onMounted(() => {
    if (props.isProcessing) startPolling()
})

onUnmounted(() => {
    stopPolling()
})

function startPolling() {
    stopPolling()
    pollInterval = setInterval(() => {
        router.reload({ only: ['analysis', 'isProcessing'] })
    }, 4000)
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval)
        pollInterval = null
    }
}

const confidenceConfig = {
    alta:  { bg: 'var(--color-success-container)', text: 'var(--color-on-success-container)', icon: 'verified' },
    media: { bg: 'rgba(234,179,8,0.15)',           text: '#a16207',                           icon: 'info' },
    baja:  { bg: 'var(--color-error-container)',   text: 'var(--color-on-error-container)',   icon: 'warning' },
}

const priorityConfig = {
    alta:  { bg: 'var(--color-error-container)',   text: 'var(--color-on-error-container)', dot: '#ef4444' },
    media: { bg: 'rgba(234,179,8,0.15)',           text: '#a16207',                          dot: '#eab308' },
    baja:  { bg: 'var(--color-bg-elevated)',       text: 'var(--color-text-secondary)',      dot: '#94a3b8' },
}

function fmt(amount) {
    if (!amount) return null
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: props.selectedContract?.currency ?? 'CLP', minimumFractionDigits: 0 }).format(amount)
}
</script>

<template>
    <AppLayout title="Análisis IA de Claim">

        <!-- Flash -->
        <div v-if="flash?.success" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-success-container); color: var(--color-on-success-container);">
            <span class="material-symbols-outlined">check_circle</span>{{ flash.success }}
        </div>
        <div v-if="flash?.error" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-error-container); color: var(--color-on-error-container);">
            <span class="material-symbols-outlined">error</span>{{ flash.error }}
        </div>

        <!-- Encabezado -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">Análisis IA de Exposición</h2>
                <p class="text-sm" style="color: var(--color-text-secondary);">Dictamen estratégico generado por IA — puntos fuertes, debilidades y acciones urgentes</p>
            </div>
            <button v-if="selectedContract && !isProcessing" @click="requestAnalysis"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-full font-bold text-sm transition-all active:scale-95"
                    style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                <span class="material-symbols-outlined" style="font-size: 16px;">psychology</span>
                {{ analysis ? 'Nuevo análisis' : 'Analizar contrato' }}
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

                <!-- Sin contrato -->
                <div v-if="!selectedContract"
                     class="flex flex-col items-center justify-center h-64 rounded-2xl"
                     style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <span class="material-symbols-outlined mb-3" style="font-size: 48px; color: var(--color-text-muted);">psychology</span>
                    <p class="font-semibold" style="color: var(--color-text-secondary);">Selecciona un contrato</p>
                </div>

                <!-- Procesando -->
                <div v-else-if="isProcessing"
                     class="flex flex-col items-center justify-center h-64 rounded-2xl"
                     style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <div class="w-12 h-12 rounded-full mb-4 flex items-center justify-center"
                         style="background: var(--color-primary-container);">
                        <span class="material-symbols-outlined animate-spin" style="font-size: 28px; color: var(--color-primary);">sync</span>
                    </div>
                    <p class="font-bold text-lg mb-2" style="color: var(--color-text-primary);">Analizando contrato…</p>
                    <p class="text-sm text-center max-w-xs" style="color: var(--color-text-secondary);">
                        La IA está revisando eventos, cartas, órdenes de cambio y diarios de obra. Esto puede tomar hasta 2 minutos.
                    </p>
                    <div class="flex items-center gap-1.5 mt-4">
                        <span class="w-2 h-2 rounded-full animate-bounce" style="background: var(--color-primary); animation-delay: 0ms;"></span>
                        <span class="w-2 h-2 rounded-full animate-bounce" style="background: var(--color-primary); animation-delay: 150ms;"></span>
                        <span class="w-2 h-2 rounded-full animate-bounce" style="background: var(--color-primary); animation-delay: 300ms;"></span>
                    </div>
                </div>

                <!-- Sin análisis -->
                <div v-else-if="!analysis"
                     class="flex flex-col items-center justify-center h-64 rounded-2xl text-center p-8"
                     style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <span class="material-symbols-outlined mb-3" style="font-size: 48px; color: var(--color-text-muted);">manage_search</span>
                    <p class="font-bold text-lg mb-2" style="color: var(--color-text-primary);">Sin análisis para este contrato</p>
                    <p class="text-sm mb-6" style="color: var(--color-text-secondary);">
                        La IA analizará eventos, cartas, diarios de obra y el cuerpo contractual para entregar un dictamen estratégico de exposición al claim.
                    </p>
                    <button @click="requestAnalysis"
                            class="flex items-center gap-2 px-6 py-3 rounded-full font-bold text-sm"
                            style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                        <span class="material-symbols-outlined" style="font-size: 18px;">psychology</span>
                        Analizar con IA
                    </button>
                </div>

                <!-- Error -->
                <div v-else-if="analysis.status === 'failed'"
                     class="rounded-2xl p-6"
                     style="background: var(--color-error-container); box-shadow: var(--shadow-card);">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="material-symbols-outlined" style="font-size: 24px; color: var(--color-error);">error</span>
                        <p class="font-bold" style="color: var(--color-on-error-container);">El análisis falló</p>
                    </div>
                    <p class="text-sm mb-4" style="color: var(--color-on-error-container);">{{ analysis.error_message }}</p>
                    <button @click="requestAnalysis"
                            class="px-5 py-2.5 rounded-full text-sm font-bold"
                            style="background: var(--color-on-error-container); color: var(--color-error-container); border: none; cursor: pointer;">
                        Reintentar análisis
                    </button>
                </div>

                <!-- Análisis completado -->
                <div v-else class="space-y-5">

                    <!-- Cabecera del análisis -->
                    <div class="rounded-2xl p-5 flex items-center justify-between"
                         style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                                 style="background: var(--color-primary-container);">
                                <span class="material-symbols-outlined" style="font-size: 20px; color: var(--color-primary);">psychology</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold" style="color: var(--color-text-primary);">Dictamen generado el {{ analysis.completed_at }}</p>
                                <p class="text-xs" style="color: var(--color-text-muted);">Solicitado: {{ analysis.created_at }}</p>
                            </div>
                        </div>
                        <!-- Confianza -->
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold"
                                  :style="`background: ${confidenceConfig[analysis.analysis_confidence]?.bg}; color: ${confidenceConfig[analysis.analysis_confidence]?.text};`">
                                <span class="material-symbols-outlined" style="font-size: 14px;">{{ confidenceConfig[analysis.analysis_confidence]?.icon }}</span>
                                Confianza {{ analysis.analysis_confidence }}
                            </span>
                        </div>
                    </div>

                    <!-- Estimaciones de exposición -->
                    <div v-if="analysis.estimated_exposure_days > 0 || analysis.estimated_exposure_cost > 0"
                         class="grid grid-cols-2 gap-4">
                        <div v-if="analysis.estimated_exposure_days > 0"
                             class="p-5 rounded-2xl text-center"
                             style="background: rgba(249,115,22,0.1); border: 1px solid rgba(249,115,22,0.3);">
                            <p class="text-3xl font-extrabold" style="color: #c2410c;">{{ analysis.estimated_exposure_days }}</p>
                            <p class="text-xs font-bold mt-1" style="color: #9a3412;">días de exposición estimada</p>
                        </div>
                        <div v-if="analysis.estimated_exposure_cost > 0"
                             class="p-5 rounded-2xl text-center"
                             style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);">
                            <p class="text-2xl font-extrabold" style="color: #dc2626;">{{ fmt(analysis.estimated_exposure_cost) }}</p>
                            <p class="text-xs font-bold mt-1" style="color: #991b1b;">exposición de costo estimada</p>
                        </div>
                    </div>

                    <!-- Evaluación narrativa -->
                    <div class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="material-symbols-outlined" style="font-size: 20px; color: var(--color-primary);">article</span>
                            <h3 class="text-sm font-extrabold uppercase tracking-widest" style="color: var(--color-text-muted);">Evaluación de exposición</h3>
                        </div>
                        <div class="text-sm leading-relaxed whitespace-pre-line" style="color: var(--color-text-secondary);">{{ analysis.exposure_assessment }}</div>
                        <div v-if="analysis.confidence_note" class="mt-4 p-3 rounded-xl text-xs"
                             :style="`background: ${confidenceConfig[analysis.analysis_confidence]?.bg}; color: ${confidenceConfig[analysis.analysis_confidence]?.text};`">
                            <strong>Nota de confianza:</strong> {{ analysis.confidence_note }}
                        </div>
                    </div>

                    <!-- Acciones urgentes -->
                    <div v-if="analysis.urgent_actions?.length > 0" class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="material-symbols-outlined" style="font-size: 20px; color: var(--color-error);">priority_high</span>
                            <h3 class="text-sm font-extrabold uppercase tracking-widest" style="color: var(--color-text-muted);">Acciones urgentes</h3>
                        </div>
                        <div class="space-y-3">
                            <div v-for="(action, i) in analysis.urgent_actions" :key="i"
                                 class="flex items-start gap-3 p-4 rounded-xl"
                                 :style="`background: ${priorityConfig[action.priority]?.bg};`">
                                <div class="w-2 h-2 rounded-full flex-shrink-0 mt-1.5"
                                     :style="`background: ${priorityConfig[action.priority]?.dot};`"></div>
                                <div class="flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-bold" :style="`color: ${priorityConfig[action.priority]?.text};`">{{ action.action }}</p>
                                        <span v-if="action.deadline"
                                              class="text-xs font-mono flex-shrink-0 px-2 py-0.5 rounded-full"
                                              style="background: rgba(0,0,0,0.08);"
                                              :style="`color: ${priorityConfig[action.priority]?.text};`">
                                            {{ action.deadline }}
                                        </span>
                                    </div>
                                    <p v-if="action.reason" class="text-xs mt-1" :style="`color: ${priorityConfig[action.priority]?.text}; opacity: 0.8;`">{{ action.reason }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Puntos fuertes y débiles -->
                    <div class="grid grid-cols-2 gap-5">

                        <div v-if="analysis.strong_points?.length > 0" class="rounded-2xl p-5" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="material-symbols-outlined" style="font-size: 18px; color: var(--color-success);">thumb_up</span>
                                <h3 class="text-xs font-extrabold uppercase tracking-widest" style="color: var(--color-text-muted);">Puntos fuertes</h3>
                            </div>
                            <ul class="space-y-2">
                                <li v-for="(point, i) in analysis.strong_points" :key="i"
                                    class="flex items-start gap-2 text-sm"
                                    style="color: var(--color-text-secondary);">
                                    <span class="material-symbols-outlined flex-shrink-0 mt-0.5" style="font-size: 14px; color: var(--color-success);">check_circle</span>
                                    {{ point }}
                                </li>
                            </ul>
                        </div>

                        <div v-if="analysis.weak_points?.length > 0" class="rounded-2xl p-5" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="material-symbols-outlined" style="font-size: 18px; color: var(--color-error);">warning</span>
                                <h3 class="text-xs font-extrabold uppercase tracking-widest" style="color: var(--color-text-muted);">Debilidades</h3>
                            </div>
                            <ul class="space-y-2">
                                <li v-for="(point, i) in analysis.weak_points" :key="i"
                                    class="flex items-start gap-2 text-sm"
                                    style="color: var(--color-text-secondary);">
                                    <span class="material-symbols-outlined flex-shrink-0 mt-0.5" style="font-size: 14px; color: var(--color-error);">cancel</span>
                                    {{ point }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Patrones detectados -->
                    <div v-if="analysis.pattern_observations?.length > 0" class="rounded-2xl p-5" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined" style="font-size: 18px; color: var(--color-primary);">insights</span>
                            <h3 class="text-xs font-extrabold uppercase tracking-widest" style="color: var(--color-text-muted);">Patrones detectados</h3>
                        </div>
                        <ul class="space-y-2">
                            <li v-for="(obs, i) in analysis.pattern_observations" :key="i"
                                class="flex items-start gap-2 text-sm p-3 rounded-xl"
                                style="background: var(--color-bg-elevated); color: var(--color-text-secondary);">
                                <span class="material-symbols-outlined flex-shrink-0 mt-0.5" style="font-size: 14px; color: var(--color-primary);">trending_up</span>
                                {{ obs }}
                            </li>
                        </ul>
                    </div>

                    <!-- Cláusulas clave -->
                    <div v-if="analysis.key_clauses?.length > 0" class="rounded-2xl p-5" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined" style="font-size: 18px; color: var(--color-primary);">gavel</span>
                            <h3 class="text-xs font-extrabold uppercase tracking-widest" style="color: var(--color-text-muted);">Cláusulas y mecanismos a invocar</h3>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="(clause, i) in analysis.key_clauses" :key="i"
                                  class="px-3 py-1.5 rounded-full text-xs font-semibold"
                                  style="background: var(--color-primary-container); color: var(--color-on-primary-container);">
                                {{ clause }}
                            </span>
                        </div>
                    </div>

                    <!-- Aviso legal -->
                    <div class="p-4 rounded-xl flex items-start gap-2"
                         style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant);">
                        <span class="material-symbols-outlined flex-shrink-0 mt-0.5" style="font-size: 16px; color: var(--color-text-muted);">info</span>
                        <p class="text-xs" style="color: var(--color-text-muted);">
                            Este análisis es generado por IA basado en los datos registrados en el sistema. No constituye asesoría legal.
                            Consulta con un abogado especialista en contratos de construcción para decisiones con consecuencias jurídicas.
                        </p>
                    </div>

                </div>
            </div>
        </div>

    </AppLayout>
</template>
