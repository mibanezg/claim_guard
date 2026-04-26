<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, usePage, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
    contract:          Object,
    assignedUsers:     Array,
    availableUsers:    Array,
    roleOptions:       Array,
    corpusDocs:        Array,
    corpusCategories:  Array,
    priceItems:        { type: Array, default: () => [] },
    categoryLabels:    { type: Object, default: () => ({}) },
})

const page  = usePage()
const flash = computed(() => page.props.flash)
const { confirmDelete, confirmDanger } = useConfirm()

const can = computed(() => {
    const perms = page.props.auth?.user?.permissions ?? []
    return {
        edit: perms.includes('contracts.edit'),
    }
})

const statusConfig = {
    borrador:   { bg: 'var(--color-bg-elevated)',       text: 'var(--color-text-secondary)' },
    vigente:    { bg: 'var(--color-success-container)', text: 'var(--color-on-success-container)' },
    suspendido: { bg: 'var(--color-error-container)',   text: 'var(--color-on-error-container)' },
    terminado:  { bg: 'var(--color-bg-elevated)',       text: 'var(--color-text-muted)' },
    en_disputa: { bg: 'var(--color-primary-container)', text: 'var(--color-on-primary-container)' },
}

const transitionLabels = {
    borrador:   'Borrador',
    vigente:    'Marcar vigente',
    suspendido: 'Suspender',
    terminado:  'Marcar terminado',
    en_disputa: 'Marcar en disputa',
}

function changeStatus(newStatus) {
    router.patch(route('contracts.changeStatus', props.contract.id), { status: newStatus })
}

function fmt(amount, currency) {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency', currency,
        minimumFractionDigits: currency === 'CLP' ? 0 : 2,
    }).format(amount)
}

// Upload PDF
const pdfForm     = useForm({ pdf: null })
const pdfInput    = ref(null)
const isDragging  = ref(false)

function onFileSelected(e) {
    const file = e.target.files?.[0]
    if (file) pdfForm.pdf = file
}

function onDrop(e) {
    isDragging.value = false
    const file = e.dataTransfer.files?.[0]
    if (file && file.type === 'application/pdf') pdfForm.pdf = file
}

function uploadPdf() {
    pdfForm.post(route('contracts.upload-pdf', props.contract.id), {
        forceFormData: true,
        onSuccess: () => { pdfForm.pdf = null; if (pdfInput.value) pdfInput.value.value = '' },
    })
}

async function removePdf() {
    if (!await confirmDanger('¿Eliminar documento base?', 'La IA ya no tendrá acceso al texto del contrato.')) return
    router.delete(route('contracts.remove-pdf', props.contract.id))
}

// Cuerpo contractual
const corpusForm    = useForm({ pdf: null, category: 'contrato_base', name: '', precedence_order: 0 })
const corpusInput   = ref(null)
const isDraggingCorpus = ref(false)

function onCorpusFileSelected(e) {
    const file = e.target.files?.[0]
    if (file) corpusForm.pdf = file
}

function onCorpusDrop(e) {
    isDraggingCorpus.value = false
    const file = e.dataTransfer.files?.[0]
    if (file && file.type === 'application/pdf') corpusForm.pdf = file
}

function uploadCorpusDoc() {
    corpusForm.post(route('contracts.corpus.upload', props.contract.id), {
        forceFormData: true,
        onSuccess: () => { corpusForm.pdf = null; corpusForm.name = ''; if (corpusInput.value) corpusInput.value.value = '' },
    })
}

function removeCorpusDoc(docId) {
    router.delete(route('contracts.corpus.remove', [props.contract.id, docId]))
}

const categoryColors = {
    contrato_base:    { bg: 'rgba(59,130,246,0.1)',  text: '#3b82f6' },
    bases_tecnicas:   { bg: 'rgba(16,185,129,0.1)',  text: '#10b981' },
    bases_admin:      { bg: 'rgba(245,158,11,0.1)',  text: '#f59e0b' },
    anexo:            { bg: 'rgba(139,92,246,0.1)',  text: '#8b5cf6' },
    addenda:          { bg: 'rgba(236,72,153,0.1)',  text: '#ec4899' },
    especificaciones: { bg: 'rgba(20,184,166,0.1)',  text: '#14b8a6' },
    otro:             { bg: 'rgba(107,114,128,0.1)', text: '#6b7280' },
}

