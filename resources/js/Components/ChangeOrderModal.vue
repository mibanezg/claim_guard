<script setup>
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

const props = defineProps({
    show:           { type: Boolean, default: false },
    contract:       { type: Object,  required: true },
    order:          { type: Object,  default: null },
    partyLabels:    { type: Object,  default: () => ({}) },
    contractEvents: { type: Array,   default: () => [] },
})

const emit = defineEmits(['close'])

const form = useForm({
    requested_by_party:   'contratista',
    description:          '',
    schedule_impact_days: 0,
    cost_impact:          0,
    status:               'solicitada',
    contractual_event_id: null,
})

watch(() => props.show, (open) => {
    if (!open) return
    form.clearErrors()
    if (props.order) {
        form.requested_by_party   = props.order.requested_by_party
        form.description          = props.order.description ?? ''
        form.schedule_impact_days = props.order.schedule_impact_days
        form.cost_impact          = props.order.cost_impact
        form.status               = props.order.status
        form.contractual_event_id = props.order.contractual_event_id ?? null
    } else {
        form.requested_by_party   = 'contratista'
        form.description          = ''
        form.schedule_impact_days = 0
        form.cost_impact          = 0
        form.status               = 'solicitada'
        form.contractual_event_id = null
    }
})

function submit() {
    const params = { contract: props.contract.id }
    if (props.order) {
        form.put(route('change-orders.update', { ...params, changeOrder: props.order.id }), {
            onSuccess: () => emit('close'),
        })
    } else {
        form.post(route('change-orders.store', params), {
            onSuccess: () => emit('close'),
        })
    }
}

const currency = props.contract?.currency ?? 'CLP'
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 style="background: rgba(0,0,0,0.5);" @click.self="emit('close')">

                <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100">
                    <div v-if="show" class="w-full max-w-2xl rounded-3xl shadow-2xl flex flex-col"
                         style="background: var(--color-bg-card); max-height: 90vh;">

                        <!-- Encabezado -->
                        <div class="flex items-center justify-between px-8 pt-8 pb-5 flex-shrink-0"
                             style="border-bottom: 1px solid var(--color-border-variant);">
                            <div>
                                <h3 class="text-xl font-extrabold" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                    {{ order ? 'Editar OC' : 'Nueva orden de cambio' }}
                                </h3>
                                <p v-if="order" class="text-sm mt-0.5 font-mono" style="color: var(--color-text-secondary);">
                                    {{ order.request_number }}
                                </p>
                            </div>
                            <button @click="emit('close')" class="w-8 h-8 flex items-center justify-center rounded-full flex-shrink-0"
                                    style="color: var(--color-text-muted); background: none; border: none; cursor: pointer;"
                                    :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                    :onMouseout="e => e.currentTarget.style.background = ''">
                                <span class="material-symbols-outlined" style="font-size: 20px;">close</span>
                            </button>
                        </div>

                        <!-- Formulario scrollable -->
                        <form @submit.prevent="submit" class="flex flex-col flex-1 min-h-0">
                            <div class="flex-1 overflow-y-auto px-8 py-5 space-y-4 modal-scroll">

                                <!-- Parte solicitante + Estado -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                               style="color: var(--color-text-secondary);">Parte solicitante *</label>
                                        <select v-model="form.requested_by_party"
                                                class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                                style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                            <option v-for="(label, key) in partyLabels" :key="key" :value="key">{{ label }}</option>
                                        </select>
                                        <p v-if="form.errors.requested_by_party" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.requested_by_party }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                               style="color: var(--color-text-secondary);">Estado *</label>
                                        <select v-model="form.status"
                                                class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                                style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                            <option value="solicitada">Solicitada</option>
                                            <option value="evaluacion">En Evaluación</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Descripción -->
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Descripción *</label>
                                    <textarea v-model="form.description" rows="4"
                                              placeholder="Describe el alcance y justificación de la orden de cambio..."
                                              class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none resize-none"
                                              style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"></textarea>
                                    <p v-if="form.errors.description" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.description }}</p>
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

                                <!-- Impactos -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                               style="color: var(--color-text-secondary);">Impacto plazo (días)</label>
                                        <input v-model.number="form.schedule_impact_days" type="number"
                                               class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                               style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                        <p class="text-xs mt-1" style="color: var(--color-text-muted);">Negativo = reducción de plazo</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                               style="color: var(--color-text-secondary);">Impacto costo ({{ currency }})</label>
                                        <input v-model.number="form.cost_impact" type="number" step="0.01"
                                               class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                               style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                        <p class="text-xs mt-1" style="color: var(--color-text-muted);">Negativo = reducción de monto</p>
                                    </div>
                                </div>

                            </div>

                            <!-- Acciones -->
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
                                    {{ order ? 'Guardar cambios' : 'Registrar OC' }}
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
.modal-scroll::-webkit-scrollbar { width: 6px; }
.modal-scroll::-webkit-scrollbar-track { background: transparent; border-radius: 99px; }
.modal-scroll::-webkit-scrollbar-thumb { background: var(--color-border-variant); border-radius: 99px; }
.modal-scroll::-webkit-scrollbar-thumb:hover { background: var(--color-text-muted); }
</style>
