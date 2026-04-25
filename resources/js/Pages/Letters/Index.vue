<script setup>
import { ref, computed, watch } from 'vue'
import { usePage, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import LetterModal from '@/Components/LetterModal.vue'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
    contracts:           { type: Object,  default: () => ({}) },
    selectedContract:    { type: Object,  default: null },
    letters:             { type: Object,  default: null },
    companies:           { type: Object,  default: () => ({}) },
    contractEvents:      { type: Array,   default: () => [] },
    filters:             { type: Object,  default: () => ({}) },
    typeLabels:          { type: Object,  default: () => ({}) },
    statusLabels:        { type: Object,  default: () => ({}) },
    defaultResponseDays: { type: Object,  default: () => ({}) },
    ai_available:        { type: Boolean, default: false },
})

const page = usePage()
const flash = computed(() => page.props.flash)
const { confirmDelete } = useConfirm()

// Contrato seleccionado
const selectedContractId = ref(props.selectedContract?.id ?? null)

watch(selectedContractId, (id) => {
    router.get(route('letters.index'), { contract_id: id }, {
        preserveState: true, preserveScroll: true, replace: true,
    })
})

// Filtros
const filterType   = ref(props.filters.type ?? '')
const filterStatus = ref(props.filters.status ?? '')

function applyFilters() {
    router.get(route('letters.index'), {
        contract_id: selectedContractId.value,
        type:   filterType.value   || undefined,
        status: filterStatus.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function clearFilters() {
    filterType.value   = ''
    filterStatus.value = ''
    applyFilters()
}

// Modal
const showModal  = ref(false)
const editLetter = ref(null)

function openCreate() { editLetter.value = null; showModal.value = true }
function openEdit(l)  { editLetter.value = l;    showModal.value = true }
function closeModal()  { showModal.value = false }

// Delete
async function deleteLetter(letter) {
    const ok = await confirmDelete(`carta ${letter.letter_number}`)
    if (!ok) return
    router.delete(route('letters.destroy', { contract: props.selectedContract.id, letter: letter.id }))
}

// Stats
const letters = computed(() => props.letters?.data ?? [])

const stats = computed(() => {
    const all = letters.value
    return {
        total:     all.length,
        emitidas:  all.filter(l => l.status === 'emitida').length,
        vencidas:  all.filter(l => l.status === 'vencida').length,
        respondidas: all.filter(l => l.status === 'respondida').length,
    }
})

// Badge de estado
function statusBadgeStyle(status) {
    const map = {
        emitida:    'background: rgba(59,130,246,0.12); color: #3b82f6;',
        recibida:   'background: rgba(168,85,247,0.12); color: #a855f7;',
        respondida: 'background: rgba(34,197,94,0.12); color: #22c55e;',
        vencida:    'background: rgba(239,68,68,0.12); color: #ef4444;',
    }
    return map[status] ?? ''
}

// Color del plazo
function deadlineStyle(letter) {
    if (!letter.response_deadline) return ''
    if (letter.status === 'respondida' || letter.status === 'vencida') return ''
    const days = letter.days_until_deadline
    if (days === null) return ''
    if (days < 0)  return 'color: #ef4444; font-weight: 700;'
    if (days <= 3) return 'color: #f97316; font-weight: 700;'
    if (days <= 7) return 'color: #eab308;'
    return ''
}

const companiesList = computed(() => props.companies?.data ?? [])

// Panel de borrador IA
const draftLetter      = ref(null)
const showDraftPanel   = ref(false)
const draftDescription = ref('')
const showFullDraft    = ref(false)
const draftForm = useForm({ description: '' })

function openDraftPanel(letter) {
    draftLetter.value      = letter
    draftDescription.value = ''
    showFullDraft.value    = false
    showDraftPanel.value   = true
}

function closeDraftPanel() {
    showDraftPanel.value = false
    draftLetter.value    = null
}

function requestDraft() {
    draftForm.description = draftDescription.value
    draftForm.post(route('letters.request-draft', {
        contract: props.selectedContract.id,
        letter:   draftLetter.value.id,
    }), { onSuccess: closeDraftPanel })
}
</script>

<template>
    <AppLayout title="Cartas">
        <div class="flex gap-6 h-full">

            <!-- Panel lateral: selector de contrato -->
            <div class="w-72 flex-shrink-0 flex flex-col gap-4">
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-3"
                        style="color: var(--color-text-secondary); font-family: var(--font-body);">Contrato</h3>
                    <div class="space-y-1">
                        <button v-for="c in contracts.data" :key="c.id"
                                @click="selectedContractId = c.id"
                                class="w-full text-left px-3 py-2.5 rounded-xl text-sm transition-all"
                                :style="selectedContractId === c.id
                                    ? 'background: var(--color-primary); color: var(--color-on-primary); font-weight: 600;'
                                    : 'color: var(--color-text-primary);'"
                                :onMouseover="e => selectedContractId !== c.id && (e.currentTarget.style.background = 'var(--color-bg-hover)')"
                                :onMouseout="e => selectedContractId !== c.id && (e.currentTarget.style.background = '')">
                            <div class="font-semibold truncate">{{ c.name }}</div>
                            <div class="text-xs opacity-70 truncate">{{ c.number }}</div>
                        </button>
                    </div>
                </div>

                <!-- Filtros -->
                <div v-if="selectedContract" class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-3"
                        style="color: var(--color-text-secondary); font-family: var(--font-body);">Filtros</h3>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider mb-1"
                                   style="color: var(--color-text-secondary);">Tipo</label>
                            <select v-model="filterType" @change="applyFilters"
                                    class="w-full px-3 py-2 rounded-xl text-sm border-none outline-none"
                                    style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                <option value="">Todos</option>
                                <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider mb-1"
                                   style="color: var(--color-text-secondary);">Estado</label>
                            <select v-model="filterStatus" @change="applyFilters"
                                    class="w-full px-3 py-2 rounded-xl text-sm border-none outline-none"
                                    style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                <option value="">Todos</option>
                                <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <button v-if="filterType || filterStatus" @click="clearFilters"
                                class="w-full py-2 rounded-xl text-xs font-bold"
                                style="background: var(--color-bg-elevated); color: var(--color-text-secondary); border: none; cursor: pointer;">
                            Limpiar filtros
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panel principal -->
            <div class="flex-1 flex flex-col gap-6 min-w-0">

                <!-- Flash -->
                <div v-if="flash.success" class="flex items-center gap-3 px-5 py-3 rounded-2xl"
                     style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);">
                    <span class="material-symbols-outlined" style="color: #22c55e; font-size: 20px;">check_circle</span>
                    <span class="text-sm font-medium" style="color: #22c55e;">{{ flash.success }}</span>
                </div>
                <div v-if="flash.error" class="flex items-center gap-3 px-5 py-3 rounded-2xl"
                     style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);">
                    <span class="material-symbols-outlined" style="color: #ef4444; font-size: 20px;">error</span>
                    <span class="text-sm font-medium" style="color: #ef4444;">{{ flash.error }}</span>
                </div>

                <template v-if="selectedContract">
                    <!-- Header + botón -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-extrabold" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                Cartas contractuales
                            </h2>
                            <p class="text-sm mt-1" style="color: var(--color-text-secondary);">
                                {{ selectedContract.name }} — {{ selectedContract.number }}
                            </p>
                        </div>
                        <button @click="openCreate"
                                class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">add</span>
                            Nueva carta
                        </button>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">Total</p>
                            <p class="text-3xl font-extrabold" style="color: var(--color-text-primary); font-family: var(--font-headline);">{{ stats.total }}</p>
                        </div>
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">Emitidas</p>
                            <p class="text-3xl font-extrabold" style="color: #3b82f6; font-family: var(--font-headline);">{{ stats.emitidas }}</p>
                        </div>
                        <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">Respondidas</p>
                            <p class="text-3xl font-extrabold" style="color: #22c55e; font-family: var(--font-headline);">{{ stats.respondidas }}</p>
                        </div>
                        <div class="rounded-2xl p-5"
                             :style="stats.vencidas > 0
                                 ? 'background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2);'
                                 : 'background: var(--color-bg-card);'">
                            <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">Vencidas</p>
                            <p class="text-3xl font-extrabold"
                               :style="stats.vencidas > 0 ? 'color: #ef4444;' : 'color: var(--color-text-primary);'"
                               style="font-family: var(--font-headline);">{{ stats.vencidas }}</p>
                        </div>
                    </div>

                    <!-- Tabla -->
                    <div class="rounded-2xl" style="background: var(--color-bg-card); overflow-x: auto;">
                        <div v-if="letters.length === 0" class="py-16 flex flex-col items-center gap-3">
                            <span class="material-symbols-outlined" style="font-size: 48px; color: var(--color-text-muted);">mail</span>
                            <p class="text-sm font-medium" style="color: var(--color-text-muted);">No hay cartas registradas</p>
                            <button @click="openCreate"
                                    class="mt-2 px-5 py-2 rounded-full text-sm font-bold"
                                    style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer;">
                                Registrar primera carta
                            </button>
                        </div>

                        <table v-else class="w-full text-sm" style="font-family: var(--font-body);">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--color-border-variant);">
                                    <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">N° Carta</th>
                                    <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Tipo / Asunto</th>
                                    <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">De → A</th>
                                    <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Emisión</th>
                                    <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Vence</th>
                                    <th class="text-left px-5 py-3 text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-secondary);">Estado</th>
                                    <th class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="letter in letters" :key="letter.id"
                                    style="border-bottom: 1px solid var(--color-border-variant);"
                                    :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                    :onMouseout="e => e.currentTarget.style.background = ''">

                                    <!-- N° Carta -->
                                    <td class="px-5 py-3">
                                        <span class="font-mono text-xs font-bold" style="color: var(--color-primary);">
                                            {{ letter.letter_number }}
                                        </span>
                                    </td>

                                    <!-- Tipo + Asunto -->
                                    <td class="px-5 py-3 max-w-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold truncate" style="color: var(--color-text-primary);">{{ letter.type_label }}</span>
                                            <span v-if="letter.ai_generated && letter.content_draft"
                                                  class="px-1.5 py-0.5 rounded text-xs font-bold flex-shrink-0"
                                                  style="background: rgba(124,58,237,0.12); color: #7c3aed;">IA</span>
                                        </div>
                                        <div class="text-xs truncate" style="color: var(--color-text-secondary);">{{ letter.subject }}</div>
                                    </td>

                                    <!-- De → A -->
                                    <td class="px-5 py-3">
                                        <div class="text-xs" style="color: var(--color-text-secondary);">
                                            <span style="color: var(--color-text-primary); font-weight: 600;">{{ letter.from_company_name }}</span>
                                            <span class="mx-1">→</span>
                                            <span>{{ letter.to_company_name }}</span>
                                        </div>
                                    </td>

                                    <!-- Fecha emisión -->
                                    <td class="px-5 py-3" style="color: var(--color-text-secondary);">
                                        {{ letter.issued_at ?? '—' }}
                                    </td>

                                    <!-- Plazo de respuesta -->
                                    <td class="px-5 py-3">
                                        <div v-if="letter.response_deadline" :style="deadlineStyle(letter)">
                                            {{ letter.response_deadline }}
                                            <div v-if="letter.days_until_deadline !== null && !['respondida','vencida'].includes(letter.status)"
                                                 class="text-xs">
                                                <span v-if="letter.days_until_deadline < 0">Vencida</span>
                                                <span v-else>{{ letter.days_until_deadline }} días</span>
                                            </div>
                                        </div>
                                        <span v-else style="color: var(--color-text-muted);">—</span>
                                    </td>

                                    <!-- Estado -->
                                    <td class="px-5 py-3">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold"
                                              :style="statusBadgeStyle(letter.status)">
                                            {{ letter.status_label }}
                                        </span>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="px-5 py-3" style="white-space: nowrap; width: 120px;">
                                        <div class="flex items-center gap-1">
                                            <!-- Asistente IA (disponible para todas las cartas) -->
                                            <button @click="ai_available ? openDraftPanel(letter) : null"
                                                    :title="ai_available ? 'Asistente IA — generar respuesta' : 'IA no configurada'"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg transition-all"
                                                    :style="ai_available
                                                        ? 'color: var(--color-secondary, #7c3aed); background: none; border: none; cursor: pointer;'
                                                        : 'color: var(--color-text-muted); background: none; border: none; cursor: not-allowed; opacity: 0.4;'"
                                                    :onMouseover="e => ai_available && (e.currentTarget.style.background = 'rgba(124,58,237,0.1)')"
                                                    :onMouseout="e => ai_available && (e.currentTarget.style.background = '')">
                                                <span class="material-symbols-outlined" style="font-size: 18px;">auto_awesome</span>
                                            </button>
                                            <!-- Editar -->
                                            <button @click="openEdit(letter)"
                                                    title="Editar"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg transition-all"
                                                    style="color: var(--color-text-muted); background: none; border: none; cursor: pointer;"
                                                    :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                                    :onMouseout="e => e.currentTarget.style.background = ''">
                                                <span style="font-family: 'Material Symbols Outlined'; font-size: 18px; font-weight: normal; font-style: normal; line-height: 1; letter-spacing: normal; text-transform: none; display: inline-block; white-space: nowrap; word-wrap: normal; direction: ltr; font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 20;">edit</span>
                                            </button>
                                            <!-- Eliminar -->
                                            <button @click="deleteLetter(letter)"
                                                    title="Eliminar"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg transition-all"
                                                    style="color: var(--color-text-muted); background: none; border: none; cursor: pointer;"
                                                    :onMouseover="e => { e.currentTarget.style.background = 'rgba(239,68,68,0.1)'; e.currentTarget.style.color = '#ef4444' }"
                                                    :onMouseout="e => { e.currentTarget.style.background = ''; e.currentTarget.style.color = 'var(--color-text-muted)' }">
                                                <span style="font-family: 'Material Symbols Outlined'; font-size: 18px; font-weight: normal; font-style: normal; line-height: 1; letter-spacing: normal; text-transform: none; display: inline-block; white-space: nowrap; word-wrap: normal; direction: ltr; font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 20;">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Paginación -->
                        <div v-if="props.letters?.links && props.letters.links.length > 3"
                             class="flex items-center justify-center gap-2 px-5 py-4"
                             style="border-top: 1px solid var(--color-border-variant);">
                            <template v-for="link in props.letters.links" :key="link.label">
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
                    <span class="material-symbols-outlined mb-4" style="font-size: 56px; color: var(--color-text-muted);">mail</span>
                    <p class="font-semibold" style="color: var(--color-text-secondary);">Selecciona un contrato para ver sus cartas</p>
                </div>
            </div>
        </div>

        <!-- Panel borrador IA -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100">
                <div v-if="showDraftPanel" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style="background: rgba(0,0,0,0.5);" @click.self="closeDraftPanel">
                    <div class="w-full max-w-lg rounded-3xl p-8 shadow-2xl" style="background: var(--color-bg-card);">
                        <!-- Header IA -->
                        <div class="flex items-center gap-3 mb-6">
                            <span class="material-symbols-outlined"
                                  style="color: var(--color-secondary, #7c3aed); font-variation-settings: 'FILL' 1;">auto_awesome</span>
                            <div>
                                <h3 class="text-lg font-extrabold"
                                    style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                    Asistente IA
                                </h3>
                                <p class="text-xs" style="color: var(--color-text-muted);">
                                    {{ draftLetter?.letter_number }} — {{ draftLetter?.type_label }}
                                </p>
                            </div>
                        </div>

                        <!-- Borrador existente -->
                        <div v-if="draftLetter?.content_draft" class="mb-5">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined" style="font-size: 14px; color: var(--color-secondary, #7c3aed); font-variation-settings: 'FILL' 1;">check_circle</span>
                                    <span class="text-xs font-bold" style="color: var(--color-secondary, #7c3aed);">Borrador generado</span>
                                </div>
                                <button @click="showFullDraft = !showFullDraft"
                                        class="text-xs font-bold px-3 py-1 rounded-full"
                                        style="background: rgba(124,58,237,0.1); color: var(--color-secondary, #7c3aed); border: none; cursor: pointer;">
                                    {{ showFullDraft ? 'Ocultar' : 'Ver completo' }}
                                </button>
                            </div>
                            <div class="p-4 rounded-2xl"
                                 style="background: var(--color-bg-elevated); border: 1px solid rgba(124,58,237,0.2);">
                                <p class="text-xs whitespace-pre-wrap"
                                   :style="`color: var(--color-text-secondary); line-height: 1.7; overflow-y: auto; ${showFullDraft ? 'max-height: 400px;' : 'max-height: 120px;'}`">
                                    {{ draftLetter.content_draft }}
                                </p>
                            </div>
                        </div>

                        <!-- Campo instrucción -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                   style="color: var(--color-text-secondary);">
                                ¿Qué necesitas redactar? *
                            </label>
                            <textarea v-model="draftDescription" rows="4"
                                      placeholder="Ej: Redacta una respuesta rechazando la OC por falta de respaldo, citando la cláusula 12.3 sobre plazo de presentación de antecedentes..."
                                      class="w-full px-4 py-3 rounded-2xl text-sm border-none outline-none resize-none"
                                      style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body); line-height: 1.6;"></textarea>
                        </div>

                        <p class="text-xs mt-3 p-3 rounded-xl"
                           style="background: rgba(124,58,237,0.06); color: var(--color-text-secondary);">
                            La IA usará el contrato y el historial de correspondencia como contexto. El borrador se genera en segundo plano — recarga en unos segundos para verlo.
                        </p>

                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" @click="closeDraftPanel"
                                    class="px-5 py-2.5 rounded-full text-sm font-bold"
                                    style="background: var(--color-bg-elevated); color: var(--color-text-secondary); border: none; cursor: pointer;">
                                Cancelar
                            </button>
                            <button @click="requestDraft"
                                    :disabled="draftForm.processing || !draftDescription.trim()"
                                    class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95 disabled:opacity-60"
                                    style="background: var(--color-secondary, #7c3aed); color: white; box-shadow: 0 4px 14px rgba(124,58,237,0.3); border: none; cursor: pointer;">
                                <span v-if="draftForm.processing">Enviando...</span>
                                <span v-else>
                                    <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">auto_awesome</span>
                                    {{ draftLetter?.content_draft ? 'Regenerar borrador' : 'Generar borrador' }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Modal -->
        <LetterModal
            :show="showModal"
            :contract="selectedContract ?? {}"
            :letter="editLetter"
            :companies="companiesList"
            :contract-events="contractEvents"
            :type-labels="typeLabels"
            :status-labels="statusLabels"
            :default-response-days="defaultResponseDays"
            @close="closeModal"
        />
    </AppLayout>
</template>
