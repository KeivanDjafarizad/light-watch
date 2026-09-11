<script setup lang="ts">
import { index as cabinetsIndex } from '@/actions/App/Http/Controllers/Api/CabinetController';
import FleetSnapshotController from '@/actions/App/Http/Controllers/Api/FleetSnapshotController';
import AlarmsDialog from '@/components/dashboard/AlarmsDialog.vue';
import CabinetList from '@/components/dashboard/CabinetList.vue';
import CabinetSheet from '@/components/dashboard/CabinetSheet.vue';
import KpiCards from '@/components/dashboard/KpiCards.vue';
import PlantMap from '@/components/dashboard/PlantMap.vue';
import Heading from '@/components/Heading.vue';
import { useEchoPublic } from '@laravel/echo-vue';
import { useHttp } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { dashboard } from '@/routes';
import type { CabinetRow, FleetSnapshot } from '@/types/dashboard';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

/**
 * Real-time operations dashboard (PRD Part 2): REST bootstrap on load,
 * then everything live arrives over websockets — `fleet` always,
 * `cabinet.{code}` only while its drill-down is open (owned by the
 * sheet), `commands` only while a command is outstanding (owned by the
 * command panel). No per-raw-event traffic ever reaches this page.
 */

const fleet = ref<FleetSnapshot | null>(null);
const cabinets = ref<CabinetRow[] | null>(null);

const selectedCode = ref<string | null>(null);
const sheetOpen = ref(false);
const alarmsOpen = ref(false);

const fleetHttp = useHttp<Record<string, never>, FleetSnapshot>({});
const cabinetsHttp = useHttp<Record<string, never>, CabinetRow[]>({});

async function loadFleet() {
    const snapshot = await fleetHttp.get(FleetSnapshotController.url());

    if (snapshot) {
        fleet.value = snapshot;
    }
}

let cabinetsTimer: ReturnType<typeof setInterval> | undefined;
let refreshDebounce: ReturnType<typeof setTimeout> | undefined;

async function loadCabinets() {
    const rows = await cabinetsHttp.get(cabinetsIndex.url());

    if (rows) {
        cabinets.value = rows;
    }
}

function scheduleCabinetsRefresh() {
    // Sparse online flips can arrive in small bursts; coalesce them.
    if (refreshDebounce !== undefined) {
        clearTimeout(refreshDebounce);
    }

    refreshDebounce = setTimeout(() => {
        void loadCabinets();
    }, 500);
}

function onFleetEvent(event: Record<string, unknown>) {
    if ('changes' in event && Array.isArray(event.changes)) {
        // online_changed: sparse flip stream — refresh the list rows.
        scheduleCabinetsRefresh();

        return;
    }

    if ('points_total' in event) {
        fleet.value = event as unknown as FleetSnapshot;
    }
}

useEchoPublic('fleet', ['.snapshot', '.online_changed'], onFleetEvent);

function openCabinet(code: string) {
    selectedCode.value = code;
    sheetOpen.value = true;
}

function onSheetOpenChange(open: boolean) {
    sheetOpen.value = open;

    if (!open) {
        // leaving the drill-down: pick up whatever changed while focused
        void loadCabinets();
        void loadFleet();
    }
}

onMounted(() => {
    void loadFleet();
    void loadCabinets();

    // Safety net for the list data (alarm counts are not event-driven):
    // cheap (~15 rows) and keeps the view honest.
    cabinetsTimer = setInterval(() => {
        void loadCabinets();
    }, 30_000);
});

onBeforeUnmount(() => {
    if (cabinetsTimer !== undefined) {
        clearInterval(cabinetsTimer);
    }

    if (refreshDebounce !== undefined) {
        clearTimeout(refreshDebounce);
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            title="Sanverano public lighting"
            description="Real-time fleet status — KPIs refresh every few seconds over websockets."
        />

        <KpiCards :snapshot="fleet" @open-alarms="alarmsOpen = true" />

        <div class="grid flex-1 gap-6 lg:grid-cols-5">
            <section class="space-y-3 lg:col-span-3">
                <h2 class="text-sm font-medium">Cabinets</h2>
                <CabinetList
                    :cabinets="cabinets"
                    :selected-code="selectedCode"
                    @select="openCabinet"
                />
            </section>

            <section class="space-y-3 lg:col-span-2">
                <h2 class="text-sm font-medium">Plant map</h2>
                <PlantMap
                    :cabinets="cabinets"
                    :selected-code="selectedCode"
                    @select="openCabinet"
                />
            </section>
        </div>

        <CabinetSheet
            v-if="selectedCode !== null"
            :key="selectedCode"
            :code="selectedCode"
            :open="sheetOpen"
            @update:open="onSheetOpenChange"
        />

        <AlarmsDialog
            v-model:open="alarmsOpen"
            :active-count="fleet?.active_alarms ?? 0"
        />
    </div>
</template>
