<script setup lang="ts">
import FormActionBar from '@/components/FormActionBar.vue';
import InputError from '@/components/InputError.vue';
import ImportBirdDialog from '@/components/pedigree-import/ImportBirdDialog.vue';
import MeritListEditor from '@/components/pedigree/MeritListEditor.vue';
import PedigreeEditorSlot from '@/components/pedigree/PedigreeEditorSlot.vue';
import PigeonSearchResult from '@/components/PigeonSearchResult.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    familyPath,
    flattenPedigree,
    GENERATION_LABELS,
    MAX_DEPTH,
    relationName,
    slotGridArea,
} from '@/lib/pedigree';
import {
    destroy,
    index as importsIndex,
    match,
    save,
    show,
} from '@/routes/pedigree-imports';
import { index as pigeonsIndex } from '@/routes/pigeons';
import { type BreadcrumbItem } from '@/types';
import type {
    Merit,
    MeritInput,
    MeritLevelOption,
    MeritTypeOption,
} from '@/types/lineage';
import type { PedigreeNode, PedigreeSlot } from '@/types/pedigree';
import type {
    ImportBird,
    ImportDraft,
    ImportMatch,
    ImportSaved,
} from '@/types/pedigree-import';
import { Head, router, useForm } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import axios from 'axios';
import {
    CircleAlert,
    ExternalLink,
    FileText,
    Home,
    MousePointerClick,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type Parent = 'S' | 'D';

const props = defineProps<{
    sheet: {
        id: number;
        file_name: string | null;
        file_url: string | null;
        is_pdf: boolean;
        // What the reader could not read
        note: string | null;
    };
    draft: ImportDraft;
    // Birds on the sheet that are already in the loft, by ring number
    matches: Record<'bird' | Parent, ImportMatch | null>;
    colors: string[];
    bloodlines: string[];
    meritTypes: MeritTypeOption[];
    meritLevels: MeritLevelOption[];
    statuses: { value: string; label: string }[];
    // Google Drive is connected and the sheet is an image
    canKeepSheet: boolean;
    // Other sheets read and waiting to be checked
    waiting: number;
    lastSaved: ImportSaved | null;
}>();

const { success, warning, error } = useToast();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: pigeonsIndex().url },
    { title: 'Import pedigrees', href: importsIndex().url },
    { title: 'Check pedigree', href: show(props.sheet.id).url },
];

const PARENT_KEYS: Parent[] = ['S', 'D'];

const PARENTS: Record<Parent, { side: string; sex: string }> = {
    S: { side: 'sire', sex: 'cock' },
    D: { side: 'dam', sex: 'hen' },
};

// ----- The form: what was read, as corrected by the user -----

const matches = reactive({ ...props.matches });

// Link each parent that is in the loft, unless the user unticks it
const linkChoice = reactive<Record<Parent, boolean>>({ S: true, D: true });

const form = useForm({
    // Add the pedigree to the bird already in the loft, or save a new pigeon
    target: (props.matches.bird ? 'existing' : 'new') as 'existing' | 'new',
    pigeon_id: null as number | null,
    // Text fields as the inputs edit them; the server turns empty ones into null
    bird: {
        ring_number: props.draft.bird.ring_number ?? '',
        name: props.draft.bird.name ?? '',
        gender: props.draft.bird.gender,
        color: props.draft.bird.color ?? '',
        bloodline: props.draft.bird.bloodline ?? '',
        bred_by: props.draft.bird.bred_by ?? '',
        remarks: props.draft.bird.remarks ?? '',
        merits: props.draft.bird.merits.map((merit) => ({ ...merit })),
        status: 'stock',
    },
    ancestors: JSON.parse(JSON.stringify(props.draft.ancestors)) as Record<
        string,
        ImportBird
    >,
    links: { S: null, D: null } as Record<Parent, number | null>,
    keep_sheet: props.canKeepSheet,
});

