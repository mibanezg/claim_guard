<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, Head } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
    existing_slugs: { type: Array,  default: () => [] },
    flash:          { type: Object, default: () => ({}) },
})

// ── Wizard steps ──────────────────────────────────────────────────────────────
const step = ref(1)
const totalSteps = 3

const steps = [
    { n: 1, label: 'Empresa',       icon: 'business' },
    { n: 2, label: 'Administrador', icon: 'person' },
    { n: 3, label: 'Confirmación',  icon: 'check_circle' },
]

// ── Formulario completo ───────────────────────────────────────────────────────
const form = useForm({
    company_name:                '',
    slug:                        '',
    email:                       '',
    phone:                       '',
    admin_name:                  '',
    admin_email:                 '',
    admin_password:              '',
    admin_password_confirmation: '',
})

// ── Slug: generación automática + verificación ────────────────────────────────
const slugStatus  = ref(null)   // null | 'checking' | 'available' | 'taken'
let   slugTimeout = null

function generateSlug(name) {
    return name.toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .slice(0, 30)
}

watch(() => form.company_name, (val) => {
    if (!val) return
    form.slug = generateSlug(val)
})

watch(() => form.slug, (val) => {
    if (!val) { slugStatus.value = null; return }
    slugStatus.value = 'checking'
    clearTimeout(slugTimeout)
    slugTimeout = setTimeout(async () => {
        try {
            const { data } = await axios.get(route('onboarding.check-slug'), { params: { slug: val } })
            form.slug        = data.slug
            slugStatus.value = data.available ? 'available' : 'taken'
        } catch { slugStatus.value = null }
    }, 400)
})

// ── Validación por paso ───────────────────────────────────────────────────────
const step1Valid = computed(() =>
    form.company_name.length >= 2 &&
    form.slug.length >= 2 &&
    form.email.includes('@') &&
    slugStatus.value === 'available'
)
const step2Valid = computed(() =>
    form.admin_name.length >= 2 &&
    form.admin_email.includes('@') &&
    form.admin_password.length >= 8 &&
    form.admin_password === form.admin_password_confirmation
)

function nextStep() {
    if (step.value === 1 && !step1Valid.value) return
    if (step.value === 2 && !step2Valid.value) return
    step.value++
}
function prevStep() { if (step.value > 1) step.value-- }
function submit()   { form.post(route('onboarding.store')) }

// ── Slug helpers ──────────────────────────────────────────────────────────────
const slugIcon  = computed(() => ({ checking: 'sync', available: 'check_circle', taken: 'cancel' }[slugStatus.value] ?? ''))
const slugColor = computed(() => ({ checking: 'var(--color-warning)', available: 'var(--color-success)', taken: 'var(--color-error)' }[slugStatus.value] ?? ''))
const slugMsg   = computed(() => ({
    checking:  'Verificando disponibilidad…',
    available: `Disponible — acceso en: ${form.slug}.claimguard.cl`,
    taken:     'Este slug ya está en uso. Elige otro.',
}[slugStatus.value] ?? ''))

// ── Fortaleza de contraseña ───────────────────────────────────────────────────
const passwordStrength = computed(() => {
    const p = form.admin_password
    if (!p) return 0
    let s = 0
    if (p.length >= 8)          s++
    if (p.length >= 12)         s++
    if (/[A-Z]/.test(p))        s++
    if (/[0-9]/.test(p))        s++
    if (/[^A-Za-z0-9]/.test(p)) s++
    return s
})
const strengthLabel = computed(() => ['', 'Débil', 'Regular', 'Buena', 'Fuerte', 'Muy fuerte'][passwordStrength.value])
const strengthColor = computed(() => ['', 'var(--color-error)', 'var(--color-warning)', 'var(--color-warning)', 'var(--color-success)', 'var(--color-success)'][passwordStrength.value])

const features = [
    'Base de datos independiente por empresa',
    'Roles y permisos configurables',
    'Gestión de contratos, eventos y cartas',
    'Indicador de riesgo de claim automático',
    'Integración opcional con Microsoft 365',
]
</script>

