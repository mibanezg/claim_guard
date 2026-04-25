<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    contract:         { type: Object, required: true },
    event:            { type: Object, required: true },
    analysis:         { type: Object, default: null },
    milestones:       { type: Array,  default: () => [] },
    delayTypeLabels:  { type: Object, default: () => ({}) },
    methodLabels:     { type: Object, default: () => ({}) },
    flash:            { type: Object, default: () => ({}) },
})

const page  = usePage()
const flash = computed(() => page.props.flash)

const form = useForm({
    affected_milestone_id: props.analysis?.affected_milestone_id ?? null,
    delay_type:            props.analysis?.delay_type            ?? 'compensable',
    is_critical_path:      props.analysis?.is_critical_path      ?? false,
    analysis_method:       props.analysis?.analysis_method       ?? 'as_planned_vs_as_built',
    baseline_date:         props.analysis?.baseline_date         ?? props.event.occurred_at_raw ?? '',
    impacted_date:         props.analysis?.impacted_date         ?? '',
    float_consumed:        props.analysis?.float_consumed        ?? null,
    concurrent_cause:      props.analysis?.concurrent_cause      ?? '',
    narrative:             props.analysis?.narrative             ?? '',
})

// Calcula días de diferencia en tiempo real
const delayDays = computed(() => {
    if (!form.baseline_date || !form.impacted_date) return null
    const b = new Date(form.baseline_date)
    const i = new Date(form.impacted_date)
    if (isNaN(b) || isNaN(i) || i < b) return null
    return Math.round((i - b) / (1000 * 60 * 60 * 24))
})

const DELAY_TYPE_COLORS = {
    compensable:  { bg: 'var(--color-error-container)',   text: 'var(--color-on-error-container)' },
    excusable:    { bg: 'rgba(59,130,246,0.12)',          text: '#1d4ed8' },
    no_excusable: { bg: 'rgba(234,179,8,0.15)',           text: '#854d0e' },
    concurrente:  { bg: 'rgba(168,85,247,0.12)',          text: '#7e22ce' },
}

const METHOD_ICONS = {
    as_planned_vs_as_built: 'compare_arrows',
    time_impact:            'timeline',
    collapsed_but_for:      'undo',
    windows:                'calendar_view_week',
    contemporaneo:          'book',
}

function submit() {
    form.post(route('delay-analysis.save', {
        contract: props.contract.id,
        event:    props.event.id,
    }), {
        preserveScroll: true,
    })
}

function goBack() {
    router.get(route('delay-analysis.index', { contract_id: props.contract.id }))
}
</script>

