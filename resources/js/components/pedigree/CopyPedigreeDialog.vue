<script setup lang="ts">
import InputError from '@/components/InputError.vue';
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
import { normalizeRing } from '@/lib/pedigree';
import { copyPedigree, pedigreeCopySources } from '@/routes/pigeons';
import type {
    PedigreeCopySide,
    PedigreeCopySource,
    PedigreeSide,
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
    // What the pigeon being edited has on each side now
    target: Record<PedigreeSide, PedigreeCopySide>;
}>();

const open = defineModel<boolean>('open', { required: true });

const { success, error } = useToast();

const SIDES: PedigreeSide[] = ['sire', 'dam'];

const query = ref('');
const results = ref<PedigreeCopySource[]>([]);
const loading = ref(false);
const source = ref<PedigreeCopySource | null>(null);
let requestId = 0;

const form = useForm({
    source_id: null as number | null,
    sides: [] as PedigreeSide[],
});

const label = (bird: { ring_number: string | null; name: string | null }) =>
    bird.ring_number || bird.name || 'Unnamed bird';

const sourceLabel = (bird: PedigreeCopySource) =>
    bird.ring_number ||
    bird.name ||
    bird.personal_number ||
    `Pigeon #${bird.id}`;

const plural = (count: number, word: string) =>
    `${count} ${word}${count === 1 ? '' : 's'}`;

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
    form.reset();
    form.clearErrors();
    load();
});

const sameRing = (a: PedigreeCopySide, b: PedigreeCopySide) =>
    normalizeRing(a.ring_number) !== '' &&
    normalizeRing(a.ring_number) === normalizeRing(b.ring_number);

// Whether a side of the chosen bird can be copied here, and what copying it would do
const option = (side: PedigreeSide) => {
    const from = source.value?.[side];
    const to = props.target[side];

    if (!from || from.state === 'empty') {
        return { disabled: true, tone: 'muted', note: `No ${side} recorded` };
    }
    if (from.state === 'linked' && from.pigeon_id === props.pigeonId) {
        return {
            disabled: true,
            tone: 'muted',
            note: `A pigeon can't be its own ${side}`,
        };
    }
    if (to.state === 'linked') {
        return from.state === 'linked' && from.pigeon_id === to.pigeon_id
            ? {
                  disabled: true,
                  tone: 'muted',
                  note: `Already the same ${side}`,
              }
            : {
                  disabled: true,
                  tone: 'muted',
                  note: `This pigeon's ${side} is linked to ${label(to)} from your loft. Unlink it on the edit page to copy.`,
              };
    }
    if (to.state === 'external' && sameRing(from, to)) {
        // The same bird already: copying only brings over what is recorded behind it
        return to.ancestors
            ? {
                  disabled: false,
                  tone: 'warning',
                  note: `Same ${side}. Replaces the ${plural(to.ancestors, 'ancestor')} recorded behind it here`,
              }
            : {
                  disabled: false,
                  tone: 'muted',
                  note: `Same ${side}. Adds the ancestors recorded behind it`,
              };
    }
    if (to.state === 'external') {
        return {
            disabled: false,
            tone: 'warning',
            note: `Replaces ${label(to)}${to.ancestors ? ` and ${plural(to.ancestors, 'ancestor')} behind it` : ''}`,
        };
    }
    return {
        disabled: false,
        tone: 'muted',
        note: `Fills this pigeon's empty ${side} side`,
    };
};

const choose = (bird: PedigreeCopySource) => {
    source.value = bird;
    form.source_id = bird.id;
    form.clearErrors();
    // Start with the sides that fill a gap, or that the birds share (full or half siblings) as long as
    // the copy brings at least as much as is recorded here already
    form.sides = SIDES.filter(
        (side) =>
            !option(side).disabled &&
            (props.target[side].state === 'empty' ||
                (bird.shared.includes(side) &&
                    (props.target[side].ancestors ?? 0) <=
                        (bird[side].ancestors ?? 0))),
    );
};

const toggle = (side: PedigreeSide) => {
    if (option(side).disabled) return;
    form.sides = form.sides.includes(side)
        ? form.sides.filter((chosen) => chosen !== side)
        : SIDES.filter((each) => each === side || form.sides.includes(each));
};

const submitLabel = computed(() =>
    form.sides.length === 2
        ? 'Copy both sides'
        : form.sides.length === 1
          ? `Copy ${form.sides[0]} side`
          : 'Copy',
);

const submit = () => {
    if (!source.value) return;
    const from = sourceLabel(source.value);
    const what =
        form.sides.length === 2 ? 'both sides' : `the ${form.sides[0]} side`;
    form.post(copyPedigree({ pigeon: props.pigeonId }).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            success(`Copied ${what} from ${from}`);
        },
    });
};

const sharedBadge = (bird: PedigreeCopySource) =>
    bird.shared.length === 2
        ? 'Same parents'
        : bird.shared[0] === 'sire'
          ? 'Same sire'
          : bird.shared[0] === 'dam'
            ? 'Same dam'
            : null;

