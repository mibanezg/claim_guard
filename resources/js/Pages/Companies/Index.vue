<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import CompanyModal from '@/Components/CompanyModal.vue'
import { router, usePage } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
    companies: Object,
    filters:   Object,
})

const page  = usePage()
const flash = computed(() => page.props.flash)
const { confirmDelete } = useConfirm()

const search     = ref(props.filters?.search ?? '')
const typeFilter = ref(props.filters?.type   ?? '')
const showModal  = ref(false)
const editing    = ref(null)

let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => applyFilters(), 400)
})
watch(typeFilter, () => applyFilters())

function applyFilters() {
    router.get(route('companies.index'), {
        search: search.value  || undefined,
        type:   typeFilter.value || undefined,
    }, { preserveState: true, replace: true })
}

function openCreate() {
    editing.value = null
    showModal.value = true
}

function openEdit(company) {
    editing.value = company
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    editing.value   = null
}

async function handleDelete(company) {
    const confirmed = await confirmDelete(company.name)
    if (confirmed) {
        router.delete(route('companies.destroy', company.id))
    }
}

const typeColors = {
    mandante:    { bg: 'var(--color-primary-container)',    text: 'var(--color-on-primary-container)' },
    contratista: { bg: 'var(--color-secondary-container)',  text: 'var(--color-on-secondary-container)' },
    ambos:       { bg: 'var(--color-bg-elevated)',          text: 'var(--color-text-secondary)' },
}
</script>

<template>
    <AppLayout title="Empresas">
        <!-- Encabezado -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2
                    class="text-2xl font-extrabold tracking-tight mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);"
                >Empresas</h2>
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    Mandantes y contratistas registrados en este espacio de trabajo
                </p>
            </div>
            <button
                class="flex items-center gap-2 px-6 py-3 rounded-full font-bold text-sm transition-all active:scale-95"
                style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;"
                @click="openCreate"
            >
                <span class="material-symbols-outlined" style="font-size: 18px;">add</span>
                Nueva Empresa
            </button>
        </div>

        <!-- Flash -->
        <div
            v-if="flash?.success"
            class="flex items-center gap-3 p-4 rounded-xl mb-6"
            style="background: var(--color-success-container); color: var(--color-on-success-container);"
        >
            <span class="material-symbols-outlined">check_circle</span>
            {{ flash.success }}
        </div>

        <!-- Filtros -->
        <div
            class="flex flex-wrap items-center gap-3 p-4 rounded-xl mb-6"
            style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant);"
        >
            <div
                class="flex items-center gap-2 flex-1 min-w-48 px-4 py-2 rounded-xl"
                style="background: var(--color-bg-input);"
            >
                <span class="material-symbols-outlined text-sm" style="color: var(--color-text-muted);">search</span>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar por nombre o RUT..."
                    class="bg-transparent border-none outline-none flex-1 text-sm"
                    style="font-family: var(--font-body); color: var(--color-text-primary);"
                >
            </div>
            <select
                v-model="typeFilter"
                class="px-4 py-2 rounded-xl text-sm border-none outline-none"
                style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"
            >
                <option value="">Todos los tipos</option>
                <option value="mandante">Mandante</option>
                <option value="contratista">Contratista</option>
                <option value="ambos">Ambos</option>
            </select>
        </div>

        <!-- Tabla -->
        <div class="rounded-3xl overflow-hidden" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr style="background: var(--color-bg-sidebar);">
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Empresa</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">RUT</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Tipo</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Contacto</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-right" style="color: var(--color-text-secondary);">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="company in companies.data"
                            :key="company.id"
                            class="transition-colors"
                            style="border-top: 1px solid var(--color-border-variant);"
                            :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                            :onMouseout="e => e.currentTarget.style.background = ''"
                        >
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--color-bg-input);">
                                        <span class="material-symbols-outlined text-sm" style="color: var(--color-primary);">business</span>
                                    </div>
                                    <span class="font-semibold text-sm" style="color: var(--color-text-primary);">{{ company.name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-sm" style="color: var(--color-text-secondary);">{{ company.rut }}</td>
                            <td class="px-6 py-5">
                                <span
                                    class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-bold"
                                    :style="`background: ${typeColors[company.type]?.bg}; color: ${typeColors[company.type]?.text};`"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full" :style="`background: ${typeColors[company.type]?.text};`"></span>
                                    {{ company.type_label }}
                                </span>
                            </td>
                            <td class="px-6 py-5 text-sm" style="color: var(--color-text-secondary);">
                                <div v-if="company.contact_name">{{ company.contact_name }}</div>
                                <div v-if="company.contact_email" class="text-xs" style="color: var(--color-text-muted);">{{ company.contact_email }}</div>
                                <span v-if="!company.contact_name && !company.contact_email" style="color: var(--color-text-muted);">—</span>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                        style="color: var(--color-text-secondary); background: none; border: none; cursor: pointer;"
                                        :onMouseover="e => e.currentTarget.style.color = 'var(--color-primary)'"
                                        :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                                        title="Editar"
                                        @click="openEdit(company)"
                                    >
                                        <span class="material-symbols-outlined" style="font-size: 18px;">edit</span>
                                    </button>
                                    <button
                                        class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                        style="color: var(--color-text-secondary); background: none; border: none; cursor: pointer;"
                                        :onMouseover="e => e.currentTarget.style.color = 'var(--color-error)'"
                                        :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                                        title="Eliminar"
                                        @click="handleDelete(company)"
                                    >
                                        <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="companies.data.length === 0">
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="material-symbols-outlined" style="font-size: 48px; color: var(--color-text-muted);">business</span>
                                    <p class="font-semibold" style="color: var(--color-text-secondary);">No hay empresas registradas</p>
                                    <p class="text-sm" style="color: var(--color-text-muted);">
                                        {{ search || typeFilter ? 'Prueba con otros filtros' : 'Crea la primera empresa para comenzar' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="companies.meta?.last_page > 1"
                class="px-6 py-4 flex items-center justify-between"
                style="background: var(--color-bg-sidebar); border-top: 1px solid var(--color-border-variant);"
            >
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    Mostrando {{ companies.meta.from }}–{{ companies.meta.to }} de {{ companies.meta.total }} empresas
                </p>
                <div class="flex items-center gap-2">
                    <a v-if="companies.links.prev" :href="companies.links.prev" class="px-3 py-1.5 rounded-lg text-sm font-semibold" style="color: var(--color-primary);">← Anterior</a>
                    <a v-if="companies.links.next" :href="companies.links.next" class="px-3 py-1.5 rounded-lg text-sm font-semibold" style="color: var(--color-primary);">Siguiente →</a>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <CompanyModal
            :show="showModal"
            :company="editing"
            @close="closeModal"
        />
    </AppLayout>
</template>
