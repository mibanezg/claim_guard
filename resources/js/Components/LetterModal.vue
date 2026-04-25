<script setup>
import { useForm } from '@inertiajs/vue3'
import { watch, computed } from 'vue'

const props = defineProps({
    show:                { type: Boolean, default: false },
    contract:            { type: Object,  required: true },
    letter:              { type: Object,  default: null },
    companies:           { type: Array,   default: () => [] },
    contractEvents:      { type: Array,   default: () => [] },
    typeLabels:          { type: Object,  default: () => ({}) },
    statusLabels:        { type: Object,  default: () => ({}) },
    defaultResponseDays: { type: Object,  default: () => ({}) },
})

const emit = defineEmits(['close'])

const form = useForm({
    letter_number:        '',
    type:                 'notificacion',
    subject:              '',
    from_company_id:      '',
    to_company_id:        '',
    issued_at:            '',
    received_at:          '',
    response_days:        5,
    status:               'emitida',
    clauses_referenced:   '',
    content_draft:        '',
    contractual_event_id: null,
})

watch(() => props.show, (open) => {
    if (!open) return
    form.clearErrors()
    if (props.letter) {
        form.letter_number        = props.letter.letter_number ?? ''
        form.type                 = props.letter.type
        form.subject              = props.letter.subject ?? ''
        form.from_company_id      = props.letter.from_company_id ?? ''
        form.to_company_id        = props.letter.to_company_id ?? ''
        form.issued_at            = props.letter.issued_at_raw ?? ''
        form.received_at          = props.letter.received_at_raw ?? ''
        form.response_days        = props.letter.response_days ?? 5
        form.status               = props.letter.status
        form.clauses_referenced   = props.letter.clauses_string ?? ''
        form.content_draft        = props.letter.content_draft ?? ''
        form.contractual_event_id = props.letter.contractual_event_id ?? null
    } else {
        form.letter_number        = ''
        form.type                 = 'notificacion'
        form.subject              = ''
        form.from_company_id      = ''
        form.to_company_id        = ''
        form.issued_at            = ''
        form.received_at          = ''
        form.response_days        = 5
        form.status               = 'emitida'
        form.clauses_referenced   = ''
        form.content_draft        = ''
        form.contractual_event_id = null
    }
})

// Actualiza los días de respuesta por defecto al cambiar el tipo
watch(() => form.type, (type) => {
    if (!props.letter) {
        form.response_days = props.defaultResponseDays[type] ?? 5
    }
})

const statusOptions = computed(() => props.statusLabels)

