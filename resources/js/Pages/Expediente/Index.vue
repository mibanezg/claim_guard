<script setup>
import { ref, computed } from 'vue'
import { usePage, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const sections = [
    { icon: 'summarize',      label: 'Resumen ejecutivo',            desc: 'Análisis situacional generado por IA (cuando disponible)' },
    { icon: 'show_chart',     label: 'Impacto acumulado',            desc: 'Plazo y costo en disputa vs monto vigente' },
    { icon: 'timeline',       label: 'Línea de tiempo',              desc: 'Cronología completa de eventos contractuales' },
    { icon: 'mail',           label: 'Registro de correspondencia',  desc: 'Todas las cartas emitidas y recibidas con estado' },
    { icon: 'receipt_long',   label: 'Órdenes de cambio',            desc: 'Historial de OC con impactos aprobados y pendientes' },
    { icon: 'folder_open',    label: 'Índice de documentos',         desc: 'Inventario de todos los documentos del contrato' },
]

const props = defineProps({
    contracts:    { type: Array,   default: () => [] },
    ai_available: { type: Boolean, default: false },
    flash:        { type: Object,  default: () => ({}) },
})

const page       = usePage()
const flashData  = computed(() => page.props.flash ?? props.flash)

const selectedId = ref(null)
const selected   = computed(() => props.contracts.find(c => c.id === selectedId.value) ?? null)

const generateForms = {}
function getForm(id) {
    if (!generateForms[id]) generateForms[id] = useForm({})
    return generateForms[id]
}

function generate(contract) {
    getForm(contract.id).post(route('expediente.generate', contract.id), {
        preserveScroll: true,
    })
}

function download(contract) {
    window.location.href = route('expediente.download', contract.id)
}

function riskColor(level) {
    const map = { bajo: '#22c55e', medio: '#eab308', alto: '#f97316', critico: '#ef4444' }
    return map[level] ?? 'var(--color-text-muted)'
}

function riskLabel(level) {
    const map = { bajo: 'Bajo', medio: 'Medio', alto: 'Alto', critico: 'Crítico' }
    return map[level] ?? '—'
}
</script>

<template>
    <AppLayout title="Expediente de Claim">
        <div class="flex gap-6 h-full">

            <!-- Panel lateral: contratos en disputa -->
            <div class="w-72 flex-shrink-0 flex flex-col gap-4">
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-3"
                        style="color: var(--color-text-secondary); font-family: var(--font-body);">
                        Contratos en disputa
                    </h3>
                    <div v-if="contracts.length === 0" class="py-8 text-center">
                        <span class="material-symbols-outlined"
                              style="font-size: 36px; color: var(--color-text-muted);">gavel</span>
                        <p class="text-xs mt-2" style="color: var(--color-text-muted);">
                            No hay contratos en disputa
                        </p>
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
                                <div v-if="c.risk_level" class="flex-shrink-0">
                                    <div class="w-2 h-2 rounded-full"
                                         :class="c.risk_level === 'critico' ? 'animate-pulse' : ''"
                                         :style="`background: ${riskColor(c.risk_level)}`"></div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Info IA -->
                <div class="rounded-2xl p-4" style="background: var(--color-bg-card);">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined"
                              :style="`font-size: 18px; color: ${ai_available ? 'var(--color-primary)' : 'var(--color-text-muted)'}`">
                            auto_awesome
                        </span>
                        <div>
                            <p class="text-xs font-bold mb-1"
                               style="color: var(--color-text-primary);">Resumen IA</p>
                            <p class="text-xs leading-relaxed" style="color: var(--color-text-muted);">
                                {{ ai_available
                                    ? 'El resumen ejecutivo se genera automáticamente con IA al generar el expediente.'
                                    : 'Configura un proveedor de IA en Ajustes para habilitar el resumen automático.'
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel principal -->
            <div class="flex-1 flex flex-col gap-6 min-w-0">

                <!-- Flash -->
                <div v-if="flashData?.success" class="flex items-center gap-3 px-5 py-3 rounded-2xl"
                     style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);">
                    <span class="material-symbols-outlined" style="color: #22c55e; font-size: 20px;">check_circle</span>
                    <span class="text-sm font-medium" style="color: #22c55e;">{{ flashData.success }}</span>
                </div>
                <div v-if="flashData?.error" class="flex items-center gap-3 px-5 py-3 rounded-2xl"
                     style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);">
                    <span class="material-symbols-outlined" style="color: #ef4444; font-size: 20px;">error</span>
                    <span class="text-sm font-medium" style="color: #ef4444;">{{ flashData.error }}</span>
                </div>

                <!-- Estado vacío: sin selección -->
                <div v-if="!selected" class="flex-1 flex flex-col items-center justify-center gap-4">
                    <span class="material-symbols-outlined"
                          style="font-size: 56px; color: var(--color-text-muted);">description</span>
                    <div class="text-center">
                        <p class="text-base font-semibold" style="color: var(--color-text-primary);">
                            Expediente de Claim
                        </p>
                        <p class="text-sm mt-1" style="color: var(--color-text-muted);">
                            {{ contracts.length > 0
                                ? 'Selecciona un contrato para gestionar su expediente'
                                : 'Los contratos en estado "en disputa" aparecerán aquí' }}
                        </p>
                    </div>
                </div>

                <template v-else>

                    <!-- Header -->
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-extrabold"
                                style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                Expediente de Claim
                            </h2>
                            <p class="text-sm mt-1" style="color: var(--color-text-secondary);">
                                {{ selected.name }} — {{ selected.number }}
                            </p>
                            <div class="flex items-center gap-3 mt-2">
                                <p class="text-xs" style="color: var(--color-text-muted);">
                                    Mandante: <strong>{{ selected.mandante }}</strong>
                                </p>
                                <span style="color: var(--color-border-variant);">·</span>
                                <p class="text-xs" style="color: var(--color-text-muted);">
                                    Contratista: <strong>{{ selected.contractor }}</strong>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button v-if="selected.has_pdf"
                                    @click="download(selected)"
                                    class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                    style="background: var(--color-bg-card); color: var(--color-text-primary); border: 1px solid var(--color-border-variant); cursor: pointer;">
                                <span class="material-symbols-outlined" style="font-size: 18px;">download</span>
                                Descargar PDF
                            </button>
                            <button @click="generate(selected)"
                                    :disabled="getForm(selected.id).processing"
                                    class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                    style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                                <span class="material-symbols-outlined" style="font-size: 18px;">
                                    {{ getForm(selected.id).processing ? 'hourglass_empty' : 'auto_awesome' }}
                                </span>
                                {{ selected.has_pdf ? 'Regenerar' : 'Generar expediente' }}
                            </button>
                        </div>
                    </div>

                    <!-- KPI cards -->
                    <div class="grid grid-cols-3 gap-4">
                        <!-- Riesgo -->
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2"
                               style="color: var(--color-text-secondary);">Nivel de riesgo</p>
                            <div class="flex items-center gap-2">
                                <div v-if="selected.risk_level"
                                     class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                     :class="selected.risk_level === 'critico' ? 'animate-pulse' : ''"
                                     :style="`background: ${riskColor(selected.risk_level)}`"></div>
                                <p class="text-2xl font-extrabold"
                                   :style="`color: ${selected.risk_level ? riskColor(selected.risk_level) : 'var(--color-text-primary)'}; font-family: var(--font-headline);`">
                                    {{ selected.risk_level ? riskLabel(selected.risk_level) : '—' }}
                                </p>
                            </div>
                            <p v-if="selected.risk_value" class="text-xs mt-1"
                               style="color: var(--color-text-muted);">
                                {{ selected.risk_value }}/100 puntos
                            </p>
                        </div>

                        <!-- Estado PDF -->
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2"
                               style="color: var(--color-text-secondary);">Estado del expediente</p>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined"
                                      :style="`font-size: 22px; color: ${selected.has_pdf ? '#22c55e' : 'var(--color-text-muted)'}`">
                                    {{ selected.has_pdf ? 'check_circle' : 'pending' }}
                                </span>
                                <p class="text-base font-bold"
                                   :style="`color: ${selected.has_pdf ? '#22c55e' : 'var(--color-text-muted)'}; font-family: var(--font-headline);`">
                                    {{ selected.has_pdf ? 'Disponible' : 'No generado' }}
                                </p>
                            </div>
                        </div>

                        <!-- Resumen IA -->
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2"
                               style="color: var(--color-text-secondary);">Resumen IA</p>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined"
                                      :style="`font-size: 22px; color: ${selected.has_summary ? 'var(--color-primary)' : 'var(--color-text-muted)'}`">
                                    {{ selected.has_summary ? 'auto_awesome' : 'auto_awesome_motion' }}
                                </span>
                                <p class="text-base font-bold"
                                   :style="`color: ${selected.has_summary ? 'var(--color-primary)' : 'var(--color-text-muted)'}; font-family: var(--font-headline);`">
                                    {{ selected.has_summary ? 'Incluido' : 'Sin resumen' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Última generación -->
                    <div v-if="selected.claim_generated_at" class="rounded-2xl p-5 flex items-center gap-4"
                         style="background: var(--color-bg-card);">
                        <span class="material-symbols-outlined"
                              style="font-size: 20px; color: var(--color-primary);">history</span>
                        <div>
                            <p class="text-xs font-bold" style="color: var(--color-text-secondary);">Última generación</p>
                            <p class="text-sm font-medium" style="color: var(--color-text-primary);">
                                {{ selected.claim_generated_at }}
                            </p>
                        </div>
                        <div class="ml-auto">
                            <div v-if="selected.claim_pdf_sharepoint_url"
                                 class="flex items-center gap-1.5 text-xs font-medium"
                                 style="color: var(--color-primary);">
                                <span class="material-symbols-outlined" style="font-size: 16px;">cloud</span>
                                Almacenado en SharePoint
                            </div>
                            <div v-else class="flex items-center gap-1.5 text-xs font-medium"
                                 style="color: var(--color-text-muted);">
                                <span class="material-symbols-outlined" style="font-size: 16px;">storage</span>
                                Almacenado localmente
                            </div>
                        </div>
                    </div>

                    <!-- Preview del resumen IA -->
                    <div v-if="selected.has_summary" class="rounded-2xl p-6"
                         style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant);">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined"
                                  style="font-size: 18px; color: var(--color-primary); font-variation-settings: 'FILL' 1;">auto_awesome</span>
                            <p class="text-xs font-bold uppercase tracking-wider"
                               style="color: var(--color-text-secondary);">Resumen ejecutivo IA</p>
                        </div>
                        <p class="text-sm leading-relaxed"
                           style="color: var(--color-text-secondary); white-space: pre-line; font-style: italic;">
                            {{ selected.claim_summary_preview }}{{ selected.claim_summary_preview?.length >= 500 ? '...' : '' }}
                        </p>
                        <p class="text-xs mt-3" style="color: var(--color-text-muted);">
                            El PDF incluye el resumen completo con formato estructurado.
                        </p>
                    </div>

                    <!-- Instrucciones / contenido del expediente -->
                    <div class="rounded-2xl p-6" style="background: var(--color-bg-card);">
                        <h3 class="text-sm font-bold mb-4"
                            style="color: var(--color-text-primary); font-family: var(--font-headline);">
                            Contenido del expediente
                        </h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div v-for="section in sections" :key="section.label"
                                 class="flex items-start gap-3 p-4 rounded-xl"
                                 style="background: var(--color-bg-elevated);">
                                <span class="material-symbols-outlined"
                                      style="font-size: 20px; color: var(--color-primary); flex-shrink: 0;">
                                    {{ section.icon }}
                                </span>
                                <div>
                                    <p class="text-xs font-bold" style="color: var(--color-text-primary);">
                                        {{ section.label }}
                                    </p>
                                    <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">
                                        {{ section.desc }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                </template>
            </div>
        </div>
    </AppLayout>
</template>
