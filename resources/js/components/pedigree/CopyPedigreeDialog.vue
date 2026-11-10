<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PigeonSearchResult from '@/components/PigeonSearchResult.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import {
    flattenPedigree,
    lineageSteps,
    normalizeRing,
    relationName,
    sexSymbol,
} from '@/lib/pedigree';
import {
    copyPedigree,
    pedigreeCopySource,
    pedigreeCopySources,
} from '@/routes/pigeons';
import type {
    PedigreeCopyChoice,
    PedigreeCopySource,
    PedigreeNode,
    PedigreeRelation,
    PedigreeSlot,
} from '@/types/pedigree';
import { useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import {
    AlertTriangle,
    Check,
    Home,
    Info,
    LoaderCircle,
    Search,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    pigeonId: number;
    // This pigeon's pedigree, as on the editor
    tree: PedigreeNode;
    // A place to fill (e.g. "SSS"), when opened from that place on the chart
    focus?: string | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const { success, error } = useToast();

// Birds are copied from and into places up to great-grandparents: S, D, SS, SD, ..., DDD
const MAX_COPY_PATH = 3;
const PLACES = [1, 2, 3].flatMap((length) =>
    Array.from({ length: 2 ** length }, (_, index) =>
        index
            .toString(2)
            .padStart(length, '0')
            .replace(/0/g, 'S')
            .replace(/1/g, 'D'),
    ),
);
const GENERATIONS = ['Parents', 'Grandparents', 'Great-grandparents'];

const query = ref('');
const results = ref<PedigreeCopySource[]>([]);
const loading = ref(false);
const source = ref<PedigreeCopySource | null>(null);
const sourceTree = ref<PedigreeNode | null>(null);
const loadingTree = ref(false);
const choices = ref<PedigreeCopyChoice[]>([]);
let requestId = 0;

const form = useForm({
    source_id: null as number | null,
    copies: [] as { from: string; to: string }[],
});

const plural = (count: number, word: string) =>
    `${count} ${word}${count === 1 ? '' : 's'}`;

const birdLabel = (bird: { ring_number: string | null; name: string | null }) =>
    bird.ring_number || bird.name || 'Unnamed bird';

const sourceLabel = (bird: PedigreeCopySource) =>
    bird.ring_number ||
    bird.name ||
    bird.personal_number ||
    `Pigeon #${bird.id}`;

const lower = (key: string) => relationName(key).toLowerCase();

// e.g. "Great-grandsire (Sire › Dam › Sire)"
const place = (key: string) =>
    `${relationName(key)} (${lineageSteps(key).join(' › ')})`;

// "Same sire", or "Its granddam is this pigeon's great-granddam"
const relationText = (relation: PedigreeRelation) =>
    relation.from === relation.to
        ? `Same ${lower(relation.to)}`
        : `Its ${lower(relation.from)} is this pigeon's ${lower(relation.to)}`;

const relationBadge = (bird: PedigreeCopySource) => {
    const [closest] = bird.related;
    if (!closest) return null;
    return closest.from === closest.to
        ? `Same ${lower(closest.to)}`
        : `Shares ${lower(closest.to)}`;
};

// Step 1: the birds to copy from

const load = async () => {
    const current = ++requestId;
    loading.value = true;
    try {
        const { data } = await axios.get(
            pedigreeCopySources({ pigeon: props.pigeonId }).url,
            { params: { q: query.value || undefined } },
        );
        // Ignore responses that arrive after a newer search
        if (current === requestId) results.value = data.pigeons;
    } catch {
        if (current === requestId) error('Could not load your pigeons');
    } finally {
        if (current === requestId) loading.value = false;
    }
};

const debouncedLoad = useDebounceFn(load, 250);
watch(query, () => debouncedLoad());

watch(open, (isOpen) => {
    if (!isOpen) return;
    query.value = '';
    source.value = null;
    sourceTree.value = null;
    choices.value = [];
    form.reset();
    form.clearErrors();
    load();
});

// Step 2: which of its ancestors go where

const targetSlots = computed(
    () => new Map(flattenPedigree(props.tree).map((slot) => [slot.key, slot])),
);
const allSourceSlots = computed(() =>
    sourceTree.value ? flattenPedigree(sourceTree.value) : [],
);
const sourceSlots = computed(() =>
    allSourceSlots.value.filter(
        (slot) => slot.node && slot.depth > 0 && slot.depth <= MAX_COPY_PATH,
    ),
);
const sourceByKey = computed(
    () => new Map(sourceSlots.value.map((slot) => [slot.key, slot])),
);

const behind = (slots: Iterable<PedigreeSlot>, key: string) =>
    [...slots].filter(
        (slot) =>
            slot.node &&
            slot.key.length > key.length &&
            slot.key.startsWith(key),
    ).length;

const sourceBehind = (key: string) => behind(allSourceSlots.value, key);
const targetBehind = (key: string) => behind(targetSlots.value.values(), key);

const isLoft = (node: PedigreeNode | null | undefined) =>
    !!node && !node.external;

const sameBird = (a: PedigreeNode, b: PedigreeNode) =>
    isLoft(a) && isLoft(b)
        ? a.id === b.id
        : normalizeRing(a.ring_number) !== '' &&
          normalizeRing(a.ring_number) === normalizeRing(b.ring_number);

// The loft bird a place in this pedigree is saved on, when that isn't this pigeon
const savedOn = (slot: PedigreeSlot) => {
    const child = slot.child;
    const anchorId = child?.external ? child.anchor_id : child?.id;
    if (!anchorId || anchorId === props.pigeonId) return null;
    const anchor = [...targetSlots.value.values()].find(
        (each) => isLoft(each.node) && each.node!.id === anchorId,
    )?.node;
    return anchor ? birdLabel(anchor) : null;
};

type Status = { disabled: boolean; tone: 'muted' | 'warning'; note: string };

// Whether a bird from the other pedigree can go into a place here, and what that would do
const status = (from: string, to: string): Status => {
    const bird = sourceByKey.value.get(from)?.node;
    const slot = targetSlots.value.get(to);

    if (!bird) return { disabled: true, tone: 'muted', note: '' };
    if (!slot) {
        return {
            disabled: true,
            tone: 'muted',
            note: `add the ${lower(to.slice(0, -1))} first`,
        };
    }

    const current = slot.node;
    if (isLoft(bird) && bird.id === props.pigeonId) {
        return {
            disabled: true,
            tone: 'muted',
            note: 'this is the pigeon itself',
        };
    }
    if (current && isLoft(current)) {
        return isLoft(bird) && bird.id === current.id
            ? { disabled: true, tone: 'muted', note: 'already this bird' }
            : {
                  disabled: true,
                  tone: 'muted',
                  note: `${birdLabel(current)} from your loft${to.length === 1 ? ' (unlink it on the edit page first)' : ''}`,
              };
    }
    if (
        current &&
        bird.external &&
        current.anchor_id === bird.anchor_id &&
        current.path === bird.path
    ) {
        return { disabled: true, tone: 'muted', note: 'already this record' };
    }

    const inherit = savedOn(slot);
    const suffix = inherit
        ? `. Saved on ${inherit} from your loft, so its offspring get it too`
        : '';
    const here = targetBehind(to);

    if (current && sameBird(current, bird)) {
        return here
            ? {
                  disabled: false,
                  tone: 'warning',
                  note: `Same bird. Replaces the ${plural(here, 'ancestor')} recorded behind it here${suffix}`,
              }
            : {
                  disabled: false,
                  tone: 'muted',
                  note: `Same bird. Adds the ancestors behind it${suffix}`,
              };
    }
    if (current) {
        return {
            disabled: false,
            tone: 'warning',
            note: `Replaces ${birdLabel(current)}${here ? ` and ${plural(here, 'ancestor')} behind it` : ''}${suffix}`,
        };
    }
    return {
        disabled: false,
        tone: 'muted',
        note: `Fills an empty place${suffix}`,
    };
};

// Places here for a bird from the other pedigree: a cock goes in a sire's place, a hen in a dam's
const placesFor = (from: string) =>
    PLACES.filter((key) => key.endsWith(from.slice(-1)));

const free = (to: string, picks: PedigreeCopyChoice[]) =>
    !picks.some(
        (pick) => pick.to && (pick.to.startsWith(to) || to.startsWith(pick.to)),
    );

// Where a bird most likely belongs here: where the same bird already is, the place that was clicked, or else
// the same place as in the other pedigree
const suggestedPlace = (from: string): string | null => {
    const ok = (to: string) =>
        !status(from, to).disabled && free(to, choices.value);
    const related = source.value?.related.find(
        (relation) => relation.from === from && ok(relation.to),
    );
    if (related) return related.to;
    if (
        props.focus &&
        props.focus.endsWith(from.slice(-1)) &&
        ok(props.focus)
    ) {
        return props.focus;
    }
    return ok(from) ? from : null;
};

const chosen = (from: string) =>
    choices.value.find((choice) => choice.from === from);

const toggle = (from: string) => {
    choices.value = chosen(from)
        ? choices.value.filter((choice) => choice.from !== from)
        : [...choices.value, { from, to: suggestedPlace(from) }];
};

// Two birds can't go into the same branch: the first would overwrite the second
const overlap = computed(() => {
    for (const a of choices.value) {
        for (const b of choices.value) {
            if (a === b || !a.to || !b.to || !b.to.startsWith(a.to)) continue;
            return a.to === b.to
                ? `Two birds are set to go into the ${lower(a.to)}'s place. Choose another place for one of them.`
                : `The ${lower(b.to)} is already copied along with the ${lower(a.to)}. Untick one of them.`;
        }
    }
    return null;
});

const ready = computed(
    () =>
        choices.value.length > 0 &&
        choices.value.every(
            (choice) => choice.to && !status(choice.from, choice.to).disabled,
        ) &&
        !overlap.value,
);

// Start with what is most likely wanted: the place that was clicked; otherwise where the birds share an ancestor
// and the other pedigree knows more behind it, and empty sides
const preselect = () => {
    const picks: PedigreeCopyChoice[] = [];

    if (props.focus) {
        const from =
            source.value?.related.find(
                (relation) => relation.to === props.focus,
            )?.from ?? props.focus;
        if (
            sourceByKey.value.has(from) &&
            !status(from, props.focus).disabled
        ) {
            picks.push({ from, to: props.focus });
        }
        choices.value = picks;
        return;
    }

    for (const relation of source.value?.related ?? []) {
        if (
            sourceByKey.value.has(relation.from) &&
            !status(relation.from, relation.to).disabled &&
            sourceBehind(relation.from) > targetBehind(relation.to) &&
            free(relation.to, picks)
        ) {
            picks.push({ ...relation });
        }
    }
    for (const key of ['S', 'D']) {
        if (
            sourceByKey.value.has(key) &&
            !targetSlots.value.get(key)?.node &&
            !status(key, key).disabled &&
            free(key, picks)
        ) {
            picks.push({ from: key, to: key });
        }
    }
    choices.value = picks;
};

const choose = async (bird: PedigreeCopySource) => {
    source.value = bird;
    sourceTree.value = null;
    choices.value = [];
    form.source_id = bird.id;
    form.clearErrors();
    loadingTree.value = true;
    try {
        const { data } = await axios.get(
            pedigreeCopySource({ pigeon: props.pigeonId, source: bird.id }).url,
        );
        if (source.value?.id !== bird.id) return;
        sourceTree.value = data.tree;
        preselect();
    } catch {
        error('Could not load that pedigree');
        source.value = null;
    } finally {
        loadingTree.value = false;
    }
};

const byGeneration = computed(() =>
    GENERATIONS.map((label, index) => ({
        label,
        slots: sourceSlots.value.filter((slot) => slot.depth === index + 1),
    })).filter((generation) => generation.slots.length),
);

const submitLabel = computed(() => {
    const [first] = choices.value;
    if (choices.value.length === 1 && first.to) {
        return `Copy as ${lower(first.to)}`;
    }
    return choices.value.length > 1
        ? `Copy ${plural(choices.value.length, 'bird')}`
        : 'Copy';
});

const submit = () => {
    if (!source.value || !ready.value) return;
    const from = sourceLabel(source.value);
    form.copies = choices.value.map((choice) => ({
        from: choice.from,
        to: choice.to!,
    }));
    const what =
        form.copies.length === 1
            ? `the ${lower(form.copies[0].to)}`
            : plural(form.copies.length, 'bird');
    form.post(copyPedigree({ pigeon: props.pigeonId }).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            success(`Copied ${what} from ${from}`);
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="pr-6"
                    >Copy pedigree from another pigeon</DialogTitle
                >
                <DialogDescription>
                    For related birds: copy a parent, grandparent or
                    great-grandparent from another pigeon's pedigree, with
                    everything recorded behind it.
                </DialogDescription>
            </DialogHeader>

            <!-- Step 1: pick the bird to copy from -->
            <div v-if="!source" class="grid gap-3">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="query"
                        class="pl-9"
                        placeholder="Search by ring number, name or personal number"
                        autocomplete="off"
                        aria-label="Search pigeons to copy from"
                    />
                    <LoaderCircle
                        v-if="loading"
                        class="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                    />
                </div>

                <div
                    class="max-h-[50vh] overflow-y-auto rounded-lg border bg-popover"
                >
                    <button
                        v-for="bird in results"
                        :key="bird.id"
                        type="button"
                        class="w-full border-b px-4 py-3 text-left last:border-b-0 hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                        @click="choose(bird)"
                    >
                        <PigeonSearchResult :pigeon="bird">
                            <template #aside>
                                <Badge
                                    v-if="relationBadge(bird)"
                                    variant="secondary"
                                    class="ml-auto shrink-0"
                                    >{{ relationBadge(bird) }}</Badge
                                >
                            </template>
                            <div
                                v-for="relation in bird.related.slice(0, 2)"
                                :key="`${relation.from}-${relation.to}`"
                                class="font-medium text-foreground"
                            >
                                {{ relationText(relation) }}
                            </div>
                        </PigeonSearchResult>
                    </button>

                    <p
                        v-if="!loading && results.length === 0"
                        class="p-6 text-center text-sm text-muted-foreground"
                    >
                        <template v-if="query"
                            >No pigeon with a recorded sire or dam matches "{{
                                query
                            }}".</template
                        >
                        <template v-else
                            >None of your other pigeons has a sire or dam
                            recorded yet.</template
                        >
                    </p>
                </div>
            </div>

            <!-- Step 2: choose the birds and where they go -->
            <form
                v-else
                id="copy-pedigree-form"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <div
                    class="flex items-center justify-between gap-3 rounded-lg bg-muted/60 px-3 py-2"
                >
                    <div class="min-w-0 text-sm">
                        <span class="text-muted-foreground">Copy from </span>
                        <span class="font-semibold tabular-nums">{{
                            sourceLabel(source)
                        }}</span>
                        <span
                            v-if="source.name && source.ring_number"
                            class="text-muted-foreground"
                        >
                            · {{ source.name }}</span
                        >
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        type="button"
                        @click="source = null"
                        >Change</Button
                    >
                </div>

                <div
                    v-if="loadingTree"
                    class="flex items-center justify-center gap-2 p-6 text-sm text-muted-foreground"
                >
                    <LoaderCircle class="size-4 animate-spin" />
                    Loading its pedigree
                </div>

                <p
                    v-else-if="!sourceSlots.length"
                    class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                >
                    Nothing is recorded in this pigeon's pedigree yet.
                </p>

                <template v-else>
                    <p class="text-sm text-muted-foreground">
                        Tick the birds to copy, and choose where each one goes.
                        Everything recorded behind a bird comes with it.
                    </p>

                    <section
                        v-for="generation in byGeneration"
                        :key="generation.label"
                        class="grid gap-2"
                    >
                        <h3
                            class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                        >
                            {{ generation.label }}
                        </h3>
                        <div
                            v-for="slot in generation.slots"
                            :key="slot.key"
                            class="rounded-xl border transition"
                            :class="
                                chosen(slot.key)
                                    ? 'border-gold bg-secondary/60'
                                    : 'hover:border-gold/60'
                            "
                        >
                            <button
                                type="button"
                                role="checkbox"
                                :aria-checked="!!chosen(slot.key)"
                                class="flex w-full items-start gap-3 p-3 text-left focus-visible:outline-none"
                                @click="toggle(slot.key)"
                            >
                                <span
                                    class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded border"
                                    :class="
                                        chosen(slot.key)
                                            ? 'border-navy bg-navy text-ivory dark:border-gold dark:bg-gold dark:text-navy'
                                            : 'border-input'
                                    "
                                >
                                    <Check
                                        v-if="chosen(slot.key)"
                                        class="size-3.5"
                                    />
                                </span>
                                <span class="grid min-w-0 flex-1 gap-0.5">
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ place(slot.key) }}</span
                                    >
                                    <span
                                        class="truncate text-sm font-semibold tabular-nums"
                                    >
                                        <span
                                            :class="
                                                slot.side === 'sire'
                                                    ? 'text-sky-700 dark:text-sky-300'
                                                    : 'text-rose-700 dark:text-rose-300'
                                            "
                                            >{{
                                                sexSymbol(
                                                    slot.side === 'sire'
                                                        ? 'male'
                                                        : 'female',
                                                )
                                            }}</span
                                        >
                                        {{ birdLabel(slot.node!) }}
                                        <span
                                            v-if="
                                                slot.node!.name &&
                                                slot.node!.ring_number
                                            "
                                            class="font-normal text-muted-foreground"
                                            >· {{ slot.node!.name }}</span
                                        >
                                    </span>
                                    <span
                                        class="flex items-center gap-1 text-xs text-muted-foreground"
                                    >
                                        <Home
                                            v-if="isLoft(slot.node)"
                                            class="size-3"
                                        />
                                        {{
                                            [
                                                isLoft(slot.node)
                                                    ? 'From your loft'
                                                    : null,
                                                sourceBehind(slot.key)
                                                    ? `${plural(sourceBehind(slot.key), 'ancestor')} recorded behind it`
                                                    : 'Nothing recorded behind it',
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')
                                        }}
                                    </span>
                                </span>
                            </button>

                            <div
                                v-if="chosen(slot.key)"
                                class="grid gap-1.5 border-t border-gold/30 px-3 py-2.5 sm:pl-11"
                            >
                                <label
                                    class="grid gap-1 text-xs font-medium"
                                    :for="`copy-into-${slot.key}`"
                                >
                                    Copy into this pigeon's pedigree as
                                    <select
                                        :id="`copy-into-${slot.key}`"
                                        v-model="chosen(slot.key)!.to"
                                        class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm font-normal focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <option :value="null" disabled>
                                            Choose a place
                                        </option>
                                        <option
                                            v-for="key in placesFor(slot.key)"
                                            :key="key"
                                            :value="key"
                                            :disabled="
                                                status(slot.key, key).disabled
                                            "
                                        >
                                            {{ place(key)
                                            }}{{
                                                status(slot.key, key).disabled
                                                    ? ` — ${status(slot.key, key).note}`
                                                    : ''
                                            }}
                                        </option>
                                    </select>
                                </label>
                                <p
                                    v-if="chosen(slot.key)!.to"
                                    class="flex items-start gap-1.5 text-xs"
                                    :class="
                                        status(slot.key, chosen(slot.key)!.to!)
                                            .tone === 'warning'
                                            ? 'text-amber-700 dark:text-amber-300'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    <AlertTriangle
                                        v-if="
                                            status(
                                                slot.key,
                                                chosen(slot.key)!.to!,
                                            ).tone === 'warning'
                                        "
                                        class="mt-px size-3.5 shrink-0"
                                    />
                                    {{
                                        status(slot.key, chosen(slot.key)!.to!)
                                            .note
                                    }}
                                </p>
                                <p v-else class="text-xs text-muted-foreground">
                                    Choose where this bird goes.
                                </p>
                            </div>
                        </div>
                    </section>

                    <p
                        v-if="overlap"
                        class="flex items-start gap-1.5 text-sm text-amber-700 dark:text-amber-300"
                    >
                        <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                        {{ overlap }}
                    </p>
                    <InputError
                        :message="form.errors.copies || form.errors.source_id"
                    />
                </template>

                <p
                    class="flex gap-2 rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground"
                >
                    <Info class="mt-px size-3.5 shrink-0 text-gold" />
                    Ring numbers are copied exactly, so these ancestors count as
                    the same birds in both pedigrees: Lineage and Pairing
                    Analysis match them, and later edits to one record can
                    update the others too. Ancestors without a ring number stay
                    separate.
                </p>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" type="button" @click="open = false"
                    >Cancel</Button
                >
                <Button
                    v-if="source"
                    type="submit"
                    form="copy-pedigree-form"
                    :disabled="form.processing || !ready"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="size-4 animate-spin"
                    />
                    {{ submitLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
