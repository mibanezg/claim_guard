<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { usePage, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    colors:                  { type: Object,  default: () => ({}) },
    defaults:                { type: Object,  default: () => ({}) },
    thresholds:              { type: Object,  default: () => ({}) },
    integration:             { type: Object,  default: null },
    sharepoint_configured:   { type: Boolean, default: false },
    ai_integration:          { type: Object,  default: null },
    ai_providers:            { type: Object,  default: () => ({}) },
    ai_configured:           { type: Boolean, default: false },
    gdrive_integration:      { type: Object,  default: null },
    gdrive_configured:       { type: Boolean, default: false },
    dropbox_integration:     { type: Object,  default: null },
    dropbox_configured:      { type: Boolean, default: false },
    onedrive_personal:       { type: Object,  default: null },
    onedrive_personal_configured: { type: Boolean, default: false },
    active_storage:          { type: String,  default: 'local' },
    flash:                   { type: Object,  default: () => ({}) },
})

const page      = usePage()
const flashData = computed(() => page.props.flash ?? props.flash)

const isAdmin = computed(() =>
    (page.props.auth?.user?.permissions ?? []).includes('settings.integrations')
)

// ── Tab activo ────────────────────────────────────────────────────────────────
const activeTab = ref('colors')
const allTabs = [
    { key: 'colors',      label: 'Colores',                 icon: 'palette',     adminOnly: false },
    { key: 'thresholds',  label: 'Umbrales de riesgo',      icon: 'tune',        adminOnly: true  },
    { key: 'ai',          label: 'Inteligencia Artificial',  icon: 'smart_toy',   adminOnly: true  },
    { key: 'storage',     label: 'Almacenamiento',          icon: 'folder_open', adminOnly: true  },
    { key: 'integration', label: 'Microsoft 365',           icon: 'cloud',       adminOnly: true  },
]
const tabs = computed(() => allTabs.filter(t => !t.adminOnly || isAdmin.value))

// ── Formulario de colores ────────────────────────────────────────────────────
const colorForm = useForm({
    color_primary:      props.colors.color_primary      ?? '#2a6496',
    color_primary_dim:  props.colors.color_primary_dim  ?? '#1a4a76',
    color_secondary:    props.colors.color_secondary    ?? '#4a7a5a',
    color_sidebar_bg:   props.colors.color_sidebar_bg   ?? '#0f172a',
    color_text_primary: props.colors.color_text_primary ?? '#1e293b',
})

const colorFields = [
    { key: 'color_primary',      label: 'Color primario',      desc: 'Botones, links, elementos activos', cssVar: '--color-primary' },
    { key: 'color_primary_dim',  label: 'Color primario oscuro', desc: 'Hover y estados activos del primario', cssVar: '--color-primary-dim' },
    { key: 'color_secondary',    label: 'Color secundario',    desc: 'Elementos de acento secundarios', cssVar: '--color-secondary' },
    { key: 'color_sidebar_bg',   label: 'Fondo del sidebar',   desc: 'Color de fondo de la barra lateral', cssVar: '--color-bg-sidebar' },
    { key: 'color_text_primary', label: 'Color de texto',      desc: 'Texto principal en toda la aplicación', cssVar: '--color-text-primary' },
]

// Preview en tiempo real: aplica CSS variables al DOM mientras el usuario elige
watch(colorForm, (newVals) => {
    const map = {
        color_primary:      '--color-primary',
        color_primary_dim:  '--color-primary-dim',
        color_secondary:    '--color-secondary',
        color_sidebar_bg:   '--color-bg-sidebar',
        color_text_primary: '--color-text-primary',
    }
    Object.entries(map).forEach(([key, cssVar]) => {
        if (newVals[key]) document.documentElement.style.setProperty(cssVar, newVals[key])
    })
}, { deep: true })

function saveColors() {
    colorForm.post(route('settings.colors'))
}

function resetColors() {
    Object.entries(props.defaults).forEach(([key, val]) => {
        colorForm[key] = val
    })
    colorForm.post(route('settings.colors.reset'))
}

// ── Formulario de umbrales ────────────────────────────────────────────────────
const thresholdForm = useForm({
    risk_threshold_high:        props.thresholds.risk_threshold_high        ?? 50,
    risk_threshold_critical:    props.thresholds.risk_threshold_critical     ?? 75,
    alert_days_overdue_events:  props.thresholds.alert_days_overdue_events   ?? 15,
    alert_days_letter_response: props.thresholds.alert_days_letter_response  ?? 5,
    notification_email_enabled: props.thresholds.notification_email_enabled === 1
                                || props.thresholds.notification_email_enabled === '1'
                                || props.thresholds.notification_email_enabled === true,
})

function saveThresholds() {
    thresholdForm.post(route('settings.thresholds'))
}

// ── Formulario de integración ─────────────────────────────────────────────────
const integrationForm = useForm({
    client_id:       props.integration?.client_id       ?? '',
    tenant_azure_id: props.integration?.tenant_azure_id ?? '',
    client_secret:   '',
    site_id:         props.integration?.site_id         ?? '',
    is_active:       props.integration?.is_active       ?? false,
})

function saveIntegration() {
    integrationForm.post(route('settings.integration'))
}

function testIntegration() {
    useForm({}).post(route('settings.integration.test'))
}

// ── Preview del sidebar ───────────────────────────────────────────────────────
const previewBg      = computed(() => colorForm.color_sidebar_bg)
const previewPrimary = computed(() => colorForm.color_primary)
const previewText    = computed(() => colorForm.color_text_primary)

// ── Formulario de IA ─────────────────────────────────────────────────────────
const aiForm = useForm({
    provider:  props.ai_integration?.provider  ?? 'anthropic',
    model:     props.ai_integration?.model     ?? '',
    api_key:   '',
    is_active: props.ai_integration?.is_active ?? false,
})

