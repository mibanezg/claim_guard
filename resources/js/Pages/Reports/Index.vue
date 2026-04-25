<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    contracts: { type: Array, default: () => [] },
})

// Contrato seleccionado para filtrar reportes por contrato
const selectedContractId = ref(null)

function buildUrl(routeName, withContract = false) {
    const params = {}
    if (withContract && selectedContractId.value) {
        params.contract_id = selectedContractId.value
    }
    return route(routeName, params)
}

function download(routeName, withContract = false) {
    window.location.href = buildUrl(routeName, withContract)
}

const reports = [
    {
        group: 'Contratos',
        icon: 'description',
        color: 'var(--color-primary)',
        colorBg: 'rgba(42,100,150,0.08)',
        items: [
            {
                label:   'Estado general de contratos',
                desc:    'Lista completa con monto vigente, plazos y semáforo de riesgo',
                formats: [
                    { label: 'Excel', icon: 'table_chart',   route: 'reports.contracts.excel', perContract: false },
                    { label: 'PDF',   icon: 'picture_as_pdf', route: 'reports.contracts.pdf',  perContract: false },
                ],
            },
        ],
    },
    {
        group: 'Eventos contractuales',
        icon: 'event_note',
        color: '#7c3aed',
        colorBg: 'rgba(124,58,237,0.08)',
        items: [
            {
                label:   'Historial de eventos',
                desc:    'Cronología de eventos con responsable, impacto y estado de resolución',
                formats: [
                    { label: 'Excel', icon: 'table_chart', route: 'reports.events.excel', perContract: true },
                ],
            },
        ],
    },
    {
        group: 'Correspondencia',
        icon: 'mail',
        color: '#0891b2',
        colorBg: 'rgba(8,145,178,0.08)',
        items: [
            {
                label:   'Registro de correspondencia',
                desc:    'Todas las cartas emitidas y recibidas con estado y vencimientos',
                formats: [
                    { label: 'Excel', icon: 'table_chart',   route: 'reports.letters.excel', perContract: true },
                    { label: 'PDF',   icon: 'picture_as_pdf', route: 'reports.letters.pdf',  perContract: true },
                ],
            },
        ],
    },
    {
        group: 'Órdenes de cambio',
        icon: 'swap_horiz',
        color: '#d97706',
        colorBg: 'rgba(217,119,6,0.08)',
        items: [
            {
                label:   'Resumen de órdenes de cambio',
                desc:    'OC con impacto en plazo y costo, estado de aprobación',
                formats: [
                    { label: 'Excel', icon: 'table_chart', route: 'reports.change-orders.excel', perContract: true },
                ],
            },
        ],
    },
    {
        group: 'Programa de trabajo',
        icon: 'calendar_month',
        color: '#059669',
        colorBg: 'rgba(5,150,105,0.08)',
        items: [
            {
                label:   'Curva S — datos de avance',
                desc:    'Hitos con avance planificado vs. real por fecha',
                formats: [
                    { label: 'Excel', icon: 'table_chart', route: 'reports.curva-s.excel', perContract: true, required: true },
                ],
            },
        ],
    },
]
</script>

