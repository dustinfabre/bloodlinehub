<script setup lang="ts">
import { byImportance, meritChipStyle, topMerit } from '@/lib/merits';
import { MAX_DEPTH, meritChip, sexSymbol } from '@/lib/pedigree';
import type { PedigreeNode, PedigreeSide } from '@/types/pedigree';
import { computed } from 'vue';

// One bird on the pedigree chart:
//   sex  ring  color
//   name  merits
//   bloodline
//   ─────────
//   remarks
// Each generation gets less room, so cards further back show fewer merits and fewer lines of remarks.
// Clicking a card opens everything recorded about the bird.
const props = defineProps<{
    node: PedigreeNode | null;
    depth: number;
    // null for the subject bird
    side: PedigreeSide | null;
}>();

const emit = defineEmits<{ open: [] }>();

// Position decides the sex of ancestors; the subject uses its own gender
const gender = computed(() => {
    if (props.side === 'sire') return 'male';
    if (props.side === 'dam') return 'female';
    return props.node?.gender ?? null;
});

const sexClass = computed(() =>
    gender.value === 'male'
        ? 'is-male'
        : gender.value === 'female'
          ? 'is-female'
          : '',
);

const primaryLabel = computed(
    () => props.node?.ring_number || props.node?.name || '—',
);

// The name gets the second line when the ring number leads
const name = computed(() => (props.node?.ring_number ? props.node.name : null));

const isLastColumn = computed(() => props.depth === MAX_DEPTH);

// The last column has room for two lines: the bloodline only shows there when there is no name
const bloodline = computed(() =>
    isLastColumn.value && name.value ? null : props.node?.bloodline,
);

// The bird's most significant merit colors the card's accent stripe
const accent = computed(() => {
    const best = topMerit(props.node?.merits);
    return best ? { '--pc-accent': best.color } : undefined;
});

// How many merit chips fit on a card in each generation
const MERIT_LIMITS = [8, 6, 4, 3, 2];

// Most significant first, so the narrow columns keep the merits that matter
const merits = computed(() => {
    const all = byImportance(props.node?.merits ?? []);
    const limit = MERIT_LIMITS[props.depth] ?? 2;
    return {
        shown: all.slice(0, limit).map((merit, index) => ({
            key: `${merit.type}-${merit.year ?? ''}-${merit.level ?? ''}-${index}`,
            text: meritChip(merit, props.depth),
            style: meritChipStyle(merit),
            title: [merit.label, merit.level_label, merit.year, merit.detail]
                .filter(Boolean)
                .join(' · '),
        })),
        more: Math.max(0, all.length - limit),
    };
});

const label = computed(() =>
    [
        gender.value === 'male'
            ? 'Cock'
            : gender.value === 'female'
              ? 'Hen'
              : null,
        props.node?.ring_number,
        props.node?.name,
    ]
        .filter(Boolean)
        .join(' '),
);
</script>

<template>
    <div v-if="!node" class="pedigree-card is-empty" :data-depth="depth">
        <span class="pc-sex" :class="sexClass">{{ sexSymbol(gender) }}</span>
        <span v-if="!isLastColumn" class="pc-unknown">Unknown</span>
    </div>

    <div
        v-else
        class="pedigree-card is-clickable"
        :class="{ 'has-merit': accent }"
        :data-depth="depth"
        :style="accent"
        role="button"
        tabindex="0"
        :aria-label="`${label}: show details`"
        @click="emit('open')"
        @keydown.enter.prevent="emit('open')"
        @keydown.space.prevent="emit('open')"
    >
        <div class="pc-top">
            <span class="pc-sex" :class="sexClass">{{
                sexSymbol(gender)
            }}</span>
            <span class="pc-ring">{{ primaryLabel }}</span>
            <span v-if="node.color" class="pc-color">{{ node.color }}</span>
        </div>

        <div v-if="name || merits.shown.length" class="pc-row">
            <span v-if="name" class="pc-name">{{ name }}</span>
            <span v-if="merits.shown.length" class="pc-merits">
                <b
                    v-for="merit in merits.shown"
                    :key="merit.key"
                    class="pc-merit"
                    :style="merit.style"
                    :title="merit.title"
                    >{{ merit.text }}</b
                >
                <span v-if="merits.more" class="pc-merit is-more"
                    >+{{ merits.more }}</span
                >
            </span>
        </div>

        <div v-if="bloodline" class="pc-meta">{{ bloodline }}</div>

        <p v-if="node.remarks && !isLastColumn" class="pc-remarks">
            {{ node.remarks }}
        </p>
    </div>
</template>
