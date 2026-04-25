<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { useConfirm } from '@/composables/useConfirm'

const props = defineProps({
    contract:       { type: Object, required: true },
    event:          { type: Object, required: true },
    costItems:      { type: Array,  default: () => [] },
    priceItems:     { type: Array,  default: () => [] },
    totals:         { type: Object, default: () => ({}) },
    directTotal:    { type: Number, default: 0 },
    indirectTotal:  { type: Number, default: 0 },
    profitTotal:    { type: Number, default: 0 },
    grandTotal:     { type: Number, default: 0 },
    categoryLabels: { type: Object, default: () => ({}) },
    flash:          { type: Object, default: () => ({}) },
})

const page  = usePage()
const flash = computed(() => page.props.flash)
const { confirmDelete } = useConfirm()

const showAddForm = ref(false)
const searchQuery = ref('')
const selectedPriceItem = ref(null)

const form = useForm({
    contract_price_item_id: null,
    description:            '',
    unit:                   '',
    quantity:               1,
    unit_cost:              0,
    cost_category:          'mano_obra_directa',
    notes:                  '',
})

// Búsqueda en el catálogo
const filteredPriceItems = computed(() => {
    if (!searchQuery.value) return props.priceItems.slice(0, 20)
    const q = searchQuery.value.toLowerCase()
    return props.priceItems
        .filter(p => p.description.toLowerCase().includes(q) || (p.code ?? '').toLowerCase().includes(q))
        .slice(0, 20)
})

function selectFromCatalog(item) {
    selectedPriceItem.value = item
    form.contract_price_item_id = item.id
    form.description = item.description
    form.unit        = item.unit
    form.unit_cost   = item.unit_cost
    // Auto-categorize from catalog category
    const catMap = {
        mano_obra: 'mano_obra_directa',
        materiales: 'materiales',
        equipos: 'equipos',
        subcontratos: 'subcontratos',
        gastos_generales: 'gastos_obra',
    }
    form.cost_category = catMap[item.category] ?? 'otro'
    searchQuery.value  = ''
}

function clearCatalogSelection() {
    selectedPriceItem.value     = null
    form.contract_price_item_id = null
    form.description            = ''
    form.unit                   = ''
    form.unit_cost              = 0
    form.cost_category          = 'mano_obra_directa'
}

// Cálculo en tiempo real
const computedAmount = computed(() =>
    Math.round(form.quantity * form.unit_cost * 100) / 100
)

function submitItem() {
    form.post(route('quantum.items.store', {
        contract: props.contract.id,
        event:    props.event.id,
    }), {
        onSuccess: () => {
            form.reset()
            showAddForm.value = false
            selectedPriceItem.value = null
        },
    })
}

async function deleteItem(id) {
    if (!await confirmDelete('este ítem del quantum')) return
    router.delete(route('quantum.items.destroy', {
        contract: props.contract.id,
        event:    props.event.id,
        costItem: id,
    }), { preserveScroll: true })
}

function goBack() {
    router.get(route('quantum.index', { contract_id: props.contract.id }))
}

function fmt(amount) {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: props.contract.currency ?? 'CLP',
        minimumFractionDigits: 0,
    }).format(amount)
}

const reconciliationDiff = computed(() =>
    props.grandTotal - props.event.cost_impact
)
const isReconciled = computed(() => Math.abs(reconciliationDiff.value) < 1)

const directCategories  = ['mano_obra_directa', 'materiales', 'equipos', 'subcontratos']
const indirectCategories = ['gastos_obra', 'overhead_sede']
</script>

