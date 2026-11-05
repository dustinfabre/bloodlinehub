<script setup lang="ts">
import type { Merit } from '@/types/lineage';
import { Award, Trophy } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(defineProps<{ merit: Merit; full?: boolean }>(), {
    full: false,
});

// Full description for hover / screen readers, e.g. "Ace Pigeon · National · 2024 · 1st Nat Ace Sprint"
const description = computed(() =>
    [
        props.merit.label,
        props.merit.level_label,
        props.merit.year,
        props.merit.detail,
    ]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="
            merit.category === 'breeding'
                ? 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300'
                : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300'
        "
        :title="description"
        :aria-label="description"
    >
        <Award
            v-if="merit.category === 'breeding'"
            class="size-3"
            aria-hidden="true"
        />
        <Trophy v-else class="size-3" aria-hidden="true" />
        {{ full ? merit.label : merit.short }}
    </span>
</template>