// Equipo del contrato
const assignForm = useForm({ user_id: '', role: '' })

function assignUser() {
    assignForm.post(route('contracts.users.assign', props.contract.id), {
        onSuccess: () => { assignForm.reset() },
    })
}

function removeUser(contractUserId) {
    router.delete(route('contracts.users.remove', [props.contract.id, contractUserId]))
}

const roleColors = {
    tenant_admin:    { bg: 'rgba(124,58,237,0.1)',  text: '#7c3aed' },
    contract_admin:  { bg: 'rgba(59,130,246,0.1)',  text: '#3b82f6' },
    field_engineer:  { bg: 'rgba(16,185,129,0.1)',  text: '#10b981' },
    manager:         { bg: 'rgba(245,158,11,0.1)',  text: '#f59e0b' },
    legal:           { bg: 'rgba(139,92,246,0.1)',  text: '#8b5cf6' },
    counterpart:     { bg: 'rgba(239,68,68,0.1)',   text: '#ef4444' },
}

// Cuadro de Precios Unitarios (CPU)
const showCpuForm  = ref(false)
const cpuImportInput = ref(null)
const cpuImportForm  = useForm({ file: null })
const cpuForm = useForm({
    code:        '',
    description: '',
    unit:        '',
    unit_cost:   0,
    category:    'mano_obra',
    is_active:   true,
})

function submitCpuItem() {
    cpuForm.post(route('price-items.store', props.contract.id), {
        onSuccess: () => { cpuForm.reset(); showCpuForm.value = false },
    })
}

async function deleteCpuItem(itemId) {
    if (!await confirmDelete('este ítem del catálogo')) return
    router.delete(route('price-items.destroy', [props.contract.id, itemId]))
}

function importCpu() {
    cpuImportForm.post(route('price-items.import', props.contract.id), {
        forceFormData: true,
        onSuccess: () => { cpuImportForm.file = null; if (cpuImportInput.value) cpuImportInput.value.value = '' },
    })
}

function onCpuFileSelected(e) {
    const file = e.target.files?.[0]
    if (file) cpuImportForm.file = file
}

function fmtCost(centavos, currency) {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency', currency: currency ?? 'CLP',
        minimumFractionDigits: currency === 'CLP' ? 0 : 2,
    }).format(centavos / 100)
}
</script>

