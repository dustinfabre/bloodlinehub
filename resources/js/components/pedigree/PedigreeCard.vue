<script setup lang="ts">
import { byImportance, meritChipStyle, topMerit } from '@/lib/merits';
import {
    formatPedigreeDate,
    MAX_DEPTH,
    meritChip,
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
        :class="{ 'has-merit': accent }"
        :data-depth="depth"
        :style="accent"
    >
        <div class="pc-top">
            <span class="pc-sex" :class="sexClass">{{
                sexSymbol(gender)
            }}</span>
            <Link v-if="href" :href="href" class="pc-ring">{{
                primaryLabel
            }}</Link>
            <span v-else class="pc-ring">{{ primaryLabel }}</span>
            <span v-if="isLastColumn && merits.shown.length" class="pc-codes">
                <b
                    v-for="merit in merits.shown"
                    :key="merit.key"
                    class="pc-code"
                    :style="merit.style"
                    :title="merit.title"
                    >{{ merit.text }}</b
                >
            </span>
        </div>

        <div v-if="isLastColumn" class="pc-line">
            {{ [secondaryName, node.color].filter(Boolean).join(' · ') }}
        </div>
        <template v-else>
            <div v-if="secondaryName" class="pc-name">{{ secondaryName }}</div>
            <div v-if="merits.shown.length" class="pc-merits">
                <span
                    v-for="merit in merits.shown"
                    :key="merit.key"
                    class="pc-merit"
                    :style="merit.style"
                    :title="merit.title"
                    >{{ merit.text }}</span
                >
                <span v-if="merits.more" class="pc-merit is-more"
                    >+{{ merits.more }}</span
                >
            </div>
            <div v-if="details" class="pc-meta">{{ details }}</div>
            <div v-if="depth === 0 && node.hatch_date" class="pc-meta">
                Hatched {{ formatPedigreeDate(node.hatch_date) }}
            </div>
            <p v-if="node.remarks" class="pc-remarks">{{ node.remarks }}</p>
        </template>
    </div>
</template>
