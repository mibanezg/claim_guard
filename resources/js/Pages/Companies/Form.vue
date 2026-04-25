<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    company: {
        type: Object,
        default: null,
    },
})

const isEditing = computed(() => !!props.company)
const title = computed(() => isEditing.value ? 'Editar Empresa' : 'Nueva Empresa')

const form = useForm({
    name:          props.company?.name          ?? '',
    rut:           props.company?.rut           ?? '',
    address:       props.company?.address       ?? '',
    contact_name:  props.company?.contact_name  ?? '',
    contact_email: props.company?.contact_email ?? '',
    type:          props.company?.type          ?? 'contratista',
})

function submit() {
    if (isEditing.value) {
        form.put(route('companies.update', props.company.id))
    } else {
        form.post(route('companies.store'))
    }
}
</script>

<template>
    <AppLayout :title="title">
        <!-- Encabezado de sección -->
        <div class="mb-8">
            <div class="flex items-center gap-2 text-sm font-bold mb-2 uppercase tracking-wide" style="color: var(--color-secondary);">
                <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">business</span>
                {{ isEditing ? 'Modificar empresa' : 'Registrar empresa' }}
            </div>
            <h2
                class="text-3xl font-extrabold tracking-tight mb-2"
                style="font-family: var(--font-headline); color: var(--color-text-primary);"
            >
                {{ title }}
            </h2>
            <p class="text-base" style="color: var(--color-text-secondary); max-width: 600px;">
                {{ isEditing
                    ? 'Modifica los datos de la empresa. El RUT solo puede editarse si no tiene contratos asociados.'
                    : 'Registra un mandante o contratista para asociarlo a contratos en este espacio de trabajo.' }}
            </p>
        </div>

        <form class="space-y-8" @submit.prevent="submit">
            <!-- Sección: Datos de la empresa — patrón de crear_contrato.html -->
            <section
                class="p-10 rounded-xl shadow-sm space-y-8"
                style="background: var(--color-bg-card);"
            >
                <!-- Encabezado de sección -->
                <div class="flex items-center gap-4 mb-2">
                    <div
                        class="w-8 h-8 rounded-lg flex items-center justify-center"
                        style="background: var(--color-bg-hover);"
                    >
                        <span class="material-symbols-outlined text-lg" style="color: var(--color-primary);">info</span>
                    </div>
                    <h3 class="text-lg font-bold" style="color: var(--color-text-primary);">Información general</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Nombre -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                            Nombre de la empresa <span style="color: var(--color-error);">*</span>
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
                        <p v-if="form.errors.name" class="text-xs ml-1" style="color: var(--color-error);">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- RUT -->
                    <div class="space-y-2">
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
                        <p v-if="form.errors.rut" class="text-xs ml-1" style="color: var(--color-error);">
                            {{ form.errors.rut }}
                        </p>
                    </div>

                    <!-- Tipo -->
                    <div class="space-y-2">
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
                        <p v-if="form.errors.type" class="text-xs ml-1" style="color: var(--color-error);">
                            {{ form.errors.type }}
                        </p>
                    </div>

                    <!-- Dirección -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                            Dirección
                        </label>
                        <input
                            v-model="form.address"
                            type="text"
                            placeholder="Ej: Av. Apoquindo 4500, Las Condes"
                            class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                            :style="`background: var(--color-bg-input); color: var(--color-text-primary);`"
                            :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                            :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                        >
                    </div>
                </div>
            </section>

            <!-- Sección: Contacto -->
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
                    <h3 class="text-lg font-bold" style="color: var(--color-text-primary);">Contacto principal</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Nombre contacto -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                            Nombre del contacto
                        </label>
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
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                            Correo electrónico
                        </label>
                        <input
                            v-model="form.contact_email"
                            type="email"
                            placeholder="contacto@empresa.cl"
                            class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                            :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.contact_email ? 'outline: 2px solid var(--color-error);' : ''}`"
                            :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                            :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                        >
                        <p v-if="form.errors.contact_email" class="text-xs ml-1" style="color: var(--color-error);">
                            {{ form.errors.contact_email }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Footer de acciones — patrón crear_contrato.html -->
            <footer
                class="flex items-center justify-between pt-8"
                style="border-top: 1px solid var(--color-border-variant);"
            >
                <Link
                    :href="route('companies.index')"
                    class="px-8 py-3 font-bold transition-colors"
                    style="color: var(--color-text-secondary);"
                    :onMouseover="e => e.currentTarget.style.color = 'var(--color-text-primary)'"
                    :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                >
                    Cancelar
                </Link>

                <div class="flex items-center gap-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-10 py-4 rounded-full font-extrabold transition-all active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed"
                        style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); font-family: var(--font-body);"
                    >
                        {{ form.processing ? 'Guardando...' : (isEditing ? 'Guardar cambios' : 'Crear empresa') }}
                    </button>
                </div>
            </footer>
        </form>
    </AppLayout>
</template>
