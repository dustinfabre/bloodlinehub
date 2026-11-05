<script setup lang="ts">
import { meritChipStyle } from '@/lib/merits';
import { GENERATION_LABELS, relationName, sexSymbol } from '@/lib/pedigree';

interface SpecimenMerit {
    code: string;
    label: string;
    // Fixed merit color from app/Enums/MeritType.php
    color: string;
    year?: number;
}

interface SpecimenBird {
    // Position from the subject, as in the app's pedigrees: '' the bird, 'S' its sire, 'SD' the sire's dam
    key: string;
    ring: string;
    sex: 'male' | 'female';
    color: string;
    bloodline: string;
    merits: SpecimenMerit[];
}

// Sample birds. Ring numbers follow the app's own "PH 2024-12345" example; colours are genetically plausible.
const BIRDS: SpecimenBird[] = [
    {
        key: '',
        ring: 'PH 2024-01873',
        sex: 'male',
        color: 'Blue Check',
        bloodline: 'Janssen',
        merits: [
            {
                code: 'HS',
                label: 'Hot Spot Winner',
                color: '#F8907C',
                year: 2025,
            },
        ],
    },
    {
        key: 'S',
        ring: 'PH 2021-00412',
        sex: 'male',
        color: 'Dark Check',
        bloodline: 'Janssen',
        merits: [
            {
                code: 'OA',
                label: 'Overall Winner',
                color: '#EBC75E',
                year: 2022,
            },
        ],
    },
    {
        key: 'D',
        ring: 'PH 2020-02145',
        sex: 'female',
        color: 'Blue Bar',
        bloodline: 'Vandenabeele',
        merits: [
            {
                code: 'SS',
                label: 'Superset Winner',
                color: '#00666C',
                year: 2022,
            },
        ],
    },
    {
        key: 'SS',
        ring: 'PH 2017-01106',
        sex: 'male',
        color: 'Dark Check',
        bloodline: 'Janssen',
        merits: [{ code: 'SB', label: 'Super Breeder', color: '#5D339A' }],
    },
    {
        key: 'SD',
        ring: 'PH 2018-00387',
        sex: 'female',
        color: 'Blue Bar',
        bloodline: 'Van Loon',
        merits: [],
    },
    {
        key: 'DS',
        ring: 'PH 2016-04420',
        sex: 'male',
        color: 'Red Check',
        bloodline: 'Hofkens',
        merits: [
            { code: 'BoW', label: 'Breeder of Winners', color: '#815AC4' },
        ],
    },
    {
        key: 'DD',
        ring: 'PH 2017-02951',
        sex: 'female',
        color: 'Mealy',
        bloodline: 'Vandenabeele',
        merits: [],
    },
];

const GENERATIONS = GENERATION_LABELS.slice(0, 3);

// Motion timeline (ms): each generation's cards fade in after the lines leading to them have drawn
const CARD_DELAY = [0, 560, 1100];
const LINE_DELAY = [260, 800];
const STUB_MS = 110;
const STAGGER_MS = 60;

// Per generation, the subject first. Static strings so Tailwind can see them.
const CARD_CLASS = [
    'self-center border-t-gold/70 px-1.5 py-2 @sm:px-2.5 @sm:py-2.5 @lg:px-3.5 @lg:py-3.5',
    'self-center px-1.5 py-1.5 @sm:px-2 @md:px-2.5 @md:py-2',
    'my-[3px] px-1.5 py-1 @sm:px-2 @md:my-1 @md:px-2.5 @md:py-1.5',
];
const RING_CLASS = [
    'text-[10.5px] font-semibold @sm:text-xs @md:text-sm @lg:text-base',
    'text-[10.5px] font-medium @sm:text-[11px] @md:text-xs @lg:text-[13px]',
    'text-[10.5px] font-medium @sm:text-[11px] @md:text-xs',
];

