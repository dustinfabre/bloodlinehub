<script setup lang="ts">
import AncestorDialog from '@/components/pedigree/AncestorDialog.vue';
import CopyPedigreeDialog from '@/components/pedigree/CopyPedigreeDialog.vue';
import PedigreeEditorSlot from '@/components/pedigree/PedigreeEditorSlot.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    flattenPedigree,
    GENERATION_LABELS,
    MAX_DEPTH,
    normalizeRing,
    slotGridArea,
} from '@/lib/pedigree';
import { edit as editPedigreeDetails } from '@/routes/pedigree-details';
import {
    editPedigree,
    edit as editRoute,
    index as indexRoute,
    pedigree as pedigreeRoute,
    show as showRoute,
} from '@/routes/pigeons';
import { type BreadcrumbItem } from '@/types';
import type { MeritLevelOption, MeritTypeOption } from '@/types/lineage';
import type {
    AncestorTarget,
    PedigreeLinks,
    PedigreeNode,
    PedigreeSlot,
} from '@/types/pedigree';
import { Head, Link } from '@inertiajs/vue3';
import {
    Contact,
    Copy,
    GitBranch,
    Home,
    MousePointerClick,
    Network,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Pigeon {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
}

const props = defineProps<{
    pigeon: Pigeon;
    tree: PedigreeNode;
    colors: string[];
    bloodlines: string[];
    meritTypes: MeritTypeOption[];
    meritLevels: MeritLevelOption[];
    links: PedigreeLinks;
}>();

const pigeonLabel = computed(
    () =>
        props.pigeon.name ||
        props.pigeon.ring_number ||
        props.pigeon.personal_number ||
        `Pigeon #${props.pigeon.id}`,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: indexRoute().url },
    {
        title: pigeonLabel.value,
        href: showRoute({ pigeon: props.pigeon.id }).url,
    },
    {
        title: 'Edit pedigree',
        href: editPedigree({ pigeon: props.pigeon.id }).url,
    },
];

const slots = computed(() => flattenPedigree(props.tree));

// Ancestor positions on the chart (2 + 4 + 8 + 16) and how many are known
const totalAncestors = 2 ** (MAX_DEPTH + 1) - 2;
const knownAncestors = computed(
    () => slots.value.filter((entry) => entry.depth > 0 && entry.node).length,
);

const loftBirds = computed(
    () =>
        new Map(
            slots.value
                .filter((entry) => entry.node?.id)
                .map((entry) => [entry.node!.id!, entry.node!]),
        ),
);

// Loft birds whose pedigrees hold another record of this outside bird (same ring number)
const recordedElsewhere = (node: PedigreeNode | null): string[] => {
    if (!node?.external || !node.ring_number) return [];
    const records = props.links[normalizeRing(node.ring_number)] ?? [];
    return [
        ...new Set(
            records
                .filter(
                    (record) =>
                        record.anchor_id !== node.anchor_id ||
                        record.path !== node.path,
                )
                .map((record) => record.label),
        ),
    ];
};

// Where an external ancestor in this position is stored: on the nearest loft bird, by path from it.
// Loft birds and the subject aren't editable here, and nothing can be added behind an unknown bird.
const targetFor = (entry: PedigreeSlot): AncestorTarget | null => {
    const step = entry.key.slice(-1);
    let anchorId: number | null = null;
    let path: string | null = null;

    if (entry.node?.external) {
        anchorId = entry.node.anchor_id;
        path = entry.node.path;
    } else if (!entry.node && entry.child?.external) {
        anchorId = entry.child.anchor_id;
        path = entry.child.path ? entry.child.path + step : null;
    } else if (!entry.node && entry.child?.id) {
        anchorId = entry.child.id;
        path = step;
    }

    if (!anchorId || !path) return null;

    return {
        entry,
        anchor_id: anchorId,
        path,
        anchor:
            anchorId === props.pigeon.id
                ? null
                : (loftBirds.value.get(anchorId) ?? null),
        behind: slots.value.filter(
            (other) =>
                other.node &&
                other.key.length > entry.key.length &&
                other.key.startsWith(entry.key),
        ).length,
        alsoIn: recordedElsewhere(entry.node),
        offspringAlsoIn: recordedElsewhere(entry.child),
        offspringLabel: entry.child?.ring_number || entry.child?.name || null,
    };
};

const editorSlots = computed(() =>
    slots.value.map((entry) => ({ entry, target: targetFor(entry) })),
);

const copyOpen = ref(false);
// The place to fill when "Copy from another pigeon" is opened from a box on the chart
const copyFocus = ref<string | null>(null);

const openCopy = (focus: string | null) => {
    copyFocus.value = focus;
    dialogOpen.value = false;
    copyOpen.value = true;
};