const availableModels = computed(() => {
    const provider = props.ai_providers[aiForm.provider]
    return provider?.models ?? {}
})

watch(() => aiForm.provider, (newProvider) => {
    aiForm.model = props.ai_providers[newProvider]?.default_model ?? ''
}, { immediate: !props.ai_integration?.model })

function submitAi() {
    aiForm.post(route('settings.ai'), { preserveScroll: true })
}

function testAi() {
    useForm({}).post(route('settings.ai.test'), { preserveScroll: true })
}

// ── Google Drive ──────────────────────────────────────────────────────────────
const gdriveForm = useForm({
    service_account_email: props.gdrive_integration?.service_account_email ?? '',
    private_key:           '',
    folder_id:             props.gdrive_integration?.folder_id ?? '',
    is_active:             props.gdrive_integration?.is_active ?? false,
})
function saveGdrive() { gdriveForm.post(route('settings.google-drive'), { preserveScroll: true }) }
function testGdrive() { useForm({}).post(route('settings.google-drive.test'), { preserveScroll: true }) }

// ── Dropbox ───────────────────────────────────────────────────────────────────
const dropboxForm = useForm({
    app_key:      props.dropbox_integration?.app_key   ?? '',
    access_token: '',
    base_path:    props.dropbox_integration?.base_path ?? '/ClaimGuard',
    is_active:    props.dropbox_integration?.is_active ?? false,
})
function saveDropbox() { dropboxForm.post(route('settings.dropbox'), { preserveScroll: true }) }
function testDropbox() { useForm({}).post(route('settings.dropbox.test'), { preserveScroll: true }) }

// ── OneDrive Personal ─────────────────────────────────────────────────────────
const onedriveForm = useForm({
    client_id:     props.onedrive_personal?.client_id     ?? '',
    client_secret: '',
    is_active:     props.onedrive_personal?.is_active     ?? false,
})
function saveOnedrive() { onedriveForm.post(route('settings.onedrive-personal'), { preserveScroll: true }) }
function authorizeOnedrive() { window.location.href = route('settings.onedrive-personal.authorize') }
function testOnedrive() { useForm({}).post(route('settings.onedrive-personal.test'), { preserveScroll: true }) }
function disconnectOnedrive() { useForm({}).post(route('settings.onedrive-personal.disconnect'), { preserveScroll: true }) }

const STORAGE_LABELS = {
    local:             { label: 'Almacenamiento local', icon: 'computer', color: 'var(--color-text-muted)' },
    sharepoint:        { label: 'Microsoft 365 / SharePoint', icon: 'cloud', color: '#0078d4' },
    onedrive_personal: { label: 'OneDrive Personal', icon: 'cloud', color: '#0078d4' },
    google_drive:      { label: 'Google Drive', icon: 'add_to_drive', color: '#34a853' },
    dropbox:           { label: 'Dropbox', icon: 'folder_open', color: '#0061ff' },
}

const azureSteps = [
    'Ve a portal.azure.com → Azure Active Directory → App registrations → New registration.',
    'Copia el Application (Client) ID y el Directory (Tenant) ID.',
    'En "Certificates & secrets" crea un nuevo Client Secret. Cópialo inmediatamente.',
    'En "API permissions" agrega Microsoft Graph → Application permissions: Files.ReadWrite.All y Sites.ReadWrite.All. Luego haz clic en "Grant admin consent".',
    'Obtén el Site ID haciendo GET a https://graph.microsoft.com/v1.0/sites/{dominio}.sharepoint.com:/sites/{nombre-sitio}',
    'Pega todos los valores en el formulario y activa la integración.',
]
</script>