// The text presentation selector stops ♂/♀ rendering as colour emoji
const glyph = (sex: SpecimenBird['sex']) => `${sexSymbol(sex)}\uFE0E`;

const chip = (merit: SpecimenMerit) =>
    merit.year ? `${merit.code} '${String(merit.year).slice(-2)}` : merit.code;

// e.g. "Overall Winner 2022", for hover titles and the accessible name
const meritTitle = (merit: SpecimenMerit) =>
    [merit.label, merit.year].filter(Boolean).join(' ');

// The chart is 4 equal rows under the generation labels; each bird spans the rows of its own ancestors,
// so every connector lands on the 25% / 75% marks of its cell
const gridArea = (key: string, column: number) => {
    const span = 4 >> key.length;
    const index = [...key].reduce(
        (row, step) => row * 2 + (step === 'D' ? 1 : 0),
        0,
    );
    return {
        gridColumn: String(column),
        gridRow: `${2 + index * span} / span ${span}`,
    };
};

const staggerOf = (key: string) =>
    [...key].reduce((row, step) => row * 2 + (step === 'D' ? 1 : 0), 0) *
    STAGGER_MS;

const cards = BIRDS.map((bird) => {
    const depth = bird.key.length;
    return {
        ...bird,
        depth,
        // Grandparents show only ring and merits on narrow widths
        compact: depth === 2,
        style: {
            ...gridArea(bird.key, depth * 2 + 1),
            '--ps-delay': `${CARD_DELAY[depth] + staggerOf(bird.key)}ms`,
        },
    };
});

const links = BIRDS.filter((bird) => bird.key.length < 2).map((bird) => {
    const delay = LINE_DELAY[bird.key.length] + staggerOf(bird.key);
    return {
        key: bird.key,
        style: gridArea(bird.key, bird.key.length * 2 + 2),
        stub: { '--ps-delay': `${delay}ms` },
        fork: { '--ps-delay': `${delay + STUB_MS}ms` },
    };
});

// The cards are presentational, so the accessible name carries the whole chart
const describe = (bird: SpecimenBird) =>
    [
        bird.ring,
        bird.color,
        bird.bloodline,
        ...bird.merits.map(meritTitle),
    ].join(', ');

// "Paternal grandsire" rather than two plain "Grandsire"s
const relation = (key: string) =>
    key.length > 1
        ? `${key.startsWith('S') ? 'Paternal' : 'Maternal'} ${relationName(key).toLowerCase()}`
        : relationName(key);

const [subject, ...ancestors] = BIRDS;
const label = [
    `Example pedigree. ${subject.sex === 'male' ? 'Cock' : 'Hen'} ${describe(subject)}.`,
    ...ancestors.map((bird) => `${relation(bird.key)} ${describe(bird)}.`),
].join(' ');
</script>

