<script setup lang="ts">
import { index as alarmsIndex } from '@/actions/App/Http/Controllers/Api/AlarmController';
import UpdatedAgo from '@/components/dashboard/UpdatedAgo.vue';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { useHttp } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import type { AlarmDto } from '@/types/dashboard';

/**
 * Open alarms list (PRD §9.1, nice-to-have drill-down): device + code +
 * opened_at. Refreshed every time it opens and when the fleet's active
 * count changes.
 */
const props = defineProps<{
    open: boolean;
    activeCount: number;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const alarms = ref<AlarmDto[] | null>(null);
const error = ref<string | null>(null);
const http = useHttp<Record<string, never>, AlarmDto[]>({});

async function load() {
    error.value = null;

    const loaded = await http.get(
        alarmsIndex.url({ query: { status: 'open' } }),
        {
            onError: () => {
                error.value = 'Alarms could not be loaded.';
            },
        },
    );

    if (loaded) {
        alarms.value = loaded;
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            alarms.value = null;
            void load();
        }
    },
);

watch(
    () => props.activeCount,
    () => {
        if (props.open) {
            void load();
        }
    },
);

const severityVariant = {
    critical: 'destructive',
    warning: 'default',
    info: 'secondary',
} as const;
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Open alarms</DialogTitle>
                <DialogDescription
                    >Worst first — newest occurrence per
                    alarm.</DialogDescription
                >
            </DialogHeader>

            <p v-if="error" class="text-destructive text-sm">{{ error }}</p>

            <div v-else-if="alarms === null" class="space-y-2">
                <Skeleton v-for="i in 3" :key="i" class="h-14 w-full" />
            </div>

            <p
                v-else-if="alarms.length === 0"
                class="text-muted-foreground py-6 text-center text-sm"
            >
                No open alarms. All clear.
            </p>

            <ul v-else class="space-y-2">
                <li
                    v-for="alarm in [...alarms].sort(
                        (a, b) =>
                            a.severity.localeCompare(b.severity) ||
                            b.opened_at.localeCompare(a.opened_at),
                    )"
                    :key="alarm.id"
                    class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{
                                alarm.device?.external_id ??
                                `#${alarm.device_id}`
                            }}
                            <span class="text-muted-foreground font-normal">
                                {{
                                    alarm.device?.cabinet_code
                                        ? `· cabinet ${alarm.device.cabinet_code}`
                                        : ''
                                }}
                            </span>
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {{ alarm.code.replaceAll('_', ' ') }} · opened
                            {{ new Date(alarm.opened_at).toLocaleString() }}
                        </p>
                    </div>
                    <Badge :variant="severityVariant[alarm.severity]">{{
                        alarm.severity
                    }}</Badge>
                </li>
            </ul>
        </DialogContent>
    </Dialog>
</template>
