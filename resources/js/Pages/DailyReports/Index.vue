<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
    contracts:        { type: Object,  required: true },
    selectedContract: { type: Object,  default: null },
    reports:          { type: Object,  default: null },
    filters:          { type: Object,  default: () => ({}) },
    missingDays:      { type: Array,   default: () => [] },
    flash:            { type: Object,  default: () => ({}) },
    weatherLabels:    { type: Object,  default: () => ({}) },
    weatherIcons:     { type: Object,  default: () => ({}) },
})

const page  = usePage()
const flash = computed(() => page.props.flash)
const { confirmDelete } = useConfirm()

const can = computed(() => {
    const perms = page.props.auth?.user?.permissions ?? []
    return { create: perms.includes('contracts.edit') }
})

const monthFilter = ref(props.filters?.month ?? '')

function selectContract(id) {
    router.get(route('daily-reports.index'), { contract_id: id }, { preserveState: false })
}

function applyFilters() {
    router.get(route('daily-reports.index'), {
        contract_id: props.selectedContract?.id,
        month:       monthFilter.value || undefined,
    }, { preserveState: true, replace: true })
}

function goCreate() {
    router.get(route('daily-reports.create'), { contract_id: props.selectedContract?.id })
}

function goEdit(report) {
    router.get(route('daily-reports.edit', {
        contract:    props.selectedContract.id,
        dailyReport: report.id,
    }))
}

async function handleDelete(report) {
    const confirmed = await confirmDelete(report.report_number)
    if (!confirmed) return
    router.delete(route('daily-reports.destroy', {
        contract:    props.selectedContract.id,
        dailyReport: report.id,
    }), { preserveScroll: true })
}

const weatherColorMap = {
    bueno:         { bg: 'rgba(34,197,94,0.12)',  text: '#166534' },
    nublado:       { bg: 'rgba(148,163,184,0.2)', text: '#475569' },
    lluvia:        { bg: 'rgba(59,130,246,0.12)', text: '#1d4ed8' },
    viento_fuerte: { bg: 'rgba(249,115,22,0.12)', text: '#c2410c' },
    nevada:        { bg: 'rgba(147,197,253,0.2)', text: '#1e40af' },
    otro:          { bg: 'var(--color-bg-elevated)', text: 'var(--color-text-muted)' },
}

const stats = computed(() => {
    const data = props.reports?.data ?? []
    return {
        total:        data.length,
        withIssues:   data.filter(r => r.has_issues).length,
        withInstr:    data.filter(r => r.has_instructions).length,
        missing:      props.missingDays.length,
    }
})

// Últimos 7 días de missing para mostrar alerta concreta
const recentMissing = computed(() => props.missingDays.slice(-7).reverse())
</script>

