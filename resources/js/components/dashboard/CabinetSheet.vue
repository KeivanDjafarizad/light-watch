<script setup lang="ts">
import { show as showCabinet } from '@/actions/App/Http/Controllers/Api/CabinetController';
import CommandPanel from '@/components/dashboard/CommandPanel.vue';
import PowerChart from '@/components/dashboard/PowerChart.vue';
import UpdatedAgo from '@/components/dashboard/UpdatedAgo.vue';
import { Badge } from '@/components/ui/badge';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useEchoPublic } from '@laravel/echo-vue';
import { useHttp } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import {
    formatWatts,
    type CabinetDetail,
    type SeriesPoint,
} from '@/types/dashboard';

/**
 * Cabinet drill-down (PRD §9.2/§9.3/§9.4), mounted per cabinet (keyed):
 * fetches the last hour of readings, renders it, then subscribes to
 * `cabinet.{code}` and appends each snapshot as it arrives. Unmounting
 * (closing the sheet / switching cabinet) leaves the channel — that is
 * what keeps browser load bounded regardless of cabinet count.
 */
const props = defineProps<{
    code: string;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const detail = ref<CabinetDetail | null>(null);
const series = ref<SeriesPoint[]>([]);
const liveSummary = ref<{
    power_w: number;
    is_online: boolean;
    points_online: number;
    points_offline: number;
    generated_at: string;
} | null>(null);
const loadError = ref<string | null>(null);

const http = useHttp<Record<string, never>, CabinetDetail>({});

useEchoPublic(
    `cabinet.${props.code}`,
    ['.snapshot'],
    (event: typeof liveSummary.value) => {
        liveSummary.value = event;

        // Append what arrives — server-throttled at the broadcast cadence
        // (PRD §9.3), no client-side throttling on top. The 2h rolling
        // window lives inside PowerChart.
        if (event !== null) {
            series.value = [
                ...series.value,
                { t: event.generated_at, power_w: event.power_w },
            ];
        }
    },
);

onMounted(async () => {
    const loaded = await http.get(showCabinet.url({ code: props.code }), {
        onError: () => {
            loadError.value = 'Cabinet detail could not be loaded.';
        },
    });

    if (loaded) {
        detail.value = loaded;
        series.value = loaded.series;
    }
});

const summary = computed(() => detail.value?.cabinet ?? null);

const headerTitle = computed(() => {
    const cabinet = summary.value;

    return cabinet === null
        ? `Cabinet ${props.code}`
        : `${cabinet.cabinet_name ?? 'Cabinet'} ${cabinet.cabinet_code}`;
});
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-xl">
            <SheetHeader>
                <SheetTitle class="flex items-center gap-2">
                    {{ headerTitle }}
                    <Badge
                        v-if="summary"
                        :variant="
                            summary.vendor === 'cp3000'
                                ? 'secondary'
                                : 'outline'
                        "
                    >
                        {{
                            summary.vendor === 'cp3000'
                                ? 'Lot C · CP-3000'
                                : 'Lot A · Lumina P2P'
                        }}
                    </Badge>
                </SheetTitle>
                <SheetDescription
                    class="flex flex-wrap items-center gap-x-4 gap-y-1"
                >
                    <template v-if="liveSummary ?? summary">
                        <span class="tabular-nums">{{
                            formatWatts((liveSummary ?? summary)?.power_w ?? 0)
                        }}</span>
                        <span class="tabular-nums">
                            {{ (liveSummary ?? summary)?.points_online ?? 0 }}
                            on /
                            {{ (liveSummary ?? summary)?.points_offline ?? 0 }}
                            off
                        </span>
                        <UpdatedAgo
                            :since="(liveSummary ?? null)?.generated_at ?? null"
                        />
                    </template>
                </SheetDescription>
            </SheetHeader>

            <div v-if="loadError" class="text-destructive px-4 text-sm">
                {{ loadError }}
            </div>

            <div v-else-if="detail === null" class="space-y-4 px-4">
                <Skeleton class="h-64 w-full" />
                <Skeleton class="h-10 w-2/3" />
            </div>

            <div v-else class="space-y-6 px-4 pb-8">
                <section class="space-y-2">
                    <h3 class="text-sm font-medium">Power — last hour, live</h3>
                    <p
                        v-if="series.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        No readings in the last hour; the chart will start
                        filling as telemetry arrives.
                    </p>
                    <PowerChart v-else :points="series" />
                </section>

                <section class="space-y-3">
                    <h3 class="text-sm font-medium">Commands</h3>
                    <p
                        v-if="detail.devices.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        No devices report under this cabinet yet.
                    </p>
                    <CommandPanel
                        v-else
                        ref="commandPanel"
                        :devices="detail.devices"
                        :seed-commands="detail.outstanding_commands"
                    />
                </section>
            </div>
        </SheetContent>
    </Sheet>
</template>
