<script setup>
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

const props = defineProps({
    show:               { type: Boolean, default: false },
    contract:           { type: Object,  required: true },
    event:              { type: Object,  default: null },
    typeLabels:         { type: Object,  default: () => ({}) },
    partyLabels:        { type: Object,  default: () => ({}) },
    resLabels:          { type: Object,  default: () => ({}) },
    notificationLabels: { type: Object,  default: () => ({}) },
    basisDocLabels:     { type: Object,  default: () => ({}) },
})

const emit = defineEmits(['close'])

const form = useForm({
    type:                 'otro',
    occurred_at:          '',
    description:          '',
    contractual_basis:    '',
    contractual_basis_doc: '',
    responsible_party:    'contratista',
    schedule_impact_days: 0,
    cost_impact:          0,
    resolution_status:    'pendiente',
    resolution_notes:     '',
    notified_at:          '',
    notification_status:  'pendiente',
    rights_reserved:      false,
    rights_reserved_at:   '',
})

watch(() => props.show, (open) => {
    if (!open) return
    form.clearErrors()
    if (props.event) {
        form.type                  = props.event.type
        form.occurred_at           = props.event.occurred_at_raw ?? ''
        form.description           = props.event.description ?? ''
        form.contractual_basis     = props.event.contractual_basis ?? ''
        form.contractual_basis_doc = props.event.contractual_basis_doc ?? ''
        form.responsible_party     = props.event.responsible_party
        form.schedule_impact_days  = props.event.schedule_impact_days
        form.cost_impact           = props.event.cost_impact
        form.resolution_status     = props.event.resolution_status
        form.resolution_notes      = props.event.resolution_notes ?? ''
        form.notified_at           = props.event.notified_at_raw ?? ''
        form.notification_status   = props.event.notification_status
        form.rights_reserved       = props.event.rights_reserved ?? false
        form.rights_reserved_at    = props.event.rights_reserved_at_raw ?? ''
    } else {
        form.type                  = 'otro'
        form.occurred_at           = ''
        form.description           = ''
        form.contractual_basis     = ''
        form.contractual_basis_doc = ''
        form.responsible_party     = 'contratista'
        form.schedule_impact_days  = 0
        form.cost_impact           = 0
        form.resolution_status     = 'pendiente'
        form.resolution_notes      = ''
        form.notified_at           = ''
        form.notification_status   = 'pendiente'
        form.rights_reserved       = false
        form.rights_reserved_at    = ''
    }
})