const linked = (letter: Parent): ImportMatch | null =>
    linkChoice[letter] && matches[letter]?.linkable ? matches[letter] : null;

// Look up a changed ring number among the loft's birds
const lookup = async (position: 'bird' | Parent, ring: string | null) => {
    if (!ring?.trim()) {
        matches[position] = null;
        return;
    }
    try {
        const { data } = await axios.get(match().url, {
            params: { ring_number: ring, position },
        });
        matches[position] = data.match;
    } catch {
        // Keep the last answer; saving checks again on the server
    }
};

watchDebounced(
    () => form.bird.ring_number,
    (ring) => lookup('bird', ring),
    { debounce: 400 },
);
for (const letter of PARENT_KEYS) {
    watchDebounced(
        () => form.ancestors[letter]?.ring_number ?? null,
        (ring) => lookup(letter, ring),
        { debounce: 400 },
    );
}

watch(
    () => matches.bird?.id ?? null,
    (id, previous) => {
        if (id !== previous) form.target = id ? 'existing' : 'new';
    },
);

// Ancestors stored behind a place that is empty (the reader found a grandparent but not the parent)
const unreachable = computed(() =>
    Object.keys(form.ancestors).filter((path) =>
        [...path].some(
            (_, length) => length > 0 && !form.ancestors[path.slice(0, length)],
        ),
    ),
);

const submit = () => {
    form.transform((data) => ({
        ...data,
        pigeon_id:
            data.target === 'existing' ? (matches.bird?.id ?? null) : null,
        // Birds behind an empty place can't be saved: leave them out (the page says so)
        ancestors: Object.fromEntries(
            Object.entries(data.ancestors).filter(
                ([path]) => !unreachable.value.includes(path),
            ),
        ),
        links: { S: linked('S')?.id ?? null, D: linked('D')?.id ?? null },
    })).post(save(props.sheet.id).url, {
        preserveScroll: true,
        onError: () => error('Check the pedigree: something needs correcting'),
    });
};

const removeSheet = () =>
    router.delete(destroy(props.sheet.id).url, {
        onSuccess: () => success('Sheet removed'),
    });

// ----- The chart -----

const meritSummaries = (merits: MeritInput[]): Merit[] =>
    merits.map((merit) => {
        const type = props.meritTypes.find((t) => t.value === merit.type);
        return {
            id: null,
            type: merit.type,
            label: type?.label ?? merit.type,
            code: type?.code ?? merit.type,
            category: type?.category ?? 'racing',
            color: type?.color ?? '#94a3b8',
            weight: type?.weight ?? 0,
            level: merit.level,
            level_label: null,
            year: merit.year,
            detail: merit.detail,
            short: type?.code ?? '',
        };
    });

const node = (key: string): PedigreeNode | null => {
    const blank = {
        anchor_id: null,
        path: null,
        gender: null,
        hatch_date: null,
        photo_url: null,
        sire: null,
        dam: null,
    };

    if (key === '') {
        const bird = form.bird;
        return {
            ...blank,
            id: null,
            external: false,
            name: bird.name,
            ring_number: bird.ring_number,
            color: bird.color,
            gender: bird.gender,
            bloodline: bird.bloodline,
            remarks: bird.remarks,
            merits: meritSummaries(bird.merits),
        };
    }

    // A linked parent from the loft brings its own record
    const loft = key.length === 1 ? linked(key as Parent) : null;
    if (loft) {
        return {
            ...blank,
            id: loft.id,
            external: false,
            name: loft.name,
            ring_number: loft.ring_number,
            color: loft.color ?? null,
            bloodline: loft.bloodline ?? null,
            remarks: null,
            merits: [],
        };
    }

    const bird = form.ancestors[key];
    if (!bird) return null;
    return {
        ...blank,
        id: null,
        external: true,
        name: bird.name,
        ring_number: bird.ring_number,
        color: bird.color,
        bloodline: bird.bloodline,
        remarks: bird.remarks,
        merits: meritSummaries(bird.merits),
    };
};

