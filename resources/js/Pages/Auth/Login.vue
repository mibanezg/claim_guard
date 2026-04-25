<script setup>
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const form = useForm({
    email:    '',
    password: '',
    remember: false,
})

const showPassword = ref(false)

function submit() {
    form.post(route('login.store'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <main class="flex min-h-screen w-full" style="font-family: var(--font-body);">

        <!-- Panel izquierdo — branding (solo desktop) -->
        <section
            class="hidden lg:flex lg:w-1/2 relative overflow-hidden items-center justify-center p-12 xl:p-24 h-screen"
            style="background: var(--color-bg-sidebar);"
        >
            <!-- Fondo con gradiente -->
            <div class="absolute inset-0 z-0 flex items-end" style="background: var(--gradient-primary); opacity: 0.12;"></div>

            <div class="relative z-10 w-full max-w-xl text-left space-y-10">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-2xl flex items-center justify-center"
                        style="background: var(--gradient-primary); box-shadow: var(--shadow-primary);"
                    >
                        <span class="material-symbols-outlined text-white" style="font-size: 24px; font-variation-settings: 'FILL' 1;">shield</span>
                    </div>
                    <span class="text-2xl font-extrabold tracking-tight" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                        Claim Guard
                    </span>
                </div>

                <!-- Tagline -->
                <div class="space-y-4">
                    <span
                        class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold tracking-wider uppercase"
                        style="background: var(--color-secondary-container); color: var(--color-on-secondary-container);"
                    >
                        Gestión contractual minera
                    </span>
                    <h1
                        class="text-5xl xl:text-6xl font-extrabold tracking-tighter leading-none"
                        style="font-family: var(--font-headline); color: var(--color-text-primary);"
                    >
                        Control total de tus contratos
                    </h1>
                    <p class="text-lg xl:text-xl leading-relaxed" style="color: var(--color-text-secondary); max-width: 420px;">
                        Gestión de eventos, cartas y riesgo de claim en un solo espacio de trabajo.
                    </p>
                </div>

                <!-- Insight card -->
                <div
                    class="p-6 rounded-2xl space-y-3"
                    style="background: var(--color-bg-card); box-shadow: var(--shadow-card);"
                >
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined" style="color: var(--color-secondary); font-variation-settings: 'FILL' 1; font-size: 20px;">auto_awesome</span>
                        <span class="text-sm font-bold" style="color: var(--color-secondary);">Inteligencia Contractual</span>
                    </div>
                    <p class="text-sm leading-relaxed italic" style="color: var(--color-text-primary);">
                        "Detecta riesgos de claim antes de que escalen. Cada evento documentado fortalece tu posición contractual."
                    </p>
                </div>
            </div>
        </section>

        <!-- Panel derecho — formulario -->
        <section
            class="w-full lg:w-1/2 flex items-center justify-center p-8 md:p-12 lg:p-24 h-screen overflow-auto"
            style="background: var(--color-bg-page);"
        >
            <div class="w-full max-w-lg space-y-8">

                <!-- Logo mobile -->
                <div class="flex lg:hidden items-center gap-3 mb-4">
                    <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center"
                        style="background: var(--gradient-primary);"
                    >
                        <span class="material-symbols-outlined text-white" style="font-size: 20px; font-variation-settings: 'FILL' 1;">shield</span>
                    </div>
                    <span class="text-xl font-extrabold" style="font-family: var(--font-headline); color: var(--color-text-primary);">Claim Guard</span>
                </div>

                <!-- Encabezado -->
                <div class="space-y-2">
                    <h2 class="text-4xl font-bold tracking-tight" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                        Bienvenido de nuevo
                    </h2>
                    <p class="text-lg" style="color: var(--color-text-secondary);">
                        Ingresa tus credenciales para acceder a tu espacio de trabajo.
                    </p>
                </div>

                <!-- Errores globales -->
                <div
                    v-if="form.errors.email && !form.errors.password"
                    class="flex items-center gap-3 p-4 rounded-xl"
                    style="background: var(--color-error-container); color: var(--color-on-error-container);"
                >
                    <span class="material-symbols-outlined" style="font-size: 20px;">error</span>
                    <span class="text-sm font-medium">{{ form.errors.email }}</span>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <!-- Email -->
                    <div class="space-y-2">
                        <label
                            for="email"
                            class="block text-sm font-semibold px-1"
                            style="color: var(--color-text-secondary);"
                        >
                            Correo institucional
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="nombre@empresa.cl"
                            class="w-full h-14 px-6 rounded-xl border-none outline-none transition-all duration-300 text-sm"
                            :style="`background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body); ${form.errors.email ? 'outline: 2px solid var(--color-error);' : ''}`"
                            :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                            :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                        >
                    </div>

                    <!-- Contraseña -->
                    <div class="space-y-2">
                        <label
                            for="password"
                            class="block text-sm font-semibold px-1"
                            style="color: var(--color-text-secondary);"
                        >
                            Contraseña
                        </label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                placeholder="••••••••••••"
                                class="w-full h-14 px-6 pr-14 rounded-xl border-none outline-none transition-all duration-300 text-sm"
                                :style="`background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body); ${form.errors.password ? 'outline: 2px solid var(--color-error);' : ''}`"
                                :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                :onblur="e => e.target.style.background = 'var(--color-bg-input)'"
                            >
                            <button
                                type="button"
                                class="absolute right-4 top-1/2 -translate-y-1/2"
                                style="background: none; border: none; cursor: pointer; color: var(--color-text-muted);"
                                @click="showPassword = !showPassword"
                            >
                                <span class="material-symbols-outlined" style="font-size: 20px;">
                                    {{ showPassword ? 'visibility_off' : 'visibility' }}
                                </span>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-xs px-1" style="color: var(--color-error);">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Recordarme -->
                    <div class="flex items-center gap-3 px-1">
                        <input
                            id="remember"
                            v-model="form.remember"
                            type="checkbox"
                            class="w-5 h-5 rounded"
                            style="accent-color: var(--color-primary); cursor: pointer;"
                        >
                        <label
                            for="remember"
                            class="text-sm font-medium select-none cursor-pointer"
                            style="color: var(--color-text-secondary);"
                        >
                            Mantener sesión activa por 30 días
                        </label>
                    </div>

                    <!-- Botón submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full h-14 rounded-full font-bold text-lg tracking-tight transition-all duration-200 active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed"
                        style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); font-family: var(--font-body); border: none; cursor: pointer;"
                    >
                        {{ form.processing ? 'Iniciando sesión...' : 'Ingresar al espacio de trabajo' }}
                    </button>
                </form>
            </div>
        </section>
    </main>
</template>
