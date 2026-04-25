<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    contracts:        { type: Object, required: true },
    selectedContract: { type: Object, default: null },
    events:           { type: Array,  default: () => [] },
    flash:            { type: Object, default: () => ({}) },
})

const page  = usePage()
const flash = computed(() => page.props.flash)

function selectContract(id) {
    router.get(route('delay-analysis.index'), { contract_id: id }, { preserveState: false })
}

function goToAnalysis(event) {
    router.get(route('delay-analysis.show', {
        contract: props.selectedContract.id,
        event:    event.id,
    }))
}

const stats = computed(() => ({
    total:     props.events.length,
    analyzed:  props.events.filter(e => e.has_analysis).length,
    critical:  props.events.filter(e => e.is_critical_path).length,
    pending:   props.events.filter(e => !e.has_analysis).length,
}))

const PARTY_COLORS = {
    mandante:    { bg: 'var(--color-error-container)',   text: 'var(--color-on-error-container)' },
    contratista: { bg: 'rgba(234,179,8,0.15)',           text: '#854d0e' },
    fuerza_mayor:{ bg: 'rgba(59,130,246,0.12)',          text: '#1d4ed8' },
    tercero:     { bg: 'rgba(168,85,247,0.12)',          text: '#7e22ce' },
}
</script>