<template>
    <Head title="Nuevo Workspace — Claim Guard" />

    <!-- Mismo layout que login.html: mitad izquierda decorativa + mitad derecha formulario -->
    <div class="flex min-h-screen" style="background: var(--color-bg-page); font-family: var(--font-body);">

        <!-- Panel izquierdo: branding -->
        <div class="hidden lg:flex lg:w-5/12 flex-col justify-between p-12"
             style="background: var(--color-bg-sidebar); border-right: 1px solid var(--color-border-variant);">

            <!-- Logo -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                     style="background: var(--gradient-primary);">
                    <span class="material-symbols-outlined" style="font-size: 20px; color: var(--color-on-primary);">shield</span>
                </div>
                <div>
                    <h1 class="text-lg font-extrabold leading-tight"
                        style="font-family: var(--font-headline); color: var(--color-text-primary);">
                        Claim Guard
                    </h1>
                    <p class="text-xs" style="color: var(--color-text-secondary);">Gestión contractual</p>
                </div>
            </div>

            <!-- Copy central -->
            <div>
                <h2 class="text-4xl font-extrabold mb-4 leading-tight"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">
                    Crea tu workspace en minutos
                </h2>
                <p class="text-base leading-relaxed mb-8"
                   style="color: var(--color-text-secondary);">
                    Configura un entorno independiente para tu empresa con base de datos propia,
                    usuarios y contratos completamente aislados.
                </p>

                <!-- Features -->
                <div class="space-y-3">
                    <div v-for="feat in features" :key="feat"
                         class="flex items-center gap-3">
                        <span class="material-symbols-outlined flex-shrink-0"
                              style="font-size: 18px; color: var(--color-primary);">check_circle</span>
                        <p class="text-sm" style="color: var(--color-text-secondary);">{{ feat }}</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <p class="text-xs" style="color: var(--color-text-muted);">
                Claim Guard © {{ new Date().getFullYear() }} — Minería y construcción en Chile
            </p>
        </div>

        <!-- Panel derecho: wizard -->
        <div class="flex-1 flex flex-col items-center justify-center p-8 lg:p-12 overflow-y-auto">
            <div class="w-full max-w-2xl">

                <!-- Logo móvil -->
                <div class="flex items-center gap-3 mb-8 lg:hidden">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                         style="background: var(--gradient-primary);">
                        <span class="material-symbols-outlined" style="font-size: 18px; color: var(--color-on-primary);">shield</span>
                    </div>
                    <span class="font-extrabold" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                        Claim Guard
                    </span>
                </div>

                <!-- Título del paso -->
                <div class="mb-6">
                    <p class="text-xs font-bold uppercase tracking-wider mb-1"
                       style="color: var(--color-primary);">
                        Paso {{ step }} de {{ totalSteps }}
                    </p>
                    <h2 class="text-2xl font-extrabold"
                        style="font-family: var(--font-headline); color: var(--color-text-primary);">
                        {{ ['', 'Datos de la empresa', 'Cuenta del administrador', 'Confirma los datos'][step] }}
                    </h2>
                </div>

                <!-- Stepper lineal -->
                <div class="flex items-center gap-0 mb-8">
                    <template v-for="(s, i) in steps" :key="s.n">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all"
                                 :style="step > s.n
                                     ? `background: var(--color-primary); color: var(--color-on-primary);`
                                     : step === s.n
                                         ? `background: var(--color-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary);`
                                         : `background: var(--color-bg-elevated); color: var(--color-text-muted);`">
                                <span v-if="step > s.n" class="material-symbols-outlined" style="font-size: 16px;">check</span>
                                <span v-else>{{ s.n }}</span>
                            </div>
                            <span class="text-xs font-medium hidden sm:block"
                                  :style="step >= s.n ? 'color: var(--color-text-primary);' : 'color: var(--color-text-muted);'">
                                {{ s.label }}
                            </span>
                        </div>
                        <div v-if="i < steps.length - 1"
                             class="flex-1 h-px mx-3"
                             :style="step > s.n ? 'background: var(--color-primary);' : 'background: var(--color-border-variant);'">
                        </div>
                    </template>
                </div>

                <!-- Flash error -->
                <div v-if="flash?.error" class="mb-5 flex items-center gap-3 px-4 py-3 rounded-2xl"
                     style="background: var(--color-error-container); border: 1px solid var(--color-error);">
                    <span class="material-symbols-outlined flex-shrink-0" style="color: var(--color-on-error-container); font-size: 20px;">error</span>
                    <span class="text-sm" style="color: var(--color-on-error-container);">{{ flash.error }}</span>
                </div>

                <!-- Card del formulario -->
                <div class="rounded-2xl p-8" style="background: var(--color-bg-card); box-shadow: var(--shadow-card); border: 1px solid var(--color-border-variant);">

                    <!-- ─── PASO 1: Empresa ────────────────────────────── -->
                    <div v-if="step === 1" class="space-y-5">

                        <div>
                            <label class="text-xs font-semibold block mb-1.5"
                                   style="color: var(--color-text-secondary);">
                                Nombre de la empresa *
                            </label>
                            <input v-model="form.company_name"
                                   type="text" placeholder="Ej: Minera Los Andes S.A."
                                   class="w-full h-12 px-4 rounded-xl text-sm border-0 outline-none transition-all"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);"
                                   @focus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   @blur="e => e.target.style.background = 'var(--color-bg-input)'" />
                            <p v-if="form.errors.company_name" class="text-xs mt-1" style="color: var(--color-error);">
                                {{ form.errors.company_name }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold block mb-1.5"
                                   style="color: var(--color-text-secondary);">
                                Slug del workspace *
                                <span class="font-normal ml-1" style="color: var(--color-text-muted);">
                                    (URL de acceso)
                                </span>
                            </label>
                            <div class="relative">
                                <input v-model="form.slug"
                                       type="text" placeholder="minera-los-andes"
                                       maxlength="30"
                                       class="w-full h-12 px-4 pr-10 rounded-xl text-sm font-mono border-0 outline-none transition-all"
                                       style="background: var(--color-bg-input); color: var(--color-text-primary);"
                                       @focus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                       @blur="e => e.target.style.background = 'var(--color-bg-input)'" />
                                <span v-if="slugIcon"
                                      class="material-symbols-outlined absolute right-3 top-3"
                                      :class="slugStatus === 'checking' ? 'animate-spin' : ''"
                                      :style="`font-size: 20px; color: ${slugColor};`">
                                    {{ slugIcon }}
                                </span>
                            </div>
                            <p v-if="slugMsg" class="text-xs mt-1.5 font-medium"
                               :style="`color: ${slugColor};`">
                                {{ slugMsg }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold block mb-1.5"
                                   style="color: var(--color-text-secondary);">
                                Email de contacto *
                            </label>
                            <input v-model="form.email"
                                   type="email" placeholder="contacto@empresa.cl"
                                   class="w-full h-12 px-4 rounded-xl text-sm border-0 outline-none transition-all"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);"
                                   @focus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   @blur="e => e.target.style.background = 'var(--color-bg-input)'" />
                            <p v-if="form.errors.email" class="text-xs mt-1" style="color: var(--color-error);">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold block mb-1.5"
                                   style="color: var(--color-text-secondary);">Teléfono <span style="color: var(--color-text-muted);">(opcional)</span></label>
                            <input v-model="form.phone"
                                   type="text" placeholder="+56 9 1234 5678"
                                   class="w-full h-12 px-4 rounded-xl text-sm border-0 outline-none transition-all"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);"
                                   @focus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   @blur="e => e.target.style.background = 'var(--color-bg-input)'" />
                        </div>
                    </div>

                    <!-- ─── PASO 2: Administrador ──────────────────────── -->
                    <div v-if="step === 2" class="space-y-5">
                        <p class="text-sm" style="color: var(--color-text-secondary);">
                            Se creará el primer usuario con rol
                            <span class="font-semibold px-1.5 py-0.5 rounded text-xs"
                                  style="background: var(--color-primary-container); color: var(--color-on-primary-container);">
                                tenant_admin
                            </span>
                        </p>

                        <div>
                            <label class="text-xs font-semibold block mb-1.5"
                                   style="color: var(--color-text-secondary);">Nombre completo *</label>
                            <input v-model="form.admin_name"
                                   type="text" placeholder="Juan Pérez"
                                   class="w-full h-12 px-4 rounded-xl text-sm border-0 outline-none transition-all"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);"
                                   @focus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   @blur="e => e.target.style.background = 'var(--color-bg-input)'" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold block mb-1.5"
                                   style="color: var(--color-text-secondary);">Email del administrador *</label>
                            <input v-model="form.admin_email"
                                   type="email" placeholder="admin@empresa.cl"
                                   class="w-full h-12 px-4 rounded-xl text-sm border-0 outline-none transition-all"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);"
                                   @focus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   @blur="e => e.target.style.background = 'var(--color-bg-input)'" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold block mb-1.5"
                                   style="color: var(--color-text-secondary);">Contraseña * (mínimo 8 caracteres)</label>
                            <input v-model="form.admin_password"
                                   type="password" placeholder="••••••••"
                                   class="w-full h-12 px-4 rounded-xl text-sm border-0 outline-none transition-all"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);"
                                   @focus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   @blur="e => e.target.style.background = 'var(--color-bg-input)'" />
                            <div v-if="form.admin_password" class="flex items-center gap-2 mt-2">
                                <div class="flex gap-1 flex-1">
                                    <div v-for="i in 5" :key="i"
                                         class="h-1.5 flex-1 rounded-full transition-all"
                                         :style="i <= passwordStrength
                                             ? `background: ${strengthColor};`
                                             : 'background: var(--color-bg-elevated);'">
                                    </div>
                                </div>
                                <span class="text-xs font-medium" :style="`color: ${strengthColor};`">
                                    {{ strengthLabel }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold block mb-1.5"
                                   style="color: var(--color-text-secondary);">Confirmar contraseña *</label>
                            <input v-model="form.admin_password_confirmation"
                                   type="password" placeholder="••••••••"
                                   class="w-full h-12 px-4 rounded-xl text-sm border-0 outline-none transition-all"
                                   style="background: var(--color-bg-input); color: var(--color-text-primary);"
                                   @focus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   @blur="e => e.target.style.background = 'var(--color-bg-input)'" />
                            <p v-if="form.admin_password_confirmation && form.admin_password !== form.admin_password_confirmation"
                               class="text-xs mt-1" style="color: var(--color-error);">
                                Las contraseñas no coinciden
                            </p>
                        </div>
                    </div>

                    <!-- ─── PASO 3: Confirmación ───────────────────────── -->
                    <div v-if="step === 3" class="space-y-4">
                        <div class="rounded-xl p-5 space-y-3"
                             style="background: var(--color-bg-input);">
                            <p class="text-xs font-bold uppercase tracking-wider"
                               style="color: var(--color-text-secondary);">Empresa</p>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span style="color: var(--color-text-secondary);">Nombre</span>
                                    <span class="font-semibold" style="color: var(--color-text-primary);">{{ form.company_name }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span style="color: var(--color-text-secondary);">URL de acceso</span>
                                    <span class="font-mono font-semibold" style="color: var(--color-primary);">{{ form.slug }}.claimguard.cl</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span style="color: var(--color-text-secondary);">Base de datos</span>
                                    <span class="font-mono text-xs" style="color: var(--color-text-primary);">claimguard_{{ form.slug.replace(/-/g,'_') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl p-5 space-y-3"
                             style="background: var(--color-bg-input);">
                            <p class="text-xs font-bold uppercase tracking-wider"
                               style="color: var(--color-text-secondary);">Administrador</p>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span style="color: var(--color-text-secondary);">Nombre</span>
                                    <span class="font-semibold" style="color: var(--color-text-primary);">{{ form.admin_name }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span style="color: var(--color-text-secondary);">Email</span>
                                    <span style="color: var(--color-text-primary);">{{ form.admin_email }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-4 rounded-xl"
                             style="background: var(--color-primary-container);">
                            <span class="material-symbols-outlined flex-shrink-0 mt-0.5"
                                  style="font-size: 18px; color: var(--color-on-primary-container);">info</span>
                            <p class="text-xs" style="color: var(--color-on-primary-container);">
                                Se creará una base de datos MySQL independiente y se ejecutarán las migraciones automáticamente.
                            </p>
                        </div>
                    </div>

                    <!-- ─── Navegación ────────────────────────────────── -->
                    <div class="flex items-center justify-between mt-8 pt-6"
                         style="border-top: 1px solid var(--color-border-variant);">
                        <button v-if="step > 1"
                                @click="prevStep"
                                class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-semibold transition-all active:scale-95"
                                style="background: var(--color-bg-elevated); color: var(--color-text-secondary); border: none; cursor: pointer;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span>
                            Atrás
                        </button>
                        <div v-else></div>

                        <button v-if="step < totalSteps"
                                @click="nextStep"
                                :disabled="(step === 1 && !step1Valid) || (step === 2 && !step2Valid)"
                                class="flex items-center gap-2 px-6 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                :style="(step === 1 && !step1Valid) || (step === 2 && !step2Valid)
                                    ? 'background: var(--color-bg-elevated); color: var(--color-text-muted); cursor: not-allowed; border: none;'
                                    : 'background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); cursor: pointer; border: none;'">
                            Continuar
                            <span class="material-symbols-outlined" style="font-size: 18px;">arrow_forward</span>
                        </button>

                        <button v-else
                                @click="submit"
                                :disabled="form.processing"
                                class="flex items-center gap-2 px-6 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: var(--color-success); color: #fff; box-shadow: 0 4px 14px rgba(74,101,79,0.3); border: none; cursor: pointer;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">
                                {{ form.processing ? 'hourglass_empty' : 'rocket_launch' }}
                            </span>
                            {{ form.processing ? 'Creando workspace…' : 'Crear workspace' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
