<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps({
    title: {
        type: String,
        default: '',
    },
})

const page = usePage()
const user = computed(() => page.props.auth.user)

function getInitials(name) {
    return name
        ?.split(' ')
        .slice(0, 2)
        .map(n => n[0])
        .join('')
        .toUpperCase() ?? '?'
}
</script>

<template>
    <!-- TopAppBar — idéntico a dash_1.html header -->
    <header
        class="fixed top-0 right-0 z-50 flex justify-between items-center px-8 transition-all duration-300 ease-in-out"
        style="
            left: var(--sidebar-width);
            height: var(--header-height);
            background: rgba(247, 249, 251, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--color-border-variant);
        "
    >
        <!-- Izquierda: título de la página -->
        <div class="flex items-center gap-8">
            <h1
                class="text-xl font-bold tracking-tight"
                style="font-family: var(--font-headline); color: var(--color-text-primary);"
            >
                {{ title }}
            </h1>
            <!-- Slot para sub-navegación (usado en dash_2) -->
            <slot name="subnav" />
        </div>

        <!-- Derecha: acciones y usuario -->
        <div class="flex items-center gap-4">
            <!-- Búsqueda -->
            <div
                class="hidden md:flex items-center px-4 py-2 rounded-full gap-2"
                style="background: var(--color-bg-sidebar);"
            >
                <span class="material-symbols-outlined" style="color: var(--color-text-secondary); font-size: 18px;">search</span>
                <input
                    class="bg-transparent border-none outline-none text-sm w-48"
                    style="font-family: var(--font-body); color: var(--color-text-primary);"
                    placeholder="Buscar..."
                    type="text"
                >
            </div>

            <!-- Notificaciones -->
            <button
                class="w-10 h-10 flex items-center justify-center rounded-lg active:scale-95 transition-all"
                style="color: var(--color-primary);"
                :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-sidebar)'"
                :onMouseout="e => e.currentTarget.style.background = ''"
            >
                <span class="material-symbols-outlined">notifications</span>
            </button>

            <!-- Avatar del usuario con iniciales -->
            <div
                class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold border-2 border-white shadow-sm select-none"
                style="background: var(--gradient-primary); color: var(--color-on-primary);"
                :title="user?.name"
            >
                {{ getInitials(user?.name) }}
            </div>
        </div>
    </header>
</template>
