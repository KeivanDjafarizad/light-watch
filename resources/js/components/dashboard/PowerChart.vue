<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Chart, registerables } from 'chart.js';
import type { SeriesPoint } from '@/types/dashboard';

/**
 * Real-time power line (PRD §9.3): bootstrapped with the last hour from
 * the REST endpoint, then appended with each `cabinet.{code}` snapshot
 * as it arrives — already throttled server-side, rendered as-is with no
 * client-side throttling on top. Client-side we only keep a rolling 2h
 * window so a long session doesn't grow memory unbounded.
 *
 * The public-lighting plant is legitimately off during the day (0 W
 * everywhere): rather than a hairline glued to the x-axis reading as a
 * broken chart, that state is made explicit with an overlay, and a
 * single data point renders as a visible dot.
 */
const props = defineProps<{
    points: SeriesPoint[];
}>();

Chart.register(...registerables);

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart<'line'> | null = null;

const WINDOW_MS = 2 * 60 * 60 * 1000;

const windowed = computed(() => {
    const cutoff = Date.now() - WINDOW_MS;
    const kept = props.points.filter((point) => Date.parse(point.t) >= cutoff);

    // Never leave the chart fully empty: keep the most recent point.
    return kept.length > 0 ? kept : props.points.slice(-1);
});

const data = computed(() =>
    windowed.value.map((point) => ({
        x: Date.parse(point.t),
        y: point.power_w,
    })),
);

const isPlantOff = computed(
    () =>
        windowed.value.length > 0 &&
        windowed.value.every((point) => point.power_w === 0),
);

function render() {
    if (chart === null) {
        return;
    }

    chart.data.datasets[0].data = data.value;
    chart.update('none');
}

onMounted(() => {
    if (canvas.value === null) {
        return;
    }

    const isDark = document.documentElement.classList.contains('dark');
    const grid = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const tick = isDark ? '#a1a1aa' : '#71717a';

    chart = new Chart(canvas.value, {
        type: 'line',
        data: {
            datasets: [
                {
                    label: 'Power (W)',
                    data: data.value,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.12)',
                    fill: true,
                    // A single point with radius 0 would be invisible:
                    // show a dot until a second point extends the line.
                    pointRadius: (context) =>
                        (context.dataset.data ?? []).length === 1 ? 3 : 0,
                    pointHitRadius: 8,
                    borderWidth: 2,
                    tension: 0.25,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'nearest', axis: 'x', intersect: false },
            parsing: false,
            normalized: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) =>
                            new Date(
                                items[0].parsed.x ?? 0,
                            ).toLocaleTimeString(),
                        label: (item) => `${Math.round(item.parsed.y ?? 0)} W`,
                    },
                },
            },
            scales: {
                x: {
                    type: 'linear',
                    ticks: {
                        color: tick,
                        maxTicksLimit: 8,
                        callback: (value) =>
                            new Date(Number(value)).toLocaleTimeString([], {
                                hour: '2-digit',
                                minute: '2-digit',
                            }),
                    },
                    grid: { color: grid },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: tick,
                        maxTicksLimit: 6,
                        callback: (value) =>
                            `${Math.round(Number(value) / 100) / 10}kW`,
                    },
                    grid: { color: grid },
                },
            },
        },
    });

    render();
});

watch(data, render);

onBeforeUnmount(() => {
    chart?.destroy();
    chart = null;
});
</script>

<template>
    <div class="relative h-64 w-full">
        <canvas ref="canvas" />

        <div
            v-if="isPlantOff"
            class="text-muted-foreground pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-1 text-sm"
            data-testid="plant-off-overlay"
        >
            <span class="font-medium">Plant is off — 0 W</span>
            <span class="text-xs"
                >lighting resumes at dusk; the line will rise here</span
            >
        </div>
    </div>
</template>
