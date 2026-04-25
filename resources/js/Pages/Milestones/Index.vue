<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import MilestoneModal from '@/Components/MilestoneModal.vue'
import CurvaS from '@/Components/CurvaS.vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
    contracts:        { type: Object, required: true },
    selectedContract: { type: Object, default: null },
    milestones:       { type: Object, required: true },
    flash:            { type: Object, default: () => ({}) },
})

const { confirmDelete } = useConfirm()

const showModal        = ref(false)
const editTarget       = ref(null)
const showImport       = ref(false)
const showMsProject    = ref(false)
const showPrimavera    = ref(false)

const importForm    = useForm({ file: null })
const msProjectForm = useForm({ file: null })
const primaveraForm = useForm({ file: null })

const statusConfig = {
    pendiente:   { bg: 'var(--color-bg-elevated)',         text: 'var(--color-text-secondary)' },
    en_progreso: { bg: 'var(--color-primary-container)',   text: 'var(--color-on-primary-container)' },
    completado:  { bg: 'var(--color-success-container)',   text: 'var(--color-on-success-container)' },
    atrasado:    { bg: 'var(--color-error-container)',     text: 'var(--color-on-error-container)' },
}

const stats = computed(() => {
    const data = props.milestones?.data ?? []
    return {
        total:      data.length,
        completado: data.filter(m => m.status === 'completado').length,
        atrasado:   data.filter(m => m.status === 'atrasado').length,
        critico:    data.filter(m => m.is_critical).length,
    }
})

function selectContract(id) {
    router.get(route('milestones.index'), { contract_id: id }, { preserveState: false })
}

function openCreate() {
    editTarget.value = null
    showModal.value  = true
}

function openEdit(milestone) {
    editTarget.value = milestone
    showModal.value  = true
}

function closeModal() {
    showModal.value  = false
    editTarget.value = null
}

async function handleDelete(milestone) {
    const confirmed = await confirmDelete(milestone.name)
    if (!confirmed) return
    router.delete(route('milestones.destroy', {
        contract:  props.selectedContract.id,
        milestone: milestone.id,
    }), {
        preserveScroll: true,
    })
}

function submitImport() {
    if (!importForm.file) return
    importForm.post(route('milestones.import', { contract: props.selectedContract.id }), {
        onSuccess: () => {
            showImport.value = false
            importForm.file  = null
        },
    })
}

function submitMsProject() {
    if (!msProjectForm.file) return
    msProjectForm.post(route('milestones.import-ms-project', { contract: props.selectedContract.id }), {
        onSuccess: () => {
            showMsProject.value = false
            msProjectForm.file  = null
        },
    })
}

function submitPrimavera() {
    if (!primaveraForm.file) return
    primaveraForm.post(route('milestones.import-primavera', { contract: props.selectedContract.id }), {
        onSuccess: () => {
            showPrimavera.value = false
            primaveraForm.file  = null
        },
    })
}

function progressColor(pct) {
    if (pct >= 100) return 'var(--color-success-container)'
    if (pct >= 60)  return 'var(--color-primary)'
    if (pct >= 30)  return 'var(--color-secondary)'
    return 'var(--color-error)'
}
</script>

