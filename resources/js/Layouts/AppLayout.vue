<script setup>
import { usePage, Head } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import AppSidebar from '@/Components/AppSidebar.vue'
import AppHeader from '@/Components/AppHeader.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Dashboard',
    },
})

const page = usePage()
const tenantColors  = computed(() => page.props.tenant_colors ?? null)
const sidebarOpen   = ref(false)

// Cierra sidebar al navegar (cambio de URL)
watch(() => page.url, () => { sidebarOpen.value = false })

// Inyecta los colores personalizados del tenant como CSS variables en el :root
onMounted(() => {
    if (tenantColors.value) {
        const root = document.documentElement
        const map = {
            color_primary:      '--color-primary',
            color_primary_dim:  '--color-primary-dim',
            color_secondary:    '--color-secondary',
            color_sidebar_bg:   '--color-bg-sidebar',
            color_text_primary: '--color-text-primary',
        }
        Object.entries(map).forEach(([key, cssVar]) => {
            if (tenantColors.value[key]) {
                root.style.setProperty(cssVar, tenantColors.value[key])
            }
        })
    }
})
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen" style="background: var(--color-bg-page);">
        <!-- Backdrop oscuro — solo mobile cuando sidebar está abierto -->
        <Transition name="fade">
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 md:hidden"
                style="background: rgba(0,0,0,0.5);"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- Sidebar -->
        <AppSidebar
            :is-open="sidebarOpen"
            @close="sidebarOpen = false"
        />

        <!-- Main Canvas -->
        <main class="app-main flex flex-col min-h-screen overflow-x-hidden">
            <AppHeader :title="title" @toggle-sidebar="sidebarOpen = !sidebarOpen">
                <template v-if="$slots.subnav" #subnav>
                    <slot name="subnav" />
                </template>
            </AppHeader>

            <!-- Área de contenido -->
            <div
                class="flex-1 p-4 md:p-8"
                style="margin-top: var(--header-height);"
            >
                <slot />
            </div>
        </main>
    </div>
</template>

<style scoped>
/* Mobile: main ocupa todo el ancho */
.app-main {
    width: 100%;
    margin-left: 0;
}
/* Desktop: main respeta el sidebar */
@media (min-width: 768px) {
    .app-main {
        margin-left: var(--sidebar-width);
        width: calc(100vw - var(--sidebar-width));
    }
}

/* Transición del backdrop */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