<template>
    <AppLayout :title="`Contrato ${contract.number}`">

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

        <!-- Encabezado -->
        <div class="flex items-start justify-between mb-8">
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <span
                        class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-xs font-bold"
                        :style="`background: ${statusConfig[contract.status]?.bg}; color: ${statusConfig[contract.status]?.text};`"
                    >
                        <span class="w-1.5 h-1.5 rounded-full" :style="`background: ${statusConfig[contract.status]?.text};`"></span>
                        {{ contract.status_label }}
                    </span>
                    <span class="text-sm font-mono" style="color: var(--color-text-muted);">{{ contract.number }}</span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">
                    {{ contract.name }}
                </h1>
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    {{ contract.type_label }} · Creado {{ contract.created_at }}
                </p>
            </div>

            <!-- Acciones -->
            <div class="flex items-center gap-3">
                <div v-if="can.edit && contract.allowed_transitions.length > 0" class="flex items-center gap-2">
                    <button
                        v-for="t in contract.allowed_transitions" :key="t"
                        class="px-4 py-2 rounded-full text-sm font-bold transition-all active:scale-95"
                        style="background: var(--color-bg-card); color: var(--color-text-primary); border: 1px solid var(--color-border-variant); cursor: pointer;"
                        @click="changeStatus(t)"
                    >
                        {{ transitionLabels[t] ?? t }}
                    </button>
                </div>
                <Link
                    :href="route('contracts.claim-status', contract.id)"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-full font-bold text-sm transition-all active:scale-95"
                    style="background: var(--color-bg-card); color: var(--color-text-primary); border: 1px solid var(--color-border-variant); box-shadow: var(--shadow-card);"
                >
                    <span class="material-symbols-outlined" style="font-size: 16px;">fact_check</span>
                    Estado del Claim
                </Link>
                <Link v-if="can.edit"
                    :href="route('contracts.edit', contract.id)"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-full font-bold text-sm transition-all active:scale-95"
                    style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary);"
                >
                    <span class="material-symbols-outlined" style="font-size: 16px;">edit</span>
                    Editar
                </Link>
            </div>
        </div>

        <!-- Grid de información -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

            <!-- Montos -->
            <div class="p-6 rounded-2xl" style="background: var(--gradient-primary); box-shadow: var(--shadow-primary);">
                <p class="text-xs font-bold uppercase tracking-wider mb-1" style="color: var(--color-on-primary); opacity: 0.7;">Monto vigente</p>
                <p class="text-2xl font-extrabold" style="color: var(--color-on-primary);">{{ fmt(contract.current_amount, contract.currency) }}</p>
                <p v-if="contract.current_amount !== contract.original_amount"
                   class="text-xs mt-1" style="color: var(--color-on-primary); opacity: 0.7;">
                    Original: {{ fmt(contract.original_amount, contract.currency) }}
                </p>
            </div>

            <!-- Fechas -->
            <div class="p-6 rounded-2xl" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                <p class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--color-text-muted);">Plazos</p>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span style="color: var(--color-text-secondary);">Inicio contractual</span>
                        <span class="font-semibold" style="color: var(--color-text-primary);">{{ contract.contractual_start_date }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--color-text-secondary);">Término contractual</span>
                        <span class="font-semibold" style="color: var(--color-text-primary);">{{ contract.contractual_end_date }}</span>
                    </div>
                    <div v-if="contract.projected_end_date" class="flex justify-between">
                        <span style="color: var(--color-text-secondary);">Término proyectado</span>
                        <span class="font-semibold" style="color: var(--color-text-primary);">{{ contract.projected_end_date }}</span>
                    </div>
                </div>
            </div>

            <!-- Partes -->
            <div class="p-6 rounded-2xl" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                <p class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--color-text-muted);">Partes</p>
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="text-xs" style="color: var(--color-text-muted);">Mandante</p>
                        <p class="font-semibold" style="color: var(--color-text-primary);">{{ contract.mandante?.name }}</p>
                    </div>
                    <div>
                        <p class="text-xs" style="color: var(--color-text-muted);">Contratista</p>
                        <p class="font-semibold" style="color: var(--color-text-primary);">{{ contract.contractor?.name }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cuerpo contractual (BT, BAE, Anexos, etc.) -->
        <div class="p-6 rounded-2xl mb-6"
             style="background: var(--color-bg-card); box-shadow: var(--shadow-card); border: 1px solid var(--color-border-variant);">
            <div class="flex items-start justify-between mb-5">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined" style="color: var(--color-primary); font-variation-settings: 'FILL' 1;">folder_open</span>
                    <div>
                        <h3 class="font-bold" style="color: var(--color-text-primary); font-family: var(--font-headline);">
                            Cuerpo contractual
                        </h3>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">
                            Contrato base, Bases Técnicas, BAE, Anexos y más — la IA los usa como contexto
                        </p>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold"
                      style="background: var(--color-primary-container); color: var(--color-on-primary-container);">
                    {{ corpusDocs.length }} {{ corpusDocs.length === 1 ? 'documento' : 'documentos' }}
                </span>
            </div>

            <!-- Lista de documentos cargados -->
            <div v-if="corpusDocs.length > 0" class="space-y-2 mb-5">
                <div v-for="doc in corpusDocs" :key="doc.id"
                     class="flex items-center justify-between px-4 py-3 rounded-xl"
                     style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant);">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined" style="font-size: 20px; color: var(--color-text-muted); font-variation-settings: 'FILL' 1;">picture_as_pdf</span>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold" style="color: var(--color-text-primary);">{{ doc.name }}</p>
                                <span v-if="doc.has_text" class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold"
                                      style="background: rgba(34,197,94,0.1); color: #22c55e;">
                                    <span class="material-symbols-outlined" style="font-size: 12px; font-variation-settings: 'FILL' 1;">check_circle</span>
                                    Texto extraído
                                </span>
                                <span v-else class="px-2 py-0.5 rounded-full text-xs font-bold"
                                      style="background: rgba(239,68,68,0.1); color: #ef4444;">
                                    Sin texto
                                </span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold"
                                      :style="`background: ${categoryColors[doc.category]?.bg ?? 'rgba(0,0,0,0.06)'}; color: ${categoryColors[doc.category]?.text ?? 'inherit'};`">
                                    {{ doc.category_label }}
                                </span>
                                <span class="text-xs" style="color: var(--color-text-muted);">{{ doc.file_size_human }}</span>
                            </div>
                        </div>
                    </div>
                    <button @click="removeCorpusDoc(doc.id)"
                            class="w-7 h-7 flex items-center justify-center rounded-lg transition-all flex-shrink-0"
                            style="background: none; border: none; cursor: pointer; color: var(--color-text-muted);"
                            title="Eliminar documento"
                            :onMouseover="e => { e.currentTarget.style.background = 'rgba(239,68,68,0.1)'; e.currentTarget.style.color = '#ef4444' }"
                            :onMouseout="e => { e.currentTarget.style.background = ''; e.currentTarget.style.color = 'var(--color-text-muted)' }">
                        <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                    </button>
                </div>
            </div>

            <!-- Zona de carga de nuevo documento -->
            <div class="pt-4" :style="corpusDocs.length > 0 ? 'border-top: 1px solid var(--color-border-variant);' : ''">
                <p class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--color-text-muted);">Agregar documento</p>

                <!-- Tipo + Nombre -->
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color: var(--color-text-muted);">Tipo de documento</label>
                        <select v-model="corpusForm.category"
                                class="w-full px-3 py-2 rounded-xl text-sm"
                                style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant); color: var(--color-text-primary); outline: none;">
                            <option v-for="cat in corpusCategories" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color: var(--color-text-muted);">Nombre (opcional)</label>
                        <input v-model="corpusForm.name" type="text" placeholder="Ej: Anexo A — Especificaciones"
                               class="w-full px-3 py-2 rounded-xl text-sm"
                               style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant); color: var(--color-text-primary); outline: none;" />
                    </div>
                </div>

                <!-- Drop zone -->
                <div class="rounded-2xl border-2 border-dashed p-6 text-center transition-all cursor-pointer"
                     :style="isDraggingCorpus
                         ? 'border-color: var(--color-primary); background: var(--color-primary-container);'
                         : 'border-color: var(--color-border-variant); background: var(--color-bg-elevated);'"
                     @dragover.prevent="isDraggingCorpus = true"
                     @dragleave="isDraggingCorpus = false"
                     @drop.prevent="onCorpusDrop"
                     @click="corpusInput?.click()">
                    <span class="material-symbols-outlined mb-2 block" style="font-size: 32px; color: var(--color-text-muted);">upload_file</span>
                    <p class="text-sm font-semibold" style="color: var(--color-text-primary);">Arrastra el PDF aquí o haz clic</p>
                    <p class="text-xs mt-1" style="color: var(--color-text-muted);">PDF · máx. 50 MB · digital o escaneado</p>
                </div>
                <input ref="corpusInput" type="file" accept=".pdf" class="hidden" @change="onCorpusFileSelected" />

                <!-- Archivo seleccionado -->
                <div v-if="corpusForm.pdf" class="flex items-center justify-between mt-3 px-4 py-3 rounded-xl"
                     style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant);">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined" style="color: var(--color-primary);">picture_as_pdf</span>
                        <span class="text-sm font-medium" style="color: var(--color-text-primary);">{{ corpusForm.pdf.name }}</span>
                        <span class="text-xs" style="color: var(--color-text-muted);">{{ (corpusForm.pdf.size / 1024 / 1024).toFixed(1) }} MB</span>
                    </div>
                    <button @click="uploadCorpusDoc"
                            :disabled="corpusForm.processing"
                            class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-bold transition-all active:scale-95 disabled:opacity-60"
                            style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer; box-shadow: var(--shadow-primary);">
                        <span class="material-symbols-outlined" style="font-size: 16px;">cloud_upload</span>
                        {{ corpusForm.processing ? 'Procesando...' : 'Cargar y extraer texto' }}
                    </button>
                </div>
                <p v-if="corpusForm.errors.pdf" class="text-xs mt-1" style="color: #ef4444;">{{ corpusForm.errors.pdf }}</p>
            </div>
        </div>

        <!-- Panel IA: documento base del contrato -->
        <div class="p-6 rounded-2xl mb-6"
             style="background: var(--color-bg-card); box-shadow: var(--shadow-card); border: 1px solid var(--color-border-variant);">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined" style="color: var(--color-secondary, #7c3aed); font-variation-settings: 'FILL' 1;">auto_awesome</span>
                    <div>
                        <h3 class="font-bold" style="color: var(--color-text-primary); font-family: var(--font-headline);">
                            Documento base para IA
                        </h3>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">
                            El PDF del contrato que la IA usará como contexto al redactar cartas
                        </p>
                    </div>
                </div>

                <!-- Estado: cargado -->
                <div v-if="contract.has_contract_text" class="flex items-center gap-2">
                    <span class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold"
                          style="background: rgba(34,197,94,0.1); color: #22c55e;">
                        <span class="material-symbols-outlined" style="font-size: 14px; font-variation-settings: 'FILL' 1;">check_circle</span>
                        Cargado
                    </span>
                    <button @click="removePdf"
                            class="w-8 h-8 flex items-center justify-center rounded-lg transition-all"
                            style="color: var(--color-text-muted); background: none; border: none; cursor: pointer;"
                            title="Eliminar documento"
                            :onMouseover="e => { e.currentTarget.style.background = 'rgba(239,68,68,0.1)'; e.currentTarget.style.color = '#ef4444' }"
                            :onMouseout="e => { e.currentTarget.style.background = ''; e.currentTarget.style.color = 'var(--color-text-muted)' }">
                        <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                    </button>
                </div>
            </div>

            <!-- Documento cargado: muestra info -->
            <div v-if="contract.has_contract_text"
                 class="flex items-center gap-3 p-4 rounded-xl"
                 style="background: rgba(124,58,237,0.06); border: 1px solid rgba(124,58,237,0.15);">
                <span class="material-symbols-outlined" style="color: var(--color-secondary, #7c3aed); font-variation-settings: 'FILL' 1;">picture_as_pdf</span>
                <div>
                    <p class="text-sm font-semibold" style="color: var(--color-text-primary);">{{ contract.contract_pdf_name }}</p>
                    <p class="text-xs" style="color: var(--color-text-muted);">
                        La IA referenciará este documento al generar borradores de cartas
                    </p>
                </div>
            </div>

            <!-- Sin documento: zona de carga -->
            <div v-else>
                <div
                    class="rounded-2xl border-2 border-dashed p-8 text-center transition-all cursor-pointer"
                    :style="isDragging
                        ? 'border-color: var(--color-secondary, #7c3aed); background: rgba(124,58,237,0.06);'
                        : 'border-color: var(--color-border-variant); background: var(--color-bg-elevated);'"
                    @dragover.prevent="isDragging = true"
                    @dragleave="isDragging = false"
                    @drop.prevent="onDrop"
                    @click="pdfInput?.click()"
                >
                    <span class="material-symbols-outlined mb-3 block" style="font-size: 40px; color: var(--color-text-muted);">upload_file</span>
                    <p class="text-sm font-semibold mb-1" style="color: var(--color-text-primary);">
                        Arrastra el PDF del contrato aquí
                    </p>
                    <p class="text-xs" style="color: var(--color-text-muted);">o haz clic para seleccionar · PDF · máx. 50 MB</p>
                </div>

                <input ref="pdfInput" type="file" accept=".pdf" class="hidden" @change="onFileSelected" />

                <!-- Archivo seleccionado -->
                <div v-if="pdfForm.pdf" class="flex items-center justify-between mt-4 px-4 py-3 rounded-xl"
                     style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant);">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined" style="color: var(--color-secondary, #7c3aed);">picture_as_pdf</span>
                        <span class="text-sm font-medium" style="color: var(--color-text-primary);">{{ pdfForm.pdf.name }}</span>
                        <span class="text-xs" style="color: var(--color-text-muted);">{{ (pdfForm.pdf.size / 1024 / 1024).toFixed(1) }} MB</span>
                    </div>
                    <button @click="uploadPdf"
                            :disabled="pdfForm.processing"
                            class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-bold transition-all active:scale-95 disabled:opacity-60"
                            style="background: var(--color-secondary, #7c3aed); color: white; border: none; cursor: pointer; box-shadow: 0 4px 14px rgba(124,58,237,0.3);">
                        <span class="material-symbols-outlined" style="font-size: 16px;">auto_awesome</span>
                        {{ pdfForm.processing ? 'Procesando (puede tardar ~30s)...' : 'Cargar y procesar' }}
                    </button>
                </div>

                <div v-if="pdfForm.errors.pdf" class="mt-2 text-xs" style="color: #ef4444;">{{ pdfForm.errors.pdf }}</div>

                <p class="text-xs mt-3" style="color: var(--color-text-muted);">
                    PDFs digitales → extracción directa (gratis). PDFs escaneados → OCR automático vía IA (requiere Anthropic configurado).
                </p>
            </div>
        </div>

        <!-- Equipo del contrato -->
        <div class="p-6 rounded-2xl mb-6"
             style="background: var(--color-bg-card); box-shadow: var(--shadow-card); border: 1px solid var(--color-border-variant);">
            <div class="flex items-center gap-3 mb-5">
                <span class="material-symbols-outlined" style="color: var(--color-primary); font-variation-settings: 'FILL' 1;">group</span>
                <div>
                    <h3 class="font-bold" style="color: var(--color-text-primary); font-family: var(--font-headline);">
                        Equipo del contrato
                    </h3>
                    <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">
                        Usuarios con acceso a este contrato y sus roles
                    </p>
                </div>
            </div>

            <!-- Lista de usuarios asignados -->
            <div v-if="assignedUsers.length > 0" class="space-y-2 mb-5">
                <div v-for="cu in assignedUsers" :key="cu.id"
                     class="flex items-center justify-between px-4 py-3 rounded-xl"
                     style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant);">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
                             style="background: var(--color-primary-container); color: var(--color-on-primary-container);">
                            {{ cu.name.charAt(0).toUpperCase() }}
                        </div>
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--color-text-primary);">{{ cu.name }}</p>
                            <p class="text-xs" style="color: var(--color-text-muted);">{{ cu.email }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold"
                              :style="`background: ${roleColors[cu.role]?.bg ?? 'rgba(0,0,0,0.06)'}; color: ${roleColors[cu.role]?.text ?? 'inherit'};`">
                            {{ cu.role_label }}
                        </span>
                        <button @click="removeUser(cu.id)"
                                class="w-7 h-7 flex items-center justify-center rounded-lg transition-all"
                                style="background: none; border: none; cursor: pointer; color: var(--color-text-muted);"
                                title="Remover del contrato"
                                :onMouseover="e => { e.currentTarget.style.background = 'rgba(239,68,68,0.1)'; e.currentTarget.style.color = '#ef4444' }"
                                :onMouseout="e => { e.currentTarget.style.background = ''; e.currentTarget.style.color = 'var(--color-text-muted)' }">
                            <span class="material-symbols-outlined" style="font-size: 18px;">person_remove</span>
                        </button>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm mb-5" style="color: var(--color-text-muted);">
                Aún no hay usuarios asignados a este contrato.
            </p>

            <!-- Agregar usuario -->
            <div v-if="availableUsers.length > 0"
                 class="flex flex-wrap items-end gap-3 pt-4"
                 style="border-top: 1px solid var(--color-border-variant);">
                <div class="flex-1 min-w-48">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--color-text-muted);">Usuario</label>
                    <select v-model="assignForm.user_id"
                            class="w-full px-3 py-2 rounded-xl text-sm"
                            style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant); color: var(--color-text-primary); outline: none;">
                        <option value="">Seleccionar usuario…</option>
                        <option v-for="u in availableUsers" :key="u.id" :value="u.id">
                            {{ u.name }} ({{ u.email }})
                        </option>
                    </select>
                    <p v-if="assignForm.errors.user_id" class="text-xs mt-1" style="color: #ef4444;">{{ assignForm.errors.user_id }}</p>
                </div>
                <div class="flex-1 min-w-40">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--color-text-muted);">Rol en el contrato</label>
                    <select v-model="assignForm.role"
                            class="w-full px-3 py-2 rounded-xl text-sm"
                            style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant); color: var(--color-text-primary); outline: none;">
                        <option value="">Seleccionar rol…</option>
                        <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                    <p v-if="assignForm.errors.role" class="text-xs mt-1" style="color: #ef4444;">{{ assignForm.errors.role }}</p>
                </div>
                <button @click="assignUser"
                        :disabled="!assignForm.user_id || !assignForm.role || assignForm.processing"
                        class="flex items-center gap-2 px-5 py-2 rounded-full text-sm font-bold transition-all active:scale-95 disabled:opacity-40"
                        style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer; box-shadow: var(--shadow-primary); white-space: nowrap;">
                    <span class="material-symbols-outlined" style="font-size: 16px;">person_add</span>
                    Asignar
                </button>
            </div>
            <p v-else-if="assignedUsers.length > 0" class="text-xs pt-4" style="color: var(--color-text-muted); border-top: 1px solid var(--color-border-variant);">
                Todos los usuarios del tenant ya están asignados a este contrato.
            </p>
        </div>

        <!-- Descripción y marco legal -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div v-if="contract.description" class="p-6 rounded-2xl" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                <p class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--color-text-muted);">Descripción</p>
                <p class="text-sm leading-relaxed" style="color: var(--color-text-secondary);">{{ contract.description }}</p>
            </div>
            <div v-if="contract.applicable_law || contract.jurisdiction" class="p-6 rounded-2xl" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                <p class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--color-text-muted);">Marco legal</p>
                <div class="space-y-2 text-sm">
                    <div v-if="contract.applicable_law" class="flex justify-between">
                        <span style="color: var(--color-text-secondary);">Ley aplicable</span>
                        <span class="font-semibold" style="color: var(--color-text-primary);">{{ contract.applicable_law }}</span>
                    </div>
                    <div v-if="contract.jurisdiction" class="flex justify-between">
                        <span style="color: var(--color-text-secondary);">Jurisdicción</span>
                        <span class="font-semibold" style="color: var(--color-text-primary);">{{ contract.jurisdiction }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--color-text-secondary);">Notificación</span>
                        <span class="font-semibold" style="color: var(--color-text-primary);">{{ contract.notification_days }} días hábiles</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- CPU — Cuadro de Precios Unitarios -->
        <div class="mt-6 p-6 rounded-2xl" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-widest" style="color: var(--color-text-muted);">Cuadro de Precios Unitarios (CPU)</h3>
                    <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">Catálogo de precios base para el cálculo de quantum por evento</p>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Descargar plantilla -->
                    <a :href="route('price-items.template', contract.id)"
                       class="flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold transition-all active:scale-95"
                       style="background: rgba(107,114,128,0.1); color: var(--color-text-secondary); border: 1px solid var(--color-border-variant);">
                        <span class="material-symbols-outlined" style="font-size: 16px;">download</span>
                        Plantilla
                    </a>
                    <!-- Import Excel -->
                    <label class="flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold cursor-pointer transition-all active:scale-95"
                           style="background: rgba(16,185,129,0.12); color: #059669; border: 1px solid rgba(16,185,129,0.3);">
                        <span class="material-symbols-outlined" style="font-size: 16px;">upload_file</span>
                        Importar Excel
                        <input ref="cpuImportInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onCpuFileSelected" />
                    </label>
                    <button v-if="cpuImportForm.file" @click="importCpu"
                            :disabled="cpuImportForm.processing"
                            class="flex items-center gap-1 px-4 py-2 rounded-full text-xs font-bold transition-all active:scale-95"
                            style="background: #059669; color: white;">
                        <span class="material-symbols-outlined" style="font-size: 14px;">check</span>
                        {{ cpuImportForm.file.name }}
                    </button>
                    <button @click="showCpuForm = !showCpuForm"
                            class="flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold transition-all active:scale-95"
                            style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary);">
                        <span class="material-symbols-outlined" style="font-size: 16px;">{{ showCpuForm ? 'close' : 'add' }}</span>
                        {{ showCpuForm ? 'Cancelar' : 'Agregar ítem' }}
                    </button>
                </div>
            </div>

            <!-- Formulario agregar ítem -->
            <div v-if="showCpuForm" class="mb-5 p-4 rounded-xl" style="background: var(--color-bg-hover);">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-bold mb-1" style="color: var(--color-text-muted);">Código</label>
                        <input v-model="cpuForm.code" type="text" placeholder="MO-001"
                               class="w-full px-3 py-2 rounded-xl text-sm"
                               style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant); color: var(--color-text-primary);" />
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-bold mb-1" style="color: var(--color-text-muted);">Descripción *</label>
                        <input v-model="cpuForm.description" type="text" placeholder="Operador grúa horquilla"
                               class="w-full px-3 py-2 rounded-xl text-sm"
                               style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant); color: var(--color-text-primary);" />
                        <p v-if="cpuForm.errors.description" class="text-xs mt-1" style="color: var(--color-error);">{{ cpuForm.errors.description }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1" style="color: var(--color-text-muted);">Unidad *</label>
                        <input v-model="cpuForm.unit" type="text" placeholder="HH / m³ / gl"
                               class="w-full px-3 py-2 rounded-xl text-sm"
                               style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant); color: var(--color-text-primary);" />
                    </div>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold mb-1" style="color: var(--color-text-muted);">Precio unitario ({{ contract.currency }}) *</label>
                        <input v-model="cpuForm.unit_cost" type="number" min="0" step="1" placeholder="0"
                               class="w-full px-3 py-2 rounded-xl text-sm"
                               style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant); color: var(--color-text-primary);" />
                        <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">Sin decimales — en centavos (ej: $25.000 → 2500000)</p>
                        <p v-if="cpuForm.errors.unit_cost" class="text-xs mt-1" style="color: var(--color-error);">{{ cpuForm.errors.unit_cost }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1" style="color: var(--color-text-muted);">Categoría *</label>
                        <select v-model="cpuForm.category"
                                class="w-full px-3 py-2 rounded-xl text-sm"
                                style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant); color: var(--color-text-primary);">
                            <option v-for="(label, key) in categoryLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button @click="submitCpuItem" :disabled="cpuForm.processing"
                                class="w-full px-5 py-2 rounded-full text-sm font-bold transition-all active:scale-95 disabled:opacity-40"
                                style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary);">
                            Agregar al catálogo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de ítems -->
            <div v-if="priceItems.length > 0" class="overflow-x-auto rounded-xl" style="border: 1px solid var(--color-border-variant);">
                <table class="w-full text-sm" style="border-collapse: collapse;">
                    <thead>
                        <tr style="background: var(--color-bg-elevated);">
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-muted);">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-muted);">Descripción</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-muted);">Unidad</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-muted);">Precio unit.</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-muted);">Categoría</th>
                            <th class="px-4 py-3" style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in priceItems" :key="item.id"
                            style="border-top: 1px solid var(--color-border-variant);">
                            <td class="px-4 py-3 font-mono text-xs" style="color: var(--color-text-muted);">{{ item.code ?? '—' }}</td>
                            <td class="px-4 py-3 font-semibold" style="color: var(--color-text-primary);">{{ item.description }}</td>
                            <td class="px-4 py-3" style="color: var(--color-text-secondary);">{{ item.unit }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold" style="color: var(--color-text-primary);">
                                {{ fmtCost(item.unit_cost, contract.currency) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                      style="background: rgba(59,130,246,0.1); color: #3b82f6;">
                                    {{ categoryLabels[item.category] ?? item.category }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <button @click="deleteCpuItem(item.id)"
                                        class="p-1 rounded-lg transition-all"
                                        style="color: var(--color-text-muted);"
                                        :onMouseover="e => { e.currentTarget.style.background = 'rgba(239,68,68,0.1)'; e.currentTarget.style.color = '#ef4444' }"
                                        :onMouseout="e => { e.currentTarget.style.background = ''; e.currentTarget.style.color = 'var(--color-text-muted)' }">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">delete</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Vacío -->
            <div v-else-if="!showCpuForm" class="flex flex-col items-center justify-center py-10 rounded-xl"
                 style="border: 2px dashed var(--color-border-variant);">
                <span class="material-symbols-outlined mb-2" style="font-size: 36px; color: var(--color-text-muted);">calculate</span>
                <p class="text-sm font-semibold mb-1" style="color: var(--color-text-secondary);">Sin ítems en el catálogo</p>
                <p class="text-xs" style="color: var(--color-text-muted);">Agrega manualmente o importa desde Excel (código, descripción, unidad, precio, categoría)</p>
            </div>
        </div>

        <!-- Volver -->
        <div class="mt-8">
            <Link :href="route('contracts.index')"
                  class="text-sm font-semibold flex items-center gap-1"
                  style="color: var(--color-text-secondary);"
                  :onMouseover="e => e.currentTarget.style.color = 'var(--color-primary)'"
                  :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'">
                <span class="material-symbols-outlined" style="font-size: 16px;">arrow_back</span>
                Volver a contratos
            </Link>
        </div>
    </AppLayout>
</template>