<template>
    <AppLayout :title="`CPM — ${event.type_label}`">

        <div v-if="flash?.success" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-success-container); color: var(--color-on-success-container);">
            <span class="material-symbols-outlined">check_circle</span>{{ flash.success }}
        </div>
        <div v-if="flash?.error" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-error-container); color: var(--color-on-error-container);">
            <span class="material-symbols-outlined">error</span>{{ flash.error }}
        </div>

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 mb-6 text-sm" style="color: var(--color-text-muted);">
            <button @click="goBack" class="hover:underline" style="color: var(--color-primary);">Análisis CPM</button>
            <span class="material-symbols-outlined" style="font-size: 16px;">chevron_right</span>
            <span style="color: var(--color-text-secondary);">{{ contract.name }}</span>
            <span class="material-symbols-outlined" style="font-size: 16px;">chevron_right</span>
            <span style="color: var(--color-text-primary); font-weight: 600;">{{ event.type_label }}</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Formulario principal -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Tipo de atraso -->
                <div class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <h3 class="text-sm font-bold uppercase tracking-widest mb-4" style="color: var(--color-text-muted);">Clasificación del atraso</h3>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <button v-for="(label, key) in delayTypeLabels" :key="key"
                                type="button"
                                @click="form.delay_type = key"
                                class="p-3 rounded-xl text-left text-sm font-semibold transition-all border-2"
                                :style="form.delay_type === key
                                    ? `background: ${DELAY_TYPE_COLORS[key]?.bg ?? 'var(--color-primary-container)'}; color: ${DELAY_TYPE_COLORS[key]?.text ?? 'var(--color-on-primary-container)'}; border-color: currentColor;`
                                    : 'border-color: var(--color-border-variant); color: var(--color-text-secondary); background: transparent;'">
                            {{ label }}
                        </button>
                    </div>

                    <!-- Ruta crítica toggle -->
                    <label class="flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all"
                           :style="form.is_critical_path ? 'background: rgba(239,68,68,0.08);' : 'background: var(--color-bg-hover);'">
                        <input type="checkbox" v-model="form.is_critical_path" class="w-4 h-4 rounded" style="accent-color: #b91c1c;" />
                        <div>
                            <p class="text-sm font-semibold" :style="form.is_critical_path ? 'color: #b91c1c;' : 'color: var(--color-text-primary);'">
                                Afecta ruta crítica del proyecto
                            </p>
                            <p class="text-xs" style="color: var(--color-text-muted);">
                                El evento generó atraso en el hito de término del contrato
                            </p>
                        </div>
                    </label>
                </div>

                <!-- Método de análisis -->
                <div class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <h3 class="text-sm font-bold uppercase tracking-widest mb-4" style="color: var(--color-text-muted);">Método de análisis</h3>

                    <div class="space-y-2">
                        <label v-for="(label, key) in methodLabels" :key="key"
                               class="flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all"
                               :style="form.analysis_method === key
                                   ? 'background: var(--color-primary-container);'
                                   : 'background: transparent;'"
                               :onMouseover="e => form.analysis_method !== key && (e.currentTarget.style.background = 'var(--color-bg-hover)')"
                               :onMouseout="e => form.analysis_method !== key && (e.currentTarget.style.background = 'transparent')">
                            <input type="radio" :value="key" v-model="form.analysis_method" class="w-4 h-4" style="accent-color: var(--color-primary);" />
                            <span class="material-symbols-outlined flex-shrink-0" style="font-size: 18px; color: var(--color-text-muted);">
                                {{ METHOD_ICONS[key] ?? 'analytics' }}
                            </span>
                            <span class="text-sm font-semibold"
                                  :style="form.analysis_method === key ? 'color: var(--color-on-primary-container);' : 'color: var(--color-text-primary);'">
                                {{ label }}
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Hito afectado y fechas -->
                <div class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <h3 class="text-sm font-bold uppercase tracking-widest mb-4" style="color: var(--color-text-muted);">Impacto en programa</h3>

                    <!-- Hito afectado -->
                    <div class="mb-4">
                        <label class="block text-xs font-bold mb-1" style="color: var(--color-text-secondary);">Hito afectado</label>
                        <select v-model="form.affected_milestone_id"
                                class="w-full px-3 py-2 rounded-xl text-sm"
                                style="background: var(--color-bg-hover); border: 1px solid var(--color-border-variant); color: var(--color-text-primary);">
                            <option :value="null">— Sin hito específico —</option>
                            <option v-for="m in milestones" :key="m.id" :value="m.id">
                                {{ m.name }} — {{ m.planned_date }}{{ m.is_critical ? ' ★' : '' }}
                            </option>
                        </select>
                        <p v-if="form.errors.affected_milestone_id" class="text-xs mt-1" style="color: var(--color-error);">
                            {{ form.errors.affected_milestone_id }}
                        </p>
                    </div>

                    <!-- Fechas baseline / impactada -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold mb-1" style="color: var(--color-text-secondary);">Fecha baseline (as-planned)</label>
                            <input type="date" v-model="form.baseline_date"
                                   class="w-full px-3 py-2 rounded-xl text-sm"
                                   style="background: var(--color-bg-hover); border: 1px solid var(--color-border-variant); color: var(--color-text-primary);" />
                            <p v-if="form.errors.baseline_date" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.baseline_date }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1" style="color: var(--color-text-secondary);">Fecha impactada (as-built / proyectada)</label>
                            <input type="date" v-model="form.impacted_date"
                                   class="w-full px-3 py-2 rounded-xl text-sm"
                                   style="background: var(--color-bg-hover); border: 1px solid var(--color-border-variant); color: var(--color-text-primary);" />
                            <p v-if="form.errors.impacted_date" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.impacted_date }}</p>
                        </div>
                    </div>

                    <!-- Resultado días -->
                    <div v-if="delayDays !== null" class="flex items-center gap-3 p-3 rounded-xl mb-4"
                         :style="delayDays > 0 ? 'background: var(--color-error-container);' : 'background: var(--color-success-container);'">
                        <span class="material-symbols-outlined"
                              :style="delayDays > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-on-success-container);'">
                            schedule
                        </span>
                        <p class="text-sm font-bold"
                           :style="delayDays > 0 ? 'color: var(--color-on-error-container);' : 'color: var(--color-on-success-container);'">
                            {{ delayDays }} días de atraso calculados
                            <span class="font-normal text-xs">
                                (impacto registrado en el evento: {{ event.schedule_impact_days }} días)
                            </span>
                        </p>
                    </div>

                    <!-- Float consumido -->
                    <div>
                        <label class="block text-xs font-bold mb-1" style="color: var(--color-text-secondary);">
                            Float / holgura consumida (días) <span class="font-normal opacity-70">— opcional</span>
                        </label>
                        <input type="number" v-model="form.float_consumed" min="0"
                               placeholder="0"
                               class="w-full px-3 py-2 rounded-xl text-sm"
                               style="background: var(--color-bg-hover); border: 1px solid var(--color-border-variant); color: var(--color-text-primary);" />
                        <p class="text-xs mt-1" style="color: var(--color-text-muted);">
                            Holgura disponible del hito que absorbió parte del atraso
                        </p>
                    </div>
                </div>

                <!-- Causa concurrente -->
                <div class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <h3 class="text-sm font-bold uppercase tracking-widest mb-1" style="color: var(--color-text-muted);">Causa concurrente</h3>
                    <p class="text-xs mb-3" style="color: var(--color-text-muted);">
                        Si existe un atraso paralelo del contratista, documéntalo aquí (relevante para clasificación "concurrente")
                    </p>
                    <textarea v-model="form.concurrent_cause" rows="3"
                              placeholder="Describir causa concurrente si aplica..."
                              class="w-full px-3 py-2 rounded-xl text-sm resize-none"
                              style="background: var(--color-bg-hover); border: 1px solid var(--color-border-variant); color: var(--color-text-primary);"></textarea>
                    <p v-if="form.errors.concurrent_cause" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.concurrent_cause }}</p>
                </div>

                <!-- Narrativa técnica -->
                <div class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <h3 class="text-sm font-bold uppercase tracking-widest mb-1" style="color: var(--color-text-muted);">Narrativa técnica <span class="text-error">*</span></h3>
                    <p class="text-xs mb-3" style="color: var(--color-text-muted);">
                        Descripción detallada de la metodología aplicada, lógica del análisis y conclusión. Mínimo 20 caracteres.
                        Este texto formará parte del expediente de claim.
                    </p>
                    <textarea v-model="form.narrative" rows="8"
                              placeholder="Describir la metodología aplicada, el razonamiento del análisis y la conclusión sobre la imputabilidad y magnitud del atraso..."
                              class="w-full px-3 py-2 rounded-xl text-sm resize-none"
                              style="background: var(--color-bg-hover); border: 1px solid var(--color-border-variant); color: var(--color-text-primary);"></textarea>
                    <div class="flex justify-between mt-1">
                        <p v-if="form.errors.narrative" class="text-xs" style="color: var(--color-error);">{{ form.errors.narrative }}</p>
                        <p class="text-xs ml-auto" style="color: var(--color-text-muted);">{{ form.narrative.length }} chars</p>
                    </div>
                </div>

                <!-- Guardar -->
                <div class="flex items-center justify-between">
                    <button type="button" @click="goBack"
                            class="px-6 py-3 rounded-full text-sm font-semibold transition-all"
                            style="background: var(--color-bg-hover); color: var(--color-text-secondary);">
                        ← Volver
                    </button>
                    <button type="button" @click="submit"
                            :disabled="form.processing"
                            class="px-8 py-3 rounded-full text-sm font-bold transition-all active:scale-95"
                            style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary);">
                        <span v-if="form.processing">Guardando…</span>
                        <span v-else>{{ analysis ? 'Actualizar análisis' : 'Guardar análisis' }}</span>
                    </button>
                </div>
            </div>

            <!-- Panel lateral: resumen del evento + guía -->
            <div class="lg:col-span-1 space-y-5">

                <!-- Datos del evento -->
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--color-text-muted);">Evento contractual</p>

                    <div class="space-y-3">
                        <div>
                            <p class="text-xs" style="color: var(--color-text-muted);">Tipo</p>
                            <p class="text-sm font-semibold" style="color: var(--color-text-primary);">{{ event.type_label }}</p>
                        </div>
                        <div>
                            <p class="text-xs" style="color: var(--color-text-muted);">Fecha de ocurrencia</p>
                            <p class="text-sm font-semibold" style="color: var(--color-text-primary);">{{ event.occurred_at }}</p>
                        </div>
                        <div>
                            <p class="text-xs" style="color: var(--color-text-muted);">Parte responsable</p>
                            <p class="text-sm font-semibold" style="color: var(--color-text-primary);">{{ event.party_label }}</p>
                        </div>
                        <div>
                            <p class="text-xs" style="color: var(--color-text-muted);">Impacto registrado</p>
                            <p class="text-lg font-extrabold" style="color: var(--color-primary);">{{ event.schedule_impact_days }} días</p>
                        </div>
                        <div>
                            <p class="text-xs" style="color: var(--color-text-muted);">Descripción</p>
                            <p class="text-xs" style="color: var(--color-text-secondary);">{{ event.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Guía de métodos -->
                <div class="rounded-2xl p-5" style="background: rgba(59,130,246,0.06); border: 1px solid rgba(59,130,246,0.15);">
                    <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: #1d4ed8;">Guía de métodos CPM</p>
                    <div class="space-y-3 text-xs" style="color: #1e40af;">
                        <div>
                            <p class="font-bold">As-Planned vs As-Built</p>
                            <p class="opacity-80">Compara el programa original con el real. Simple y ampliamente aceptado.</p>
                        </div>
                        <div>
                            <p class="font-bold">Time Impact Analysis (TIA)</p>
                            <p class="opacity-80">Inserta el evento en el programa y mide el corrimiento del hito. Más robusto.</p>
                        </div>
                        <div>
                            <p class="font-bold">Collapsed But-For</p>
                            <p class="opacity-80">Elimina el evento del as-built para ver cómo habría terminado sin él.</p>
                        </div>
                        <div>
                            <p class="font-bold">Windows Analysis</p>
                            <p class="opacity-80">Divide el proyecto en ventanas de tiempo y analiza cada período.</p>
                        </div>
                        <div>
                            <p class="font-bold">Análisis contemporáneo</p>
                            <p class="opacity-80">Basado en los diarios de obra y registros del período afectado.</p>
                        </div>
                    </div>
                </div>

                <!-- Tipos de atraso -->
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--color-text-muted);">Clasificación legal</p>
                    <div class="space-y-2 text-xs">
                        <div class="p-2 rounded-lg" style="background: var(--color-error-container);">
                            <p class="font-bold" style="color: var(--color-on-error-container);">Compensable</p>
                            <p style="color: var(--color-on-error-container); opacity: 0.8;">Imputable al mandante → tiempo + costo</p>
                        </div>
                        <div class="p-2 rounded-lg" style="background: rgba(59,130,246,0.12);">
                            <p class="font-bold" style="color: #1d4ed8;">Excusable</p>
                            <p style="color: #1d4ed8; opacity: 0.8;">Fuerza mayor → solo tiempo, sin costo</p>
                        </div>
                        <div class="p-2 rounded-lg" style="background: rgba(234,179,8,0.15);">
                            <p class="font-bold" style="color: #854d0e;">No excusable</p>
                            <p style="color: #854d0e; opacity: 0.8;">Imputable al contratista → multas posibles</p>
                        </div>
                        <div class="p-2 rounded-lg" style="background: rgba(168,85,247,0.12);">
                            <p class="font-bold" style="color: #7e22ce;">Concurrente</p>
                            <p style="color: #7e22ce; opacity: 0.8;">Ambas partes contribuyen → distribución proporcional</p>
                        </div>
                    </div>
                </div>

                <!-- Estado actual -->
                <div v-if="analysis" class="rounded-2xl p-5" style="background: var(--color-success-container);">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined" style="font-size: 18px; color: var(--color-on-success-container);">check_circle</span>
                        <p class="text-xs font-bold" style="color: var(--color-on-success-container);">Análisis registrado</p>
                    </div>
                    <p class="text-xs" style="color: var(--color-on-success-container); opacity: 0.8;">
                        Este análisis forma parte del expediente de claim. Actualiza cuando haya nueva información.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
