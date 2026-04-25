<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    contract:      { type: Object, required: true },
    report:        { type: Object, default: null },
    weatherLabels: { type: Object, default: () => ({}) },
    contractEvents:{ type: Array,  default: () => [] },
})

const isEdit = computed(() => !!props.report)

const form = useForm({
    report_date:           props.report?.report_date_raw ?? '',
    weather:               props.report?.weather         ?? 'bueno',
    temperature:           props.report?.temperature     ?? '',
    work_executed:         props.report?.work_executed   ?? '',
    personnel_on_site:     props.report?.personnel_on_site?.length
                               ? props.report.personnel_on_site
                               : [],
    equipment_on_site:     props.report?.equipment_on_site?.length
                               ? props.report.equipment_on_site
                               : [],
    materials_received:    props.report?.materials_received    ?? '',
    instructions_received: props.report?.instructions_received ?? '',
    issues_encountered:    props.report?.issues_encountered    ?? '',
    safety_incidents:      props.report?.safety_incidents      ?? '',
    visitors:              props.report?.visitors              ?? '',
    general_notes:         props.report?.general_notes         ?? '',
    event_ids:             props.report?.event_ids             ?? [],
})

// Personal
function addPersonnel() {
    form.personnel_on_site.push({ trade: '', count: 1 })
}
function removePersonnel(i) {
    form.personnel_on_site.splice(i, 1)
}

// Equipos
function addEquipment() {
    form.equipment_on_site.push({ name: '', quantity: 1 })
}
function removeEquipment(i) {
    form.equipment_on_site.splice(i, 1)
}

// Eventos vinculados
function toggleEvent(id) {
    const idx = form.event_ids.indexOf(id)
    if (idx === -1) form.event_ids.push(id)
    else form.event_ids.splice(idx, 1)
}

const totalPersonnel = computed(() =>
    form.personnel_on_site.reduce((sum, p) => sum + (parseInt(p.count) || 0), 0)
)

function submit() {
    if (isEdit.value) {
        form.put(route('daily-reports.update', {
            contract:    props.contract.id,
            dailyReport: props.report.id,
        }))
    } else {
        form.post(route('daily-reports.store', { contract: props.contract.id }))
    }
}

function goBack() {
    router.get(route('daily-reports.index', { contract_id: props.contract.id }))
}

const weatherIcons = {
    bueno:         'sunny',
    nublado:       'cloud',
    lluvia:        'rainy',
    viento_fuerte: 'air',
    nevada:        'ac_unit',
    otro:          'device_thermostat',
}
</script>

