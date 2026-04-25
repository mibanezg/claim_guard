<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UserModal from '@/Components/UserModal.vue'
import { router, usePage } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
    users:   Object,
    filters: Object,
    roles:   Array,
})

const page  = usePage()
const flash = computed(() => page.props.flash)
const { confirmDelete } = useConfirm()

const search     = ref(props.filters?.search ?? '')
const roleFilter = ref(props.filters?.role   ?? '')
const showModal  = ref(false)
const editing    = ref(null)

let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => applyFilters(), 400)
})
watch(roleFilter, () => applyFilters())

function applyFilters() {
    router.get(route('users.index'), {
        search: search.value     || undefined,
        role:   roleFilter.value || undefined,
    }, { preserveState: true, replace: true })
}

function openCreate() {
    editing.value  = null
    showModal.value = true
}

function openEdit(user) {
    editing.value  = user
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    editing.value   = null
}

async function handleDelete(user) {
    const confirmed = await confirmDelete(user.name)
    if (confirmed) {
        router.delete(route('users.destroy', user.id))
    }
}

const roleColors = {
    tenant_admin:   { bg: 'var(--color-primary-container)',   text: 'var(--color-on-primary-container)' },
    contract_admin: { bg: 'var(--color-secondary-container)', text: 'var(--color-on-secondary-container)' },
    field_engineer: { bg: 'var(--color-bg-elevated)',         text: 'var(--color-text-secondary)' },
    manager:        { bg: 'var(--color-primary-container)',   text: 'var(--color-on-primary-container)' },
    legal:          { bg: 'var(--color-secondary-container)', text: 'var(--color-on-secondary-container)' },
    counterpart:    { bg: 'var(--color-bg-elevated)',         text: 'var(--color-text-muted)' },
}
</script>

<template>
    <AppLayout title="Usuarios">
        <!-- Encabezado -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2
                    class="text-2xl font-extrabold tracking-tight mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);"
                >Usuarios</h2>
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    Miembros del espacio de trabajo y sus roles de acceso
                </p>
            </div>
            <button
                class="flex items-center gap-2 px-6 py-3 rounded-full font-bold text-sm transition-all active:scale-95"
                style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;"
                @click="openCreate"
            >
                <span class="material-symbols-outlined" style="font-size: 18px;">person_add</span>
                Nuevo Usuario
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
                    placeholder="Buscar por nombre o correo..."
                    class="bg-transparent border-none outline-none flex-1 text-sm"
                    style="font-family: var(--font-body); color: var(--color-text-primary);"
                >
            </div>
            <select
                v-model="roleFilter"
                class="px-4 py-2 rounded-xl text-sm border-none outline-none"
                style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"
            >
                <option value="">Todos los roles</option>
                <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
            </select>
        </div>

        <!-- Tabla -->
        <div class="rounded-3xl overflow-hidden" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr style="background: var(--color-bg-sidebar);">
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Usuario</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Correo</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Rol</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest" style="color: var(--color-text-secondary);">Creado</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-right" style="color: var(--color-text-secondary);">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="transition-colors"
                            style="border-top: 1px solid var(--color-border-variant);"
                            :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                            :onMouseout="e => e.currentTarget.style.background = ''"
                        >
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"
                                        style="background: var(--gradient-primary); color: var(--color-on-primary);"
                                    >
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="font-semibold text-sm" style="color: var(--color-text-primary);">{{ user.name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-sm" style="color: var(--color-text-secondary);">{{ user.email }}</td>
                            <td class="px-6 py-5">
                                <span
                                    class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-bold"
                                    :style="`background: ${roleColors[user.role]?.bg ?? 'var(--color-bg-elevated)'}; color: ${roleColors[user.role]?.text ?? 'var(--color-text-secondary)'};`"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full" :style="`background: ${roleColors[user.role]?.text ?? 'var(--color-text-secondary)'};`"></span>
                                    {{ user.role_label }}
                                </span>
                            </td>
                            <td class="px-6 py-5 text-sm" style="color: var(--color-text-secondary);">{{ user.created_at }}</td>
                            <td class="px-6 py-5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                        style="color: var(--color-text-secondary); background: none; border: none; cursor: pointer;"
                                        :onMouseover="e => e.currentTarget.style.color = 'var(--color-primary)'"
                                        :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                                        title="Editar"
                                        @click="openEdit(user)"
                                    >
                                        <span class="material-symbols-outlined" style="font-size: 18px;">edit</span>
                                    </button>
                                    <button
                                        class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                                        style="color: var(--color-text-secondary); background: none; border: none; cursor: pointer;"
                                        :onMouseover="e => e.currentTarget.style.color = 'var(--color-error)'"
                                        :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                                        title="Eliminar"
                                        @click="handleDelete(user)"
                                    >
                                        <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="users.data.length === 0">
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="material-symbols-outlined" style="font-size: 48px; color: var(--color-text-muted);">group</span>
                                    <p class="font-semibold" style="color: var(--color-text-secondary);">No hay usuarios registrados</p>
                                    <p class="text-sm" style="color: var(--color-text-muted);">
                                        {{ search || roleFilter ? 'Prueba con otros filtros' : 'Agrega el primer usuario para comenzar' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="users.meta?.last_page > 1"
                class="px-6 py-4 flex items-center justify-between"
                style="background: var(--color-bg-sidebar); border-top: 1px solid var(--color-border-variant);"
            >
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    Mostrando {{ users.meta.from }}–{{ users.meta.to }} de {{ users.meta.total }} usuarios
                </p>
                <div class="flex items-center gap-2">
                    <a v-if="users.links.prev" :href="users.links.prev" class="px-3 py-1.5 rounded-lg text-sm font-semibold" style="color: var(--color-primary);">← Anterior</a>
                    <a v-if="users.links.next" :href="users.links.next" class="px-3 py-1.5 rounded-lg text-sm font-semibold" style="color: var(--color-primary);">Siguiente →</a>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <UserModal
            :show="showModal"
            :user="editing"
            :roles="roles"
            @close="closeModal"
        />
    </AppLayout>
</template>