const sideLine = (side: PedigreeCopySide) => {
    if (side.state === 'empty') return '—';
    if (side.state === 'linked') return `${label(side)} (your loft)`;
    return side.ancestors
        ? `${label(side)} + ${plural(side.ancestors, 'ancestor')}`
        : label(side);
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
                    For birds from the same family, such as full or half
                    siblings. Copy the sire side, the dam side or both, with
                    everything recorded behind them.
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

                <div class="grid max-h-[50vh] gap-2 overflow-y-auto pr-1">
                    <button
                        v-for="bird in results"
                        :key="bird.id"
                        type="button"
                        class="grid gap-1.5 rounded-lg border bg-card px-3 py-2.5 text-left transition hover:border-gold hover:bg-accent/40 focus-visible:border-gold focus-visible:outline-none"
                        @click="choose(bird)"
                    >
                        <div class="flex items-center gap-2">
                            <span class="truncate font-semibold tabular-nums">{{
                                sourceLabel(bird)
                            }}</span>
                            <span
                                v-if="bird.name && bird.ring_number"
                                class="truncate text-sm text-muted-foreground"
                                >{{ bird.name }}</span
                            >
                            <Badge
                                v-if="sharedBadge(bird)"
                                variant="secondary"
                                class="ml-auto shrink-0"
                                >{{ sharedBadge(bird) }}</Badge
                            >
                        </div>
                        <div
                            class="grid gap-x-4 gap-y-0.5 text-xs text-muted-foreground sm:grid-cols-2"
                        >
                            <span class="truncate"
                                ><span class="text-sky-700 dark:text-sky-300"
                                    >♂</span
                                >
                                {{ sideLine(bird.sire) }}</span
                            >
                            <span class="truncate"
                                ><span class="text-rose-700 dark:text-rose-300"
                                    >♀</span
                                >
                                {{ sideLine(bird.dam) }}</span
                            >
                        </div>
                    </button>

                    <p
                        v-if="!loading && results.length === 0"
                        class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
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

            <!-- Step 2: choose the sides -->
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

                <div class="grid gap-3 sm:grid-cols-2" role="group">
                    <button
                        v-for="side in SIDES"
                        :key="side"
                        type="button"
                        role="checkbox"
                        :aria-checked="form.sides.includes(side)"
                        :aria-disabled="option(side).disabled"
                        class="grid gap-2 rounded-xl border p-4 text-left transition focus-visible:border-gold focus-visible:outline-none"
                        :class="
                            option(side).disabled
                                ? 'cursor-not-allowed opacity-60'
                                : form.sides.includes(side)
                                  ? 'border-gold bg-secondary/60'
                                  : 'hover:border-gold/60'
                        "
                        @click="toggle(side)"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="flex size-5 shrink-0 items-center justify-center rounded border"
                                :class="
                                    form.sides.includes(side)
                                        ? 'border-navy bg-navy text-ivory dark:border-gold dark:bg-gold dark:text-navy'
                                        : 'border-input'
                                "
                            >
                                <Check
                                    v-if="form.sides.includes(side)"
                                    class="size-3.5"
                                />
                            </span>
                            <span class="font-semibold">{{
                                side === 'sire' ? 'Sire side' : 'Dam side'
                            }}</span>
                        </div>

                        <div
                            v-if="source[side].state !== 'empty'"
                            class="text-sm"
                        >
                            <p class="truncate font-medium tabular-nums">
                                {{ label(source[side]) }}
                                <span
                                    v-if="
                                        source[side].name &&
                                        source[side].ring_number
                                    "
                                    class="font-normal text-muted-foreground"
                                    >· {{ source[side].name }}</span
                                >
                            </p>
                            <p
                                v-if="source[side].state === 'linked'"
                                class="flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <Home class="size-3" />
                                From your loft: links the same bird, its own
                                pedigree comes along
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                {{
                                    source[side].ancestors
                                        ? `With ${plural(source[side].ancestors!, 'ancestor')} recorded behind it`
                                        : 'No ancestors recorded behind it yet'
                                }}
                            </p>
                        </div>

                        <p
                            class="flex items-start gap-1.5 text-xs"
                            :class="
                                option(side).tone === 'warning'
                                    ? 'text-amber-700 dark:text-amber-300'
                                    : 'text-muted-foreground'
                            "
                        >
                            <AlertTriangle
                                v-if="option(side).tone === 'warning'"
                                class="mt-px size-3.5 shrink-0"
                            />
                            {{ option(side).note }}
                        </p>
                    </button>
                </div>

                <InputError
                    :message="form.errors.sides || form.errors.source_id"
                />

                <p
                    class="flex gap-2 rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground"
                >
                    <Info class="mt-px size-3.5 shrink-0 text-gold" />
                    Ring numbers are copied exactly, so these ancestors count as
                    the same birds in both pedigrees: Lineage and Test Pairing
                    match them, and later edits to one record can update the
                    others too. Ancestors without a ring number stay separate.
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
                    :disabled="form.processing || form.sides.length === 0"
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
