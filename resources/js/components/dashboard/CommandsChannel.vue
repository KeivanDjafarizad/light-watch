<script setup lang="ts">
import { useEchoPublic } from '@laravel/echo-vue';

/**
 * Mounted only while a command is outstanding (PRD §11): subscribes to
 * the `commands` channel on mount, leaves it on unmount. The channel is
 * reference-counted globally, so concurrent panels share one socket
 * subscription.
 */
import type { CommandStatus } from '@/types/dashboard';

const emit = defineEmits<{
    statusChanged: [event: { command_id: number; status: CommandStatus }];
}>();

useEchoPublic(
    'commands',
    ['.status_changed'],
    (event: { command_id: number; status: string }) => {
        emit('statusChanged', {
            command_id: event.command_id,
            status: event.status as CommandStatus,
        });
    },
);
</script>

<template>
    <span hidden aria-hidden="true" />
</template>
