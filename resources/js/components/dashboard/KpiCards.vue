<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import UpdatedAgo from '@/components/dashboard/UpdatedAgo.vue';
import { formatWatts, type FleetSnapshot } from '@/types/dashboard';
import { Activity, BellRing, Plug, Zap } from '@lucide/vue';

defineProps<{
    snapshot: FleetSnapshot | null;
}>();

const emit = defineEmits<{
    openAlarms: [];
}>();
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Card>
            <CardContent class="flex items-center justify-between gap-2">
                <div class="space-y-1">
                    <p
                        class="text-muted-foreground flex items-center gap-1.5 text-xs font-medium tracking-wide uppercase"
                    >
                        <Plug class="size-3.5" /> Points online
                    </p>
                    <p class="text-2xl font-semibold tabular-nums">
                        <span class="text-emerald-600 dark:text-emerald-400">{{
                            snapshot?.points_online ?? '–'
                        }}</span>
                        <span
                            class="text-muted-foreground text-base font-normal"
                        >
                            / {{ snapshot?.points_total ?? '–' }}
                        </span>
                    </p>
                    <p v-if="snapshot" class="text-muted-foreground text-xs">
                        {{ snapshot.points_offline }} offline
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent>
                <p
                    class="text-muted-foreground flex items-center gap-1.5 text-xs font-medium tracking-wide uppercase"
                >
                    <Activity class="size-3.5" /> Points offline
                </p>
                <p
                    class="mt-1 text-2xl font-semibold tabular-nums"
                    :class="
                        (snapshot?.points_offline ?? 0) > 0
                            ? 'text-amber-600 dark:text-amber-400'
                            : ''
                    "
                >
                    {{ snapshot?.points_offline ?? '–' }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs">
                    silence &gt; 3× reporting interval
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardContent>
                <p
                    class="text-muted-foreground flex items-center gap-1.5 text-xs font-medium tracking-wide uppercase"
                >
                    <Zap class="size-3.5" /> Instantaneous power
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ snapshot ? formatWatts(snapshot.power_w_total) : '–' }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs">
                    online devices only
                </p>
            </CardContent>
        </Card>

        <Card
            class="hover:border-ring cursor-pointer transition-colors"
            role="button"
            tabindex="0"
            data-testid="kpi-alarms"
            @click="emit('openAlarms')"
            @keydown.enter="emit('openAlarms')"
        >
            <CardContent>
                <p
                    class="text-muted-foreground flex items-center gap-1.5 text-xs font-medium tracking-wide uppercase"
                >
                    <BellRing class="size-3.5" /> Active alarms
                </p>
                <p
                    class="mt-1 text-2xl font-semibold tabular-nums"
                    :class="
                        (snapshot?.active_alarms ?? 0) > 0
                            ? 'text-red-600 dark:text-red-400'
                            : ''
                    "
                >
                    {{ snapshot?.active_alarms ?? '–' }}
                </p>
                <p class="text-muted-foreground mt-1 text-xs">
                    click for the open list
                </p>
            </CardContent>
        </Card>
    </div>

    <div class="flex items-center justify-between">
        <UpdatedAgo :since="snapshot?.generated_at ?? null" />
    </div>
</template>
