<script setup>
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

const props = defineProps({
    show:    { type: Boolean, default: false },
    company: { type: Object,  default: null  },
})

const emit = defineEmits(['close'])

const form = useForm({
    name:          '',
    rut:           '',
    address:       '',
    contact_name:  '',
    contact_email: '',
    type:          'contratista',
})

// Resetea o rellena el form cada vez que se abre el modal
watch(() => props.show, (open) => {
    if (!open) return
    form.clearErrors()
    if (props.company) {
        form.name          = props.company.name          ?? ''
        form.rut           = props.company.rut           ?? ''
        form.address       = props.company.address       ?? ''
        form.contact_name  = props.company.contact_name  ?? ''
        form.contact_email = props.company.contact_email ?? ''
        form.type          = props.company.type          ?? 'contratista'
    } else {
        form.reset()
    }
})

function close() {
    form.clearErrors()
    emit('close')
}

function submit() {
    if (props.company) {
        form.put(route('companies.update', props.company.id), {
            onSuccess: () => close(),
        })
    } else {
        form.post(route('companies.store'), {
            onSuccess: () => close(),
        })
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background: rgba(44,52,55,0.45); backdrop-filter: blur(4px);"
                @click.self="close"
            >
                <Transition
                    enter-active-class="transition duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                >
                    <div
                        v-if="show"
                        class="w-full max-w-2xl rounded-2xl p-8 space-y-6"
                        style="background: var(--color-bg-card); box-shadow: var(--shadow-modal);"
                    >
                        <!-- Cabecera -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: var(--color-bg-hover);">
                                    <span class="material-symbols-outlined" style="color: var(--color-primary); font-size: 20px;">business</span>
                                </div>
                                <h3 class="text-lg font-extrabold" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                    {{ company ? 'Editar empresa' : 'Nueva empresa' }}
                                </h3>
                            </div>
                            <button
                                class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                style="background: none; border: none; cursor: pointer; color: var(--color-text-secondary);"
                                :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                :onMouseout="e => e.currentTarget.style.background = 'none'"
                                @click="close"
                            >
                                <span class="material-symbols-outlined" style="font-size: 20px;">close</span>
                            </button>
                        </div>

                        <form class="space-y-5" @submit.prevent="submit">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <!-- Nombre -->
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                                        Nombre <span style="color: var(--color-error);">*</span>
                                    </label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        placeholder="Ej: Minera Escondida Ltda."
                                        class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                        :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.name ? 'outline: 2px solid var(--color-error);' : ''}`"
                                        :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                        :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                                    >
                                    <p v-if="form.errors.name" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.name }}</p>
                                </div>

                                <!-- RUT -->
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                                        RUT <span style="color: var(--color-error);">*</span>
                                    </label>
                                    <input
                                        v-model="form.rut"
                                        type="text"
                                        placeholder="Ej: 76.123.456-7"
                                        class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                        :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.rut ? 'outline: 2px solid var(--color-error);' : ''}`"
                                        :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                        :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                                    >
                                    <p v-if="form.errors.rut" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.rut }}</p>
                                </div>

                                <!-- Tipo -->
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                                        Tipo <span style="color: var(--color-error);">*</span>
                                    </label>
                                    <select
                                        v-model="form.type"
                                        class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                        :style="`background: var(--color-bg-input); color: var(--color-text-primary);`"
                                        :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                        :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                                    >
                                        <option value="mandante">Mandante</option>
                                        <option value="contratista">Contratista</option>
                                        <option value="ambos">Mandante y Contratista</option>
                                    </select>
                                </div>

                                <!-- Dirección -->
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">Dirección</label>
                                    <input
                                        v-model="form.address"
                                        type="text"
                                        placeholder="Ej: Av. Apoquindo 4500"
                                        class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                        :style="`background: var(--color-bg-input); color: var(--color-text-primary);`"
                                        :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                        :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                                    >
                                </div>

                                <!-- Contacto -->
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">Nombre contacto</label>
                                    <input
                                        v-model="form.contact_name"
                                        type="text"
                                        placeholder="Ej: Juan Pérez"
                                        class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                        :style="`background: var(--color-bg-input); color: var(--color-text-primary);`"
                                        :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                        :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                                    >
                                </div>

                                <!-- Email contacto -->
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">Correo contacto</label>
                                    <input
                                        v-model="form.contact_email"
                                        type="email"
                                        placeholder="contacto@empresa.cl"
                                        class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                        :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.contact_email ? 'outline: 2px solid var(--color-error);' : ''}`"
                                        :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                        :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                                    >
                                    <p v-if="form.errors.contact_email" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.contact_email }}</p>
                                </div>
                            </div>

                            <!-- Acciones -->
                            <div class="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    class="h-11 px-6 rounded-full font-bold text-sm transition-all"
                                    style="background: var(--color-bg-input); color: var(--color-text-primary); border: none; cursor: pointer;"
                                    @click="close"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="h-11 px-8 rounded-full font-bold text-sm transition-all active:scale-95 disabled:opacity-60"
                                    style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;"
                                >
                                    {{ form.processing ? 'Guardando...' : (company ? 'Guardar cambios' : 'Crear empresa') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