<template>
    <AppLayout title="Reportes">
        <div class="flex gap-6 h-full">

            <!-- Panel lateral: filtro de contrato -->
            <div class="w-72 flex-shrink-0 flex flex-col gap-4">
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-3"
                        style="color: var(--color-text-secondary); font-family: var(--font-body);">
                        Filtrar por contrato
                    </h3>
                    <p class="text-xs mb-3" style="color: var(--color-text-muted);">
                        Opcional — aplica a reportes que lo admiten
                    </p>

                    <!-- Sin filtro -->
                    <button @click="selectedContractId = null"
                            class="w-full text-left px-3 py-2.5 rounded-xl text-sm transition-all mb-1"
                            :style="selectedContractId === null
                                ? 'background: var(--color-primary); color: var(--color-on-primary); font-weight: 600;'
                                : 'color: var(--color-text-primary);'"
                            :onMouseover="e => selectedContractId !== null && (e.currentTarget.style.background = 'var(--color-bg-hover)')"
                            :onMouseout="e => selectedContractId !== null && (e.currentTarget.style.background = '')">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined" style="font-size: 16px;">all_inclusive</span>
                            Todos los contratos
                        </div>
                    </button>

                    <div class="space-y-1 mt-1">
                        <button v-for="c in contracts" :key="c.id"
                                @click="selectedContractId = c.id"
                                class="w-full text-left px-3 py-2 rounded-xl text-xs transition-all"
                                :style="selectedContractId === c.id
                                    ? 'background: var(--color-primary); color: var(--color-on-primary); font-weight: 600;'
                                    : 'color: var(--color-text-primary);'"
                                :onMouseover="e => selectedContractId !== c.id && (e.currentTarget.style.background = 'var(--color-bg-hover)')"
                                :onMouseout="e => selectedContractId !== c.id && (e.currentTarget.style.background = '')">
                            <div class="truncate">{{ c.label }}</div>
                        </button>
                    </div>
                </div>

                <!-- Nota informativa -->
                <div class="rounded-2xl p-4" style="background: var(--color-bg-card);">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined"
                              style="font-size: 18px; color: var(--color-primary);">info</span>
                        <p class="text-xs leading-relaxed" style="color: var(--color-text-muted);">
                            Los reportes se generan al instante con los datos actuales del sistema.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Panel principal -->
            <div class="flex-1 flex flex-col gap-6 min-w-0">

                <!-- Header -->
                <div>
                    <h2 class="text-2xl font-extrabold"
                        style="font-family: var(--font-headline); color: var(--color-text-primary);">
                        Reportes y exportaciones
                    </h2>
                    <p class="text-sm mt-1" style="color: var(--color-text-secondary);">
                        Exporta datos del sistema en Excel o PDF
                        <span v-if="selectedContractId">
                            — filtrando por: <strong>{{ contracts.find(c => c.id === selectedContractId)?.label }}</strong>
                        </span>
                    </p>
                </div>

                <!-- Grupos de reportes -->
                <div class="flex flex-col gap-4">
                    <div v-for="group in reports" :key="group.group"
                         class="rounded-2xl overflow-hidden"
                         style="background: var(--color-bg-card);">

                        <!-- Header del grupo -->
                        <div class="px-6 py-4 flex items-center gap-3"
                             style="border-bottom: 1px solid var(--color-border-variant);">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center"
                                 :style="`background: ${group.colorBg};`">
                                <span class="material-symbols-outlined"
                                      :style="`font-size: 18px; color: ${group.color};`">
                                    {{ group.icon }}
                                </span>
                            </div>
                            <h3 class="text-sm font-bold"
                                style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                {{ group.group }}
                            </h3>
                        </div>

                        <!-- Items del grupo -->
                        <div v-for="item in group.items" :key="item.label"
                             class="px-6 py-4 flex items-center gap-4"
                             style="border-bottom: 1px solid var(--color-border-variant);">

                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold"
                                   style="color: var(--color-text-primary);">{{ item.label }}</p>
                                <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">{{ item.desc }}</p>
                            </div>

                            <!-- Botones de formato -->
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <div v-for="fmt in item.formats" :key="fmt.route">
                                    <!-- Botón deshabilitado si requiere contrato y no hay seleccionado -->
                                    <button v-if="fmt.required && !selectedContractId"
                                            disabled
                                            class="flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-bold"
                                            style="background: var(--color-bg-elevated); color: var(--color-text-muted); cursor: not-allowed; border: none;"
                                            title="Selecciona un contrato para este reporte">
                                        <span class="material-symbols-outlined" style="font-size: 15px;">{{ fmt.icon }}</span>
                                        {{ fmt.label }}
                                    </button>

                                    <button v-else
                                            @click="download(fmt.route, fmt.perContract)"
                                            class="flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-bold transition-all active:scale-95"
                                            :style="fmt.label === 'PDF'
                                                ? 'background: rgba(239,68,68,0.1); color: #b91c1c; border: 1px solid rgba(239,68,68,0.2); cursor: pointer;'
                                                : 'background: rgba(5,150,105,0.1); color: #059669; border: 1px solid rgba(5,150,105,0.2); cursor: pointer;'">
                                        <span class="material-symbols-outlined" style="font-size: 15px;">{{ fmt.icon }}</span>
                                        {{ fmt.label }}
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