<template>
    <AppLayout :title="isEdit ? 'Editar reporte' : 'Nuevo reporte diario'">

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 mb-6 text-sm" style="color: var(--color-text-muted);">
            <button @click="goBack" class="font-semibold hover:underline" style="background: none; border: none; cursor: pointer; color: var(--color-primary);">
                Diario de Obra
            </button>
            <span class="material-symbols-outlined" style="font-size: 16px;">chevron_right</span>
            <span style="color: var(--color-text-secondary);">{{ contract.name }}</span>
            <span class="material-symbols-outlined" style="font-size: 16px;">chevron_right</span>
            <span>{{ isEdit ? report.report_number : 'Nuevo reporte' }}</span>
        </div>

        <form @submit.prevent="submit">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Columna principal -->
                <div class="lg:col-span-2 space-y-5">

                    <!-- Sección: Identificación -->
                    <div class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <h3 class="text-sm font-extrabold uppercase tracking-widest mb-4"
                            style="font-family: var(--font-headline); color: var(--color-text-muted);">Identificación</h3>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Fecha *</label>
                                <input v-model="form.report_date" type="date"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                       style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"
                                       :max="new Date().toISOString().slice(0,10)" />
                                <p v-if="form.errors.report_date" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.report_date }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Temperatura (°C)</label>
                                <input v-model.number="form.temperature" type="number" placeholder="Ej: 18"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                       style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                            </div>
                        </div>

                        <!-- Clima -->
                        <label class="block text-xs font-bold uppercase tracking-wider mb-2"
                               style="color: var(--color-text-secondary);">Condición climática *</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="(label, key) in weatherLabels" :key="key" type="button"
                                    @click="form.weather = key"
                                    class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all"
                                    :style="form.weather === key
                                        ? 'background: var(--color-primary-container); color: var(--color-on-primary-container); border: 2px solid var(--color-primary);'
                                        : 'background: var(--color-bg-elevated); color: var(--color-text-secondary); border: 2px solid transparent;'">
                                <span class="material-symbols-outlined" style="font-size: 16px;">{{ weatherIcons[key] }}</span>
                                {{ label }}
                            </button>
                        </div>
                    </div>

                    <!-- Sección: Trabajo ejecutado -->
                    <div class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <h3 class="text-sm font-extrabold uppercase tracking-widest mb-4"
                            style="font-family: var(--font-headline); color: var(--color-text-muted);">Trabajo ejecutado *</h3>
                        <textarea v-model="form.work_executed" rows="5"
                                  placeholder="Describe las actividades ejecutadas durante la jornada. Sé específico: frente de trabajo, actividades, avance logrado..."
                                  class="w-full px-4 py-3 rounded-xl text-sm border-none outline-none resize-none"
                                  style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body); line-height: 1.6;"></textarea>
                        <p v-if="form.errors.work_executed" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.work_executed }}</p>
                    </div>

                    <!-- Sección: Instrucciones del mandante (clave para claims) -->
                    <div class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card); border-left: 4px solid #3b82f6;">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="material-symbols-outlined" style="font-size: 20px; color: #1d4ed8;">record_voice_over</span>
                            <h3 class="text-sm font-extrabold uppercase tracking-widest"
                                style="font-family: var(--font-headline); color: #1d4ed8;">Instrucciones del mandante</h3>
                        </div>
                        <p class="text-xs mb-3" style="color: var(--color-text-muted);">
                            Registra instrucciones verbales, directivas de fiscalización, cambios de alcance comunicados oralmente.
                            Esta sección es evidencia crítica en caso de claim.
                        </p>
                        <textarea v-model="form.instructions_received" rows="3"
                                  placeholder="Ej: El Inspector Técnico de Obra instruyó pausar los trabajos en el sector norte hasta nuevo aviso..."
                                  class="w-full px-4 py-3 rounded-xl text-sm border-none outline-none resize-none"
                                  style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body); line-height: 1.6;"></textarea>
                    </div>

                    <!-- Sección: Problemas e interferencias -->
                    <div class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <h3 class="text-sm font-extrabold uppercase tracking-widest mb-4"
                            style="font-family: var(--font-headline); color: var(--color-text-muted);">Problemas / Interferencias</h3>
                        <textarea v-model="form.issues_encountered" rows="3"
                                  placeholder="Frentes bloqueados, esperas por diseño, interferencias con otros contratistas, condiciones imprevistas..."
                                  class="w-full px-4 py-3 rounded-xl text-sm border-none outline-none resize-none"
                                  style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body); line-height: 1.6;"></textarea>
                    </div>

                    <!-- Sección: Seguridad -->
                    <div class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <h3 class="text-sm font-extrabold uppercase tracking-widest mb-4"
                            style="font-family: var(--font-headline); color: var(--color-text-muted);">Seguridad</h3>
                        <textarea v-model="form.safety_incidents" rows="2"
                                  placeholder="Incidentes, cuasi-accidentes, observaciones de seguridad. Si no hubo incidentes, puedes dejarlo vacío."
                                  class="w-full px-4 py-3 rounded-xl text-sm border-none outline-none resize-none"
                                  style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"></textarea>
                    </div>

                    <!-- Sección: Otros -->
                    <div class="rounded-2xl p-6" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <h3 class="text-sm font-extrabold uppercase tracking-widest mb-4"
                            style="font-family: var(--font-headline); color: var(--color-text-muted);">Información adicional</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Materiales recibidos</label>
                                <textarea v-model="form.materials_received" rows="2"
                                          placeholder="Guías de despacho, materiales recepcionados..."
                                          class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none resize-none"
                                          style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Visitas</label>
                                <textarea v-model="form.visitors" rows="1"
                                          placeholder="Inspectores, mandante, terceros que visitaron la obra..."
                                          class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none resize-none"
                                          style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Notas generales</label>
                                <textarea v-model="form.general_notes" rows="2"
                                          placeholder="Observaciones adicionales relevantes para el registro..."
                                          class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none resize-none"
                                          style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"></textarea>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Columna lateral -->
                <div class="space-y-5">

                    <!-- Acciones -->
                    <div class="rounded-2xl p-5" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <button type="submit" :disabled="form.processing"
                                class="w-full py-3 rounded-xl font-bold text-sm transition-all active:scale-95 disabled:opacity-60 mb-3"
                                style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                            {{ form.processing ? 'Guardando…' : (isEdit ? 'Guardar cambios' : 'Registrar reporte') }}
                        </button>
                        <button type="button" @click="goBack"
                                class="w-full py-3 rounded-xl font-bold text-sm"
                                style="background: var(--color-bg-elevated); color: var(--color-text-secondary); border: none; cursor: pointer;">
                            Cancelar
                        </button>
                    </div>

                    <!-- Personal en obra -->
                    <div class="rounded-2xl p-5" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-extrabold uppercase tracking-widest" style="color: var(--color-text-muted);">
                                Personal en obra
                                <span v-if="totalPersonnel > 0" class="ml-1 px-1.5 py-0.5 rounded-full text-xs"
                                      style="background: var(--color-primary-container); color: var(--color-on-primary-container);">{{ totalPersonnel }}</span>
                            </h3>
                            <button type="button" @click="addPersonnel"
                                    class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold"
                                    style="background: var(--color-primary-container); color: var(--color-on-primary-container); border: none; cursor: pointer;">
                                <span class="material-symbols-outlined" style="font-size: 14px;">add</span>
                                Agregar
                            </button>
                        </div>

                        <div v-if="form.personnel_on_site.length === 0"
                             class="text-center py-4 text-xs" style="color: var(--color-text-muted);">
                            Sin personal registrado
                        </div>

                        <div v-for="(p, i) in form.personnel_on_site" :key="i" class="flex items-center gap-2 mb-2">
                            <input v-model="p.trade" type="text" placeholder="Oficio"
                                   class="flex-1 px-3 py-2 rounded-xl text-xs border-none outline-none"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                            <input v-model.number="p.count" type="number" min="0" placeholder="N°"
                                   class="w-16 px-3 py-2 rounded-xl text-xs text-center border-none outline-none"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                            <button type="button" @click="removePersonnel(i)"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg"
                                    style="background: none; border: none; cursor: pointer; color: var(--color-text-muted);"
                                    :onMouseover="e => e.currentTarget.style.color = 'var(--color-error)'"
                                    :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-muted)'">
                                <span class="material-symbols-outlined" style="font-size: 16px;">close</span>
                            </button>
                        </div>
                    </div>

                    <!-- Equipos -->
                    <div class="rounded-2xl p-5" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-extrabold uppercase tracking-widest" style="color: var(--color-text-muted);">Equipos en obra</h3>
                            <button type="button" @click="addEquipment"
                                    class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold"
                                    style="background: var(--color-primary-container); color: var(--color-on-primary-container); border: none; cursor: pointer;">
                                <span class="material-symbols-outlined" style="font-size: 14px;">add</span>
                                Agregar
                            </button>
                        </div>

                        <div v-if="form.equipment_on_site.length === 0"
                             class="text-center py-4 text-xs" style="color: var(--color-text-muted);">
                            Sin equipos registrados
                        </div>

                        <div v-for="(eq, i) in form.equipment_on_site" :key="i" class="flex items-center gap-2 mb-2">
                            <input v-model="eq.name" type="text" placeholder="Equipo"
                                   class="flex-1 px-3 py-2 rounded-xl text-xs border-none outline-none"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                            <input v-model.number="eq.quantity" type="number" min="0" placeholder="N°"
                                   class="w-16 px-3 py-2 rounded-xl text-xs text-center border-none outline-none"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                            <button type="button" @click="removeEquipment(i)"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg"
                                    style="background: none; border: none; cursor: pointer; color: var(--color-text-muted);"
                                    :onMouseover="e => e.currentTarget.style.color = 'var(--color-error)'"
                                    :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-muted)'">
                                <span class="material-symbols-outlined" style="font-size: 16px;">close</span>
                            </button>
                        </div>
                    </div>

                    <!-- Eventos vinculados -->
                    <div v-if="contractEvents.length > 0" class="rounded-2xl p-5" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <h3 class="text-xs font-extrabold uppercase tracking-widest mb-3" style="color: var(--color-text-muted);">Eventos vinculados</h3>
                        <p class="text-xs mb-3" style="color: var(--color-text-muted);">Marca los eventos contractuales documentados en este reporte</p>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            <label v-for="ev in contractEvents" :key="ev.id"
                                   class="flex items-start gap-2 p-2.5 rounded-xl cursor-pointer transition-colors"
                                   :style="form.event_ids.includes(ev.id)
                                       ? 'background: var(--color-primary-container);'
                                       : 'background: var(--color-bg-elevated);'"
                                   @click="toggleEvent(ev.id)">
                                <span class="material-symbols-outlined mt-0.5 flex-shrink-0"
                                      style="font-size: 16px;"
                                      :style="form.event_ids.includes(ev.id) ? 'color: var(--color-primary);' : 'color: var(--color-text-muted);'">
                                    {{ form.event_ids.includes(ev.id) ? 'check_box' : 'check_box_outline_blank' }}
                                </span>
                                <div>
                                    <p class="text-xs font-semibold" style="color: var(--color-text-primary);">{{ ev.type_label }}</p>
                                    <p class="text-xs" style="color: var(--color-text-muted);">{{ ev.occurred_at }} — {{ ev.description?.slice(0, 60) }}{{ ev.description?.length > 60 ? '…' : '' }}</p>
                                </div>
                            </label>
                        </div>
                    </div>

                </div>
            </div>
        </form>

    </AppLayout>
</template>
