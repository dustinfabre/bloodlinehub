<script setup lang="ts">
import {
    contrastText,
    formatPedigreeDate,
    MAX_DEPTH,
    sexSymbol,
} from '@/lib/pedigree';
import { show as showRoute } from '@/routes/pigeons';
import type { PedigreeNode, PedigreeSide } from '@/types/pedigree';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    node: PedigreeNode | null;
    depth: number;
    // null for the subject bird
    side: PedigreeSide | null;
    linkable?: boolean;
}>();

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

// Name gets its own line only when the ring number is the primary label
const secondaryName = computed(() =>
    props.node?.ring_number ? props.node.name : null,
);

const details = computed(() => {
    if (!props.node) return '';
    const sex =
        props.depth !== 0
            ? null
            : gender.value === 'male'
              ? 'Cock'
              : gender.value === 'female'
                ? 'Hen'
                : null;
    return [sex, props.node.color, props.node.bloodline]
        .filter(Boolean)
        .join(' · ');
});

const isLastColumn = computed(() => props.depth === MAX_DEPTH);

// Where the highlight name pill goes: its own row on the subject, beside the ring number for parents and
// grandparents, and nowhere in the narrow outer columns (the colored bar and chart legend cover those)
const tagPlacement = computed(() => {
    if (!props.node?.highlight) return null;
    if (props.depth === 0) return 'row';
    return props.depth <= 2 ? 'inline' : null;
});

const tagStyle = computed(() =>
    props.node?.highlight
        ? {
              backgroundColor: props.node.highlight.color,
              color: contrastText(props.node.highlight.color),
          }
        : undefined,
);

const highlightStyle = computed(() => {
    const highlight = props.node?.highlight;
    if (!highlight) return undefined;
    return {
        '--pc-highlight': highlight.color,
        '--pc-highlight-tint': `${highlight.color}1A`,
    };
});

const href = computed(() =>
    props.linkable && props.node?.id
        ? showRoute({ pigeon: props.node.id }).url
        : null,
);
</script>

<template>
    <div v-if="!node" class="pedigree-card is-empty" :data-depth="depth">
        <span class="pc-sex" :class="sexClass">{{ sexSymbol(gender) }}</span>
        <span v-if="!isLastColumn" class="pc-unknown">Unknown</span>
    </div>

    <div
        v-else
        class="pedigree-card"
        :class="{ 'is-highlighted': node.highlight }"
        :data-depth="depth"
        :style="highlightStyle"
    >
        <div v-if="tagPlacement === 'row'" class="pc-tag-row">
            <span class="pc-tag" :style="tagStyle">{{
                node.highlight?.name
            }}</span>
        </div>
        <div class="pc-top">
            <span class="pc-sex" :class="sexClass">{{
                sexSymbol(gender)
            }}</span>
            <Link v-if="href" :href="href" class="pc-ring">{{
                primaryLabel
            }}</Link>
            <span v-else class="pc-ring">{{ primaryLabel }}</span>
            <span
                v-if="tagPlacement === 'inline'"
                class="pc-tag"
                :style="tagStyle"
            >
                {{ node.highlight?.name }}
            </span>
        </div>

        <div v-if="isLastColumn" class="pc-line">
            {{ [secondaryName, node.color].filter(Boolean).join(' · ') }}
        </div>
        <template v-else>
            <div v-if="secondaryName" class="pc-name">{{ secondaryName }}</div>
            <div v-if="details" class="pc-meta">{{ details }}</div>
            <div v-if="depth === 0 && node.hatch_date" class="pc-meta">
                Hatched {{ formatPedigreeDate(node.hatch_date) }}
            </div>
            <p v-if="node.remarks" class="pc-remarks">{{ node.remarks }}</p>
        </template>
    </div>
</template>