<template>
    <AppLayout title="Configuración">

        <!-- Flash -->
        <div v-if="flashData?.success" class="mb-6 flex items-center gap-3 px-5 py-3 rounded-2xl"
             style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);">
            <span class="material-symbols-outlined" style="color: #22c55e; font-size: 20px;">check_circle</span>
            <span class="text-sm font-medium" style="color: #22c55e;">{{ flashData.success }}</span>
        </div>
        <div v-if="flashData?.error" class="mb-6 flex items-center gap-3 px-5 py-3 rounded-2xl"
             style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);">
            <span class="material-symbols-outlined" style="color: #ef4444; font-size: 20px;">error</span>
            <span class="text-sm font-medium" style="color: #ef4444;">{{ flashData.error }}</span>
        </div>

        <!-- Header + tabs -->
        <div class="mb-6">
            <h2 class="text-2xl font-extrabold mb-4"
                style="font-family: var(--font-headline); color: var(--color-text-primary);">
                Configuración del tenant
            </h2>

            <!-- Tab bar -->
            <div class="flex gap-1 p-1 rounded-2xl" style="background: var(--color-bg-card); display: inline-flex;">
                <button v-for="tab in tabs" :key="tab.key"
                        @click="activeTab = tab.key"
                        class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all"
                        :style="activeTab === tab.key
                            ? 'background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary);'
                            : 'color: var(--color-text-secondary); background: none;'">
                    <span class="material-symbols-outlined" style="font-size: 18px;">{{ tab.icon }}</span>
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- TAB: COLORES                                                       -->
        <!-- ================================================================= -->
        <div v-if="activeTab === 'colors'" class="grid grid-cols-3 gap-6">

            <!-- Formulario color pickers -->
            <div class="col-span-2 flex flex-col gap-4">
                <div class="rounded-2xl p-6" style="background: var(--color-bg-card);">
                    <h3 class="text-sm font-bold mb-5"
                        style="font-family: var(--font-headline); color: var(--color-text-primary);">
                        Variables de color personalizables
                    </h3>

                    <div class="space-y-5">
                        <div v-for="field in colorFields" :key="field.key" class="flex items-center gap-4">
                            <!-- Color picker -->
                            <div class="relative flex-shrink-0">
                                <input type="color"
                                       v-model="colorForm[field.key]"
                                       class="w-12 h-12 rounded-xl cursor-pointer border-0 p-0.5"
                                       style="background: var(--color-bg-elevated);"
                                       :id="field.key" />
                            </div>

                            <!-- Texto + hex -->
                            <div class="flex-1 min-w-0">
                                <label :for="field.key"
                                       class="text-sm font-semibold block mb-0.5"
                                       style="color: var(--color-text-primary); cursor: pointer;">
                                    {{ field.label }}
                                </label>
                                <p class="text-xs" style="color: var(--color-text-muted);">{{ field.desc }}</p>
                            </div>

                            <!-- Input hex manual -->
                            <div class="flex-shrink-0">
                                <input type="text"
                                       v-model="colorForm[field.key]"
                                       maxlength="7"
                                       class="w-28 px-3 py-2 rounded-xl text-xs font-mono text-center border-0 outline-none"
                                       style="background: var(--color-bg-elevated); color: var(--color-text-primary);"
                                       placeholder="#000000" />
                            </div>

                            <!-- Dot preview con CSS var real -->
                            <div class="w-6 h-6 rounded-lg flex-shrink-0 border"
                                 style="border-color: var(--color-border-variant);"
                                 :style="`background: ${colorForm[field.key]}`"></div>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="flex items-center gap-3 mt-6 pt-5"
                         style="border-top: 1px solid var(--color-border-variant);">
                        <button @click="saveColors"
                                :disabled="colorForm.processing"
                                class="flex items-center gap-2 px-6 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">save</span>
                            Guardar colores
                        </button>
                        <button @click="resetColors"
                                class="flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-semibold transition-all active:scale-95"
                                style="background: var(--color-bg-elevated); color: var(--color-text-secondary); border: none; cursor: pointer;">
                            <span class="material-symbols-outlined" style="font-size: 18px;">restart_alt</span>
                            Restaurar predeterminados
                        </button>
                    </div>
                </div>
            </div>

            <!-- Preview del sidebar -->
            <div class="flex flex-col gap-4">
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                    <p class="text-xs font-bold uppercase tracking-wider mb-4"
                       style="color: var(--color-text-secondary);">Preview en tiempo real</p>

                    <!-- Mini sidebar simulado -->
                    <div class="rounded-xl overflow-hidden" style="height: 340px; position: relative;">
                        <div class="h-full flex flex-col p-4"
                             :style="`background: ${previewBg}; width: 100%;`">

                            <!-- Logo simulado -->
                            <div class="flex items-center gap-2 mb-6">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                                     :style="`background: ${previewPrimary};`">
                                    <span class="material-symbols-outlined text-white" style="font-size: 16px;">shield</span>
                                </div>
                                <div>
                                    <p class="text-xs font-extrabold" style="color: #ffffff;">Claim Guard</p>
                                    <p class="text-xs opacity-60" style="color: #ffffff; font-size: 9px;">Tu empresa</p>
                                </div>
                            </div>

                            <!-- Items de nav simulados -->
                            <div class="space-y-1 flex-1">
                                <div v-for="item in ['Dashboard', 'Contratos', 'Eventos', 'Cartas']"
                                     :key="item"
                                     class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs"
                                     :style="item === 'Contratos'
                                         ? `background: ${previewPrimary}; color: #ffffff; font-weight: 600;`
                                         : 'color: rgba(255,255,255,0.6);'">
                                    <span class="material-symbols-outlined" style="font-size: 14px;">circle</span>
                                    {{ item }}
                                </div>
                            </div>

                            <!-- Usuario simulado -->
                            <div class="pt-3 flex items-center gap-2"
                                 style="border-top: 1px solid rgba(255,255,255,0.1);">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center"
                                     :style="`background: ${previewPrimary};`">
                                    <span class="material-symbols-outlined text-white" style="font-size: 13px;">person</span>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold" style="color: #ffffff;">Usuario</p>
                                    <p style="color: rgba(255,255,255,0.5); font-size: 9px;">tenant_admin</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Muestra de botón primario -->
                    <div class="mt-4 p-4 rounded-xl flex flex-col gap-2"
                         style="background: var(--color-bg-elevated);">
                        <p class="text-xs font-bold mb-1" style="color: var(--color-text-secondary);">Elementos de UI</p>
                        <button class="w-full py-2 rounded-full text-xs font-bold text-white"
                                :style="`background: ${previewPrimary};`">
                            Botón primario
                        </button>
                        <p class="text-xs" :style="`color: ${previewText};`">
                            Texto de ejemplo en color configurado
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- TAB: UMBRALES DE RIESGO                                           -->
        <!-- ================================================================= -->
        <div v-if="activeTab === 'thresholds'" class="max-w-2xl">
            <div class="rounded-2xl p-6" style="background: var(--color-bg-card);">
                <h3 class="text-sm font-bold mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">
                    Umbrales del indicador de riesgo
                </h3>
                <p class="text-xs mb-6" style="color: var(--color-text-muted);">
                    Define los puntos de corte que determinan el nivel de riesgo de cada contrato.
                </p>

                <div class="space-y-6">

                    <!-- Umbrales de score -->
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider mb-3"
                           style="color: var(--color-text-secondary);">Niveles de score (0–100)</p>

                        <!-- Visual de rangos -->
                        <div class="flex rounded-xl overflow-hidden mb-4 h-8 text-xs font-bold">
                            <div class="flex items-center justify-center text-white"
                                 :style="`width: ${thresholdForm.risk_threshold_high}%; background: #22c55e;`">
                                Bajo
                            </div>
                            <div class="flex items-center justify-center text-white"
                                 :style="`width: ${thresholdForm.risk_threshold_critical - thresholdForm.risk_threshold_high}%; background: #eab308;`">
                                Medio
                            </div>
                            <div class="flex items-center justify-center text-white"
                                 :style="`width: ${100 - thresholdForm.risk_threshold_critical}%; background: #ef4444;`">
                                Crítico
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-semibold block mb-1"
                                       style="color: var(--color-text-primary);">
                                    Score mínimo nivel "Alto"
                                </label>
                                <p class="text-xs mb-2" style="color: var(--color-text-muted);">Por debajo: Bajo o Medio</p>
                                <div class="flex items-center gap-3">
                                    <input type="range" v-model.number="thresholdForm.risk_threshold_high"
                                           min="10" max="80" step="5" class="flex-1"
                                           style="accent-color: var(--color-primary);" />
                                    <span class="text-sm font-bold w-8 text-center"
                                          style="color: var(--color-primary);">
                                        {{ thresholdForm.risk_threshold_high }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-semibold block mb-1"
                                       style="color: var(--color-text-primary);">
                                    Score mínimo nivel "Crítico"
                                </label>
                                <p class="text-xs mb-2" style="color: var(--color-text-muted);">Por debajo de este: Alto</p>
                                <div class="flex items-center gap-3">
                                    <input type="range" v-model.number="thresholdForm.risk_threshold_critical"
                                           min="30" max="99" step="5" class="flex-1"
                                           style="accent-color: var(--color-primary);" />
                                    <span class="text-sm font-bold w-8 text-center"
                                          style="color: #ef4444;">
                                        {{ thresholdForm.risk_threshold_critical }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="border-top: 1px solid var(--color-border-variant);"></div>

                    <!-- Alertas -->
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider mb-3"
                           style="color: var(--color-text-secondary);">Parámetros de alertas</p>
                        <div class="space-y-4">

                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold"
                                       style="color: var(--color-text-primary);">
                                        Días para marcar evento sin resolver
                                    </p>
                                    <p class="text-xs" style="color: var(--color-text-muted);">
                                        Eventos con más de N días en estado pendiente/negociación
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 ml-4">
                                    <input type="range" v-model.number="thresholdForm.alert_days_overdue_events"
                                           min="1" max="60" step="1" style="width: 120px; accent-color: var(--color-primary);" />
                                    <span class="text-sm font-bold w-10 text-center"
                                          style="color: var(--color-primary);">
                                        {{ thresholdForm.alert_days_overdue_events }}d
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold"
                                       style="color: var(--color-text-primary);">
                                        Días hábiles de respuesta por defecto
                                    </p>
                                    <p class="text-xs" style="color: var(--color-text-muted);">
                                        Plazo aplicado a cartas nuevas si no se especifica
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 ml-4">
                                    <input type="range" v-model.number="thresholdForm.alert_days_letter_response"
                                           min="1" max="30" step="1" style="width: 120px; accent-color: var(--color-primary);" />
                                    <span class="text-sm font-bold w-10 text-center"
                                          style="color: var(--color-primary);">
                                        {{ thresholdForm.alert_days_letter_response }}d
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold"
                                       style="color: var(--color-text-primary);">
                                        Notificaciones por email
                                    </p>
                                    <p class="text-xs" style="color: var(--color-text-muted);">
                                        Enviar alertas al escalar a nivel alto o crítico
                                    </p>
                                </div>
                                <button @click="thresholdForm.notification_email_enabled = !thresholdForm.notification_email_enabled"
                                        class="relative w-12 h-6 rounded-full transition-all flex-shrink-0"
                                        :style="thresholdForm.notification_email_enabled
                                            ? 'background: var(--color-primary);'
                                            : 'background: var(--color-bg-elevated);'">
                                    <div class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all"
                                         :style="thresholdForm.notification_email_enabled ? 'left: 26px;' : 'left: 2px;'">
                                    </div>
                                </button>
                            </div>

                        </div>
                    </div>

                </div>

                <!-- Acciones -->
                <div class="mt-6 pt-5 flex items-center gap-3"
                     style="border-top: 1px solid var(--color-border-variant);">
                    <button @click="saveThresholds"
                            :disabled="thresholdForm.processing"
                            class="flex items-center gap-2 px-6 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                            style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                        <span class="material-symbols-outlined" style="font-size: 18px;">save</span>
                        Guardar umbrales
                    </button>
                </div>

                <!-- Nota: umbrales no recalculan scores existentes -->
                <p class="text-xs mt-3" style="color: var(--color-text-muted);">
                    Los cambios se aplican en el próximo cálculo de riesgo. Los scores existentes no se recalculan automáticamente.
                </p>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- TAB: INTELIGENCIA ARTIFICIAL                                        -->
        <!-- ================================================================= -->
        <div v-if="activeTab === 'ai'" class="max-w-2xl flex flex-col gap-4">

            <!-- Estado actual -->
            <div class="rounded-2xl p-5 flex items-center gap-4"
                 :style="ai_configured
                     ? 'background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.2);'
                     : 'background: var(--color-bg-card); border: 1px solid var(--color-border);'">
                <span class="material-symbols-outlined"
                      :style="`font-size: 28px; color: ${ai_configured ? '#22c55e' : 'var(--color-text-muted)'}`">
                    smart_toy
                </span>
                <div>
                    <p class="font-semibold text-sm" style="color: var(--color-text-primary);">
                        {{ ai_configured ? 'IA configurada y activa' : 'IA no configurada' }}
                    </p>
                    <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">
                        {{ ai_configured
                            ? `Proveedor: ${ai_providers[ai_integration?.provider]?.label} · Modelo: ${ai_integration?.model}`
                            : 'Configura un proveedor para habilitar redacción asistida, análisis de riesgo y resúmenes de expediente.' }}
                    </p>
                </div>
                <button v-if="ai_configured" @click="testAi"
                        class="ml-auto px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                        style="background: var(--color-bg-input); color: var(--color-text-secondary);">
                    Probar conexión
                </button>
            </div>

            <!-- Formulario -->
            <div class="rounded-2xl p-6"
                 style="background: var(--color-bg-card); border: 1px solid var(--color-border);">

                <p class="text-xs font-bold uppercase tracking-wider mb-5"
                   style="color: var(--color-text-muted);">Proveedor de IA</p>

                <!-- Selector de proveedor como cards -->
                <div class="grid grid-cols-3 gap-3 mb-5">
                    <button v-for="(info, key) in ai_providers" :key="key"
                            @click="aiForm.provider = key"
                            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all"
                            :style="aiForm.provider === key
                                ? 'border-color: var(--color-primary); background: var(--color-primary); box-shadow: var(--shadow-primary);'
                                : 'border-color: var(--color-border); background: var(--color-bg-input);'">
                        <span class="material-symbols-outlined"
                              :style="`font-size: 26px; color: ${aiForm.provider === key ? '#fff' : 'var(--color-text-muted)'};`">
                            {{ key === 'anthropic' ? 'psychology' : key === 'openai' ? 'auto_awesome' : key === 'deepseek' ? 'hub' : 'bolt' }}
                        </span>
                        <span class="text-xs font-semibold text-center leading-tight"
                              :style="aiForm.provider === key ? 'color: #fff;' : 'color: var(--color-text-secondary);'">
                            {{ info.label }}
                        </span>
                    </button>
                </div>

                <!-- Modelo -->
                <div class="mb-4">
                    <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">Modelo</label>
                    <select v-model="aiForm.model"
                            class="w-full h-11 px-3 rounded-xl text-sm border-0 outline-none"
                            style="background: var(--color-bg-input); color: var(--color-text-primary);">
                        <option v-for="(label, modelKey) in availableModels" :key="modelKey" :value="modelKey">
                            {{ label }}
                        </option>
                    </select>
                </div>

                <!-- API Key -->
                <div class="mb-4">
                    <label class="text-xs font-semibold block mb-1.5" style="color: var(--color-text-secondary);">
                        API Key
                        <span v-if="ai_integration?.has_key" class="font-normal ml-1" style="color: var(--color-success);">
                            · Clave guardada (déjala vacía para no cambiarla)
                        </span>
                    </label>
                    <input v-model="aiForm.api_key"
                           type="password"
                           :placeholder="ai_integration?.has_key ? '••••••••••••••••' : 'sk-... / AIza... / tu clave API'"
                           class="w-full h-11 px-4 rounded-xl text-sm border-0 outline-none font-mono"
                           style="background: var(--color-bg-input); color: var(--color-text-primary);" />
                    <p class="text-xs mt-1.5" style="color: var(--color-text-muted);">
                        {{ aiForm.provider === 'anthropic' ? 'Obtén tu API Key en console.anthropic.com'
                         : aiForm.provider === 'openai'   ? 'Obtén tu API Key en platform.openai.com/api-keys'
                         : 'Obtén tu API Key en aistudio.google.com' }}
                    </p>
                    <p v-if="aiForm.errors.api_key" class="text-xs mt-1" style="color: var(--color-error);">{{ aiForm.errors.api_key }}</p>
                </div>

                <!-- Toggle activo -->
                <label class="flex items-center gap-3 cursor-pointer select-none mb-6">
                    <div class="relative w-10 h-5 rounded-full transition-all"
                         :style="aiForm.is_active ? 'background: var(--color-primary)' : 'background: var(--color-bg-elevated)'"
                         @click="aiForm.is_active = !aiForm.is_active">
                        <div class="absolute top-0.5 w-4 h-4 rounded-full bg-white shadow transition-all"
                             :style="aiForm.is_active ? 'left: calc(100% - 18px)' : 'left: 2px'"></div>
                    </div>
                    <span class="text-sm" style="color: var(--color-text-secondary);">Integración activa</span>
                </label>

                <button @click="submitAi" :disabled="aiForm.processing"
                        class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all active:scale-95"
                        style="background: var(--color-primary); color: #fff; border: none; cursor: pointer;">
                    Guardar configuración IA
                </button>
            </div>

            <!-- Info uso -->
            <div class="rounded-2xl p-5" style="background: var(--color-bg-card); border: 1px solid var(--color-border);">
                <p class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--color-text-muted);">¿Para qué se usa la IA?</p>
                <div class="space-y-2">
                    <div v-for="uso in [
                        { icon: 'edit_note',   text: 'Redacción asistida de cartas contractuales (borrador requiere aprobación humana)' },
                        { icon: 'crisis_alert',text: 'Recomendaciones automáticas cuando el riesgo de claim es alto o crítico' },
                        { icon: 'summarize',   text: 'Resumen ejecutivo del expediente de claim para disputas contractuales' },
                    ]" :key="uso.text" class="flex items-start gap-3">
                        <span class="material-symbols-outlined flex-shrink-0 mt-0.5"
                              style="font-size: 16px; color: var(--color-primary);">{{ uso.icon }}</span>
                        <p class="text-xs" style="color: var(--color-text-secondary);">{{ uso.text }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- TAB: ALMACENAMIENTO                                                -->
        <!-- ================================================================= -->
        <div v-if="activeTab === 'storage'" class="max-w-2xl flex flex-col gap-6">

            <!-- Proveedor activo -->
            <div class="rounded-2xl p-5 flex items-center gap-4"
                 :style="`background: var(--color-bg-card); border-left: 4px solid ${STORAGE_LABELS[active_storage]?.color ?? 'var(--color-border-variant)'};`">
                <span class="material-symbols-outlined" style="font-size: 28px;"
                      :style="`color: ${STORAGE_LABELS[active_storage]?.color ?? 'var(--color-text-muted)'};`">
                    {{ STORAGE_LABELS[active_storage]?.icon ?? 'folder' }}
                </span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider mb-0.5" style="color: var(--color-text-muted);">Proveedor activo</p>
                    <p class="font-bold" style="color: var(--color-text-primary);">{{ STORAGE_LABELS[active_storage]?.label ?? active_storage }}</p>
                </div>
            </div>

            <!-- OneDrive Personal -->
            <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                <div class="px-6 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--color-border-variant);">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined" style="color: #0078d4; font-size: 22px;">cloud</span>
                        <div>
                            <p class="font-bold text-sm" style="color: var(--color-text-primary);">OneDrive Personal</p>
                            <p class="text-xs" style="color: var(--color-text-muted);">Cuenta outlook.com — OAuth delegado</p>
                        </div>
                    </div>
                    <span v-if="onedrive_personal_configured"
                          class="text-xs font-bold px-3 py-1 rounded-full"
                          style="background: rgba(34,197,94,0.1); color: #22c55e;">Conectado</span>
                </div>
                <div class="p-6 flex flex-col gap-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider block mb-1.5" style="color: var(--color-text-secondary);">Client ID (App Registration)</label>
                            <input v-model="onedriveForm.client_id"
                                   placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                   class="w-full px-4 py-2.5 rounded-xl text-sm font-mono"
                                   style="background: var(--color-bg-input); border: 1px solid var(--color-border); color: var(--color-text-primary);" />
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider block mb-1.5" style="color: var(--color-text-secondary);">
                                Client Secret
                                <span v-if="onedrive_personal?.has_secret" class="normal-case font-normal ml-1" style="color: var(--color-text-muted);">· Guardado</span>
                            </label>
                            <input v-model="onedriveForm.client_secret" type="password"
                                   placeholder="s1n8Q~..."
                                   class="w-full px-4 py-2.5 rounded-xl text-sm font-mono"
                                   style="background: var(--color-bg-input); border: 1px solid var(--color-border); color: var(--color-text-primary);" />
                        </div>
                    </div>
                    <p class="text-xs" style="color: var(--color-text-muted);">URI de redirección a registrar en Azure: <code class="px-1.5 py-0.5 rounded" style="background: var(--color-bg-elevated);">{{ $page.props.ziggy?.url ?? 'http://localhost:8000' }}/settings/microsoft/callback</code></p>
                    <div class="flex gap-3 pt-2 flex-wrap">
                        <button @click="saveOnedrive" :disabled="onedriveForm.processing"
                                class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: var(--color-bg-elevated); color: var(--color-text-primary); border: 1px solid var(--color-border); cursor: pointer;">
                            1. Guardar credenciales
                        </button>
                        <button @click="authorizeOnedrive"
                                class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer;">
                            <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">open_in_new</span>
                            2. Autorizar con Microsoft
                        </button>
                        <button v-if="onedrive_personal_configured" @click="testOnedrive"
                                class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: var(--color-bg-elevated); color: var(--color-text-primary); border: 1px solid var(--color-border); cursor: pointer;">
                            Probar conexión
                        </button>
                        <button v-if="onedrive_personal_configured" @click="disconnectOnedrive"
                                class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: rgba(239,68,68,0.1); color: #ef4444; border: none; cursor: pointer;">
                            Desconectar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Google Drive -->
            <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                <div class="px-6 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--color-border-variant);">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined" style="color: #34a853; font-size: 22px;">add_to_drive</span>
                        <div>
                            <p class="font-bold text-sm" style="color: var(--color-text-primary);">Google Drive</p>
                            <p class="text-xs" style="color: var(--color-text-muted);">Vía Service Account — sin billing requerido</p>
                        </div>
                    </div>
                    <span v-if="gdrive_configured"
                          class="text-xs font-bold px-3 py-1 rounded-full"
                          style="background: rgba(34,197,94,0.1); color: #22c55e;">Activo</span>
                </div>
                <div class="p-6 flex flex-col gap-4">
                    <div>
                        <label class="text-xs font-bold uppercase tracking-wider block mb-1.5" style="color: var(--color-text-secondary);">Email de la Service Account</label>
                        <input v-model="gdriveForm.service_account_email" type="email"
                               placeholder="claimguard@proyecto.iam.gserviceaccount.com"
                               class="w-full px-4 py-2.5 rounded-xl text-sm"
                               style="background: var(--color-bg-input); border: 1px solid var(--color-border); color: var(--color-text-primary);" />
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase tracking-wider block mb-1.5" style="color: var(--color-text-secondary);">
                            Private Key (PEM)
                            <span v-if="gdrive_integration?.has_key" class="normal-case font-normal ml-2" style="color: var(--color-text-muted);">· Clave guardada (déjala vacía para no cambiarla)</span>
                        </label>
                        <textarea v-model="gdriveForm.private_key" rows="4"
                                  placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...&#10;-----END RSA PRIVATE KEY-----"
                                  class="w-full px-4 py-2.5 rounded-xl text-xs font-mono"
                                  style="background: var(--color-bg-input); border: 1px solid var(--color-border); color: var(--color-text-primary); resize: vertical;"></textarea>
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase tracking-wider block mb-1.5" style="color: var(--color-text-secondary);">ID de la carpeta raíz en Drive</label>
                        <input v-model="gdriveForm.folder_id"
                               placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms"
                               class="w-full px-4 py-2.5 rounded-xl text-sm font-mono"
                               style="background: var(--color-bg-input); border: 1px solid var(--color-border); color: var(--color-text-primary);" />
                        <p class="text-xs mt-1" style="color: var(--color-text-muted);">ID de la carpeta que compartiste con la service account. Lo encuentras en la URL al abrir la carpeta en Drive.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" @click="gdriveForm.is_active = !gdriveForm.is_active"
                                class="relative w-12 h-6 rounded-full transition-colors duration-200 flex-shrink-0"
                                :style="gdriveForm.is_active ? 'background: var(--color-primary);' : 'background: var(--color-border);'">
                            <span class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200"
                                  :style="gdriveForm.is_active ? 'transform: translateX(24px);' : 'transform: translateX(4px);'"></span>
                        </button>
                        <span class="text-sm" style="color: var(--color-text-secondary);">Integración activa</span>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button @click="saveGdrive" :disabled="gdriveForm.processing"
                                class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer;">
                            Guardar
                        </button>
                        <button v-if="gdrive_configured" @click="testGdrive"
                                class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: var(--color-bg-elevated); color: var(--color-text-primary); border: 1px solid var(--color-border); cursor: pointer;">
                            Probar conexión
                        </button>
                    </div>
                </div>
            </div>

            <!-- Dropbox -->
            <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card);">
                <div class="px-6 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--color-border-variant);">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined" style="color: #0061ff; font-size: 22px;">folder_open</span>
                        <div>
                            <p class="font-bold text-sm" style="color: var(--color-text-primary);">Dropbox</p>
                            <p class="text-xs" style="color: var(--color-text-muted);">Vía access token — app gratuita disponible</p>
                        </div>
                    </div>
                    <span v-if="dropbox_configured"
                          class="text-xs font-bold px-3 py-1 rounded-full"
                          style="background: rgba(34,197,94,0.1); color: #22c55e;">Activo</span>
                </div>
                <div class="p-6 flex flex-col gap-4">
                    <div>
                        <label class="text-xs font-bold uppercase tracking-wider block mb-1.5" style="color: var(--color-text-secondary);">App Key (opcional)</label>
                        <input v-model="dropboxForm.app_key"
                               placeholder="abc123def456"
                               class="w-full px-4 py-2.5 rounded-xl text-sm font-mono"
                               style="background: var(--color-bg-input); border: 1px solid var(--color-border); color: var(--color-text-primary);" />
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase tracking-wider block mb-1.5" style="color: var(--color-text-secondary);">
                            Access Token
                            <span v-if="dropbox_integration?.has_token" class="normal-case font-normal ml-2" style="color: var(--color-text-muted);">· Token guardado (déjalo vacío para no cambiarlo)</span>
                        </label>
                        <input v-model="dropboxForm.access_token" type="password"
                               placeholder="sl.AAAA..."
                               class="w-full px-4 py-2.5 rounded-xl text-sm font-mono"
                               style="background: var(--color-bg-input); border: 1px solid var(--color-border); color: var(--color-text-primary);" />
                        <p class="text-xs mt-1" style="color: var(--color-text-muted);">Genera un token de larga duración en <strong>dropbox.com/developers → App Console → tu app → Generated access token</strong>.</p>
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase tracking-wider block mb-1.5" style="color: var(--color-text-secondary);">Carpeta base</label>
                        <input v-model="dropboxForm.base_path"
                               placeholder="/ClaimGuard"
                               class="w-full px-4 py-2.5 rounded-xl text-sm font-mono"
                               style="background: var(--color-bg-input); border: 1px solid var(--color-border); color: var(--color-text-primary);" />
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" @click="dropboxForm.is_active = !dropboxForm.is_active"
                                class="relative w-12 h-6 rounded-full transition-colors duration-200 flex-shrink-0"
                                :style="dropboxForm.is_active ? 'background: var(--color-primary);' : 'background: var(--color-border);'">
                            <span class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200"
                                  :style="dropboxForm.is_active ? 'transform: translateX(24px);' : 'transform: translateX(4px);'"></span>
                        </button>
                        <span class="text-sm" style="color: var(--color-text-secondary);">Integración activa</span>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button @click="saveDropbox" :disabled="dropboxForm.processing"
                                class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer;">
                            Guardar
                        </button>
                        <button v-if="dropbox_configured" @click="testDropbox"
                                class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                                style="background: var(--color-bg-elevated); color: var(--color-text-primary); border: 1px solid var(--color-border); cursor: pointer;">
                            Probar conexión
                        </button>
                    </div>
                </div>
            </div>

            <!-- Nota OneDrive Personal -->
            <div class="rounded-2xl p-5 flex gap-4" style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant);">
                <span class="material-symbols-outlined flex-shrink-0 mt-0.5" style="color: #0078d4; font-size: 20px;">cloud</span>
                <div>
                    <p class="text-sm font-semibold mb-1" style="color: var(--color-text-primary);">OneDrive / SharePoint</p>
                    <p class="text-xs" style="color: var(--color-text-muted);">Configura la integración con Microsoft 365 en el tab <strong>"Microsoft 365"</strong>. Funciona con OneDrive personal y SharePoint corporativo usando la misma API Graph.</p>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- TAB: MICROSOFT 365                                                 -->
        <!-- ================================================================= -->
        <div v-if="activeTab === 'integration'" class="max-w-2xl flex flex-col gap-4">

            <!-- Estado actual -->
            <div class="rounded-2xl p-5 flex items-center gap-4"
                 :style="sharepoint_configured
                     ? 'background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.2);'
                     : 'background: var(--color-bg-card);'">
                <span class="material-symbols-outlined"
                      :style="`font-size: 28px; color: ${sharepoint_configured ? '#22c55e' : 'var(--color-text-muted)'}`">
                    {{ sharepoint_configured ? 'cloud_done' : 'cloud_off' }}
                </span>
                <div>
                    <p class="text-sm font-bold"
                       :style="`color: ${sharepoint_configured ? '#22c55e' : 'var(--color-text-primary)'}`">
                        {{ sharepoint_configured ? 'SharePoint conectado' : 'SharePoint no configurado' }}
                    </p>
                    <p class="text-xs" style="color: var(--color-text-muted);">
                        {{ sharepoint_configured
                            ? 'Los documentos se almacenan en SharePoint / OneDrive'
                            : 'Los documentos se almacenan localmente. Configura la integración para usar SharePoint.' }}
                    </p>
                </div>
                <button v-if="sharepoint_configured"
                        @click="testIntegration"
                        class="ml-auto flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold"
                        style="background: rgba(34,197,94,0.1); color: #166534; border: 1px solid rgba(34,197,94,0.2); cursor: pointer;">
                    <span class="material-symbols-outlined" style="font-size: 16px;">wifi_tethering</span>
                    Probar conexión
                </button>
            </div>

            <!-- Formulario -->
            <div class="rounded-2xl p-6" style="background: var(--color-bg-card);">
                <h3 class="text-sm font-bold mb-1"
                    style="font-family: var(--font-headline); color: var(--color-text-primary);">
                    Credenciales Azure AD / Microsoft Graph
                </h3>
                <p class="text-xs mb-5" style="color: var(--color-text-muted);">
                    Registra tu aplicación en portal.azure.com y completa los campos.
                </p>

                <div class="space-y-4">

                    <div>
                        <label class="text-xs font-semibold block mb-1.5"
                               style="color: var(--color-text-primary);">
                            Application (Client) ID
                        </label>
                        <input v-model="integrationForm.client_id"
                               type="text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                               class="w-full px-4 py-2.5 rounded-xl text-sm font-mono border-0 outline-none"
                               style="background: var(--color-bg-elevated); color: var(--color-text-primary);" />
                    </div>

                    <div>
                        <label class="text-xs font-semibold block mb-1.5"
                               style="color: var(--color-text-primary);">
                            Directory (Tenant) ID de Azure
                        </label>
                        <input v-model="integrationForm.tenant_azure_id"
                               type="text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                               class="w-full px-4 py-2.5 rounded-xl text-sm font-mono border-0 outline-none"
                               style="background: var(--color-bg-elevated); color: var(--color-text-primary);" />
                    </div>

                    <div>
                        <label class="text-xs font-semibold block mb-1.5"
                               style="color: var(--color-text-primary);">
                            Client Secret
                            <span class="font-normal ml-1" style="color: var(--color-text-muted);">
                                {{ integration?.has_secret ? '(dejar vacío para mantener el actual)' : '(requerido)' }}
                            </span>
                        </label>
                        <input v-model="integrationForm.client_secret"
                               type="password" placeholder="●●●●●●●●●●●●●●●●●●●●"
                               class="w-full px-4 py-2.5 rounded-xl text-sm border-0 outline-none"
                               style="background: var(--color-bg-elevated); color: var(--color-text-primary);" />
                    </div>

                    <div>
                        <label class="text-xs font-semibold block mb-1.5"
                               style="color: var(--color-text-primary);">
                            Site ID de SharePoint
                        </label>
                        <input v-model="integrationForm.site_id"
                               type="text" placeholder="tuempresa.sharepoint.com,site-id,web-id"
                               class="w-full px-4 py-2.5 rounded-xl text-sm font-mono border-0 outline-none"
                               style="background: var(--color-bg-elevated); color: var(--color-text-primary);" />
                        <p class="text-xs mt-1" style="color: var(--color-text-muted);">
                            Obtén el Site ID con:
                            <code class="px-1 py-0.5 rounded text-xs"
                                  style="background: var(--color-bg-elevated);">
                                GET https://graph.microsoft.com/v1.0/sites/{dominio}:/sites/{nombre}
                            </code>
                        </p>
                    </div>

                    <!-- Toggle activo -->
                    <div class="flex items-center justify-between pt-2">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--color-text-primary);">
                                Integración activa
                            </p>
                            <p class="text-xs" style="color: var(--color-text-muted);">
                                Desactiva sin borrar la configuración
                            </p>
                        </div>
                        <button @click="integrationForm.is_active = !integrationForm.is_active"
                                class="relative w-12 h-6 rounded-full transition-all"
                                :style="integrationForm.is_active ? 'background: var(--color-primary);' : 'background: var(--color-bg-elevated);'">
                            <div class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all"
                                 :style="integrationForm.is_active ? 'left: 26px;' : 'left: 2px;'"></div>
                        </button>
                    </div>

                </div>

                <div class="mt-6 pt-5" style="border-top: 1px solid var(--color-border-variant);">
                    <button @click="saveIntegration"
                            :disabled="integrationForm.processing"
                            class="flex items-center gap-2 px-6 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95"
                            style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                        <span class="material-symbols-outlined" style="font-size: 18px;">save</span>
                        Guardar integración
                    </button>
                </div>
            </div>

            <!-- Guía rápida -->
            <div class="rounded-2xl p-5" style="background: var(--color-bg-card);">
                <p class="text-xs font-bold uppercase tracking-wider mb-3"
                   style="color: var(--color-text-secondary);">Guía de configuración Azure AD</p>
                <ol class="space-y-2">
                    <li v-for="(step, i) in azureSteps" :key="i"
                        class="flex items-start gap-3">
                        <span class="flex-shrink-0 w-5 h-5 rounded-full text-xs font-bold flex items-center justify-center mt-0.5"
                              style="background: var(--color-primary); color: #fff;">
                            {{ i + 1 }}
                        </span>
                        <p class="text-xs" style="color: var(--color-text-secondary);">{{ step }}</p>
                    </li>
                </ol>
            </div>

        </div>

    </AppLayout>
</template>
