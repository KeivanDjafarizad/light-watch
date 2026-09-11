<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cabinetStatus, formatWatts, type CabinetRow } from '@/types/dashboard';
import { computed } from 'vue';

const props = defineProps<{
    cabinets: CabinetRow[] | null;
    selectedCode: string | null;
}>();

const emit = defineEmits<{
    select: [code: string];
}>();

const statusDot = {
    critical: 'bg-red-500',
    warning: 'bg-amber-500',
    ok: 'bg-emerald-500',
    offline: 'bg-muted-foreground',
} as const;

const severityBadge = {
    critical: 'destructive',
    warning: 'default',
    info: 'secondary',
} as const;

const sorted = computed(() => {
    // worst first: critical, then offline, then alphabetical
    const rank = (row: CabinetRow) => {
        const status = cabinetStatus(row);

        return status === 'critical' ? 0 : status === 'warning' ? 1 : 2;
    };

    return [...(props.cabinets ?? [])].sort(
        (a, b) =>
            rank(a) - rank(b) || a.cabinet_code.localeCompare(b.cabinet_code),
    );
});
</script>

<template>
    <div v-if="cabinets === null" class="space-y-2">
        <Skeleton v-for="i in 4" :key="i" class="h-16 w-full" />
    </div>

    <div
        v-else-if="cabinets.length === 0"
        class="text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm"
    >
        No cabinets yet — they appear as soon as devices report telemetry.
    </div>

    <div v-else class="space-y-2">
        <Card
            v-for="cabinet in sorted"
            :key="cabinet.cabinet_code"
            :class="[
                'hover:border-ring cursor-pointer transition-colors',
                selectedCode === cabinet.cabinet_code ? 'border-ring' : '',
            ]"
            role="button"
            tabindex="0"
            :data-testid="`cabinet-${cabinet.cabinet_code}`"
            @click="emit('select', cabinet.cabinet_code)"
            @keydown.enter="emit('select', cabinet.cabinet_code)"
        >
            <CardContent class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <div class="flex min-w-52 flex-1 items-center gap-3">
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :class="statusDot[cabinetStatus(cabinet)]"
                        aria-hidden="true"
                    />
                    <div class="min-w-0">
                        <p class="truncate font-medium">
                            {{
                                cabinet.cabinet_name ??
                                `Cabinet ${cabinet.cabinet_code}`
                            }}
                            <span class="text-muted-foreground font-normal">{{
                                cabinet.cabinet_code
                            }}</span>
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {{
                                cabinet.vendor === 'cp3000'
                                    ? 'Lot C · CP-3000'
                                    : 'Lot A · Lumina P2P'
                            }}
                        </p>
                    </div>
                </div>

                <div class="text-sm tabular-nums">
                    <span class="text-emerald-600 dark:text-emerald-400">{{
                        cabinet.points_online
                    }}</span>
                    <span class="text-muted-foreground"> on</span>
                    <span class="text-muted-foreground mx-1">·</span>
                    <span
                        :class="
                            cabinet.points_offline > 0
                                ? 'text-amber-600 dark:text-amber-400'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ cabinet.points_offline }} off
                    </span>
                </div>

                <div
                    class="min-w-24 text-right text-sm font-medium tabular-nums"
                >
                    {{ formatWatts(cabinet.power_w) }}
                </div>

                <Badge
                    v-if="cabinet.active_alarms > 0"
                    :variant="
                        severityBadge[cabinet.worst_alarm_severity ?? 'warning']
                    "
                >
                    {{ cabinet.active_alarms }} alarm{{
                        cabinet.active_alarms > 1 ? 's' : ''
                    }}
                </Badge>
            </CardContent>
        </Card>
    </div>
</template>