const build = (key: string): PedigreeNode | null => {
    const found = node(key);
    if (found && key.length < MAX_DEPTH) {
        found.sire = build(`${key}S`);
        found.dam = build(`${key}D`);
    }
    return found;
};

const slots = computed(() => flattenPedigree(build('')!));

// Ancestors read from the sheet can be corrected; empty places behind a known bird can be filled
const editable = (entry: PedigreeSlot) =>
    entry.depth > 0 && (entry.node ? entry.node.external : !!entry.child);

const ancestorCount = computed(() => Object.keys(form.ancestors).length);
const totalAncestors = 2 ** (MAX_DEPTH + 1) - 2;

const dialogOpen = ref(false);
const activeKey = ref('');

const openBird = (key: string) => {
    activeKey.value = key;
    dialogOpen.value = true;
};

const saveBird = (bird: ImportBird) => {
    form.ancestors = { ...form.ancestors, [activeKey.value]: bird };
};

// Leaving a bird out leaves out everything behind it too
const removeBird = () => {
    const key = activeKey.value;
    form.ancestors = Object.fromEntries(
        Object.entries(form.ancestors).filter(
            ([path]) => !path.startsWith(key),
        ),
    );
};

const behind = computed(
    () =>
        Object.keys(form.ancestors).filter(
            (path) =>
                path.length > activeKey.value.length &&
                path.startsWith(activeKey.value),
        ).length,
);

// ----- Messages -----

const subjectLabel = computed(
    () => form.bird.ring_number || form.bird.name || 'this pigeon',
);

const firstError = (prefix: string) =>
    Object.entries(form.errors).find(([key]) => key.startsWith(prefix))?.[1];

// Errors that don't belong to a field of the pigeon itself, e.g. an ancestor's
const otherErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => !key.startsWith('bird.'))
        .map(([key, message]) => {
            const place = key.match(/^ancestors\.([SD]+)\./)?.[1];
            return place
                ? `${relationName(place)} (${familyPath(place).toLowerCase()}): ${message}`
                : message;
        }),
);

onMounted(() => {
    const report = props.lastSaved;
    if (!report) return;
    success(
        `Saved ${report.pigeon.label ?? 'the pigeon'} with ${report.ancestors} ${report.ancestors === 1 ? 'ancestor' : 'ancestors'}`,
    );
    if (report.kept.length)
        warning(
            `${report.kept.length} ${report.kept.length === 1 ? 'place already had a different bird' : 'places already had a different bird'}, so ${report.kept.length === 1 ? 'it was' : 'they were'} kept`,
        );
    if (report.sheet_failed)
        warning(
            'The sheet could not be saved to your Google Drive. The pedigree itself was saved.',
        );
});
</script>

