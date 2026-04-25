<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    user:  { type: Object, default: null },
    roles: { type: Array,  default: () => [] },
})

const isEditing = computed(() => !!props.user)
const title     = computed(() => isEditing.value ? 'Editar Usuario' : 'Nuevo Usuario')

const roleLabels = {
    tenant_admin:   'Administrador',
    contract_admin: 'Admin. de Contratos',
    field_engineer: 'Ingeniero de Campo',
    manager:        'Gerente',
    legal:          'Legal',
    counterpart:    'Contraparte',
}

const form = useForm({
    name:     props.user?.name     ?? '',
    email:    props.user?.email    ?? '',
    password: '',
    role:     props.user?.role     ?? '',
})

function submit() {
    if (isEditing.value) {
        form.put(route('users.update', props.user.id))
    } else {
        form.post(route('users.store'))
    }
}
</script>

<template>
    <AppLayout :title="title">
        <!-- Encabezado -->
        <div class="mb-8">
            <div class="flex items-center gap-2 text-sm font-bold mb-2 uppercase tracking-wide" style="color: var(--color-secondary);">
                <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">group</span>
                {{ isEditing ? 'Modificar usuario' : 'Agregar usuario' }}
            </div>
            <h2
                class="text-3xl font-extrabold tracking-tight mb-2"
                style="font-family: var(--font-headline); color: var(--color-text-primary);"
            >
                {{ title }}
            </h2>
            <p class="text-base" style="color: var(--color-text-secondary); max-width: 600px;">
                {{ isEditing
                    ? 'Modifica los datos del usuario o su rol de acceso en este espacio de trabajo.'
                    : 'Registra un nuevo usuario y asígnale un rol para controlar su nivel de acceso.' }}
            </p>
        </div>

        <form class="space-y-8" @submit.prevent="submit">
            <!-- Sección: Datos personales -->
            <section
                class="p-10 rounded-xl shadow-sm space-y-8"
                style="background: var(--color-bg-card);"
            >
                <div class="flex items-center gap-4 mb-2">
                    <div
                        class="w-8 h-8 rounded-lg flex items-center justify-center"
                        style="background: var(--color-bg-hover);"
                    >
                        <span class="material-symbols-outlined text-lg" style="color: var(--color-primary);">person</span>
                    </div>
                    <h3 class="text-lg font-bold" style="color: var(--color-text-primary);">Datos del usuario</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Nombre -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                            Nombre completo <span style="color: var(--color-error);">*</span>
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
                        <p v-if="form.errors.name" class="text-xs ml-1" style="color: var(--color-error);">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Correo -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                            Correo electrónico <span style="color: var(--color-error);">*</span>
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
                        <p v-if="form.errors.email" class="text-xs ml-1" style="color: var(--color-error);">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Contraseña -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                            Contraseña
                            <span v-if="!isEditing" style="color: var(--color-error);">*</span>
                            <span v-else class="font-normal ml-1" style="color: var(--color-text-muted);">(dejar en blanco para no cambiar)</span>
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
                        <p v-if="form.errors.password" class="text-xs ml-1" style="color: var(--color-error);">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Rol -->
                    <div class="space-y-2">
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
                        <p v-if="form.errors.role" class="text-xs ml-1" style="color: var(--color-error);">
                            {{ form.errors.role }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Descripción de roles -->
            <section
                class="p-8 rounded-xl"
                style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant);"
            >
                <div class="flex items-center gap-3 mb-5">
                    <span class="material-symbols-outlined" style="color: var(--color-primary); font-size: 20px;">info</span>
                    <h3 class="text-sm font-bold" style="color: var(--color-text-primary);">Descripción de roles</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs" style="color: var(--color-text-secondary);">
                    <div class="flex gap-2">
                        <span class="font-bold" style="color: var(--color-text-primary); min-width: 140px;">Administrador</span>
                        <span>Acceso total al espacio de trabajo, configuración y aprobación de órdenes de cambio.</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="font-bold" style="color: var(--color-text-primary); min-width: 140px;">Admin. Contratos</span>
                        <span>Gestión completa de contratos asignados: eventos, cartas y órdenes de cambio.</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="font-bold" style="color: var(--color-text-primary); min-width: 140px;">Ing. de Campo</span>
                        <span>Registro de eventos, avance de hitos y carga de documentos en sus contratos.</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="font-bold" style="color: var(--color-text-primary); min-width: 140px;">Gerente</span>
                        <span>Solo lectura y acceso al dashboard ejecutivo y al indicador de riesgo.</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="font-bold" style="color: var(--color-text-primary); min-width: 140px;">Legal</span>
                        <span>Acceso al expediente de claim y lectura completa del historial contractual.</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="font-bold" style="color: var(--color-text-primary); min-width: 140px;">Contraparte</span>
                        <span>Portal externo: visualiza únicamente la información compartida explícitamente.</span>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer
                class="flex items-center justify-between pt-8"
                style="border-top: 1px solid var(--color-border-variant);"
            >
                <Link
                    :href="route('users.index')"
                    class="px-8 py-3 font-bold transition-colors"
                    style="color: var(--color-text-secondary);"
                    :onMouseover="e => e.currentTarget.style.color = 'var(--color-text-primary)'"
                    :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                >
                    Cancelar
                </Link>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-10 py-4 rounded-full font-extrabold transition-all active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed"
                    style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); font-family: var(--font-body);"
                >
                    {{ form.processing ? 'Guardando...' : (isEditing ? 'Guardar cambios' : 'Crear usuario') }}
                </button>
            </footer>
        </form>
    </AppLayout>
</template>