<template>
    <AppLayout title="Diarios de Obra">

        <!-- Flash -->
        <div v-if="flash?.success" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-success-container); color: var(--color-on-success-container);">
            <span class="material-symbols-outlined">check_circle</span>{{ flash.success }}
        </div>

        <!-- Encabezado -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">Diario de Obra</h2>
                <p class="text-sm" style="color: var(--color-text-secondary);">Registro contemporáneo diario — evidencia clave para el expediente de claim</p>
            </div>
            <button v-if="selectedContract && can.create" @click="goCreate"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-full font-bold text-sm transition-all active:scale-95"
                    style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                <span class="material-symbols-outlined" style="font-size: 16px;">add</span>
                Registrar día
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
                    <span class="material-symbols-outlined mb-3" style="font-size: 48px; color: var(--color-text-muted);">book</span>
                    <p class="font-semibold" style="color: var(--color-text-secondary);">Selecciona un contrato</p>
                </div>

                <template v-else>

                    <!-- Alerta días sin reporte -->
                    <div v-if="missingDays.length > 0"
                         class="flex items-start gap-3 p-4 rounded-xl mb-4"
                         style="background: rgba(234,179,8,0.12); border: 1px solid rgba(234,179,8,0.3);">
                        <span class="material-symbols-outlined mt-0.5" style="font-size: 20px; color: #a16207;">warning</span>
                        <div>
                            <p class="text-sm font-bold" style="color: #a16207;">{{ missingDays.length }} día(s) sin reporte en los últimos 60 días</p>
                            <p class="text-xs mt-0.5" style="color: #92400e;">
                                Más recientes: {{ recentMissing.slice(0,5).join(', ') }}{{ missingDays.length > 5 ? '…' : '' }}
                            </p>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-4 gap-4 mb-6">
                        <div class="p-4 rounded-2xl text-center" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-text-primary);">{{ stats.total }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-text-muted);">Reportes</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center"
                             :style="stats.missing > 0 ? 'background: rgba(234,179,8,0.15);' : 'background: var(--color-success-container);'">
                            <p class="text-2xl font-extrabold"
                               :style="stats.missing > 0 ? 'color: #a16207;' : 'color: var(--color-on-success-container);'">{{ stats.missing }}</p>
                            <p class="text-xs font-bold mt-1"
                               :style="stats.missing > 0 ? 'color: #a16207; opacity: 0.8;' : 'color: var(--color-on-success-container); opacity: 0.8;'">Días sin reporte</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center" style="background: var(--color-primary-container);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-on-primary-container);">{{ stats.withInstr }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-on-primary-container); opacity: 0.8;">Con instrucciones</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center"
                             :style="stats.withIssues > 0 ? 'background: var(--color-error-container);' : 'background: var(--color-bg-card); box-shadow: var(--shadow-card);'">
                            <p class="text-2xl font-extrabold"
                               :style="stats.withIssues > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-text-primary);'">{{ stats.withIssues }}</p>
                            <p class="text-xs font-bold mt-1"
                               :style="stats.withIssues > 0 ? 'color: var(--color-on-error-container); opacity: 0.8;' : 'color: var(--color-text-muted);'">Con problemas</p>
                        </div>
                    </div>

                    <!-- Filtro mes -->
                    <div class="flex gap-3 mb-4 p-3 rounded-xl"
                         style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant);">
                        <input v-model="monthFilter" type="month" @change="applyFilters"
                               class="px-3 py-2 rounded-xl text-xs border-none outline-none"
                               style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                        <button v-if="monthFilter" @click="monthFilter = ''; applyFilters()"
                                class="px-3 py-2 rounded-xl text-xs font-semibold"
                                style="background: var(--color-bg-elevated); color: var(--color-text-secondary); border: none; cursor: pointer;">
                            Limpiar
                        </button>
                    </div>

                    <!-- Lista de reportes -->
                    <div class="space-y-3">
                        <div v-for="r in reports?.data ?? []" :key="r.id"
                             class="rounded-2xl p-5 transition-all"
                             style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">

                            <!-- Cabecera del reporte -->
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <div class="flex items-center gap-3">
                                    <!-- Clima -->
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                                         :style="`background: ${weatherColorMap[r.weather]?.bg};`">
                                        <span class="material-symbols-outlined"
                                              :style="`font-size: 20px; color: ${weatherColorMap[r.weather]?.text};`">{{ r.weather_icon }}</span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-extrabold" style="color: var(--color-text-primary);">{{ r.report_date }}</span>
                                            <span class="text-xs font-mono px-2 py-0.5 rounded-full"
                                                  style="background: var(--color-bg-elevated); color: var(--color-text-muted);">{{ r.report_number }}</span>
                                        </div>
                                        <div class="flex items-center gap-3 mt-0.5">
                                            <span class="text-xs" style="color: var(--color-text-muted);">{{ r.weather_label }}</span>
                                            <span v-if="r.temperature !== null" class="text-xs" style="color: var(--color-text-muted);">{{ r.temperature }}°C</span>
                                            <span v-if="r.total_personnel > 0" class="text-xs flex items-center gap-1" style="color: var(--color-text-muted);">
                                                <span class="material-symbols-outlined" style="font-size: 12px;">groups</span>
                                                {{ r.total_personnel }} personas
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Badges + acciones -->
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span v-if="r.has_instructions"
                                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold"
                                          style="background: rgba(59,130,246,0.12); color: #1d4ed8;"
                                          title="Contiene instrucciones del mandante">
                                        <span class="material-symbols-outlined" style="font-size: 11px;">record_voice_over</span>
                                        Instrucciones
                                    </span>
                                    <span v-if="r.has_issues"
                                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold"
                                          style="background: var(--color-error-container); color: var(--color-on-error-container);">
                                        <span class="material-symbols-outlined" style="font-size: 11px;">report</span>
                                        Problemas
                                    </span>
                                    <span v-if="r.has_incidents"
                                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold"
                                          style="background: rgba(239,68,68,0.15); color: #dc2626;">
                                        <span class="material-symbols-outlined" style="font-size: 11px;">emergency</span>
                                        Seguridad
                                    </span>
                                    <button @click="goEdit(r)"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg"
                                            style="color: var(--color-text-secondary); background: none; border: none; cursor: pointer;"
                                            :onMouseover="e => e.currentTarget.style.color = 'var(--color-primary)'"
                                            :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                                            title="Editar">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">edit</span>
                                    </button>
                                    <button @click="handleDelete(r)"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg"
                                            style="color: var(--color-text-secondary); background: none; border: none; cursor: pointer;"
                                            :onMouseover="e => e.currentTarget.style.color = 'var(--color-error)'"
                                            :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                                            title="Eliminar">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Trabajo ejecutado -->
                            <p class="text-sm leading-relaxed mb-3" style="color: var(--color-text-secondary);">{{ r.work_executed }}</p>

                            <!-- Instrucciones del mandante (destacado) -->
                            <div v-if="r.instructions_received"
                                 class="flex items-start gap-2 p-3 rounded-xl mb-3"
                                 style="background: rgba(59,130,246,0.08); border-left: 3px solid #3b82f6;">
                                <span class="material-symbols-outlined flex-shrink-0 mt-0.5" style="font-size: 16px; color: #1d4ed8;">record_voice_over</span>
                                <div>
                                    <p class="text-xs font-bold mb-1" style="color: #1d4ed8;">Instrucciones del mandante</p>
                                    <p class="text-xs" style="color: var(--color-text-secondary);">{{ r.instructions_received }}</p>
                                </div>
                            </div>

                            <!-- Problemas -->
                            <div v-if="r.issues_encountered"
                                 class="flex items-start gap-2 p-3 rounded-xl mb-3"
                                 style="background: var(--color-error-container); border-left: 3px solid var(--color-error);">
                                <span class="material-symbols-outlined flex-shrink-0 mt-0.5" style="font-size: 16px; color: var(--color-error);">report</span>
                                <div>
                                    <p class="text-xs font-bold mb-1" style="color: var(--color-on-error-container);">Problemas / Interferencias</p>
                                    <p class="text-xs" style="color: var(--color-on-error-container);">{{ r.issues_encountered }}</p>
                                </div>
                            </div>

                            <!-- Eventos vinculados -->
                            <div v-if="r.events?.length > 0" class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-bold" style="color: var(--color-text-muted);">Eventos:</span>
                                <span v-for="ev in r.events" :key="ev.id"
                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold"
                                      style="background: rgba(168,85,247,0.12); color: #a855f7;">
                                    {{ ev.type_label }} ({{ ev.occurred_at }})
                                </span>
                            </div>
                        </div>

                        <!-- Vacío -->
                        <div v-if="(reports?.data ?? []).length === 0"
                             class="flex flex-col items-center justify-center py-16 rounded-2xl"
                             style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                            <span class="material-symbols-outlined mb-3" style="font-size: 48px; color: var(--color-text-muted);">book</span>
                            <p class="font-semibold" style="color: var(--color-text-secondary);">Sin reportes registrados</p>
                            <p class="text-sm mt-1" style="color: var(--color-text-muted);">Los diarios de obra son evidencia contemporánea clave</p>
                            <button v-if="can.create" @click="goCreate" class="mt-4 px-5 py-2.5 rounded-full text-sm font-bold"
                                    style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer;">
                                Registrar primer día
                            </button>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div v-if="reports?.meta?.last_page > 1"
                         class="mt-4 px-6 py-4 flex items-center justify-between rounded-2xl"
                         style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant);">
                        <p class="text-sm" style="color: var(--color-text-secondary);">
                            Mostrando {{ reports.meta.from }}–{{ reports.meta.to }} de {{ reports.meta.total }} reportes
                        </p>
                        <div class="flex items-center gap-2">
                            <a v-if="reports.links.prev" :href="reports.links.prev"
                               class="px-3 py-1.5 rounded-lg text-sm font-semibold" style="color: var(--color-primary);">← Anterior</a>
                            <a v-if="reports.links.next" :href="reports.links.next"
                               class="px-3 py-1.5 rounded-lg text-sm font-semibold" style="color: var(--color-primary);">Siguiente →</a>
                        </div>
                    </div>

                </template>
            </div>
        </div>

    </AppLayout>
</template>
