<script setup>
import { ref, computed } from 'vue'
import { usePage, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
    contracts:         { type: Array,   default: () => [] },
    selectedContract:  { type: Object,  default: null },
    documents:         { type: Object,  default: null },
    filters:           { type: Object,  default: () => ({}) },
    categoryLabels:    { type: Object,  default: () => ({}) },
    sharepoint_active: { type: Boolean, default: false },
})

const page  = usePage()
const flash = computed(() => page.props.flash)
const { confirmDelete } = useConfirm()

const selectedContractId = ref(props.selectedContract?.id ?? null)

function selectContract(id) {
    selectedContractId.value = id
    router.get(route('documents.index'), { contract_id: id }, {
        preserveState: true, preserveScroll: true, replace: true,
    })
}

// Filtro categoría
const filterCategory = ref(props.filters.category ?? '')
function applyFilter() {
    router.get(route('documents.index'), {
        contract_id: selectedContractId.value,
        category:    filterCategory.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

// Upload
const uploadForm = useForm({ file: null, category: 'otro' })
const showUpload = ref(false)
const fileInput  = ref(null)

function openUpload() { showUpload.value = true }
function closeUpload() { showUpload.value = false; uploadForm.reset() }

function submitUpload() {
    uploadForm.post(route('documents.store', props.selectedContract.id), {
        onSuccess: closeUpload,
        forceFormData: true,
    })
}

function handleFile(e) {
    uploadForm.file = e.target.files[0] ?? null
}

// Eliminar
async function deleteDoc(doc) {
    if (!await confirmDelete(doc.name)) return
    router.delete(route('documents.destroy', {
        contract: props.selectedContract.id,
        document: doc.id,
    }))
}

const docs = computed(() => props.documents?.data ?? [])

// Stats
const stats = computed(() => {
    const all = docs.value
    return {
        total:      props.documents?.total ?? 0,
        sharepoint: all.filter(d => d.sharepoint_id).length,
        local:      all.filter(d => !d.sharepoint_id).length,
    }
})

function fileIcon(mimeType) {
    if (!mimeType) return 'insert_drive_file'
    if (mimeType.includes('pdf'))   return 'picture_as_pdf'
    if (mimeType.includes('image')) return 'image'
    if (mimeType.includes('word') || mimeType.includes('document')) return 'description'
    if (mimeType.includes('sheet') || mimeType.includes('excel'))   return 'table_chart'
    if (mimeType.includes('zip') || mimeType.includes('compressed')) return 'folder_zip'
    return 'insert_drive_file'
}
</script>

<template>
    <AppLayout title="Documentos">
        <div class="flex gap-6 h-full">

            <!-- Panel lateral -->
            <div class="w-72 flex-shrink-0 flex flex-col gap-4">
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-3"
                        style="color: var(--color-text-secondary); font-family: var(--font-body);">Contrato</h3>
                    <div class="space-y-1">
                        <button v-for="c in contracts" :key="c.id"
                                @click="selectContract(c.id)"
                                class="w-full text-left px-3 py-2.5 rounded-xl text-sm transition-all"
                                :style="selectedContractId === c.id
                                    ? 'background: var(--color-primary); color: var(--color-on-primary); font-weight: 600;'
                                    : 'color: var(--color-text-primary);'"
                                :onMouseover="e => selectedContractId !== c.id && (e.currentTarget.style.background = 'var(--color-bg-hover)')"
                                :onMouseout="e => selectedContractId !== c.id && (e.currentTarget.style.background = '')">
                            <div class="font-semibold truncate">{{ c.name }}</div>
                            <div class="text-xs opacity-70">{{ c.number }}</div>
                        </button>
                    </div>
                </div>

                <!-- Filtro categoría -->
                <div v-if="selectedContract" class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-3"
                        style="color: var(--color-text-secondary); font-family: var(--font-body);">Filtros</h3>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1"
                               style="color: var(--color-text-secondary);">Categoría</label>
                        <select v-model="filterCategory" @change="applyFilter"
                                class="w-full px-3 py-2 rounded-xl text-sm border-none outline-none"
                                style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                            <option value="">Todas</option>
                            <option v-for="(label, key) in categoryLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Panel principal -->
            <div class="flex-1 flex flex-col gap-6 min-w-0">

                <!-- Flash -->
                <div v-if="flash?.success" class="flex items-center gap-3 px-5 py-3 rounded-2xl"
                     style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);">
                    <span class="material-symbols-outlined" style="color: #22c55e; font-size: 20px;">check_circle</span>
                    <span class="text-sm font-medium" style="color: #22c55e;">{{ flash.success }}</span>
                </div>
                <div v-if="flash?.error" class="flex items-center gap-3 px-5 py-3 rounded-2xl"
                     style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);">
                    <span class="material-symbols-outlined" style="color: #ef4444; font-size: 20px;">error</span>
                    <span class="text-sm font-medium" style="color: #ef4444;">{{ flash.error }}</span>
                </div>

                <template v-if="selectedContract">

                    <!-- Banner SharePoint no configurado -->
                    <div v-if="!sharepoint_active"
                         class="flex items-start gap-3 px-5 py-4 rounded-2xl"
                         style="background: var(--color-info-container, rgba(59,130,246,0.08)); border-left: 3px solid #3b82f6;">
                        <span class="material-symbols-outlined" style="color: #3b82f6; font-size: 20px; flex-shrink: 0;">info</span>
                        <p class="text-sm" style="color: var(--color-text-primary);">
                            <strong>SharePoint no configurado.</strong>
                            Los documentos se almacenan en el servidor local.
                            Para habilitar SharePoint, configura la integración de Microsoft 365 en
                            <strong>Configuración → Integraciones</strong>.
                        </p>
                    </div>

                    <!-- Header -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-extrabold"
                                style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                Documentos
                            </h2>
                            <p class="text-sm mt-1" style="color: var(--color-text-secondary);">
                                {{ selectedContract.name }} — {{ selectedContract.number }}
                            </p>
                        </div>
                        <button @click="openUpload"
                                class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">upload_file</span>
                            Subir documento
                        </button>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-4">
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">Total</p>
                            <p class="text-3xl font-extrabold" style="color: var(--color-text-primary); font-family: var(--font-headline);">{{ stats.total }}</p>
                        </div>
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">En SharePoint</p>
                            <p class="text-3xl font-extrabold"
                               :style="stats.sharepoint > 0 ? 'color: var(--color-primary);' : 'color: var(--color-text-primary);'"
                               style="font-family: var(--font-headline);">{{ stats.sharepoint }}</p>
                        </div>
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">Almacenamiento local</p>
                            <p class="text-3xl font-extrabold"
                               :style="stats.local > 0 && !sharepoint_active ? 'color: #eab308;' : 'color: var(--color-text-primary);'"
                               style="font-family: var(--font-headline);">{{ stats.local }}</p>
                        </div>
                    </div>

                    <!-- Tabla de documentos -->
                    <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                        <div v-if="docs.length === 0" class="py-16 flex flex-col items-center gap-3">
                            <span class="material-symbols-outlined" style="font-size: 48px; color: var(--color-text-muted);">folder_open</span>
                            <p class="text-sm font-medium" style="color: var(--color-text-muted);">No hay documentos registrados</p>
                            <button @click="openUpload"
                                    class="mt-2 px-5 py-2 rounded-full text-sm font-bold"
                                    style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer;">
                                Subir primer documento
                            </button>
                        </div>

                        <table v-else class="w-full text-sm" style="font-family: var(--font-body); table-layout: fixed;">
                            <colgroup>
                                <col style="width: 44px;" />    <!-- Ícono -->
                                <col />                         <!-- Nombre -->
                                <col style="width: 160px;" />   <!-- Categoría -->
                                <col style="width: 110px;" />   <!-- Tamaño -->
                                <col style="width: 120px;" />   <!-- Storage -->
                                <col style="width: 100px;" />   <!-- Fecha -->
                                <col style="width: 60px;" />    <!-- Acciones -->
                            </colgroup>
                            <thead>
                                <tr style="border-bottom: 1px solid var(--color-border-variant);">
                                    <th class="px-4 py-3"></th>
                                    <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Nombre</th>
                                    <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Categoría</th>
                                    <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Tamaño</th>
                                    <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Storage</th>
                                    <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Subido</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="doc in docs" :key="doc.id"
                                    style="border-bottom: 1px solid var(--color-border-variant);"
                                    :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                    :onMouseout="e => e.currentTarget.style.background = ''">

                                    <!-- Ícono -->
                                    <td class="px-4 py-3">
                                        <span class="material-symbols-outlined" style="font-size: 22px; color: var(--color-primary);">
                                            {{ fileIcon(doc.file_type) }}
                                        </span>
                                    </td>

                                    <!-- Nombre -->
                                    <td class="px-4 py-3" style="min-width: 0;">
                                        <a v-if="doc.storage_url"
                                           :href="doc.storage_url"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="font-medium truncate block hover:underline"
                                           style="color: var(--color-primary);">
                                            {{ doc.name }}
                                        </a>
                                        <span v-else class="font-medium truncate block"
                                              style="color: var(--color-text-primary);">{{ doc.name }}</span>
                                        <span class="text-xs" style="color: var(--color-text-muted);">
                                            {{ doc.uploader_name ?? '—' }}
                                        </span>
                                    </td>

                                    <!-- Categoría -->
                                    <td class="px-4 py-3">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold"
                                              style="background: var(--color-bg-elevated); color: var(--color-text-secondary);">
                                            {{ categoryLabels[doc.category] ?? doc.category }}
                                        </span>
                                    </td>

                                    <!-- Tamaño -->
                                    <td class="px-4 py-3 text-xs" style="color: var(--color-text-secondary);">
                                        {{ doc.file_size_human }}
                                    </td>

                                    <!-- Storage -->
                                    <td class="px-4 py-3">
                                        <div v-if="doc.sharepoint_id" class="flex items-center gap-1.5">
                                            <span class="material-symbols-outlined" style="font-size: 14px; color: var(--color-primary);">cloud</span>
                                            <span class="text-xs font-medium" style="color: var(--color-primary);">SharePoint</span>
                                        </div>
                                        <div v-else class="flex items-center gap-1.5">
                                            <span class="material-symbols-outlined" style="font-size: 14px; color: #eab308;">storage</span>
                                            <span class="text-xs font-medium" style="color: #eab308;">Local</span>
                                        </div>
                                    </td>

                                    <!-- Fecha -->
                                    <td class="px-4 py-3 text-xs" style="color: var(--color-text-secondary);">
                                        {{ doc.created_at }}
                                    </td>

                                    <!-- Acciones -->
                                    <td class="px-4 py-3">
                                        <button @click="deleteDoc(doc)"
                                                title="Eliminar"
                                                class="w-8 h-8 flex items-center justify-center rounded-lg transition-all"
                                                style="color: var(--color-text-muted); background: none; border: none; cursor: pointer;"
                                                :onMouseover="e => { e.currentTarget.style.background = 'rgba(239,68,68,0.1)'; e.currentTarget.style.color = '#ef4444' }"
                                                :onMouseout="e => { e.currentTarget.style.background = ''; e.currentTarget.style.color = 'var(--color-text-muted)' }">
                                            <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Paginación -->
                        <div v-if="props.documents?.links && props.documents.links.length > 3"
                             class="flex items-center justify-center gap-2 px-5 py-4"
                             style="border-top: 1px solid var(--color-border-variant);">
                            <template v-for="link in props.documents.links" :key="link.label">
                                <button v-if="link.url"
                                        @click="router.get(link.url, {}, { preserveScroll: true })"
                                        class="px-3 py-1.5 rounded-lg text-sm font-medium"
                                        :style="link.active
                                            ? 'background: var(--color-primary); color: var(--color-on-primary);'
                                            : 'background: var(--color-bg-elevated); color: var(--color-text-secondary);'"
                                        v-html="link.label">
                                </button>
                            </template>
                        </div>
                    </div>

                </template>

                <!-- Sin contrato seleccionado -->
                <div v-else class="flex-1 flex flex-col items-center justify-center py-24 rounded-2xl"
                     style="background: var(--color-bg-card);">
                    <span class="material-symbols-outlined mb-4" style="font-size: 56px; color: var(--color-text-muted);">folder</span>
                    <p class="font-semibold" style="color: var(--color-text-secondary);">Selecciona un contrato para ver sus documentos</p>
                </div>
            </div>
        </div>

        <!-- Modal subir documento -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100">
                <div v-if="showUpload" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style="background: rgba(0,0,0,0.5);" @click.self="closeUpload">
                    <div class="w-full max-w-md rounded-3xl p-8 shadow-2xl" style="background: var(--color-bg-card);">
                        <h3 class="text-lg font-extrabold mb-6"
                            style="font-family: var(--font-headline); color: var(--color-text-primary);">
                            Subir documento
                        </h3>

                        <div class="space-y-4">
                            <!-- Archivo -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Archivo *</label>
                                <input ref="fileInput" type="file"
                                       @change="handleFile"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                       style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                <p v-if="uploadForm.errors.file" class="text-xs mt-1" style="color: #ef4444;">
                                    {{ uploadForm.errors.file }}
                                </p>
                            </div>

                            <!-- Categoría -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Categoría *</label>
                                <select v-model="uploadForm.category"
                                        class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                        style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                    <option v-for="(label, key) in categoryLabels" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Storage info -->
                        <p class="text-xs mt-4 p-3 rounded-xl"
                           :style="sharepoint_active
                               ? 'background: rgba(59,130,246,0.08); color: var(--color-text-secondary);'
                               : 'background: rgba(234,179,8,0.08); color: var(--color-text-secondary);'">
                            <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: middle;">
                                {{ sharepoint_active ? 'cloud' : 'storage' }}
                            </span>
                            {{ sharepoint_active
                                ? 'Se guardará en SharePoint'
                                : 'Se guardará en almacenamiento local (SharePoint no configurado)' }}
                        </p>

                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" @click="closeUpload"
                                    class="px-5 py-2.5 rounded-full text-sm font-bold"
                                    style="background: var(--color-bg-elevated); color: var(--color-text-secondary); border: none; cursor: pointer;">
                                Cancelar
                            </button>
                            <button @click="submitUpload"
                                    :disabled="uploadForm.processing || !uploadForm.file"
                                    class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95 disabled:opacity-60"
                                    style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                                <span v-if="uploadForm.processing">Subiendo...</span>
                                <span v-else>Subir archivo</span>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AppLayout>
</template>