<template>
    <AppLayout title="Análisis CPM — Análisis de Plazo">

        <div v-if="flash?.success" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-success-container); color: var(--color-on-success-container);">
            <span class="material-symbols-outlined">check_circle</span>{{ flash.success }}
        </div>

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">Análisis CPM — Plazo</h2>
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    Metodología de análisis de plazo por evento — sustento técnico del expediente de claim
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            <!-- Contratos -->
            <div class="lg:col-span-1">
                <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <div class="px-5 py-4" style="border-bottom: 1px solid var(--color-border-variant);">
                        <p class="text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Contratos</p>
                    </div>
                    <nav class="p-2">
                        <button v-for="c in contracts.data" :key="c.id" @click="selectContract(c.id)"
                                class="w-full text-left px-4 py-3 rounded-xl mb-1 text-sm transition-all"
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

            <!-- Principal -->
            <div class="lg:col-span-3">

                <div v-if="!selectedContract" class="flex flex-col items-center justify-center h-64 rounded-2xl"
                     style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <span class="material-symbols-outlined mb-3" style="font-size: 48px; color: var(--color-text-muted);">account_tree</span>
                    <p class="font-semibold" style="color: var(--color-text-secondary);">Selecciona un contrato</p>
                </div>

                <template v-else>
                    <!-- Stats -->
                    <div class="grid grid-cols-4 gap-4 mb-6">
                        <div class="p-4 rounded-2xl text-center" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-text-primary);">{{ stats.total }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-text-muted);">Eventos con impacto</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center" style="background: var(--color-success-container);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-on-success-container);">{{ stats.analyzed }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-on-success-container); opacity: 0.8;">Analizados</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center" style="background: rgba(239,68,68,0.12);">
                            <p class="text-2xl font-extrabold" style="color: #b91c1c;">{{ stats.critical }}</p>
                            <p class="text-xs font-bold mt-1" style="color: #b91c1c; opacity: 0.8;">Ruta crítica</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center"
                             :style="stats.pending > 0 ? 'background: var(--color-error-container);' : 'background: var(--color-bg-card); box-shadow: var(--shadow-card);'">
                            <p class="text-2xl font-extrabold"
                               :style="stats.pending > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-text-primary);'">{{ stats.pending }}</p>
                            <p class="text-xs font-bold mt-1"
                               :style="stats.pending > 0 ? 'color: var(--color-on-error-container); opacity: 0.8;' : 'color: var(--color-text-muted);'">Sin analizar</p>
                        </div>
                    </div>

                    <!-- Nota informativa -->
                    <div class="flex items-start gap-3 p-4 rounded-xl mb-5"
                         style="background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.2);">
                        <span class="material-symbols-outlined flex-shrink-0 mt-0.5" style="font-size: 18px; color: #1d4ed8;">info</span>
                        <p class="text-xs" style="color: #1d4ed8;">
                            Solo se muestran eventos con impacto en programa (&gt; 0 días). Para registrar el impacto en un evento, edítalo en el módulo de Eventos.
                        </p>
                    </div>

                    <!-- Lista eventos -->
                    <div class="space-y-3">
                        <div v-for="e in events" :key="e.id"
                             class="rounded-2xl p-5 flex items-center gap-4 cursor-pointer transition-all"
                             style="background: var(--color-bg-card); box-shadow: var(--shadow-card);"
                             :onMouseover="r => r.currentTarget.style.background = 'var(--color-bg-hover)'"
                             :onMouseout="r => r.currentTarget.style.background = 'var(--color-bg-card)'"
                             @click="goToAnalysis(e)">

                            <!-- Estado icon -->
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                                 :style="e.has_analysis
                                     ? (e.is_critical_path ? 'background: rgba(239,68,68,0.12);' : 'background: var(--color-success-container);')
                                     : 'background: var(--color-error-container);'">
                                <span class="material-symbols-outlined" style="font-size: 20px;"
                                      :style="e.has_analysis
                                          ? (e.is_critical_path ? 'color: #b91c1c;' : 'color: var(--color-on-success-container);')
                                          : 'color: var(--color-error);'">
                                    {{ e.has_analysis ? (e.is_critical_path ? 'warning' : 'check_circle') : 'pending' }}
                                </span>
                            </div>

                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                                    <span class="text-sm font-bold" style="color: var(--color-text-primary);">{{ e.type_label }}</span>
                                    <span class="text-xs" style="color: var(--color-text-muted);">{{ e.occurred_at }}</span>
                                    <!-- Parte responsable -->
                                    <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                                          :style="`background: ${(PARTY_COLORS[e.responsible_party] ?? PARTY_COLORS.tercero).bg}; color: ${(PARTY_COLORS[e.responsible_party] ?? PARTY_COLORS.tercero).text};`">
                                        {{ e.party_label }}
                                    </span>
                                </div>
                                <p class="text-xs truncate" style="color: var(--color-text-secondary);">{{ e.description }}</p>
                                <!-- Método de análisis si existe -->
                                <div v-if="e.delay_type_label" class="flex items-center gap-2 mt-1">
                                    <span class="text-xs font-semibold" style="color: var(--color-text-muted);">{{ e.delay_type_label }}</span>
                                    <span class="text-xs" style="color: var(--color-text-muted);">·</span>
                                    <span class="text-xs" style="color: var(--color-text-muted);">{{ e.analysis_method_label }}</span>
                                </div>
                            </div>

                            <!-- Días -->
                            <div class="text-right flex-shrink-0">
                                <div class="text-sm font-bold" style="color: var(--color-text-primary);">
                                    {{ e.schedule_impact_days }} días
                                </div>
                                <div class="text-xs" style="color: var(--color-text-muted);">impacto</div>
                                <div v-if="e.is_critical_path" class="text-xs mt-0.5 font-bold" style="color: #b91c1c;">
                                    ruta crítica
                                </div>
                            </div>

                            <!-- Flecha -->
                            <span class="material-symbols-outlined flex-shrink-0" style="font-size: 20px; color: var(--color-text-muted);">chevron_right</span>
                        </div>

                        <div v-if="events.length === 0" class="flex flex-col items-center justify-center py-16 rounded-2xl"
                             style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                            <span class="material-symbols-outlined mb-3" style="font-size: 48px; color: var(--color-text-muted);">account_tree</span>
                            <p class="font-semibold mb-1" style="color: var(--color-text-secondary);">Sin eventos con impacto en programa</p>
                            <p class="text-xs" style="color: var(--color-text-muted);">Registra el impacto en días en el módulo de Eventos</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
