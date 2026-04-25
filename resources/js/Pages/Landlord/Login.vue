<script setup>
import { useForm, Head } from '@inertiajs/vue3'

const props = defineProps({
    flash: { type: Object, default: () => ({}) },
})

const form = useForm({
    email:    '',
    password: '',
    remember: false,
})

function submit() {
    form.post(route('landlord.login.store'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Acceso Landlord — Claim Guard" />

    <div class="flex min-h-screen" style="background: var(--color-bg-page); font-family: var(--font-body);">

        <!-- Panel izquierdo: branding -->
        <div class="hidden lg:flex lg:w-5/12 flex-col justify-between p-12"
             style="background: var(--color-bg-sidebar); border-right: 1px solid rgba(255,255,255,0.06);">

            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                     style="background: var(--color-primary);">
                    <span class="material-symbols-outlined" style="font-size:20px; color:#fff;">shield</span>
                </div>
                <div>
                    <p class="text-lg font-extrabold leading-tight"
                       style="color: var(--color-text-sidebar); font-family: var(--font-headline);">Claim Guard</p>
                    <p class="text-xs" style="color: var(--color-text-sidebar-muted);">Panel Landlord</p>
                </div>
            </div>

            <div>
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-6"
                     style="background: rgba(255,255,255,0.08);">
                    <span class="material-symbols-outlined" style="font-size:28px; color: var(--color-text-sidebar);">admin_panel_settings</span>
                </div>
                <h2 class="text-3xl font-extrabold mb-3 leading-tight"
                    style="color: var(--color-text-sidebar); font-family: var(--font-headline);">
                    Acceso restringido
                </h2>
                <p class="text-sm leading-relaxed" style="color: var(--color-text-sidebar-muted);">
                    Solo los super administradores pueden acceder a este panel.
                    Desde aquí gestionas workspaces, planes y suscripciones de toda la plataforma.
                </p>

                <div class="mt-8 space-y-3">
                    <div v-for="item in [
                            'Gestión de todos los workspaces',
                            'Activar y suspender tenants',
                            'Configurar planes y suscripciones',
                            'Crear nuevos workspaces',
                         ]" :key="item" class="flex items-center gap-3">
                        <span class="material-symbols-outlined flex-shrink-0"
                              style="font-size:16px; color: var(--color-primary);">check_circle</span>
                        <p class="text-sm" style="color: var(--color-text-sidebar-muted);">{{ item }}</p>
                    </div>
                </div>
            </div>

            <p class="text-xs" style="color: var(--color-text-sidebar-muted);">
                Claim Guard © {{ new Date().getFullYear() }} — Acceso solo para super administradores
            </p>
        </div>

        <!-- Panel derecho: formulario -->
        <div class="flex-1 flex items-center justify-center p-8">
            <div class="w-full max-w-sm">

                <!-- Logo móvil -->
                <div class="flex items-center gap-3 mb-8 lg:hidden">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                         style="background: var(--color-primary);">
                        <span class="material-symbols-outlined" style="font-size:18px; color:#fff;">shield</span>
                    </div>
                    <span class="font-extrabold" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                        Claim Guard
                    </span>
                </div>

                <div class="mb-7">
                    <h1 class="text-2xl font-extrabold"
                        style="color: var(--color-text-primary); font-family: var(--font-headline);">
                        Iniciar sesión
                    </h1>
                    <p class="text-sm mt-1" style="color: var(--color-text-muted);">
                        Panel de administración central
                    </p>
                </div>

                <!-- Error flash -->
                <div v-if="flash?.error" class="mb-5 flex items-center gap-3 px-4 py-3 rounded-2xl"
                     style="background: var(--color-error-container); border: 1px solid var(--color-error);">
                    <span class="material-symbols-outlined flex-shrink-0" style="color: var(--color-on-error-container); font-size:20px;">error</span>
                    <span class="text-sm" style="color: var(--color-on-error-container);">{{ flash.error }}</span>
                </div>

                <!-- Card formulario -->
                <div class="rounded-2xl p-6"
                     style="background: var(--color-bg-card); border: 1px solid var(--color-border); box-shadow: var(--shadow-card);">

                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-semibold block mb-1.5"
                                   style="color: var(--color-text-secondary);">Email</label>
                            <input v-model="form.email"
                                   type="email" placeholder="admin@claimguard.cl"
                                   autocomplete="email"
                                   class="w-full h-12 px-4 rounded-xl text-sm border-0 outline-none transition-all"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);"
                                   @focus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   @blur="e => e.target.style.background = 'var(--color-bg-input)'"
                                   @keyup.enter="submit" />
                            <p v-if="form.errors.email" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold block mb-1.5"
                                   style="color: var(--color-text-secondary);">Contraseña</label>
                            <input v-model="form.password"
                                   type="password" placeholder="••••••••"
                                   autocomplete="current-password"
                                   class="w-full h-12 px-4 rounded-xl text-sm border-0 outline-none transition-all"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);"
                                   @focus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   @blur="e => e.target.style.background = 'var(--color-bg-input)'"
                                   @keyup.enter="submit" />
                            <p v-if="form.errors.password" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.password }}</p>
                        </div>

                        <label class="flex items-center gap-2.5 cursor-pointer select-none">
                            <input v-model="form.remember" type="checkbox"
                                   class="w-4 h-4 rounded accent-primary" />
                            <span class="text-sm" style="color: var(--color-text-secondary);">Mantener sesión iniciada</span>
                        </label>
                    </div>

                    <button @click="submit"
                            :disabled="form.processing"
                            class="w-full mt-5 h-12 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition-all active:scale-95"
                            style="background: var(--color-primary); color: #fff; box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                        <span v-if="form.processing" class="material-symbols-outlined animate-spin" style="font-size:18px;">progress_activity</span>
                        {{ form.processing ? 'Verificando…' : 'Ingresar al panel' }}
                    </button>
                </div>

                <p class="text-center text-xs mt-5" style="color: var(--color-text-muted);">
                    ¿Eres administrador de un workspace?
                    <a href="/login" style="color: var(--color-primary);">Ir al login de tenant →</a>
                </p>
            </div>
        </div>
    </div>
</template>