<template>
    <AppLayout title="Programa de Trabajo">

        <!-- Flash -->
        <div v-if="flash?.success" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-success-container); color: var(--color-on-success-container);">
            <span class="material-symbols-outlined">check_circle</span>
            {{ flash.success }}
        </div>
        <div v-if="flash?.error" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-error-container); color: var(--color-on-error-container);">
            <span class="material-symbols-outlined">error</span>
            {{ flash.error }}
        </div>
        <div v-if="flash?.info" class="flex items-center gap-3 p-4 rounded-xl mb-6"
             style="background: var(--color-primary-container); color: var(--color-on-primary-container);">
            <span class="material-symbols-outlined">info</span>
            {{ flash.info }}
        </div>

        <!-- Encabezado -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">
                    Programa de Trabajo
                </h2>
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    Hitos contractuales por contrato
                </p>
            </div>
            <div v-if="selectedContract" class="flex items-center gap-3">
                <!-- Importar Primavera -->
                <button @click="showPrimavera = !showPrimavera; showMsProject = false; showImport = false"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-full font-bold text-sm transition-all active:scale-95"
                        style="background: var(--color-bg-card); color: var(--color-text-primary); border: 1px solid var(--color-border-variant); cursor: pointer;">
                    <span class="material-symbols-outlined" style="font-size: 16px;">hub</span>
                    Primavera P6
                </button>
                <!-- Importar MS Project -->
                <button @click="showMsProject = !showMsProject; showPrimavera = false; showImport = false"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-full font-bold text-sm transition-all active:scale-95"
                        style="background: var(--color-bg-card); color: var(--color-text-primary); border: 1px solid var(--color-border-variant); cursor: pointer;">
                    <span class="material-symbols-outlined" style="font-size: 16px;">schema</span>
                    MS Project
                </button>
                <!-- Importar Excel -->
                <button @click="showImport = !showImport; showMsProject = false; showPrimavera = false"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-full font-bold text-sm transition-all active:scale-95"
                        style="background: var(--color-bg-card); color: var(--color-text-primary); border: 1px solid var(--color-border-variant); cursor: pointer;">
                    <span class="material-symbols-outlined" style="font-size: 16px;">upload_file</span>
                    Importar Excel
                </button>
                <!-- Nuevo hito -->
                <button @click="openCreate"
                        class="flex items-center gap-2 px-5 py-2.5 rounded-full font-bold text-sm transition-all active:scale-95"
                        style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); cursor: pointer;">
                    <span class="material-symbols-outlined" style="font-size: 16px;">add</span>
                    Nuevo Hito
                </button>
            </div>
        </div>

        <!-- Importar Excel panel -->
        <div v-if="showImport && selectedContract"
             class="p-6 rounded-2xl mb-6"
             style="background: var(--color-bg-card); box-shadow: var(--shadow-card); border: 1px solid var(--color-border-variant);">
            <p class="text-sm font-bold mb-3" style="color: var(--color-text-primary);">Importar hitos desde Excel</p>
            <p class="text-xs mb-4" style="color: var(--color-text-muted);">
                El archivo debe tener las columnas: <strong>nombre</strong>, fecha_planificada, fecha_real, avance_porcentaje, estado, critico, genera_notificacion, descripcion
            </p>
            <form @submit.prevent="submitImport" class="flex items-center gap-3">
                <input type="file" accept=".xlsx,.xls,.csv"
                       @change="e => importForm.file = e.target.files[0]"
                       class="flex-1 text-sm px-4 py-2 rounded-xl"
                       style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                <button type="submit" :disabled="!importForm.file || importForm.processing"
                        class="px-5 py-2 rounded-full text-sm font-bold transition-all disabled:opacity-50"
                        style="background: var(--gradient-primary); color: var(--color-on-primary);">
                    Importar
                </button>
                <button type="button" @click="showImport = false"
                        class="px-4 py-2 rounded-full text-sm font-bold"
                        style="background: var(--color-bg-elevated); color: var(--color-text-secondary);">
                    Cancelar
                </button>
            </form>
        </div>

        <!-- MS Project import panel -->
        <div v-if="showMsProject && selectedContract"
             class="p-6 rounded-2xl mb-6"
             style="background: var(--color-bg-card); box-shadow: var(--shadow-card); border: 1px solid var(--color-border-variant);">
            <div class="flex items-start gap-3 mb-4">
                <span class="material-symbols-outlined" style="color: var(--color-primary); font-size: 22px;">schema</span>
                <div>
                    <p class="text-sm font-bold mb-1" style="color: var(--color-text-primary);">Importar desde Microsoft Project</p>
                    <p class="text-xs" style="color: var(--color-text-muted);">
                        En MS Project: <strong>Archivo → Guardar como → Formato XML de Project (*.xml)</strong>.
                        Los hitos importados se identifican por su UID y no sobreescriben datos contractuales ya registrados.
                    </p>
                    <p v-if="selectedContract.ms_project_imported_at"
                       class="text-xs mt-2" style="color: var(--color-text-muted);">
                        Última importación: <strong>{{ selectedContract.ms_project_imported_at }}</strong>
                    </p>
                </div>
            </div>
            <form @submit.prevent="submitMsProject" class="flex items-center gap-3">
                <input type="file" accept=".xml"
                       @change="e => msProjectForm.file = e.target.files[0]"
                       class="flex-1 text-sm px-4 py-2 rounded-xl"
                       style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                <button type="submit" :disabled="!msProjectForm.file || msProjectForm.processing"
                        class="px-5 py-2 rounded-full text-sm font-bold transition-all disabled:opacity-50"
                        style="background: var(--gradient-primary); color: var(--color-on-primary);">
                    {{ msProjectForm.processing ? 'Enviando…' : 'Procesar' }}
                </button>
                <button type="button" @click="showMsProject = false"
                        class="px-4 py-2 rounded-full text-sm font-bold"
                        style="background: var(--color-bg-elevated); color: var(--color-text-secondary);">
                    Cancelar
                </button>
            </form>
        </div>

        <!-- Primavera P6 import panel -->
        <div v-if="showPrimavera && selectedContract"
             class="p-6 rounded-2xl mb-6"
             style="background: var(--color-bg-card); box-shadow: var(--shadow-card); border: 1px solid var(--color-border-variant);">
            <div class="flex items-start gap-3 mb-4">
                <span class="material-symbols-outlined" style="color: var(--color-secondary); font-size: 22px;">hub</span>
                <div>
                    <p class="text-sm font-bold mb-1" style="color: var(--color-text-primary);">Importar desde Primavera P6</p>
                    <p class="text-xs" style="color: var(--color-text-muted);">
                        Desde P6: <strong>File → Export → Primavera PM (XER)</strong> o <strong>XML</strong>.
                        Las actividades importadas se identifican por su ID y no sobreescriben datos contractuales registrados manualmente.
                    </p>
                    <p v-if="selectedContract.primavera_imported_at"
                       class="text-xs mt-2" style="color: var(--color-text-muted);">
                        Última importación: <strong>{{ selectedContract.primavera_imported_at }}</strong>
                    </p>
                </div>
            </div>
            <form @submit.prevent="submitPrimavera" class="flex items-center gap-3">
                <input type="file" accept=".xer,.xml,.txt"
                       @change="e => primaveraForm.file = e.target.files[0]"
                       class="flex-1 text-sm px-4 py-2 rounded-xl"
                       style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                <button type="submit" :disabled="!primaveraForm.file || primaveraForm.processing"
                        class="px-5 py-2 rounded-full text-sm font-bold transition-all disabled:opacity-50"
                        style="background: var(--gradient-primary); color: var(--color-on-primary);">
                    {{ primaveraForm.processing ? 'Enviando…' : 'Procesar' }}
                </button>
                <button type="button" @click="showPrimavera = false"
                        class="px-4 py-2 rounded-full text-sm font-bold"
                        style="background: var(--color-bg-elevated); color: var(--color-text-secondary);">
                    Cancelar
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            <!-- Panel de contratos -->
            <div class="lg:col-span-1">
                <div class="rounded-2xl overflow-hidden"
                     style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <div class="px-5 py-4"
                         style="border-bottom: 1px solid var(--color-border-variant);">
                        <p class="text-xs font-bold uppercase tracking-widest"
                           style="color: var(--color-text-muted);">Contratos</p>
                    </div>
                    <nav class="p-2">
                        <button
                            v-for="c in contracts.data" :key="c.id"
                            @click="selectContract(c.id)"
                            class="w-full text-left px-4 py-3 rounded-xl mb-1 transition-all text-sm"
                            :style="selectedContract?.id === c.id
                                ? 'background: var(--color-primary-container); color: var(--color-on-primary-container); font-weight: 700;'
                                : 'color: var(--color-text-secondary);'"
                            :onMouseover="e => selectedContract?.id !== c.id && (e.currentTarget.style.background = 'var(--color-bg-hover)')"
                            :onMouseout="e => selectedContract?.id !== c.id && (e.currentTarget.style.background = '')"
                        >
                            <div class="font-semibold truncate">{{ c.name }}</div>
                            <div class="text-xs font-mono opacity-70">{{ c.number }}</div>
                        </button>
                        <div v-if="contracts.data.length === 0"
                             class="px-4 py-8 text-center">
                            <span class="material-symbols-outlined mb-2 block" style="font-size: 32px; color: var(--color-text-muted);">description</span>
                            <p class="text-xs mb-3" style="color: var(--color-text-muted);">Sin contratos</p>
                            <Link :href="route('contracts.create')"
                                  class="text-xs font-bold"
                                  style="color: var(--color-primary);">
                                + Crear contrato
                            </Link>
                        </div>
                    </nav>
                </div>
            </div>

            <!-- Panel principal -->
            <div class="lg:col-span-3">

                <!-- Sin contrato seleccionado -->
                <div v-if="!selectedContract"
                     class="flex flex-col items-center justify-center h-64 rounded-2xl"
                     style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <span class="material-symbols-outlined mb-3" style="font-size: 48px; color: var(--color-text-muted);">calendar_month</span>
                    <p class="font-semibold" style="color: var(--color-text-secondary);">Selecciona un contrato</p>
                </div>

                <template v-else>
                    <!-- Stats del contrato seleccionado -->
                    <div class="grid grid-cols-4 gap-4 mb-6">
                        <div class="p-4 rounded-2xl text-center"
                             style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-text-primary);">{{ stats.total }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-text-muted);">Total</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center"
                             style="background: var(--color-success-container);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-on-success-container);">{{ stats.completado }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-on-success-container); opacity: 0.8;">Completados</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center"
                             style="background: var(--color-error-container);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-on-error-container);">{{ stats.atrasado }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-on-error-container); opacity: 0.8;">Atrasados</p>
                        </div>
                        <div class="p-4 rounded-2xl text-center"
                             style="background: var(--color-primary-container);">
                            <p class="text-2xl font-extrabold" style="color: var(--color-on-primary-container);">{{ stats.critico }}</p>
                            <p class="text-xs font-bold mt-1" style="color: var(--color-on-primary-container); opacity: 0.8;">Críticos</p>
                        </div>
                    </div>

                    <!-- Curva S -->
                    <CurvaS :milestones="milestones.data ?? []" />

                    <!-- Tabla de hitos -->
                    <div class="rounded-2xl overflow-hidden"
                         style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr style="background: var(--color-bg-sidebar);">
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest"
                                            style="color: var(--color-text-secondary);">Hito</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest"
                                            style="color: var(--color-text-secondary);">Fecha plan.</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest"
                                            style="color: var(--color-text-secondary);">Fecha real</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest w-36"
                                            style="color: var(--color-text-secondary);">Avance</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest"
                                            style="color: var(--color-text-secondary);">Estado</th>
                                        <th class="px-5 py-4 text-xs font-bold uppercase tracking-widest text-right"
                                            style="color: var(--color-text-secondary);">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="m in milestones.data" :key="m.id"
                                        class="transition-colors"
                                        style="border-top: 1px solid var(--color-border-variant);"
                                        :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                        :onMouseout="e => e.currentTarget.style.background = ''"
                                    >
                                        <!-- Hito -->
                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-2">
                                                <span v-if="m.is_critical"
                                                      class="material-symbols-outlined text-xs"
                                                      style="color: var(--color-error); font-size: 14px;"
                                                      title="Hito crítico">flag</span>
                                                <span v-if="m.generates_notification"
                                                      class="material-symbols-outlined text-xs"
                                                      style="color: var(--color-primary); font-size: 14px;"
                                                      title="Genera notificación">notifications</span>
                                                <div>
                                                    <div class="text-sm font-semibold"
                                                         style="color: var(--color-text-primary);">{{ m.name }}</div>
                                                    <div v-if="m.description"
                                                         class="text-xs truncate max-w-xs"
                                                         style="color: var(--color-text-muted);">{{ m.description }}</div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Fecha plan -->
                                        <td class="px-5 py-4 text-sm"
                                            :style="m.is_delayed ? 'color: var(--color-error); font-weight: 600;' : 'color: var(--color-text-secondary);'">
                                            {{ m.planned_date }}
                                        </td>

                                        <!-- Fecha real -->
                                        <td class="px-5 py-4 text-sm" style="color: var(--color-text-secondary);">
                                            {{ m.actual_date ?? '—' }}
                                        </td>

                                        <!-- Avance -->
                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 h-1.5 rounded-full"
                                                     style="background: var(--color-bg-elevated);">
                                                    <div class="h-full rounded-full transition-all"
                                                         :style="`width: ${m.progress_percentage}%; background: ${progressColor(m.progress_percentage)};`"></div>
                                                </div>
                                                <span class="text-xs font-bold w-9 text-right"
                                                      style="color: var(--color-text-secondary);">{{ m.progress_percentage }}%</span>
                                            </div>
                                        </td>

                                        <!-- Estado -->
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center gap-1.5 py-0.5 px-2.5 rounded-full text-xs font-bold"
                                                  :style="`background: ${statusConfig[m.status]?.bg}; color: ${statusConfig[m.status]?.text};`">
                                                {{ m.status_label }}
                                            </span>
                                        </td>

                                        <!-- Acciones -->
                                        <td class="px-5 py-4 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <button @click="openEdit(m)"
                                                        class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                                        style="color: var(--color-text-secondary); background: none; border: none; cursor: pointer;"
                                                        :onMouseover="e => e.currentTarget.style.color = 'var(--color-primary)'"
                                                        :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                                                        title="Editar">
                                                    <span class="material-symbols-outlined" style="font-size: 18px;">edit</span>
                                                </button>
                                                <button @click="handleDelete(m)"
                                                        class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                                        style="color: var(--color-text-secondary); background: none; border: none; cursor: pointer;"
                                                        :onMouseover="e => e.currentTarget.style.color = 'var(--color-error)'"
                                                        :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                                                        title="Eliminar">
                                                    <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="milestones.data.length === 0">
                                        <td colspan="6" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center gap-3">
                                                <span class="material-symbols-outlined"
                                                      style="font-size: 48px; color: var(--color-text-muted);">calendar_month</span>
                                                <p class="font-semibold" style="color: var(--color-text-secondary);">Sin hitos registrados</p>
                                                <p class="text-sm" style="color: var(--color-text-muted);">
                                                    Crea hitos manualmente o importa desde Excel
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Modal -->
        <MilestoneModal
            v-if="selectedContract"
            :show="showModal"
            :contract="selectedContract"
            :milestone="editTarget"
            @close="closeModal"
        />

    </AppLayout>
</template>
