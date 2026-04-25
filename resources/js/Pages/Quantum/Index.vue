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
    router.get(route('quantum.index'), { contract_id: id }, { preserveState: false })
}

function goToQuantum(event) {
    router.get(route('quantum.show', {
        contract: props.selectedContract.id,
        event:    event.id,
    }))
}

function fmt(amount) {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: props.selectedContract?.currency ?? 'CLP',
        minimumFractionDigits: 0,
    }).format(amount)
}

const stats = computed(() => ({
    total:       props.events.length,
    documented:  props.events.filter(e => e.has_quantum).length,
    reconciled:  props.events.filter(e => e.reconciled).length,
    pending:     props.events.filter(e => !e.has_quantum).length,
}))
</script>

<template>
    <AppLayout title="Quantum — Desglose de Costos">

        <div v-if="flash?.success" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-success-container); color: var(--color-on-success-container);">
            <span class="material-symbols-outlined">check_circle</span>{{ flash.success }}
        </div>

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">Quantum — Metodología de Costo</h2>
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    Desglose formal del monto reclamado por evento — sustento técnico del expediente
                </p>
            </div>
            <a v-if="selectedContract && stats.documented > 0"
               :href="route('quantum.export.contract', { contract: selectedContract.id })"
               class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
               style="background: rgba(16,185,129,0.12); color: #059669; border: 1px solid rgba(16,185,129,0.3);">
                <span class="material-symbols-outlined" style="font-size: 18px;">download</span>
                Exportar todo a Excel
            </a>
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
                    <span class="material-symbols-outlined mb-3" style="font-size: 48px; color: var(--color-text-muted);">calculate</span>
                    <p class="font-semibold" style="color: var(--color-text-secondary);">Selecciona un contrato</p>
                </div>

                <template v-else>
                    <!-- Stats -->
                    <div class="grid grid-cols-4 gap-4 mb-6">
                        <div class="p-4 rounded-2xl text-center" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-text-primary);">{{ stats.total }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-text-muted);">Eventos</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center" style="background: var(--color-success-container);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-on-success-container);">{{ stats.documented }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-on-success-container); opacity: 0.8;">Con quantum</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center" style="background: rgba(59,130,246,0.12);">
                            <p class="text-2xl font-extrabold" style="color: #1d4ed8;">{{ stats.reconciled }}</p>
                            <p class="text-xs font-bold mt-1" style="color: #1d4ed8; opacity: 0.8;">Conciliados</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center"
                             :style="stats.pending > 0 ? 'background: var(--color-error-container);' : 'background: var(--color-bg-card); box-shadow: var(--shadow-card);'">
                            <p class="text-2xl font-extrabold"
                               :style="stats.pending > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-text-primary);'">{{ stats.pending }}</p>
                            <p class="text-xs font-bold mt-1"
                               :style="stats.pending > 0 ? 'color: var(--color-on-error-container); opacity: 0.8;' : 'color: var(--color-text-muted);'">Sin documentar</p>
                        </div>
                    </div>

                    <!-- Lista eventos -->
                    <div class="space-y-3">
                        <div v-for="e in events" :key="e.id"
                             class="rounded-2xl p-5 flex items-center gap-4 cursor-pointer transition-all"
                             style="background: var(--color-bg-card); box-shadow: var(--shadow-card);"
                             :onMouseover="r => r.currentTarget.style.background = 'var(--color-bg-hover)'"
                             :onMouseout="r => r.currentTarget.style.background = 'var(--color-bg-card)'"
                             @click="goToQuantum(e)">

                            <!-- Estado icon -->
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                                 :style="e.has_quantum
                                     ? (e.reconciled ? 'background: rgba(59,130,246,0.12);' : 'background: var(--color-success-container);')
                                     : 'background: var(--color-error-container);'">
                                <span class="material-symbols-outlined" style="font-size: 20px;"
                                      :style="e.has_quantum
                                          ? (e.reconciled ? 'color: #1d4ed8;' : 'color: var(--color-on-success-container);')
                                          : 'color: var(--color-error);'">
                                    {{ e.has_quantum ? (e.reconciled ? 'check_circle' : 'receipt_long') : 'pending' }}
                                </span>
                            </div>

                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-sm font-bold" style="color: var(--color-text-primary);">{{ e.type_label }}</span>
                                    <span class="text-xs" style="color: var(--color-text-muted);">{{ e.occurred_at }}</span>
                                </div>
                                <p class="text-xs truncate" style="color: var(--color-text-secondary);">{{ e.description }}</p>
                            </div>

                            <!-- Montos -->
                            <div class="text-right flex-shrink-0">
                                <div class="text-sm font-bold" style="color: var(--color-text-primary);">
                                    {{ fmt(e.quantum_total) }}
                                </div>
                                <div class="text-xs" style="color: var(--color-text-muted);">
                                    quantum
                                </div>
                                <div v-if="e.cost_impact > 0" class="text-xs mt-0.5"
                                     :style="e.reconciled ? 'color: #1d4ed8;' : 'color: var(--color-text-muted);'">
                                    {{ e.reconciled ? '= impacto registrado' : `Impacto: ${fmt(e.cost_impact)}` }}
                                </div>
                            </div>

                            <!-- Flecha -->
                            <span class="material-symbols-outlined flex-shrink-0" style="font-size: 20px; color: var(--color-text-muted);">chevron_right</span>
                        </div>

                        <div v-if="events.length === 0" class="flex flex-col items-center justify-center py-16 rounded-2xl"
                             style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                            <span class="material-symbols-outlined mb-3" style="font-size: 48px; color: var(--color-text-muted);">calculate</span>
                            <p class="font-semibold" style="color: var(--color-text-secondary);">Sin eventos registrados para este contrato</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
