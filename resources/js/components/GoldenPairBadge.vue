<script setup lang="ts">
import { Crown } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    // Youngsters of the pair holding a winning merit
    winners: number;
    offspring?: number;
}>();

const description = computed(
    () =>
        `Golden Pair: ${props.winners} ${props.winners === 1 ? 'youngster' : 'youngsters'} with a winning merit` +
        (props.offspring ? ` out of ${props.offspring}` : ''),
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full border border-gold/60 bg-gold/15 px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-foreground"
        :title="description"
        :aria-label="description"
    >
        <Crown class="size-3 text-gold" aria-hidden="true" />
        Golden Pair
    </span>
</template>
