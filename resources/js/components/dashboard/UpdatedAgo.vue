<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';

/**
 * "Last updated Xs ago" indicator (PRD §9.1): if it stops advancing,
 * that itself is the signal that something is stuck.
 */
const props = defineProps<{
    since: string | null;
}>();

const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;

timer = setInterval(() => {
    now.value = Date.now();
}, 1000);

onBeforeUnmount(() => {
    if (timer !== undefined) {
        clearInterval(timer);
    }
});

const secondsAgo = computed(() => {
    if (props.since === null) {
        return null;
    }

    const parsed = Date.parse(props.since);

    if (Number.isNaN(parsed)) {
        return null;
    }

    return Math.max(0, Math.round((now.value - parsed) / 1000));
});

const isStale = computed(
    () => secondsAgo.value !== null && secondsAgo.value > 30,
);
</script>

<template>
    <span
        class="text-muted-foreground inline-flex items-center gap-1.5 text-xs"
        :class="{ 'text-amber-600 dark:text-amber-400': isStale }"
    >
        <span
            class="size-1.5 rounded-full"
            :class="isStale ? 'animate-pulse bg-amber-500' : 'bg-emerald-500'"
        />
        <template v-if="secondsAgo === null">no data yet</template>
        <template v-else-if="secondsAgo < 60"
            >updated {{ secondsAgo }}s ago</template
        >
        <template v-else
            >updated {{ Math.floor(secondsAgo / 60) }}m ago</template
        >
    </span>
</template>