function submit() {
    const routeParams = { contract: props.contract.id }
    if (props.event) {
        form.put(route('events.update', { ...routeParams, event: props.event.id }), {
            onSuccess: () => emit('close'),
        })
    } else {
        form.post(route('events.store', routeParams), {
            onSuccess: () => emit('close'),
        })
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 style="background: rgba(0,0,0,0.5);" @click.self="emit('close')">

                <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100">
                    <div v-if="show" class="w-full max-w-2xl rounded-3xl p-8 shadow-2xl max-h-[90vh] overflow-y-auto"
                         style="background: var(--color-bg-card);">

                        <!-- Encabezado -->
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-extrabold" style="font-family: var(--font-headline); color: var(--color-text-primary);">
                                {{ event ? 'Editar evento' : 'Registrar evento' }}
                            </h3>
                            <button @click="emit('close')" class="w-8 h-8 flex items-center justify-center rounded-full"
                                    style="color: var(--color-text-muted); background: none; border: none; cursor: pointer;"
                                    :onMouseover="e => e.currentTarget.style.background = 'var(--color-bg-hover)'"
                                    :onMouseout="e => e.currentTarget.style.background = ''">
                                <span class="material-symbols-outlined" style="font-size: 20px;">close</span>
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="space-y-4">

                            <!-- Tipo + Fecha -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Tipo de evento *</label>
                                    <select v-model="form.type" class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                            style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                        <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                    <p v-if="form.errors.type" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.type }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Fecha de ocurrencia *</label>
                                    <input v-model="form.occurred_at" type="date"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                    <p v-if="form.errors.occurred_at" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.occurred_at }}</p>
                                </div>
                            </div>

                            <!-- Descripción -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Descripción *</label>
                                <textarea v-model="form.description" rows="3"
                                          placeholder="Describe el evento contractual con detalle suficiente para el expediente..."
                                          class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none resize-none"
                                          style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"></textarea>
                                <p v-if="form.errors.description" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.description }}</p>
                            </div>

                            <!-- Base contractual -->
                            <div class="p-4 rounded-2xl" style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant);">
                                <p class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--color-text-muted);">Fundamento contractual</p>
                                <div class="grid grid-cols-2 gap-4 mb-3">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                               style="color: var(--color-text-secondary);">Documento base</label>
                                        <select v-model="form.contractual_basis_doc"
                                                class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                                style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                            <option value="">Sin especificar</option>
                                            <option v-for="(label, key) in basisDocLabels" :key="key" :value="key">{{ label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <!-- spacer -->
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Cláusula o fundamento</label>
                                    <textarea v-model="form.contractual_basis" rows="2"
                                              placeholder="Ej: Cláusula 12.3 — Eventos de fuerza mayor..."
                                              class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none resize-none"
                                              style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"></textarea>
                                </div>
                            </div>

                            <!-- Parte responsable + Estado resolución -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Parte responsable *</label>
                                    <select v-model="form.responsible_party" class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                            style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                        <option v-for="(label, key) in partyLabels" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Estado de resolución *</label>
                                    <select v-model="form.resolution_status" class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                            style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                        <option v-for="(label, key) in resLabels" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Impactos -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Impacto plazo (días) *</label>
                                    <input v-model.number="form.schedule_impact_days" type="number" min="0"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                           style="color: var(--color-text-secondary);">Impacto costo *</label>
                                    <input v-model.number="form.cost_impact" type="number" min="0" step="0.01"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                </div>
                            </div>

                            <!-- Notificación -->
                            <div class="p-4 rounded-2xl" style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant);">
                                <p class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--color-text-muted);">Notificación al mandante</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                               style="color: var(--color-text-secondary);">Fecha de notificación</label>
                                        <input v-model="form.notified_at" type="date"
                                               class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                               style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                        <p class="text-xs mt-1" style="color: var(--color-text-muted);">El estado se calcula automáticamente</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                               style="color: var(--color-text-secondary);">Estado notificación *</label>
                                        <select v-model="form.notification_status" class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                                style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                            <option v-for="(label, key) in notificationLabels" :key="key" :value="key">{{ label }}</option>
                                        </select>
                                        <p class="text-xs mt-1" style="color: var(--color-text-muted);">Ajusta solo si aplica «No aplica»</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Reserva de derechos -->
                            <div v-if="form.responsible_party !== 'contratista'"
                                 class="p-4 rounded-2xl"
                                 :style="form.rights_reserved
                                     ? 'background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.3);'
                                     : 'background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant);'">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined" style="font-size: 18px; color: #1d4ed8;">policy</span>
                                        <p class="text-xs font-bold uppercase tracking-wider" style="color: #1d4ed8;">Reserva de derechos</p>
                                    </div>
                                    <!-- Toggle -->
                                    <button type="button" @click="form.rights_reserved = !form.rights_reserved"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                            :style="form.rights_reserved ? 'background: #3b82f6;' : 'background: var(--color-border-variant);'">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow"
                                              :style="form.rights_reserved ? 'transform: translateX(20px);' : 'transform: translateX(4px);'"></span>
                                    </button>
                                </div>
                                <p class="text-xs mb-3" style="color: var(--color-text-muted);">
                                    Marca si el trabajo se ejecutó bajo protesta o con reserva explícita de derechos de reclamo.
                                </p>
                                <div v-if="form.rights_reserved">
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color: #1d4ed8;">
                                        Fecha de reserva *
                                    </label>
                                    <input v-model="form.rights_reserved_at" type="date"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                    <p class="text-xs mt-2" style="color: var(--color-text-muted);">
                                        Si ya enviaste una carta de reserva, regístrala en el módulo de Cartas (tipo <strong>Reserva de Derechos</strong>) y vincúlala a este evento.
                                    </p>
                                    <p v-if="form.errors.rights_reserved_at" class="text-xs mt-1" style="color: var(--color-error);">{{ form.errors.rights_reserved_at }}</p>
                                </div>
                            </div>

                            <!-- Notas de resolución -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5"
                                       style="color: var(--color-text-secondary);">Notas de resolución</label>
                                <textarea v-model="form.resolution_notes" rows="2"
                                          placeholder="Acuerdo alcanzado, próximos pasos, etc."
                                          class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none resize-none"
                                          style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"></textarea>
                            </div>

                            <!-- Acciones -->
                            <div class="flex items-center justify-end gap-3 pt-4 border-t"
                                 style="border-color: var(--color-border-variant);">
                                <button type="button" @click="emit('close')"
                                        class="px-5 py-2.5 rounded-full text-sm font-bold"
                                        style="background: var(--color-bg-elevated); color: var(--color-text-secondary); border: none; cursor: pointer;">
                                    Cancelar
                                </button>
                                <button type="submit" :disabled="form.processing"
                                        class="px-5 py-2.5 rounded-full text-sm font-bold transition-all active:scale-95 disabled:opacity-60"
                                        style="background: var(--gradient-primary); color: var(--color-on-primary); box-shadow: var(--shadow-primary); border: none; cursor: pointer;">
                                    {{ event ? 'Guardar cambios' : 'Registrar evento' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