<template>
    <Head :title="`Check pedigree - ${subjectLabel}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <form
            class="flex h-full flex-1 flex-col gap-6 p-4 pb-32 sm:p-6 md:pb-6"
            @submit.prevent="submit"
        >
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold sm:text-3xl">
                        Check the pedigree
                    </h1>
                    <p class="mt-1 max-w-2xl text-muted-foreground">
                        Compare what was read with the sheet and correct
                        anything that's wrong. Nothing is saved until you save.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-if="waiting"
                        class="rounded-full bg-secondary px-3 py-1 text-sm"
                        >{{ waiting }} more to check</span
                    >
                    <Button
                        type="button"
                        variant="outline"
                        @click="removeSheet"
                    >
                        <Trash2 class="size-4" />
                        Remove sheet
                    </Button>
                </div>
            </div>

            <div
                v-if="sheet.note"
                class="flex gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" />
                <p>{{ sheet.note }}</p>
            </div>

            <div
                v-if="otherErrors.length"
                class="rounded-xl border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive"
            >
                <p v-for="(message, index) in otherErrors" :key="index">
                    {{ message }}
                </p>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
                <!-- The sheet, to compare with -->
                <aside class="min-w-0 xl:sticky xl:top-4 xl:self-start">
                    <div class="rounded-xl border bg-card p-2">
                        <a
                            v-if="sheet.file_url && !sheet.is_pdf"
                            :href="sheet.file_url"
                            target="_blank"
                            rel="noopener"
                            class="block"
                        >
                            <img
                                :src="sheet.file_url"
                                alt="The pedigree sheet"
                                class="max-h-[50vh] w-full rounded-lg object-contain xl:max-h-[80vh]"
                            />
                        </a>
                        <div
                            v-else
                            class="flex flex-col items-center gap-3 p-8 text-center"
                        >
                            <FileText class="size-10 text-muted-foreground" />
                            <p class="text-sm">{{ sheet.file_name }}</p>
                            <Button
                                v-if="sheet.file_url"
                                as-child
                                variant="outline"
                                size="sm"
                            >
                                <a :href="sheet.file_url">Download the PDF</a>
                            </Button>
                        </div>
                        <p
                            v-if="sheet.file_url && !sheet.is_pdf"
                            class="flex items-center gap-1 px-2 pt-2 text-xs text-muted-foreground"
                        >
                            <ExternalLink class="size-3" />
                            Click the sheet to open it full size.
                        </p>
                    </div>
                </aside>

                <div class="grid min-w-0 content-start gap-6">
                    <!-- The pigeon itself -->
                    <section
                        class="grid gap-4 rounded-xl border bg-card p-4 sm:p-5"
                    >
                        <h2 class="text-lg font-semibold">The pigeon</h2>

                        <div
                            v-if="matches.bird"
                            class="grid gap-3 rounded-lg border border-gold/40 bg-secondary/50 p-3 text-sm"
                        >
                            <p class="flex gap-2">
                                <Home
                                    class="mt-0.5 size-4 shrink-0 text-gold"
                                />
                                This ring number is already in your loft:
                            </p>
                            <PigeonSearchResult
                                :pigeon="matches.bird"
                                class="pl-6"
                            />
                            <div class="grid gap-2 pl-6">
                                <label class="flex items-start gap-2">
                                    <input
                                        v-model="form.target"
                                        type="radio"
                                        value="existing"
                                        class="mt-1 accent-gold"
                                    />
                                    <span
                                        >Add this pedigree to it. Only what's
                                        missing is filled in; nothing already
                                        recorded changes.</span
                                    >
                                </label>
                                <label class="flex items-start gap-2">
                                    <input
                                        v-model="form.target"
                                        type="radio"
                                        value="new"
                                        class="mt-1 accent-gold"
                                    />
                                    <span>Save it as a new pigeon</span>
                                </label>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="bird_ring">Ring number *</Label>
                                <Input
                                    id="bird_ring"
                                    v-model="form.bird.ring_number"
                                    autocomplete="off"
                                />
                                <InputError
                                    :message="form.errors['bird.ring_number']"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="bird_name">Name</Label>
                                <Input
                                    id="bird_name"
                                    v-model="form.bird.name"
                                    autocomplete="off"
                                />
                                <InputError
                                    :message="form.errors['bird.name']"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="bird_gender">Sex</Label>
                                <select
                                    id="bird_gender"
                                    v-model="form.bird.gender"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <option :value="null">Not specified</option>
                                    <option value="male">Cock</option>
                                    <option value="female">Hen</option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="bird_color">Color</Label>
                                <Input
                                    id="bird_color"
                                    v-model="form.bird.color"
                                    autocomplete="off"
                                    list="review-colors"
                                />
                                <datalist id="review-colors">
                                    <option
                                        v-for="color in colors"
                                        :key="color"
                                        :value="color"
                                    />
                                </datalist>
                            </div>
                            <div class="grid gap-2">
                                <Label for="bird_bloodline">Bloodline</Label>
                                <Input
                                    id="bird_bloodline"
                                    v-model="form.bird.bloodline"
                                    autocomplete="off"
                                    list="review-bloodlines"
                                />
                                <datalist id="review-bloodlines">
                                    <option
                                        v-for="bloodline in bloodlines"
                                        :key="bloodline"
                                        :value="bloodline"
                                    />
                                </datalist>
                            </div>
                            <div class="grid gap-2">
                                <Label for="bird_bred_by">Bred by</Label>
                                <Input
                                    id="bird_bred_by"
                                    v-model="form.bird.bred_by"
                                    autocomplete="off"
                                />
                            </div>
                            <div
                                v-if="form.target === 'new'"
                                class="grid gap-2"
                            >
                                <Label for="bird_status">Status</Label>
                                <select
                                    id="bird_status"
                                    v-model="form.bird.status"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <option
                                        v-for="status in statuses"
                                        :key="status.value"
                                        :value="status.value"
                                    >
                                        {{ status.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="bird_remarks"
                                    >Remarks
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(shown on pedigree)</span
                                    ></Label
                                >
                                <Textarea
                                    id="bird_remarks"
                                    v-model="form.bird.remarks"
                                    rows="4"
                                />
                                <InputError
                                    :message="form.errors['bird.remarks']"
                                />
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label>Merits</Label>
                                <MeritListEditor
                                    v-model="form.bird.merits"
                                    :merit-types="meritTypes"
                                    :merit-levels="meritLevels"
                                    :error="firstError('bird.merits')"
                                />
                            </div>
                        </div>
                    </section>

                    <!-- Parents already in the loft -->
                    <section
                        v-if="matches.S || matches.D"
                        class="grid gap-3 rounded-xl border bg-card p-4 sm:p-5"
                    >
                        <h2 class="text-lg font-semibold">
                            Parents in your loft
                        </h2>
                        <template v-for="letter in PARENT_KEYS" :key="letter">
                            <div
                                v-if="matches[letter]"
                                class="grid gap-2 rounded-lg border p-3 text-sm"
                            >
                                <label class="flex items-start gap-3">
                                    <Checkbox
                                        v-model="linkChoice[letter]"
                                        :disabled="!matches[letter]!.linkable"
                                        class="mt-0.5"
                                    />
                                    <PigeonSearchResult
                                        :pigeon="matches[letter]!"
                                        class="flex-1"
                                    >
                                        <template #aside>
                                            <span
                                                class="shrink-0 rounded-full bg-secondary px-2 py-0.5 text-xs font-medium capitalize"
                                                >{{
                                                    PARENTS[letter].side
                                                }}</span
                                            >
                                        </template>
                                    </PigeonSearchResult>
                                </label>
                                <p
                                    v-if="matches[letter]!.linkable"
                                    class="pl-7 text-muted-foreground"
                                >
                                    <template v-if="linkChoice[letter]">
                                        Linked as the
                                        {{ PARENTS[letter].side }}, so its own
                                        record and pedigree are used. Empty
                                        places in its pedigree are filled in
                                        from this sheet.
                                    </template>
                                    <template v-else>
                                        Not linked: the
                                        {{ PARENTS[letter].side }} is saved as
                                        an outside bird, as read from the sheet.
                                    </template>
                                </p>
                                <p v-else class="pl-7 text-muted-foreground">
                                    It isn't recorded as a
                                    {{ PARENTS[letter].sex }} in your loft, so
                                    it can't be linked as the
                                    {{ PARENTS[letter].side }}. It is saved as
                                    an outside bird instead.
                                </p>
                                <InputError
                                    class="pl-7"
                                    :message="form.errors[`links.${letter}`]"
                                />
                            </div>
                        </template>
                    </section>

                    <!-- Ancestors -->
                    <section class="grid gap-3 rounded-xl border bg-card">
                        <div class="grid gap-1 px-4 pt-4 sm:px-5">
                            <h2 class="text-lg font-semibold">
                                Ancestors
                                <span class="font-normal text-muted-foreground"
                                    >{{ ancestorCount }} of
                                    {{ totalAncestors }}</span
                                >
                            </h2>
                            <p class="flex gap-2 text-sm text-muted-foreground">
                                <MousePointerClick
                                    class="mt-0.5 size-4 shrink-0 text-gold"
                                />
                                Click a bird to correct it or leave it out, or
                                an empty box to add one the sheet has.
                            </p>
                            <p
                                v-if="unreachable.length"
                                class="flex gap-2 text-sm text-amber-700 dark:text-amber-400"
                            >
                                <CircleAlert class="mt-0.5 size-4 shrink-0" />
                                {{ unreachable.length }}
                                {{
                                    unreachable.length === 1 ? 'bird' : 'birds'
                                }}
                                read behind an empty box won't be saved. Fill in
                                the empty box in front of
                                {{ unreachable.length === 1 ? 'it' : 'them' }}
                                to keep
                                {{ unreachable.length === 1 ? 'it' : 'them' }}.
                            </p>
                        </div>
                        <div class="overflow-x-auto px-4 pb-4 sm:px-5">
                            <div class="min-w-[900px]">
                                <div
                                    class="mb-3 grid grid-cols-[190px_repeat(4,minmax(0,1fr))] gap-x-5 text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                >
                                    <span
                                        v-for="(
                                            label, index
                                        ) in GENERATION_LABELS"
                                        :key="label"
                                    >
                                        <span class="mr-1 text-gold">{{
                                            String(index + 1).padStart(2, '0')
                                        }}</span>
                                        {{ label }}
                                    </span>
                                </div>
                                <div
                                    class="grid grid-cols-[190px_repeat(4,minmax(0,1fr))] grid-rows-[repeat(16,minmax(2.75rem,auto))] gap-x-5"
                                >
                                    <div
                                        v-for="entry in slots"
                                        :key="entry.key || 'subject'"
                                        class="relative flex min-w-0 items-center py-0.5"
                                        :style="slotGridArea(entry)"
                                    >
                                        <PedigreeEditorSlot
                                            :entry="entry"
                                            :editable="editable(entry)"
                                            @edit="openBird(entry.key)"
                                        />
                                        <template
                                            v-if="
                                                entry.node &&
                                                entry.depth < MAX_DEPTH
                                            "
                                        >
                                            <span
                                                class="absolute top-1/2 left-full w-2.5 border-t border-border"
                                            />
                                            <span
                                                class="absolute top-1/4 bottom-1/4 left-[calc(100%+0.625rem)] w-2.5 rounded-l-lg border border-r-0 border-border"
                                            />
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <label
                        v-if="canKeepSheet"
                        class="flex items-start gap-3 rounded-xl border bg-card p-4 text-sm"
                    >
                        <Checkbox v-model="form.keep_sheet" class="mt-0.5" />
                        <span>
                            Keep this sheet with the pigeon's certificates
                            <span class="block text-muted-foreground"
                                >Saved to your Google Drive. Only you see it
                                unless you show it on the pedigree.</span
                            >
                        </span>
                    </label>

                    <FormActionBar
                        :cancel-href="importsIndex().url"
                        :submit-label="
                            waiting ? 'Save and check the next' : 'Save pigeon'
                        "
                        :processing="form.processing"
                    />
                </div>
            </div>
        </form>

        <ImportBirdDialog
            v-model:open="dialogOpen"
            :position="activeKey"
            :bird="form.ancestors[activeKey] ?? null"
            :behind="behind"
            :colors="colors"
            :bloodlines="bloodlines"
            :merit-types="meritTypes"
            :merit-levels="meritLevels"
            @save="saveBird"
            @remove="removeBird"
        />
    </AppLayout>
</template>
