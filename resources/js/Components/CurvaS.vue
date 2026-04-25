<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Tooltip,
    Legend,
} from 'chart.js'

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend)

const props = defineProps({
    milestones: { type: Array, default: () => [] },
})

const canvas = ref(null)
let chart    = null

function buildChartData(milestones) {
    if (!milestones.length) return null

    // Ordenar por fecha planificada
    const sorted = [...milestones].sort((a, b) => {
        return new Date(a.planned_date_raw) - new Date(b.planned_date_raw)
    })

    const labels   = []
    const planned  = []
    const real     = []
    let   cumPlan  = 0
    let   cumReal  = 0
    const total    = sorted.length

    sorted.forEach((m, i) => {
        labels.push(m.planned_date ?? `Hito ${i + 1}`)
        cumPlan = Math.round(((i + 1) / total) * 100)
        planned.push(cumPlan)

        // Avance real acumulado: promedio ponderado de % completado
        cumReal = Math.round(sorted.slice(0, i + 1).reduce((s, x) => s + x.progress_percentage, 0) / (i + 1))
        real.push(cumReal)
    })

    return { labels, planned, real }
}

function destroyChart() {
    if (chart) {
        chart.destroy()
        chart = null
    }
}

function renderChart() {
    destroyChart()
    if (!canvas.value) return

    const data = buildChartData(props.milestones)
    if (!data) return

    const primaryColor   = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#2a6486'
    const secondaryColor = getComputedStyle(document.documentElement).getPropertyValue('--color-secondary').trim() || '#5a8a9a'

    chart = new Chart(canvas.value, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label:           'Planificado (%)',
                    data:            data.planned,
                    borderColor:     primaryColor,
                    backgroundColor: primaryColor + '20',
                    borderWidth:     2,
                    fill:            true,
                    tension:         0.3,
                    pointRadius:     3,
                },
                {
                    label:           'Real (%)',
                    data:            data.real,
                    borderColor:     secondaryColor,
                    backgroundColor: secondaryColor + '20',
                    borderWidth:     2,
                    borderDash:      [5, 4],
                    fill:            true,
                    tension:         0.3,
                    pointRadius:     3,
                },
            ],
        },
        options: {
            responsive:          true,
            maintainAspectRatio: false,
            interaction: {
                mode:      'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    labels: {
                        color:    getComputedStyle(document.documentElement).getPropertyValue('--color-text-secondary').trim(),
                        font:     { size: 11, family: 'Inter' },
                        boxWidth: 16,
                    },
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y}%`,
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        color:    getComputedStyle(document.documentElement).getPropertyValue('--color-text-muted').trim(),
                        font:     { size: 10 },
                        maxRotation: 45,
                        maxTicksLimit: 12,
                    },
                    grid: { display: false },
                },
                y: {
                    min: 0,
                    max: 100,
                    ticks: {
                        color:    getComputedStyle(document.documentElement).getPropertyValue('--color-text-muted').trim(),
                        font:     { size: 10 },
                        callback: v => `${v}%`,
                    },
                    grid: {
                        color: getComputedStyle(document.documentElement).getPropertyValue('--color-border-variant').trim() + '80',
                    },
                },
            },
        },
    })
}

onMounted(renderChart)
onUnmounted(destroyChart)
watch(() => props.milestones, renderChart, { deep: true })
</script>

<template>
    <div v-if="milestones.length > 1" class="p-6 rounded-2xl mb-6"
         style="background: var(--color-bg-card); box-shadow: var(--shadow-card);">
        <p class="text-xs font-bold uppercase tracking-widest mb-4"
           style="color: var(--color-text-muted);">Curva S — Avance planificado vs real</p>
        <div style="height: 220px;">
            <canvas ref="canvas"></canvas>
        </div>
    </div>
</template>
