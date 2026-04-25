<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    contract:  { type: Object, default: null },
    companies: { type: Array,  default: () => [] },
})

const isEditing = computed(() => !!props.contract)
const title     = computed(() => isEditing.value ? 'Editar Contrato' : 'Nuevo Contrato')

const form = useForm({
    name:                   props.contract?.name                   ?? '',
    description:            props.contract?.description            ?? '',
    type:                   props.contract?.type                   ?? 'obra',
    mandante_company_id:    props.contract?.mandante_company_id    ?? '',
    contractor_company_id:  props.contract?.contractor_company_id  ?? '',
    original_amount:        props.contract?.original_amount        ?? '',
    currency:               props.contract?.currency               ?? 'CLP',
    contractual_start_date: props.contract?.contractual_start_date ?? '',
    contractual_end_date:   props.contract?.contractual_end_date   ?? '',
    actual_start_date:      props.contract?.actual_start_date      ?? '',
    projected_end_date:     props.contract?.projected_end_date     ?? '',
    notification_days:      props.contract?.notification_days      ?? 5,
    applicable_law:         props.contract?.applicable_law         ?? '',
    jurisdiction:           props.contract?.jurisdiction           ?? '',
})

function submit() {
    if (isEditing.value) {
        form.put(route('contracts.update', props.contract.id))
    } else {
        form.post(route('contracts.store'))
    }
}

const mandantes   = computed(() => props.companies.filter(c => ['mandante', 'ambos'].includes(c.type)))
const contratistas = computed(() => props.companies.filter(c => ['contratista', 'ambos'].includes(c.type)))
</script>

