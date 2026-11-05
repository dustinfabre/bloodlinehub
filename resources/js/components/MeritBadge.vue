<script setup lang="ts">
import { meritChipStyle } from '@/lib/merits';
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
    <!-- Each merit has its own fixed color (MeritType::color()); the ring keeps pale ones visible on white -->
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-black/10 ring-inset dark:ring-white/15"
        :style="meritChipStyle(merit)"
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