<template>
    <AppLayout :title="`Quantum — ${event.type_label}`">

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 mb-6 text-sm" style="color: var(--color-text-muted);">
            <button @click="goBack" style="background: none; border: none; cursor: pointer; color: var(--color-primary); font-weight: 600;">Quantum</button>
            <span class="material-symbols-outlined" style="font-size: 16px;">chevron_right</span>
            <span style="color: var(--color-text-secondary);">{{ contract.name }}</span>
            <span class="material-symbols-outlined" style="font-size: 16px;">chevron_right</span>
            <span>{{ event.type_label }} ({{ event.occurred_at }})</span>
        </div>

        <div v-if="flash?.success" class="flex items-center gap-3 p-4 rounded-xl mb-4"
             style="background: var(--color-success-container); color: var(--color-on-success-container);">
            <span class="material-symbols-outlined">check_circle</span>{{ flash.success }}
        </div>

        <!-- Aviso: sin CPU cargado -->
        <div v-if="priceItems.length === 0" class="flex items-start gap-3 p-4 rounded-xl mb-4"
             style="background: rgba(234,179,8,0.12); color: #92400e; border: 1px solid rgba(234,179,8,0.35);">
            <span class="material-symbols-outlined flex-shrink-0" style="color: #b45309; font-size: 20px;">warning</span>
            <div class="text-sm">
                <strong>Sin Cuadro de Precios Unitarios (CPU) cargado.</strong>
                Para ingresar ítems de quantum desde el catálogo de precios, primero debes cargar el CPU del contrato.
                <a :href="route('contracts.show', contract.id)" class="font-bold underline ml-1"
                   style="color: #92400e;">Ir al contrato para cargar el CPU</a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Columna principal: ítems de costo -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Info del evento -->
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-bold" style="color: var(--color-text-primary);">{{ event.type_label }} — {{ event.occurred_at }}</p>
                            <p class="text-xs mt-1" style="color: var(--color-text-secondary);">{{ event.description }}</p>
                        </div>
                        <a v-if="costItems.length > 0"
                           :href="route('quantum.export.event', { contract: contract.id, event: event.id })"
                           class="flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold flex-shrink-0 ml-4 transition-all active:scale-95"
                           style="background: rgba(16,185,129,0.12); color: #059669; border: 1px solid rgba(16,185,129,0.3);">
                            <span class="material-symbols-outlined" style="font-size: 15px;">download</span>
                            Exportar Excel
                        </a>
                        <span class="text-xs font-bold px-3 py-1 rounded-full"
                              style="background: var(--color-bg-elevated); color: var(--color-text-muted);">
                            {{ event.party_label }}
                        </span>
                    </div>
                    <div v-if="event.cost_impact > 0" class="mt-3 pt-3" style="border-top: 1px solid var(--color-border-variant);">
                        <p class="text-xs" style="color: var(--color-text-muted);">
                            Impacto registrado en el evento: <strong style="color: var(--color-text-primary);">{{ fmt(event.cost_impact) }}</strong>
                        </p>
                    </div>
                </div>

                <!-- Tabla de ítems -->
                <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <div class="px-5 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--color-border-variant);">
                        <p class="text-sm font-bold" style="color: var(--color-text-primary);">Ítems del quantum</p>
                        <button @click="showAddForm = !showAddForm"
                                class="flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-bold transition-all"
                                style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer;">
                            <span class="material-symbols-outlined" style="font-size: 14px;">{{ showAddForm ? 'close' : 'add' }}</span>
                            {{ showAddForm ? 'Cancelar' : 'Agregar ítem' }}
                        </button>
                    </div>

                    <!-- Formulario agregar ítem -->
                    <div v-if="showAddForm" class="p-5" style="background: var(--color-bg-elevated); border-bottom: 1px solid var(--color-border-variant);">
                        <form @submit.prevent="submitItem" class="space-y-4">

                            <!-- Búsqueda en catálogo -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color: var(--color-text-secondary);">
                                    Buscar en catálogo de precios (CPU)
                                </label>
                                <div v-if="selectedPriceItem" class="flex items-center gap-2 p-3 rounded-xl"
                                     style="background: var(--color-primary-container);">
                                    <span class="material-symbols-outlined" style="font-size: 16px; color: var(--color-primary);">check_circle</span>
                                    <span class="text-xs font-semibold flex-1" style="color: var(--color-on-primary-container);">{{ selectedPriceItem.label }}</span>
                                    <button type="button" @click="clearCatalogSelection"
                                            class="text-xs font-bold" style="background: none; border: none; cursor: pointer; color: var(--color-primary);">
                                        Cambiar
                                    </button>
                                </div>
                                <div v-else>
                                    <input v-model="searchQuery" type="text" placeholder="Escribe para buscar en el CPU o deja vacío para precio manual…"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                    <div v-if="searchQuery || filteredPriceItems.length > 0" class="mt-1 rounded-xl overflow-hidden max-h-48 overflow-y-auto"
                                         style="background: var(--color-bg-card); border: 1px solid var(--color-border-variant);">
                                        <div v-if="filteredPriceItems.length === 0" class="p-3 text-xs text-center" style="color: var(--color-text-muted);">
                                            No encontrado — completa los campos manualmente
                                        </div>
                                        <button v-for="p in filteredPriceItems" :key="p.id" type="button"
                                                @click="selectFromCatalog(p)"
                                                class="w-full text-left px-4 py-2.5 text-xs hover:bg-[var(--color-bg-hover)] transition-colors"
                                                style="background: none; border: none; cursor: pointer; color: var(--color-text-primary);">
                                            <span class="font-semibold">{{ p.label }}</span>
                                            <span class="ml-2" style="color: var(--color-text-muted);">{{ new Intl.NumberFormat('es-CL', {style:'currency', currency: contract.currency ?? 'CLP', minimumFractionDigits: 0}).format(p.unit_cost) }}/{{ p.unit }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Campos del ítem -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color: var(--color-text-secondary);">Descripción *</label>
                                    <input v-model="form.description" type="text"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color: var(--color-text-secondary);">Categoría *</label>
                                    <select v-model="form.cost_category" class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                            style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);">
                                        <option v-for="(label, key) in categoryLabels" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color: var(--color-text-secondary);">Unidad *</label>
                                    <input v-model="form.unit" type="text" placeholder="hr, m3, gl, un…"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color: var(--color-text-secondary);">Cantidad *</label>
                                    <input v-model.number="form.quantity" type="number" min="0.001" step="0.001"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color: var(--color-text-secondary);">
                                        Precio unitario * {{ selectedPriceItem ? '(del CPU)' : '(manual)' }}
                                    </label>
                                    <input v-model.number="form.unit_cost" type="number" min="0.01" step="0.01"
                                           class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                           style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);"
                                           :readonly="!!selectedPriceItem" />
                                </div>
                            </div>

                            <!-- Monto calculado -->
                            <div class="flex items-center justify-between p-4 rounded-xl"
                                 style="background: var(--color-primary-container);">
                                <span class="text-sm font-bold" style="color: var(--color-on-primary-container);">Monto calculado</span>
                                <span class="text-lg font-extrabold" style="color: var(--color-primary);">
                                    {{ new Intl.NumberFormat('es-CL', {style:'currency', currency: contract.currency ?? 'CLP', minimumFractionDigits: 0}).format(computedAmount) }}
                                </span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color: var(--color-text-secondary);">Notas (opcional)</label>
                                <input v-model="form.notes" type="text" placeholder="Respaldo, referencia en el contrato…"
                                       class="w-full px-4 py-2.5 rounded-xl text-sm border-none outline-none"
                                       style="background: var(--color-bg-input); color: var(--color-text-primary); font-family: var(--font-body);" />
                            </div>

                            <button type="submit" :disabled="form.processing"
                                    class="px-5 py-2.5 rounded-full text-sm font-bold"
                                    style="background: var(--gradient-primary); color: var(--color-on-primary); border: none; cursor: pointer;">
                                Agregar ítem
                            </button>
                        </form>
                    </div>

                    <!-- Lista de ítems agrupados por categoría -->
                    <div v-if="costItems.length > 0">
                        <template v-for="cat in Object.keys(categoryLabels)" :key="cat">
                            <template v-if="costItems.filter(i => i.cost_category === cat).length > 0">
                                <div class="px-5 py-2 text-xs font-bold uppercase tracking-wider"
                                     style="background: var(--color-bg-sidebar); color: var(--color-text-muted);">
                                    {{ categoryLabels[cat] }}
                                    <span v-if="directCategories.includes(cat)" class="ml-1 text-xs normal-case font-normal" style="color: var(--color-text-muted);">(Directo)</span>
                                    <span v-else-if="indirectCategories.includes(cat)" class="ml-1 text-xs normal-case font-normal" style="color: var(--color-text-muted);">(Indirecto)</span>
                                </div>
                                <div v-for="item in costItems.filter(i => i.cost_category === cat)" :key="item.id"
                                     class="flex items-center gap-3 px-5 py-3"
                                     style="border-top: 1px solid var(--color-border-variant);">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm" style="color: var(--color-text-primary);">{{ item.description }}</span>
                                            <span v-if="item.is_from_catalog" class="text-xs px-1.5 py-0.5 rounded-full"
                                                  style="background: var(--color-primary-container); color: var(--color-on-primary-container);">CPU</span>
                                        </div>
                                        <div class="text-xs mt-0.5" style="color: var(--color-text-muted);">
                                            {{ item.quantity }} {{ item.unit }} × {{ fmt(item.unit_cost) }}
                                            <span v-if="item.notes" class="ml-2">— {{ item.notes }}</span>
                                        </div>
                                    </div>
                                    <div class="text-sm font-bold flex-shrink-0" style="color: var(--color-text-primary);">{{ fmt(item.amount) }}</div>
                                    <button @click="deleteItem(item.id)"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg"
                                            style="background: none; border: none; cursor: pointer; color: var(--color-text-muted);"
                                            :onMouseover="e => e.currentTarget.style.color = 'var(--color-error)'"
                                            :onMouseout="e => e.currentTarget.style.color = 'var(--color-text-muted)'">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">delete</span>
                                    </button>
                                </div>
                            </template>
                        </template>
                    </div>

                    <div v-else class="py-12 text-center">
                        <span class="material-symbols-outlined mb-2 block" style="font-size: 36px; color: var(--color-text-muted);">receipt_long</span>
                        <p class="text-sm" style="color: var(--color-text-muted);">Sin ítems. Agrega el primero desde el catálogo o manualmente.</p>
                    </div>
                </div>
            </div>

            <!-- Columna lateral: resumen -->
            <div class="space-y-5">

                <!-- Resumen de totales -->
                <div class="rounded-2xl p-5" style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
                    <p class="text-xs font-bold uppercase tracking-wider mb-4" style="color: var(--color-text-muted);">Resumen del quantum</p>
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--color-text-secondary);">Costos directos</span>
                            <span class="font-bold" style="color: var(--color-text-primary);">{{ fmt(directTotal) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--color-text-secondary);">Costos indirectos</span>
                            <span class="font-bold" style="color: var(--color-text-primary);">{{ fmt(indirectTotal) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span style="color: var(--color-text-secondary);">Utilidad</span>
                            <span class="font-bold" style="color: var(--color-text-primary);">{{ fmt(profitTotal) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-extrabold pt-3"
                             style="border-top: 2px solid var(--color-border-variant);">
                            <span style="color: var(--color-text-primary);">Total quantum</span>
                            <span style="color: var(--color-primary);">{{ fmt(grandTotal) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Conciliación con impacto registrado -->
                <div v-if="event.cost_impact > 0" class="rounded-2xl p-5"
                     :style="isReconciled ? 'background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3);' : 'background: rgba(234,179,8,0.1); border: 1px solid rgba(234,179,8,0.3);'">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="material-symbols-outlined" style="font-size: 18px;"
                              :style="isReconciled ? 'color: #1d4ed8;' : 'color: #a16207;'">
                            {{ isReconciled ? 'check_circle' : 'warning' }}
                        </span>
                        <p class="text-xs font-bold" :style="isReconciled ? 'color: #1d4ed8;' : 'color: #a16207;'">
                            {{ isReconciled ? 'Quantum conciliado' : 'Diferencia con impacto registrado' }}
                        </p>
                    </div>
                    <div class="space-y-1 text-xs" :style="isReconciled ? 'color: #1d4ed8;' : 'color: #a16207;'">
                        <div class="flex justify-between">
                            <span>Impacto en evento</span>
                            <span class="font-bold">{{ fmt(event.cost_impact) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Total quantum</span>
                            <span class="font-bold">{{ fmt(grandTotal) }}</span>
                        </div>
                        <div v-if="!isReconciled" class="flex justify-between pt-2" style="border-top: 1px solid rgba(0,0,0,0.1);">
                            <span class="font-bold">Diferencia</span>
                            <span class="font-bold">{{ fmt(reconciliationDiff) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Nota sobre CPU -->
                <div v-if="priceItems.length === 0" class="rounded-2xl p-4"
                     style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant);">
                    <div class="flex items-start gap-2">
                        <span class="material-symbols-outlined flex-shrink-0" style="font-size: 16px; color: var(--color-text-muted);">info</span>
                        <p class="text-xs" style="color: var(--color-text-muted);">
                            No hay CPU cargado para este contrato. Agrega ítems manualmente o carga el Cuadro de Precios Unitarios desde la ficha del contrato.
                        </p>
                    </div>
                </div>
                <div v-else class="rounded-2xl p-4"
                     style="background: var(--color-bg-elevated); border: 1px solid var(--color-border-variant);">
                    <div class="flex items-start gap-2">
                        <span class="material-symbols-outlined flex-shrink-0" style="font-size: 16px; color: var(--color-primary);">library_books</span>
                        <p class="text-xs" style="color: var(--color-text-muted);">
                            CPU disponible: <strong style="color: var(--color-text-primary);">{{ priceItems.length }} ítems</strong> en el catálogo de precios.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