const dialogOpen = ref(false);
const activeTarget = ref<AncestorTarget | null>(null);

const openEditor = (target: AncestorTarget | null) => {
    if (!target) return;
    activeTarget.value = target;
    dialogOpen.value = true;
};
</script>

<template>
    <Head :title="`Edit pedigree - ${pigeonLabel}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold sm:text-3xl">
                        Edit pedigree
                    </h1>
                    <p class="text-muted-foreground">
                        {{ pigeonLabel }} · {{ knownAncestors }} of
                        {{ totalAncestors }} ancestors recorded
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="openCopy(null)">
                        <Copy class="size-4" />
                        Copy from another pigeon
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="editRoute({ pigeon: pigeon.id }).url"
                            >Edit pigeon</Link
                        >
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="editPedigreeDetails().url">
                            <Contact class="size-4" />
                            Contact details
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="pedigreeRoute({ pigeon: pigeon.id }).url">
                            <Network class="size-4" />
                            View pedigree
                        </Link>
                    </Button>
                </div>
            </div>

            <div
                class="h-1.5 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                :aria-valuenow="knownAncestors"
                aria-valuemin="0"
                :aria-valuemax="totalAncestors"
            >
                <div
                    class="h-full rounded-full bg-gold transition-all"
                    :style="{
                        width: `${(knownAncestors / totalAncestors) * 100}%`,
                    }"
                />
            </div>

            <div class="grid gap-3 text-sm sm:grid-cols-3">
                <div class="flex gap-3 rounded-xl border bg-card p-4">
                    <MousePointerClick
                        class="mt-0.5 size-4 shrink-0 text-gold"
                    />
                    <p>
                        Click an empty box to add an ancestor, or a filled one
                        to change it. Fill in the parents before the
                        grandparents.
                    </p>
                </div>
                <div class="flex gap-3 rounded-xl border bg-card p-4">
                    <Home class="mt-0.5 size-4 shrink-0 text-gold" />
                    <p>
                        Birds from your loft are linked, so their own pedigree
                        comes along. Link parents on the
                        <Link
                            :href="editRoute({ pigeon: pigeon.id }).url"
                            class="font-medium underline underline-offset-2"
                            >pigeon's edit page</Link
                        >.
                    </p>
                </div>
                <div class="flex gap-3 rounded-xl border bg-card p-4">
                    <GitBranch class="mt-0.5 size-4 shrink-0 text-gold" />
                    <p>
                        Ancestors behind a loft bird are saved on that bird, so
                        all of its offspring inherit them. For a related bird,
                        copy a parent, grandparent or great-grandparent's line
                        from another pigeon.
                    </p>
                </div>
            </div>

            <div class="rounded-xl border bg-card">
                <p
                    class="border-b px-4 py-2 text-xs text-muted-foreground lg:hidden"
                >
                    Scroll sideways to see every generation.
                </p>
                <div class="overflow-x-auto p-4">
                    <div class="min-w-[960px]">
                        <div
                            class="mb-3 grid grid-cols-[210px_repeat(4,minmax(0,1fr))] gap-x-6 text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                        >
                            <span
                                v-for="(label, index) in GENERATION_LABELS"
                                :key="label"
                            >
                                <span class="mr-1 text-gold">{{
                                    String(index + 1).padStart(2, '0')
                                }}</span>
                                {{ label }}
                            </span>
                        </div>
                        <div
                            class="grid grid-cols-[210px_repeat(4,minmax(0,1fr))] grid-rows-[repeat(16,minmax(2.75rem,auto))] gap-x-6"
                        >
                            <div
                                v-for="{ entry, target } in editorSlots"
                                :key="entry.key || 'subject'"
                                class="relative flex min-w-0 items-center py-0.5"
                                :style="slotGridArea(entry)"
                            >
                                <PedigreeEditorSlot
                                    :entry="entry"
                                    :editable="!!target"
                                    @edit="openEditor(target)"
                                />
                                <template
                                    v-if="entry.node && entry.depth < MAX_DEPTH"
                                >
                                    <span
                                        class="absolute top-1/2 left-full w-3 border-t border-border"
                                    />
                                    <span
                                        class="absolute top-1/4 bottom-1/4 left-[calc(100%+0.75rem)] w-3 rounded-l-lg border border-r-0 border-border"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <CopyPedigreeDialog
            v-model:open="copyOpen"
            :pigeon-id="pigeon.id"
            :tree="tree"
            :focus="copyFocus"
        />

        <AncestorDialog
            v-model:open="dialogOpen"
            :pigeon-id="pigeon.id"
            :target="activeTarget"
            :colors="colors"
            :bloodlines="bloodlines"
            :merit-types="meritTypes"
            :merit-levels="meritLevels"
            @copy="openCopy"
        />
    </AppLayout>
</template>
