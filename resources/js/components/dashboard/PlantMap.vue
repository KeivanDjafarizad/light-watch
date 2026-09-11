<script setup lang="ts">
import { cabinetStatus, type CabinetRow } from '@/types/dashboard';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Plant map (PRD §9.5): one marker per cabinet (the PRD-sanctioned
 * default over 640 individual Lot A points), color-coded worst-case
 * like the cabinet list, clicking opens the same drill-down. Without
 * plant:import there are legitimately no coordinates — empty state,
 * not an error.
 *
 * Note: tiles come from OpenStreetMap and need internet access; the
 * markers themselves work offline.
 */
const props = defineProps<{
    cabinets: CabinetRow[] | null;
    selectedCode: string | null;
}>();

const emit = defineEmits<{
    select: [code: string];
}>();

const container = ref<HTMLDivElement | null>(null);
let map: L.Map | null = null;
let markers: L.LayerGroup | null = null;

const colors = {
    critical: '#ef4444',
    warning: '#f59e0b',
    ok: '#10b981',
    offline: '#71717a',
} as const;

const withCoords = () =>
    (props.cabinets ?? []).filter(
        (cabinet) => cabinet.lat !== null && cabinet.lng !== null,
    );

function redraw() {
    if (map === null || markers === null) {
        return;
    }

    markers.clearLayers();

    const located = withCoords();

    for (const cabinet of located) {
        const marker = L.circleMarker(
            [cabinet.lat as number, cabinet.lng as number],
            {
                radius: props.selectedCode === cabinet.cabinet_code ? 14 : 10,
                color: '#ffffff',
                weight: 2,
                fillColor: colors[cabinetStatus(cabinet)],
                fillOpacity: 0.95,
            },
        );

        marker.bindTooltip(
            `<strong>${cabinet.cabinet_name ?? 'Cabinet'} ${cabinet.cabinet_code}</strong><br>` +
                `${cabinet.points_online}/${cabinet.points_online + cabinet.points_offline} online` +
                (cabinet.active_alarms > 0
                    ? ` · ${cabinet.active_alarms} alarm(s)`
                    : ''),
        );

        marker.on('click', () => emit('select', cabinet.cabinet_code));

        marker.addTo(markers as L.LayerGroup);
    }

    if (located.length > 0) {
        map.fitBounds(
            L.latLngBounds(
                located.map((cabinet) => [
                    cabinet.lat as number,
                    cabinet.lng as number,
                ]),
            ).pad(0.25),
            {
                animate: false,
            },
        );
    }
}

onMounted(() => {
    if (container.value === null) {
        return;
    }

    map = L.map(container.value, {
        attributionControl: true,
        scrollWheelZoom: true,
    }).setView([45.42, 9.13], 13);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    markers = L.layerGroup().addTo(map);

    redraw();
});

watch(() => props.cabinets, redraw, { deep: true });

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    markers = null;
});
</script>

<template>
    <div
        v-if="cabinets !== null && withCoords().length === 0"
        class="text-muted-foreground flex h-full min-h-72 flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-8 text-center text-sm"
    >
        <p>No device coordinates yet.</p>
        <p class="text-xs">
            Run
            <code class="bg-muted rounded px-1 py-0.5"
                >php artisan plant:import assets/plant.csv</code
            >
            to place the cabinets on the map.
        </p>
    </div>

    <div
        v-else
        ref="container"
        class="z-0 h-full min-h-72 w-full overflow-hidden rounded-xl border"
        data-testid="plant-map"
    />
</template>
