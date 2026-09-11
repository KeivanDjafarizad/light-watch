<script setup lang="ts">
import { store } from '@/actions/App/Http/Controllers/Api/CommandController';
import CommandsChannel from '@/components/dashboard/CommandsChannel.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    commandOutcome,
    type CabinetDevice,
    type CommandDto,
    type CommandStatus,
} from '@/types/dashboard';

/**
 * Command controls with optimistic feedback (PRD §9.4): the target state
 * shows immediately with an "awaiting confirmation" treatment — before
 * the API response, and long before any ack/telemetry confirmation.
 * The `commands` channel drives the three distinct terminal visuals:
 * confirmed (green), failed (red), unconfirmed (amber — NOT an error:
 * fire-and-forget means we honestly don't know what happened).
 */
const props = defineProps<{
    devices: CabinetDevice[];
    seedCommands: CommandDto[];
}>();

type CommandForm = {
    device_id: number | null;
    type: string;
    payload?: { level?: number };
};

const http = useHttp<CommandForm, CommandDto>({
    device_id: null,
    type: '',
});

const selectedDeviceId = ref<number | null>(props.devices[0]?.id ?? null);
const dimLevel = ref(60);
const sendError = ref<string | null>(null);

/** id → command, including optimistic entries (id < 0) before the API answers. */
const tracked = ref(new Map<number, CommandDto>());
let optimisticSeq = -1;

for (const command of props.seedCommands) {
    tracked.value.set(command.id, command);
}

const selectedDevice = computed(
    () =>
        props.devices.find((device) => device.id === selectedDeviceId.value) ??
        null,
);

const hasOutstanding = computed(() =>
    [...tracked.value.values()].some(
        (command) => commandOutcome(command.status) === 'pending',
    ),
);

/** latest command per device, most recent first */
const latest = computed(() => {
    const byDevice = new Map<number, CommandDto>();

    for (const command of tracked.value.values()) {
        const current = byDevice.get(command.device_id);

        if (current === undefined || command.issued_at >= current.issued_at) {
            byDevice.set(command.device_id, command);
        }
    }

    return [...byDevice.values()]
        .sort((a, b) => b.issued_at.localeCompare(a.issued_at))
        .slice(0, 4);
});

function applyStatusChange(event: {
    command_id: number;
    status: CommandStatus;
}) {
    const command = tracked.value.get(event.command_id);

    if (command === undefined) {
        return;
    }

    tracked.value.set(event.command_id, { ...command, status: event.status });
}

async function send(type: 'on' | 'off' | 'dim') {
    if (selectedDeviceId.value === null || http.processing) {
        return;
    }

    sendError.value = null;

    const optimisticId = optimisticSeq--;
    const payload = type === 'dim' ? { level: dimLevel.value } : undefined;

    http.device_id = selectedDeviceId.value;
    http.type = type;
    http.payload = payload;

    tracked.value.set(optimisticId, {
        id: optimisticId,
        device_id: selectedDeviceId.value,
        type,
        payload: payload ?? null,
        status: 'pending',
        issued_at: new Date().toISOString(),
        sent_at: null,
        acked_at: null,
        ack_code: null,
        confirmed_at: null,
        reconcile_by: null,
    });

    const created = await http.post(store.url(), {
        onError: () => {
            sendError.value = 'The command could not be issued.';
        },
    });

    tracked.value.delete(optimisticId);

    if (created) {
        tracked.value.set(created.id, created);
    }
}

defineExpose({ applyStatusChange });
</script>

<template>
    <div class="space-y-4">
        <CommandsChannel
            v-if="hasOutstanding"
            @status-changed="applyStatusChange"
        />

        <div v-if="devices.length > 1" class="space-y-1.5">
            <Label>Point</Label>
            <Select v-model="selectedDeviceId as any">
                <SelectTrigger class="w-full">
                    <SelectValue placeholder="Select a point" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="device in devices"
                        :key="device.id"
                        :value="device.id"
                    >
                        {{ device.external_id
                        }}{{ device.label ? ` · ${device.label}` : '' }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <p v-else-if="selectedDevice" class="text-muted-foreground text-sm">
            Cabinet device
            <span class="text-foreground font-medium">{{
                selectedDevice.external_id
            }}</span>
            <template v-if="selectedDevice.switch_state">
                · currently
                {{
                    selectedDevice.switch_state === 'on'
                        ? 'on'
                        : selectedDevice.switch_state === 'off'
                          ? 'off'
                          : 'unknown'
                }}
            </template>
        </p>

        <div class="space-y-1.5">
            <div class="flex items-center justify-between">
                <Label for="dim-level">Dim level</Label>
                <span class="text-muted-foreground text-sm tabular-nums"
                    >{{ dimLevel }}%</span
                >
            </div>
            <input
                id="dim-level"
                v-model.number="dimLevel"
                type="range"
                min="0"
                max="100"
                step="5"
                class="accent-primary w-full"
            />
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                size="sm"
                :disabled="http.processing || selectedDeviceId === null"
                data-testid="cmd-on"
                @click="send('on')"
            >
                Switch on
            </Button>
            <Button
                size="sm"
                variant="outline"
                :disabled="http.processing || selectedDeviceId === null"
                data-testid="cmd-off"
                @click="send('off')"
            >
                Switch off
            </Button>
            <Button
                size="sm"
                variant="secondary"
                :disabled="http.processing || selectedDeviceId === null"
                data-testid="cmd-dim"
                @click="send('dim')"
            >
                Dim {{ dimLevel }}%
            </Button>
        </div>

        <p v-if="sendError" class="text-destructive text-sm">{{ sendError }}</p>

        <div v-if="latest.length > 0" class="space-y-2" aria-live="polite">
            <div
                v-for="command in latest"
                :key="command.id"
                class="rounded-lg border px-3 py-2 text-sm"
                :data-testid="`command-${command.id}`"
                :class="{
                    'animate-pulse border-dashed':
                        commandOutcome(command.status) === 'pending',
                    'border-emerald-500/50 bg-emerald-500/5':
                        commandOutcome(command.status) === 'confirmed',
                    'border-red-500/50 bg-red-500/5':
                        commandOutcome(command.status) === 'failed',
                    'border-amber-500/50 bg-amber-500/5':
                        commandOutcome(command.status) === 'unconfirmed',
                }"
            >
                <span class="font-medium">
                    {{
                        command.type === 'dim'
                            ? `Dim ${command.payload?.level ?? '?'}%`
                            : command.type === 'on'
                              ? 'Switch on'
                              : 'Switch off'
                    }}
                </span>
                on
                {{
                    props.devices.find(
                        (device) => device.id === command.device_id,
                    )?.external_id ?? `#${command.device_id}`
                }}
                —

                <span
                    v-if="commandOutcome(command.status) === 'pending'"
                    class="text-muted-foreground"
                >
                    awaiting confirmation…
                </span>
                <span
                    v-else-if="commandOutcome(command.status) === 'confirmed'"
                    class="text-emerald-600 dark:text-emerald-400"
                >
                    {{
                        command.status === 'acked'
                            ? 'acknowledged by vendor'
                            : 'confirmed by telemetry'
                    }}
                </span>
                <span
                    v-else-if="commandOutcome(command.status) === 'failed'"
                    class="text-red-600 dark:text-red-400"
                >
                    failed (vendor error or ack timeout)
                </span>
                <span v-else class="text-amber-700 dark:text-amber-400">
                    no confirmation available — fire &amp; forget, outcome
                    unknown
                </span>
            </div>
        </div>
    </div>
</template>
