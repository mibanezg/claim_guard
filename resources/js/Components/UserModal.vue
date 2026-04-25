<script setup>
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

const props = defineProps({
    show:  { type: Boolean, default: false },
    user:  { type: Object,  default: null  },
    roles: { type: Array,   default: () => [] },
})

const emit = defineEmits(['close'])

const roleLabels = {
    tenant_admin:   'Administrador',
    contract_admin: 'Admin. de Contratos',
    field_engineer: 'Ingeniero de Campo',
    manager:        'Gerente',
    legal:          'Legal',
    counterpart:    'Contraparte',
}

const form = useForm({
    name:     '',
    email:    '',
    password: '',
    role:     '',
})

watch(() => props.show, (open) => {
    if (!open) return
    form.clearErrors()
    if (props.user) {
        form.name     = props.user.name  ?? ''
        form.email    = props.user.email ?? ''
        form.password = ''
        form.role     = props.user.role  ?? ''
    } else {
        form.reset()
    }
})

function close() {
    form.clearErrors()
    emit('close')
}

function submit() {
    if (props.user) {
        form.put(route('users.update', props.user.id), {
            onSuccess: () => close(),
        })
    } else {
        form.post(route('users.store'), {
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
                        class="w-full max-w-xl rounded-2xl p-8 space-y-6"
                        style="background: var(--color-bg-card); box-shadow: var(--shadow-modal);"
                    >
                        <!-- Cabecera -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: var(--color-bg-hover);">
                                    <span class="material-symbols-outlined" style="color: var(--color-primary); font-size: 20px;">person</span>
                                </div>
                                <h3 class="text-lg font-extrabold" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                    {{ user ? 'Editar usuario' : 'Nuevo usuario' }}
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
                                        placeholder="Ej: Juan Pérez"
                                        class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                        :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.name ? 'outline: 2px solid var(--color-error);' : ''}`"
                                        :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                        :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                                    >
                                    <p v-if="form.errors.name" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.name }}</p>
                                </div>

                                <!-- Correo -->
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                                        Correo <span style="color: var(--color-error);">*</span>
                                    </label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        placeholder="usuario@empresa.cl"
                                        class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                        :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.email ? 'outline: 2px solid var(--color-error);' : ''}`"
                                        :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                        :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                                    >
                                    <p v-if="form.errors.email" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.email }}</p>
                                </div>

                                <!-- Contraseña -->
                                <div class="space-y-1.5 md:col-span-2">
                                    <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                                        Contraseña
                                        <span v-if="!user" style="color: var(--color-error);">*</span>
                                        <span v-else class="font-normal ml-1" style="color: var(--color-text-muted);">(dejar vacío para no cambiar)</span>
                                    </label>
                                    <input
                                        v-model="form.password"
                                        type="password"
                                        placeholder="Mínimo 8 caracteres"
                                        class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                        :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.password ? 'outline: 2px solid var(--color-error);' : ''}`"
                                        :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                        :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                                    >
                                    <p v-if="form.errors.password" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.password }}</p>
                                </div>

                                <!-- Rol -->
                                <div class="space-y-1.5 md:col-span-2">
                                    <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                                        Rol <span style="color: var(--color-error);">*</span>
                                    </label>
                                    <select
                                        v-model="form.role"
                                        class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                        :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.role ? 'outline: 2px solid var(--color-error);' : ''}`"
                                        :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                        :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                                    >
                                        <option value="" disabled>Seleccionar rol...</option>
                                        <option v-for="r in roles" :key="r.id" :value="r.name">
                                            {{ roleLabels[r.name] ?? r.name }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.role" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.role }}</p>
                                </div>
                            </div>

                            <!-- Acciones -->
                            <div class="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    class="h-11 px-6 rounded-full font-bold text-sm"
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
                                    {{ form.processing ? 'Guardando...' : (user ? 'Guardar cambios' : 'Crear usuario') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