<template>
    <AppLayout :title="title">
        <!-- Encabezado -->
        <div class="mb-10">
            <div class="flex items-center gap-2 text-sm font-bold mb-2 uppercase tracking-wide" style="color: var(--color-secondary);">
                <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">verified_user</span>
                {{ isEditing ? 'Modificar contrato' : 'Modo borrador' }}
            </div>
            <h1 class="text-4xl font-extrabold tracking-tight mb-3" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                {{ title }}
            </h1>
            <p class="text-lg leading-relaxed" style="color: var(--color-text-secondary); max-width: 640px;">
                {{ isEditing
                    ? 'Modifica los datos del contrato. El número se genera automáticamente y no puede cambiarse.'
                    : 'Registra un nuevo contrato. El número se asignará automáticamente al guardar.' }}
            </p>
        </div>

        <form class="space-y-10" @submit.prevent="submit">

            <!-- Sección 1: Información general -->
            <section class="p-10 rounded-xl shadow-sm space-y-8" style="background: var(--color-bg-card);">
                <div class="flex items-center gap-4 mb-2">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--color-bg-hover);">
                        <span class="material-symbols-outlined text-lg" style="color: var(--color-primary);">info</span>
                    </div>
                    <h2 class="text-xl font-bold" style="color: var(--color-text-primary);">Información general</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Nombre -->
                    <div class="space-y-2 md:col-span-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                            Nombre del contrato <span style="color: var(--color-error);">*</span>
                        </label>
                        <input v-model="form.name" type="text"
                               placeholder="Ej: Construcción Planta Concentradora Fase 2"
                               class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                               :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.name ? 'outline: 2px solid var(--color-error);' : ''}`"
                               :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                               :onblur="e => e.target.style.background = 'var(--color-bg-input)'">
                        <p v-if="form.errors.name" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.name }}</p>
                    </div>

                    <!-- Tipo -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                            Tipo de contrato <span style="color: var(--color-error);">*</span>
                        </label>
                        <select v-model="form.type"
                                class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                :style="`background: var(--color-bg-input); color: var(--color-text-primary);`">
                            <option value="obra">Obra</option>
                            <option value="suministro">Suministro</option>
                            <option value="servicios">Servicios</option>
                            <option value="EPC">EPC</option>
                            <option value="mixto">Mixto</option>
                        </select>
                    </div>

                    <!-- Días notificación -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                            Días hábiles de notificación <span style="color: var(--color-error);">*</span>
                        </label>
                        <input v-model="form.notification_days" type="number" min="1" max="90"
                               placeholder="5"
                               class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                               :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.notification_days ? 'outline: 2px solid var(--color-error);' : ''}`"
                               :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                               :onblur="e => e.target.style.background = 'var(--color-bg-input)'">
                        <p v-if="form.errors.notification_days" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.notification_days }}</p>
                    </div>

                    <!-- Descripción -->
                    <div class="space-y-2 md:col-span-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">Descripción</label>
                        <textarea v-model="form.description" rows="3"
                                  placeholder="Descripción general del alcance y objeto del contrato..."
                                  class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all resize-none"
                                  :style="`background: var(--color-bg-input); color: var(--color-text-primary);`"
                                  :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                  :onblur="e => e.target.style.background = 'var(--color-bg-input)'"></textarea>
                    </div>
                </div>
            </section>

            <!-- Sección 2: Partes -->
            <section class="p-10 rounded-xl shadow-sm space-y-8" style="background: var(--color-bg-card);">
                <div class="flex items-center gap-4 mb-2">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--color-bg-hover);">
                        <span class="material-symbols-outlined text-lg" style="color: var(--color-primary);">group</span>
                    </div>
                    <h2 class="text-xl font-bold" style="color: var(--color-text-primary);">Partes del contrato</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Mandante -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                            Mandante <span style="color: var(--color-error);">*</span>
                        </label>
                        <select v-model="form.mandante_company_id"
                                class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.mandante_company_id ? 'outline: 2px solid var(--color-error);' : ''}`">
                            <option value="" disabled>Seleccionar mandante...</option>
                            <option v-for="c in mandantes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="form.errors.mandante_company_id" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.mandante_company_id }}</p>
                        <p v-if="mandantes.length === 0" class="text-xs ml-1" style="color: var(--color-text-muted);">
                            No hay empresas de tipo mandante.
                            <Link :href="route('companies.create')" style="color: var(--color-primary);">Crear empresa →</Link>
                        </p>
                    </div>

                    <!-- Contratista -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                            Contratista <span style="color: var(--color-error);">*</span>
                        </label>
                        <select v-model="form.contractor_company_id"
                                class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.contractor_company_id ? 'outline: 2px solid var(--color-error);' : ''}`">
                            <option value="" disabled>Seleccionar contratista...</option>
                            <option v-for="c in contratistas" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="form.errors.contractor_company_id" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.contractor_company_id }}</p>
                    </div>
                </div>
            </section>

            <!-- Sección 3: Plazos y monto -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <!-- Fechas (2/3) -->
                <section class="lg:col-span-2 p-10 rounded-xl shadow-sm space-y-8" style="background: var(--color-bg-card);">
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--color-bg-hover);">
                            <span class="material-symbols-outlined text-lg" style="color: var(--color-primary);">calendar_month</span>
                        </div>
                        <h2 class="text-xl font-bold" style="color: var(--color-text-primary);">Plazos contractuales</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                                Inicio contractual <span style="color: var(--color-error);">*</span>
                            </label>
                            <input v-model="form.contractual_start_date" type="date"
                                   class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                   :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.contractual_start_date ? 'outline: 2px solid var(--color-error);' : ''}`"
                                   :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   :onblur="e => e.target.style.background = 'var(--color-bg-input)'">
                            <p v-if="form.errors.contractual_start_date" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.contractual_start_date }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">
                                Término contractual <span style="color: var(--color-error);">*</span>
                            </label>
                            <input v-model="form.contractual_end_date" type="date"
                                   class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                   :style="`background: var(--color-bg-input); color: var(--color-text-primary); ${form.errors.contractual_end_date ? 'outline: 2px solid var(--color-error);' : ''}`"
                                   :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   :onblur="e => e.target.style.background = 'var(--color-bg-input)'">
                            <p v-if="form.errors.contractual_end_date" class="text-xs ml-1" style="color: var(--color-error);">{{ form.errors.contractual_end_date }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">Inicio real</label>
                            <input v-model="form.actual_start_date" type="date"
                                   class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                   :style="`background: var(--color-bg-input); color: var(--color-text-primary);`"
                                   :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   :onblur="e => e.target.style.background = 'var(--color-bg-input)'">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">Término proyectado</label>
                            <input v-model="form.projected_end_date" type="date"
                                   class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                                   :style="`background: var(--color-bg-input); color: var(--color-text-primary);`"
                                   :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                                   :onblur="e => e.target.style.background = 'var(--color-bg-input)'">
                        </div>
                    </div>
                </section>

                <!-- Monto (1/3) -->
                <section class="p-8 rounded-xl flex flex-col justify-between"
                         style="background: var(--gradient-primary); box-shadow: var(--shadow-primary);">
                    <div>
                        <div class="flex items-center gap-3 mb-5">
                            <span class="material-symbols-outlined" style="color: var(--color-on-primary); font-size: 22px; font-variation-settings: 'FILL' 1;">payments</span>
                            <h2 class="text-lg font-bold" style="color: var(--color-on-primary);">Monto contractual</h2>
                        </div>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-xs font-bold uppercase tracking-wide" style="color: var(--color-on-primary); opacity: 0.8;">
                                    Moneda <span style="opacity: 1;">*</span>
                                </label>
                                <select v-model="form.currency"
                                        class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none font-bold"
                                        style="background: rgba(255,255,255,0.15); color: var(--color-on-primary);">
                                    <option value="CLP" style="background: var(--color-bg-card); color: var(--color-text-primary);">CLP — Peso chileno</option>
                                    <option value="USD" style="background: var(--color-bg-card); color: var(--color-text-primary);">USD — Dólar americano</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-xs font-bold uppercase tracking-wide" style="color: var(--color-on-primary); opacity: 0.8;">
                                    Monto original <span style="opacity: 1;">*</span>
                                </label>
                                <input v-model="form.original_amount" type="number" min="1" step="1"
                                       :placeholder="form.currency === 'CLP' ? '50000000' : '250000'"
                                       class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none font-semibold"
                                       style="background: rgba(255,255,255,0.15); color: var(--color-on-primary);">
                                <p v-if="form.errors.original_amount" class="text-xs" style="color: var(--color-on-primary); opacity: 0.9;">{{ form.errors.original_amount }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-8 p-4 rounded-xl" style="background: rgba(255,255,255,0.12);">
                        <p class="text-xs leading-snug" style="color: var(--color-on-primary); opacity: 0.85;">
                            El monto vigente se actualiza automáticamente al aprobar órdenes de cambio.
                        </p>
                    </div>
                </section>
            </div>

            <!-- Sección 4: Marco legal -->
            <section class="p-10 rounded-xl shadow-sm space-y-8" style="background: var(--color-bg-card);">
                <div class="flex items-center gap-4 mb-2">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--color-bg-hover);">
                        <span class="material-symbols-outlined text-lg" style="color: var(--color-primary);">gavel</span>
                    </div>
                    <h2 class="text-xl font-bold" style="color: var(--color-text-primary);">Marco legal</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">Ley aplicable</label>
                        <input v-model="form.applicable_law" type="text"
                               placeholder="Ej: Derecho chileno"
                               class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                               :style="`background: var(--color-bg-input); color: var(--color-text-primary);`"
                               :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                               :onblur="e => e.target.style.background = 'var(--color-bg-input)'">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold ml-1" style="color: var(--color-text-secondary);">Jurisdicción</label>
                        <input v-model="form.jurisdiction" type="text"
                               placeholder="Ej: Tribunales de Santiago"
                               class="w-full rounded-xl px-4 py-3 text-sm border-none outline-none transition-all"
                               :style="`background: var(--color-bg-input); color: var(--color-text-primary);`"
                               :onfocus="e => e.target.style.background = 'var(--color-bg-input-focus)'"
                               :onblur="e => e.target.style.background = 'var(--color-bg-input)'">
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="flex items-center justify-between pt-10"
                    style="border-top: 1px solid var(--color-border-variant);">
                <Link
                    :href="route('contracts.index')"
                    class="px-8 py-3 font-bold transition-colors"
                    style="color: var(--color-text-secondary);"
                    :onMouseover="e => e.currentTarget.style.color = 'var(--color-text-primary)'"
                    :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-secondary)'"
                >
                    Cancelar
                </Link>
                <div class="flex items-center gap-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-10 py-4 rounded-full font-extrabold transition-all active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed"
                        style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); font-family: var(--font-body); border: none; cursor: pointer;"
                    >
                        {{ form.processing ? 'Guardando...' : (isEditing ? 'Guardar cambios' : 'Crear contrato') }}
                    </button>
                </div>
            </footer>
        </form>
    </AppLayout>
</template>