<template>
    <div role="img" :aria-label="label" class="@container w-full min-w-0">
        <div
            class="grid grid-cols-[minmax(0,1fr)_0.625rem_minmax(0,1fr)_0.625rem_minmax(0,1fr)] grid-rows-[auto_repeat(4,minmax(0,1fr))] text-ivory @sm:grid-cols-[minmax(0,1fr)_0.75rem_minmax(0,1fr)_0.75rem_minmax(0,1fr)] @md:grid-cols-[minmax(0,1fr)_1rem_minmax(0,1fr)_1rem_minmax(0,1fr)] @lg:grid-cols-[minmax(0,1.12fr)_1.375rem_minmax(0,1fr)_1.375rem_minmax(0,1fr)]"
        >
            <div
                v-for="(title, depth) in GENERATIONS"
                :key="title"
                class="ps-in truncate pb-2 text-[9px] font-semibold tracking-[0.08em] text-ivory/50 uppercase @sm:text-[10px] @md:pb-3 @md:tracking-[0.14em]"
                :style="{
                    gridColumn: String(depth * 2 + 1),
                    gridRow: '1',
                    '--ps-delay': `${CARD_DELAY[depth]}ms`,
                }"
            >
                {{ title }}
            </div>

            <div
                v-for="link in links"
                :key="`link-${link.key}`"
                class="relative"
                :style="link.style"
                aria-hidden="true"
            >
                <span
                    class="ps-stub absolute top-[calc(50%-0.5px)] left-0 w-1/2 border-t border-gold/50"
                    :style="link.stub"
                />
                <span
                    class="ps-fork absolute inset-y-1/4 right-0 left-1/2 border border-r-0 border-gold/50"
                    :style="link.fork"
                />
            </div>

            <div
                v-for="card in cards"
                :key="`bird-${card.key}`"
                class="ps-in flex min-w-0 flex-col justify-center overflow-hidden rounded-md border border-white/10 bg-white/5 leading-snug"
                :class="CARD_CLASS[card.depth]"
                :style="card.style"
            >
                <span
                    v-if="card.depth === 0"
                    class="mb-1 text-[9px] font-semibold tracking-[0.12em] text-ivory/55 uppercase @sm:text-[10px]"
                >
                    {{ glyph(card.sex) }}
                    {{ card.sex === 'male' ? 'Cock' : 'Hen' }}
                </span>

                <span
                    class="flex min-w-0 items-center gap-1 @md:gap-1.5"
                    :class="RING_CLASS[card.depth]"
                >
                    <span
                        v-if="card.depth > 0"
                        class="hidden shrink-0 font-normal text-ivory/50 @sm:inline"
                        >{{ glyph(card.sex) }}</span
                    >
                    <span class="truncate tabular-nums">{{ card.ring }}</span>
                </span>

                <span
                    class="mt-0.5 truncate text-[10px] text-ivory/60 @sm:text-[11px] @lg:text-xs"
                    :class="{ 'hidden @md:block': card.compact }"
                >
                    {{ card.color }}
                </span>

                <span
                    class="flex-wrap items-center gap-x-1.5 gap-y-1"
                    :class="
                        card.compact && !card.merits.length
                            ? 'hidden @md:flex'
                            : 'flex'
                    "
                >
                    <span
                        class="truncate text-[10px] text-ivory/60 @sm:text-[11px] @lg:text-xs"
                        :class="{ 'hidden @md:inline': card.compact }"
                        >{{ card.bloodline }}</span
                    >
                    <span
                        v-for="merit in card.merits"
                        :key="merit.code"
                        class="inline-flex shrink-0 rounded-full px-1.5 text-[10px] leading-[15px] font-semibold tabular-nums ring-1 ring-ivory/25"
                        :class="{ 'mt-1 @md:mt-0': card.compact }"
                        :style="meritChipStyle(merit)"
                        :title="meritTitle(merit)"
                        >{{ chip(merit) }}</span
                    >
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Runs once on mount: bird, lines to its parents, parents, lines to grandparents, grandparents */
.ps-in {
    animation: ps-fade 380ms var(--ease-crisp, cubic-bezier(0.22, 1, 0.36, 1))
        both;
    animation-delay: var(--ps-delay, 0ms);
}

.ps-stub {
    animation: ps-stub 110ms linear both;
    animation-delay: var(--ps-delay, 0ms);
}

.ps-fork {
    animation: ps-fork 260ms linear both;
    animation-delay: var(--ps-delay, 0ms);
}

@keyframes ps-fade {
    from {
        opacity: 0;
        transform: translateX(-4px);
    }
}

@keyframes ps-stub {
    from {
        clip-path: inset(0 100% 0 0);
    }
    to {
        clip-path: inset(0);
    }
}

/* The upright grows out from the stub, then the two arms reach the parents */
@keyframes ps-fork {
    from {
        clip-path: inset(50% calc(100% - 1px) 50% 0);
    }
    55% {
        clip-path: inset(0 calc(100% - 1px) 0 0);
    }
    to {
        clip-path: inset(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .ps-in,
    .ps-stub,
    .ps-fork {
        animation: none;
    }
}
</style>