function submit() {
    const routeParams = { contract: props.contract.id }
    if (props.letter) {
        form.put(route('letters.update', { ...routeParams, letter: props.letter.id }), {
            onSuccess: () => emit('close'),
        })
    } else {
        form.post(route('letters.store', routeParams), {
            onSuccess: () => emit('close'),
        })
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 style="background: rgba(0,0,0,0.5);" @click.self="emit('close')">

                <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100">
                    <div v-if="show" class="w-full max-w-3xl rounded-3xl shadow-2xl flex flex-col"
                         style="background: var(--color-bg-card); max-height: 90vh;">

                        <!-- Encabezado — fijo, no scrollea -->
                        <div class="flex items-center justify-between px-8 pt-8 pb-5 flex-shrink-0"
                             style="border-bottom: 1px solid var(--color-border-variant);">
                            <div>
                                <h3 class="text-xl font-extrabold" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                    {{ letter ? 'Editar carta' : 'Registrar carta' }}
                                </h3>
                                <p v-if="letter" class="text-sm mt-0.5" style="color: var(--color-text-secondary);">
                                    {{ letter.letter_number }}
                                </p>
                            </div>
                            <button @click="emit('close')" class="w-8 h-8 flex items-center justify-center rounded-full flex-shrink-0"
                                    style="color: var(--color-text-muted); background: none; border: none; cursor: pointer;"
                                    :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                    :onMouseout="e => e.currentTarget.style.background = ''">
                                <span class="material-symbols-outlined" style="font-size: 20px;">close</span>
                            </button>
                        </div>

                        <!-- Cuerpo del formulario — solo esta parte scrollea -->
                        <form @submit.prevent="submit" class="flex flex-col flex-1 min-h-0">
                            <div class="flex-1 overflow-y-auto px-8 py-5 space-y-4 modal-scroll">

                            <!-- Número de carta (asignado por LOD.CL u otro sistema) -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">N° de carta *
                                    <span class="font-normal normal-case tracking-normal ml-1" style="color: var(--color-text-muted);">
                                        (número asignado por LOD.CL u otro sistema)
                                    </span>
                                </label>
                                <input v-model="form.letter_number" type="text"
                                       placeholder="Ej: LOD-2025-CTR001-0042"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                       style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                <p v-if="form.errors.letter_number" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.letter_number }}</p>
                            </div>

                            <!-- Tipo + Estado -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Tipo de carta *</label>
                                    <select v-model="form.type" class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                            style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                        <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                    <p v-if="form.errors.type" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.type }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Estado *</label>
                                    <select v-model="form.status" class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                            style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                        <option v-for="(label, key) in statusOptions" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Asunto -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Asunto *</label>
                                <input v-model="form.subject" type="text"
                                       placeholder="Asunto de la carta contractual..."
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                       style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                <p v-if="form.errors.subject" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.subject }}</p>
                            </div>

                            <!-- Empresa emisora + receptora -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Empresa emisora *</label>
                                    <select v-model="form.from_company_id" class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                            style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                        <option value="">Seleccionar empresa...</option>
                                        <option v-for="co in companies" :key="co.id" :value="co.id">{{ co.name }}</option>
                                    </select>
                                    <p v-if="form.errors.from_company_id" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.from_company_id }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Empresa receptora *</label>
                                    <select v-model="form.to_company_id" class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                            style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                        <option value="">Seleccionar empresa...</option>
                                        <option v-for="co in companies" :key="co.id" :value="co.id"
                                                :disabled="co.id === form.from_company_id">{{ co.name }}</option>
                                    </select>
                                    <p v-if="form.errors.to_company_id" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.to_company_id }}</p>
                                </div>
                            </div>

                            <!-- Fechas + Días de respuesta -->
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Fecha de emisión</label>
                                    <input v-model="form.issued_at" type="date"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Fecha de recepción</label>
                                    <input v-model="form.received_at" type="date"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Días para responder</label>
                                    <input v-model.number="form.response_days" type="number" min="0"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                    <p class="text-xs mt-1" style="color: var(--color-text-muted);">Días hábiles (Chile)</p>
                                </div>
                            </div>

                            <!-- Evento relacionado -->
                            <div v-if="contractEvents.length > 0">
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Evento relacionado</label>
                                <select v-model="form.contractual_event_id"
                                        class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                        style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                    <option :value="null">Sin evento vinculado</option>
                                    <option v-for="ev in contractEvents" :key="ev.id" :value="ev.id">
                                        {{ ev.label }} — {{ ev.description }}
                                    </option>
                                </select>
                            </div>

                            <!-- Cláusulas referenciadas -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Cláusulas referenciadas</label>
                                <input v-model="form.clauses_referenced" type="text"
                                       placeholder="Ej: 12.3, 15.1, Anexo B (separadas por coma)"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                       style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                            </div>

                            <!-- Borrador del contenido -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Borrador del contenido</label>
                                <textarea v-model="form.content_draft" rows="6"
                                          placeholder="Redacción de la carta..."
                                          class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none resize-none"
                                          style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"></textarea>
                            </div>

                            </div>

                            <!-- Acciones — fijas abajo, no scrollean -->
                            <div class="flex items-center justify-end gap-3 px-8 py-5 flex-shrink-0"
                                 style="border-top: 1px solid var(--color-border-variant);">
                                <button type="button" @click="emit('close')"
                                        class="px-5 py-2.5 rounded-full text-sm font-bold"
                                        style="background: var(--color-bg-elevated); color: var(--color-text-secondary); border: none; cursor: pointer;">
                                    Cancelar
                                </button>
                                <button type="submit" :disabled="form.processing"
                                        class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95 disabled:opacity-60"
                                        style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                                    {{ letter ? 'Guardar cambios' : 'Registrar carta' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--color-border-variant) transparent;
}
.modal-scroll::-webkit-scrollbar {
    width: 6px;
}
.modal-scroll::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 99px;
}
.modal-scroll::-webkit-scrollbar-thumb {
    background: var(--color-border-variant);
    border-radius: 99px;
}
.modal-scroll::-webkit-scrollbar-thumb:hover {
    background: var(--color-text-muted);
}
</style>
