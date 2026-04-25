<script setup>
import { Head } from '@inertiajs/vue3'

const props = defineProps({
    tenant: { type: Object, required: true },
})

const nextSteps = [
    'Inicia sesión en el nuevo workspace con el email de administrador.',
    'Ve a Configuración → colores para personalizar la apariencia.',
    'Registra las empresas (mandantes y contratistas) en el módulo Empresas.',
    'Crea el primer contrato e invita al equipo desde Usuarios.',
    'Opcional: configura la integración con Microsoft 365 para SharePoint.',
]
</script>

<template>
    <Head title="Workspace creado — Claim Guard" />

    <div class="flex min-h-screen" style="background: var(--color-bg-page); font-family: var(--font-body);">

        <!-- Panel izquierdo: branding (mismo patrón que login.html) -->
        <div class="hidden lg:flex lg:w-5/12 flex-col justify-between p-12"
             style="background: var(--color-bg-sidebar);">

            <div>
                <div class="flex items-center gap-3 mb-12">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center"
                         style="background: var(--color-primary);">
                        <span class="material-symbols-outlined" style="font-size: 20px; color: #fff;">shield</span>
                    </div>
                    <span class="text-lg font-bold" style="color: var(--color-text-sidebar); font-family: var(--font-headline);">Claim Guard</span>
                </div>

                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-6"
                     style="background: rgba(34,197,94,0.15); border: 2px solid rgba(34,197,94,0.4);">
                    <span class="material-symbols-outlined" style="font-size: 32px; color: #22c55e;">check_circle</span>
                </div>

                <h1 class="text-3xl font-extrabold mb-3"
                    style="color: var(--color-text-sidebar); font-family: var(--font-headline);">
                    ¡Workspace creado!
                </h1>
                <p style="color: var(--color-text-sidebar-muted); line-height: 1.7;">
                    El tenant <strong style="color: var(--color-text-sidebar);">{{ tenant.name }}</strong> está listo para usar.
                    Ya puedes iniciar sesión como administrador.
                </p>
            </div>

            <p class="text-xs" style="color: var(--color-text-sidebar-muted);">© 2025 Claim Guard. Todos los derechos reservados.</p>
        </div>

        <!-- Panel derecho: detalles -->
        <div class="flex-1 flex items-center justify-center p-8">
            <div class="w-full max-w-lg">

                <!-- Header móvil -->
                <div class="lg:hidden flex items-center gap-3 mb-8">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center"
                         style="background: var(--color-primary);">
                        <span class="material-symbols-outlined" style="font-size: 20px; color: #fff;">shield</span>
                    </div>
                    <span class="text-lg font-bold" style="color: var(--color-text-primary); font-family: var(--font-headline);">Claim Guard</span>
                </div>

                <!-- Card datos del workspace -->
                <div class="rounded-2xl p-6 mb-5"
                     style="background: var(--color-bg-card); border: 1px solid var(--color-border); box-shadow: 0 1px 3px rgba(0,0,0,0.07);">

                    <p class="text-xs font-bold uppercase tracking-wider mb-4"
                       style="color: var(--color-text-muted);">Datos del workspace</p>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--color-text-muted);">Nombre</span>
                            <span class="text-sm font-semibold" style="color: var(--color-text-primary);">{{ tenant.name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--color-text-muted);">URL de acceso</span>
                            <span class="text-sm font-mono" style="color: var(--color-primary);">{{ tenant.slug }}.claimguard.cl</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--color-text-muted);">Base de datos</span>
                            <span class="text-sm font-mono" style="color: var(--color-text-secondary);">{{ tenant.database }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--color-text-muted);">Email</span>
                            <span class="text-sm" style="color: var(--color-text-primary);">{{ tenant.email }}</span>
                        </div>
                    </div>

                    <div class="mt-5 pt-4 flex items-start gap-3"
                         style="border-top: 1px solid var(--color-border);">
                        <span class="material-symbols-outlined flex-shrink-0 mt-0.5"
                              style="font-size: 18px; color: var(--color-primary);">info</span>
                        <p class="text-xs" style="color: var(--color-text-muted);">
                            El administrador puede iniciar sesión en
                            <strong style="color: var(--color-primary);">{{ tenant.slug }}.claimguard.cl/login</strong>
                            con el email y contraseña configurados.
                        </p>
                    </div>
                </div>

                <!-- Card próximos pasos -->
                <div class="rounded-2xl p-6 mb-6"
                     style="background: var(--color-bg-card); border: 1px solid var(--color-border); box-shadow: 0 1px 3px rgba(0,0,0,0.07);">
                    <p class="text-xs font-bold uppercase tracking-wider mb-4"
                       style="color: var(--color-text-muted);">Próximos pasos</p>
                    <div class="space-y-3">
                        <div v-for="step in nextSteps" :key="step"
                             class="flex items-start gap-3">
                            <span class="material-symbols-outlined flex-shrink-0 mt-0.5"
                                  style="font-size: 18px; color: var(--color-success);">arrow_right</span>
                            <p class="text-sm" style="color: var(--color-text-secondary);">{{ step }}</p>
                        </div>
                    </div>
                </div>

                <!-- Botón -->
                <a :href="route('onboarding.show')"
                   class="inline-flex items-center gap-2 px-6 py-3 rounded-full text-sm font-bold transition-all active:scale-95"
                   style="background: var(--color-primary); color: #fff; box-shadow: 0 4px 14px rgba(42,100,150,0.3); text-decoration: none;">
                    <span class="material-symbols-outlined" style="font-size: 18px;">add</span>
                    Crear otro workspace
                </a>
            </div>
        </div>
    </div>
</template>
