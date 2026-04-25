<script setup>
import { reactive, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
    show:     { type: Boolean, default: false },
    contract: { type: Object, required: true },
    milestone: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const form = useForm({
    name:                   '',
    description:            '',
    planned_date:           '',
    actual_date:            '',
    progress_percentage:    0,
    is_critical:            false,
    generates_notification: false,
    status:                 'pendiente',
})

watch(() => props.show, (open) => {
    if (open) {
        if (props.milestone) {
            form.name                   = props.milestone.name
            form.description            = props.milestone.description ?? ''
            form.planned_date           = props.milestone.planned_date_raw ?? ''
            form.actual_date            = props.milestone.actual_date_raw ?? ''
            form.progress_percentage    = props.milestone.progress_percentage
            form.is_critical            = props.milestone.is_critical
            form.generates_notification = props.milestone.generates_notification
            form.status                 = props.milestone.status
        } else {
            form.reset()
            form.status = 'pendiente'
        }
        form.clearErrors()
    }
})

function submit() {
    if (props.milestone) {
        form.put(
            route('milestones.update', { contract: props.contract.id, milestone: props.milestone.id }),
            { onSuccess: () => emit('close') }
        )
    } else {
        form.post(
            route('milestones.store', { contract: props.contract.id }),
            { onSuccess: () => emit('close') }
        )
    }
}

function close() {
    emit('close')
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 style="background: rgba(0,0,0,0.5);" @click.self="close">

                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="show" class="w-full max-w-xl rounded-3xl p-8 shadow-2xl"
                         style="background: var(--color-bg-card);">

                        <!-- Encabezado -->
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-extrabold"
                                style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                {{ milestone ? 'Editar hito' : 'Nuevo hito' }}
                            </h3>
                            <button @click="close"
                                    class="w-8 h-8 flex items-center justify-center rounded-full transition-colors"
                                    style="color: var(--color-text-muted);"
                                    :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                    :onMouseout="e => e.currentTarget.style.background = ''">
                                <span class="material-symbols-outlined" style="font-size: 20px;">close</span>
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="space-y-4">

                            <!-- Nombre -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Nombre *</label>
                                <input v-model="form.name" type="text" placeholder="Nombre del hito"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                       style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                <p v-if="form.errors.name" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.name }}</p>
                            </div>

                            <!-- Descripción -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Descripción</label>
                                <textarea v-model="form.description" rows="2" placeholder="Descripción opcional"
                                          class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none resize-none"
                                          style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"></textarea>
                            </div>

                            <!-- Fechas -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Fecha planificada *</label>
                                    <input v-model="form.planned_date" type="date"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                    <p v-if="form.errors.planned_date" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.planned_date }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Fecha real</label>
                                    <input v-model="form.actual_date" type="date"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                </div>
                            </div>

                            <!-- Avance + Estado -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Avance % *</label>
                                    <input v-model.number="form.progress_percentage" type="number"
                                           min="0" max="100"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Estado *</label>
                                    <select v-model="form.status"
                                            class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                            style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                        <option value="pendiente">Pendiente</option>
                                        <option value="en_progreso">En Progreso</option>
                                        <option value="completado">Completado</option>
                                        <option value="atrasado">Atrasado</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Checkboxes -->
                            <div class="flex items-center gap-6 pt-1">
                                <label class="flex items-center gap-2 cursor-pointer text-sm"
                                       style="color: var(--color-text-secondary);">
                                    <input v-model="form.is_critical" type="checkbox"
                                           class="w-4 h-4 rounded"
                                           style="accent-color: var(--color-primary);" />
                                    Hito crítico
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer text-sm"
                                       style="color: var(--color-text-secondary);">
                                    <input v-model="form.generates_notification" type="checkbox"
                                           class="w-4 h-4 rounded"
                                           style="accent-color: var(--color-primary);" />
                                    Genera notificación
                                </label>
                            </div>

                            <!-- Acciones -->
                            <div class="flex items-center justify-end gap-3 pt-4 border-t"
                                 style="border-color: var(--color-border-variant);">
                                <button type="button" @click="close"
                                        class="px-5 py-2.5 rounded-full text-sm font-bold transition-all"
                                        style="background: var(--color-bg-elevated); color: var(--color-text-secondary);">
                                    Cancelar
                                </button>
                                <button type="submit" :disabled="form.processing"
                                        class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95 disabled:opacity-60"
                                        style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary);">
                                    {{ milestone ? 'Guardar cambios' : 'Crear hito' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
