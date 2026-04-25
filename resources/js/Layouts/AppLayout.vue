<script setup>
import { usePage, Head } from '@inertiajs/vue3'
import { computed, onMounted } from 'vue'
import AppSidebar from '@/Components/AppSidebar.vue'
import AppHeader from '@/Components/AppHeader.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Dashboard',
    },
})

const page = usePage()
const tenantColors = computed(() => page.props.tenant_colors ?? null)

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
        <!-- SideNavBar — fijo a la izquierda -->
        <AppSidebar />

        <!-- Main Canvas: width explícito para no desbordarse del viewport -->
        <main
            class="flex flex-col min-h-screen overflow-x-hidden"
            style="margin-left: var(--sidebar-width); width: calc(100vw - var(--sidebar-width));"
        >
            <!-- TopAppBar — fijo en la parte superior -->
            <AppHeader :title="title">
                <template v-if="$slots.subnav" #subnav>
                    <slot name="subnav" />
                </template>
            </AppHeader>

            <!-- Área de contenido -->
            <div
                class="flex-1 p-8"
                style="margin-top: var(--header-height);"
            >
                <slot />
            </div>
        </main>
    </div>
</template>
