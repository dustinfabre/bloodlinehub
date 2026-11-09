<script setup lang="ts">
// The loft check board: tick each bird you see (tap its ring number or type it), then mark the ones left as
// missing. Ticks are kept on this device until the check is saved, so a lost signal or a locked phone loses nothing.
import MultiSelectFilter, { type FilterOption, type FilterPreset } from '@/components/MultiSelectFilter.vue';
import PigeonSearchResult from '@/components/PigeonSearchResult.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { PIGEON_STATUSES, statusStyle } from '@/lib/pigeon-status';
import { create as createRoute, index as indexRoute, show as showRoute, store as storeRoute } from '@/routes/loft-checks';
import { type AppPageProps, type BreadcrumbItem } from '@/types';
import {
    formatCheckDate,
    formatDay,
    formatShortDay,
    type LoftCheckBird,
    type LoftCheckScope,
    type LoftCheckSummary,
    type RingSeriesOption,
} from '@/types/loft-check';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, ClipboardCheck, EyeOff, LayoutGrid, List, LoaderCircle, RotateCcw, Undo2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    scope: LoftCheckScope;
    label: string;
    locations: { id: number; name: string }[];
    seriesOptions: RingSeriesOption[];
    inLoftStatuses: string[];
    birds: LoftCheckBird[];
    missingBirds: LoftCheckBird[];
    lastCheck: LoftCheckSummary | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Loft Check', href: indexRoute().url },
    { title: 'Count', href: createRoute().url },
];

const page = usePage<AppPageProps>();

// --- What to count ---------------------------------------------------------------------------------------------

const location = ref(props.scope.location);
const series = ref(props.scope.series);
const statuses = ref<string[]>([...props.scope.status]);

const statusOptions: FilterOption[] = PIGEON_STATUSES.filter((status) => props.inLoftStatuses.includes(status.value)).map(
    ({ value, label, dotClass }) => ({ value, label, dotClass }),
);

// Shown on the filter instead of "4 selected" while every in-loft status is counted
const statusPresets: FilterPreset[] = [{ label: 'All in the loft', values: props.inLoftStatuses }];

const seriesChoices = computed(() => {
    const options = [...props.seriesOptions];
    // A series picked earlier that has no birds here (e.g. after changing the location) stays selectable
    if (series.value !== 'all' && !options.some((option) => option.value === series.value)) {
        options.push({ value: series.value, label: series.value, count: 0, from: null, to: null });
    }
    return options;
});

const seriesRange = (option: RingSeriesOption) =>
    option.from && option.to && option.from !== option.to ? ` (${option.from}–${option.to})` : '';

const changeScope = () => {
    const allStatuses = !statuses.value.length || statuses.value.length === props.inLoftStatuses.length;

    router.get(
        createRoute().url,
        {
            location: location.value === 'all' ? undefined : location.value,
            series: series.value === 'all' ? undefined : series.value,
            status: allStatuses ? undefined : statuses.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const setLocation = (value: unknown) => {
    location.value = String(value);
    // Series differ from one location to another
    series.value = 'all';
    changeScope();
};

const setSeries = (value: unknown) => {
    series.value = String(value);
    changeScope();
};

watch(statuses, changeScope, { deep: true });

// --- Ticks, kept on this device until saved ------------------------------------------------------------------------

const seen = ref(new Set<number>());
// Missing birds that came back
const back = ref(new Set<number>());
const startedAt = ref(Date.now());
const restoredAt = ref<number | null>(null);
const lastTouched = ref<number | null>(null);

const DRAFT_MAX_AGE = 12 * 60 * 60 * 1000;

const draftKey = computed(
    () => `loft-check:v1:${page.props.auth.user.id}:${props.scope.location}|${props.scope.series}|${props.scope.status.join(',')}`,
);

const loadDraft = () => {
    seen.value = new Set();
    back.value = new Set();
    restoredAt.value = null;
    startedAt.value = Date.now();
    lastTouched.value = null;

    try {
        const raw = localStorage.getItem(draftKey.value);
        if (!raw) return;

        const draft = JSON.parse(raw) as { seen?: number[]; back?: number[]; startedAt?: number };
        if (!draft.startedAt || Date.now() - draft.startedAt > DRAFT_MAX_AGE) {
            localStorage.removeItem(draftKey.value);
            return;
        }

        const birdIds = new Set(props.birds.map((bird) => bird.id));
        const missingIds = new Set(props.missingBirds.map((bird) => bird.id));
        seen.value = new Set((draft.seen ?? []).filter((id) => birdIds.has(id)));
        back.value = new Set((draft.back ?? []).filter((id) => missingIds.has(id)));

        if (seen.value.size || back.value.size) {
            startedAt.value = draft.startedAt;
            restoredAt.value = draft.startedAt;
        }
    } catch {
        // Storage can be unavailable (private window, blocked site data): the count just isn't kept
    }
};

const saveDraft = () => {
    try {
        if (!seen.value.size && !back.value.size) {
            localStorage.removeItem(draftKey.value);
            return;
        }
        localStorage.setItem(
            draftKey.value,
            JSON.stringify({ seen: [...seen.value], back: [...back.value], startedAt: startedAt.value }),
        );
    } catch {
        // See loadDraft
    }
};

watch(draftKey, loadDraft, { immediate: true });
watch([seen, back], saveDraft, { deep: true });

const startOver = () => {
    seen.value = new Set();
    back.value = new Set();
    startedAt.value = Date.now();
    restoredAt.value = null;
    lastTouched.value = null;
};

const toggleSeen = (bird: LoftCheckBird) => {
    if (seen.value.has(bird.id)) seen.value.delete(bird.id);
    else seen.value.add(bird.id);
    lastTouched.value = bird.id;
};

const toggleBack = (bird: LoftCheckBird) => {
    if (back.value.has(bird.id)) back.value.delete(bird.id);
    else back.value.add(bird.id);
    lastTouched.value = bird.id;
};

const seenCount = computed(() => props.birds.filter((bird) => seen.value.has(bird.id)).length);
const notSeen = computed(() => props.birds.filter((bird) => !seen.value.has(bird.id)));
const progress = computed(() => (props.birds.length ? Math.round((seenCount.value / props.birds.length) * 100) : 0));

// --- Board -------------------------------------------------------------------------------------------------------

const readPreference = (key: string, fallback: string) => {
    try {
        return localStorage.getItem(key) ?? fallback;
    } catch {
        return fallback;
    }
};

const view = ref<'tiles' | 'list'>(readPreference('loft-check:view', 'tiles') === 'list' ? 'list' : 'tiles');
const onlyNotSeen = ref(false);

watch(view, (value) => {
    try {
        localStorage.setItem('loft-check:view', value);
    } catch {
        // A remembered view is only a convenience
    }
});

// The number on the tile: the bird's number in its ring series, or what else identifies it
const tileLabel = (bird: LoftCheckBird) => bird.number ?? bird.personal_number ?? bird.ring_number ?? bird.name ?? `#${bird.id}`;
const fullLabel = (bird: LoftCheckBird) => bird.ring_number || bird.personal_number || bird.name || `Pigeon #${bird.id}`;

const seriesLabel = (value: string) =>
    props.seriesOptions.find((option) => option.value === value)?.label ?? value;

// Birds on the board, grouped by ring series when more than one series is counted
const groups = computed(() => {
    const visible = onlyNotSeen.value ? notSeen.value : props.birds;
    const bySeries = new Map<string, LoftCheckBird[]>();

    for (const bird of visible) {
        if (!bySeries.has(bird.series)) bySeries.set(bird.series, []);
        bySeries.get(bird.series)!.push(bird);
    }

    return [...bySeries].map(([value, birds]) => ({ value, label: seriesLabel(value), birds }));
});

const showGroupHeadings = computed(() => new Set(props.birds.map((bird) => bird.series)).size > 1);

// --- Typing a ring number --------------------------------------------------------------------------------------

const entry = ref('');
const entryMessage = ref<{ text: string; tone: 'ok' | 'warn' } | null>(null);

interface EntryMatch {
    bird: LoftCheckBird;
    missing: boolean;
    // 3: the number in the series, 2: its last digits, 1: anywhere in the ring, personal number or name
    score: number;
}

const withoutLeadingZeros = (value: string) => value.replace(/^0+(?=\d)/, '');

const matches = computed<EntryMatch[]>(() => {
    const query = entry.value.trim().toUpperCase();
    if (!query) return [];

    const digits = /^\d+$/.test(query) ? withoutLeadingZeros(query) : '';
    const candidates = [
        ...props.birds.map((bird) => ({ bird, missing: false })),
        ...props.missingBirds.map((bird) => ({ bird, missing: true })),
    ];

    const scored = candidates
        .map(({ bird, missing }) => {
            const number = bird.number ? withoutLeadingZeros(bird.number) : '';
            let score = 0;

            if (digits && number === digits) score = 3;
            else if (digits && number.endsWith(digits)) score = 2;
            else if ([bird.ring_number, bird.personal_number, bird.name].some((text) => text?.toUpperCase().includes(query))) score = 1;

            return { bird, missing, score };
        })
        .filter((match) => match.score > 0);

    // Once the number itself matches, loose matches elsewhere in the ring are only noise
    const best = Math.max(0, ...scored.map((match) => match.score));
    return scored
        .filter((match) => best < 2 || match.score >= 2)
        .sort((a, b) => b.score - a.score)
        .slice(0, 6);
});

const applyMatch = ({ bird, missing }: EntryMatch) => {
    lastTouched.value = bird.id;

    if (missing) {
        back.value.add(bird.id);
        entryMessage.value = { text: `${fullLabel(bird)} was missing: marked as back home`, tone: 'ok' };
    } else if (seen.value.has(bird.id)) {
        entryMessage.value = { text: `${fullLabel(bird)} is already ticked`, tone: 'warn' };
    } else {
        seen.value.add(bird.id);
        entryMessage.value = { text: `${fullLabel(bird)} ticked`, tone: 'ok' };
    }

    entry.value = '';
};

const submitEntry = () => {
    if (!entry.value.trim()) return;

    const list = matches.value;
    if (!list.length) {
        entryMessage.value = { text: `No bird ${entry.value.trim()} in this group`, tone: 'warn' };
        return;
    }

    const exact = list.filter((match) => match.score === 3);
    const pick = exact.length === 1 ? exact[0] : list.length === 1 ? list[0] : null;

    if (pick) applyMatch(pick);
    else entryMessage.value = { text: 'Several birds match: tap the right one', tone: 'warn' };
};

watch(entry, (value) => {
    if (value) entryMessage.value = null;
});

// --- Finish ------------------------------------------------------------------------------------------------------

const finishOpen = ref(false);
// Birds not seen that will be marked missing; untick to leave a bird as it is
const markMissing = ref(new Set<number>());
const note = ref('');
const saving = ref(false);
const errors = ref<Record<string, string>>({});

const openFinish = () => {
    markMissing.value = new Set(notSeen.value.map((bird) => bird.id));
    errors.value = {};
    finishOpen.value = true;
};

const toggleMarkMissing = (id: number, checked: unknown) => {
    if (checked === true) markMissing.value.add(id);
    else markMissing.value.delete(id);
};

const missingToMark = computed(() => notSeen.value.filter((bird) => markMissing.value.has(bird.id)));
const backBirds = computed(() => props.missingBirds.filter((bird) => back.value.has(bird.id)));

const saveLabel = computed(() => {
    if (saving.value) return 'Saving…';
    const count = missingToMark.value.length;
    return count ? `Save and mark ${count} missing` : 'Save check';
});

const save = () => {
    router.post(
        storeRoute().url,
        {
            location: props.scope.location,
            series: props.scope.series,
            status: props.scope.status,
            present: props.birds.filter((bird) => seen.value.has(bird.id)).map((bird) => bird.id),
            missing: missingToMark.value.map((bird) => bird.id),
            returned: backBirds.value.map((bird) => bird.id),
            note: note.value.trim() || null,
        },
        {
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onSuccess: () => {
                try {
                    localStorage.removeItem(draftKey.value);
                } catch {
                    // See loadDraft
                }
            },
            onError: (formErrors) => (errors.value = formErrors),
        },
    );
};
</script>

<template>
    <Head title="Loft Check" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:p-6">
            <div>
                <h1 class="text-xl font-semibold text-foreground sm:text-2xl">Loft check</h1>
                <p class="mt-1 text-sm text-muted-foreground">Tick each bird you see. The ones left are your missing birds.</p>
            </div>

            <!-- What to count -->
            <Card class="gap-0 py-4">
                <CardContent class="grid gap-3 px-4 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label for="check-location" class="text-xs text-muted-foreground">Location</Label>
                        <Select :model-value="location" @update:model-value="setLocation">
                            <SelectTrigger id="check-location" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All locations</SelectItem>
                                <SelectItem v-for="loc in locations" :key="loc.id" :value="String(loc.id)">{{ loc.name }}</SelectItem>
                                <SelectItem value="none">No location</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="check-series" class="text-xs text-muted-foreground">Ring series</Label>
                        <Select :model-value="series" @update:model-value="setSeries">
                            <SelectTrigger id="check-series" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All series</SelectItem>
                                <SelectItem v-for="option in seriesChoices" :key="option.value" :value="option.value">
                                    {{ option.label }}{{ seriesRange(option) }} · {{ option.count }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="check-status" class="text-xs text-muted-foreground">Status</Label>
                        <MultiSelectFilter
                            id="check-status"
                            v-model="statuses"
                            :options="statusOptions"
                            :presets="statusPresets"
                            placeholder="All in the loft"
                            :searchable="false"
                        />
                    </div>
                </CardContent>
            </Card>

            <p v-if="lastCheck" class="text-sm text-muted-foreground">
                Last count of this group:
                <Link :href="showRoute({ loftCheck: lastCheck.id }).url" class="font-medium text-foreground underline-offset-4 hover:underline">
                    {{ formatCheckDate(lastCheck.created_at) }}
                </Link>
                · {{ lastCheck.present_count }} of {{ lastCheck.expected_count }} seen<template v-if="lastCheck.missing_count">, {{ lastCheck.missing_count }} marked missing</template>
            </p>

            <!-- Nothing to count -->
            <Card v-if="!birds.length && !missingBirds.length">
                <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                    <ClipboardCheck class="h-12 w-12 text-muted-foreground/60" />
                    <h3 class="mt-4 font-semibold">No birds in this group</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Pick another location, ring series or status.</p>
                </CardContent>
            </Card>

            <template v-else>
                <div
                    v-if="restoredAt"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-muted/40 px-3 py-2 text-sm"
                >
                    <span>Picked up your count from {{ formatCheckDate(new Date(restoredAt).toISOString()) }}.</span>
                    <Button variant="ghost" size="sm" @click="startOver">
                        <RotateCcw class="h-4 w-4" />
                        Start over
                    </Button>
                </div>

                <!-- Progress, always in view while ticking -->
                <div class="sticky top-14 z-20 -mx-4 border-y bg-background/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 md:top-0">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm">
                                <span class="text-lg font-semibold tabular-nums">{{ seenCount }}</span>
                                <span class="text-muted-foreground"> / {{ birds.length }} seen</span>
                                <span v-if="notSeen.length" class="ml-2 font-medium text-purple-700 tabular-nums dark:text-purple-300">
                                    {{ notSeen.length }} not seen
                                </span>
                                <span v-if="back.size" class="ml-2 font-medium text-green-700 tabular-nums dark:text-green-300">
                                    {{ back.size }} back
                                </span>
                            </p>
                        </div>
                        <Button :disabled="saving" @click="openFinish">
                            <Check class="h-4 w-4" />
                            Finish
                        </Button>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100" aria-label="Birds seen">
                        <div class="h-full rounded-full bg-green-600 transition-[width]" :style="{ width: `${progress}%` }" />
                    </div>
                </div>

                <!-- Type the number on the ring -->
                <form class="space-y-2" @submit.prevent="submitEntry">
                    <Label for="ring-entry" class="text-xs text-muted-foreground">Type the last digits of a ring and press Enter</Label>
                    <div class="flex gap-2">
                        <Input
                            id="ring-entry"
                            v-model="entry"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="e.g. 1017"
                            class="font-mono text-base"
                        />
                        <Button type="submit" variant="outline">Tick</Button>
                    </div>
                    <p
                        v-if="entryMessage"
                        role="status"
                        :class="['text-sm', entryMessage.tone === 'ok' ? 'text-green-700 dark:text-green-300' : 'text-amber-700 dark:text-amber-300']"
                    >
                        {{ entryMessage.text }}
                    </p>
                    <div v-if="entry.trim() && matches.length" class="overflow-hidden rounded-lg border bg-popover">
                        <button
                            v-for="match in matches"
                            :key="`${match.missing ? 'm' : 'b'}-${match.bird.id}`"
                            type="button"
                            class="w-full border-b px-4 py-3 text-left last:border-b-0 hover:bg-accent focus:bg-accent focus:outline-none"
                            @click="applyMatch(match)"
                        >
                            <PigeonSearchResult :pigeon="match.bird">
                                <template #aside>
                                    <Badge v-if="match.missing" variant="outline" class="border-purple-400 text-purple-700 dark:text-purple-300">Missing</Badge>
                                    <Badge v-else-if="seen.has(match.bird.id)" variant="outline" class="border-green-600 text-green-700 dark:text-green-300">Ticked</Badge>
                                </template>
                            </PigeonSearchResult>
                        </button>
                    </div>
                </form>

                <!-- Board controls -->
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="inline-flex rounded-lg border p-0.5" role="group" aria-label="Board view">
                        <Button size="sm" :variant="view === 'tiles' ? 'secondary' : 'ghost'" :aria-pressed="view === 'tiles'" @click="view = 'tiles'">
                            <LayoutGrid class="h-4 w-4" />
                            Numbers
                        </Button>
                        <Button size="sm" :variant="view === 'list' ? 'secondary' : 'ghost'" :aria-pressed="view === 'list'" @click="view = 'list'">
                            <List class="h-4 w-4" />
                            Details
                        </Button>
                    </div>
                    <Button size="sm" :variant="onlyNotSeen ? 'secondary' : 'outline'" :aria-pressed="onlyNotSeen" @click="onlyNotSeen = !onlyNotSeen">
                        <EyeOff class="h-4 w-4" />
                        Only not seen
                    </Button>
                </div>

                <p v-if="birds.length && onlyNotSeen && !notSeen.length" class="py-6 text-center text-sm text-muted-foreground">
                    Every bird is ticked.
                </p>

                <!-- The birds -->
                <section v-for="group in groups" :key="group.value" class="space-y-2">
                    <h2 v-if="showGroupHeadings" class="text-sm font-medium text-muted-foreground">
                        {{ group.label }} · {{ group.birds.length }}
                    </h2>

                    <div v-if="view === 'tiles'" class="grid grid-cols-[repeat(auto-fill,minmax(4.5rem,1fr))] gap-1.5">
                        <button
                            v-for="bird in group.birds"
                            :key="bird.id"
                            type="button"
                            :title="fullLabel(bird)"
                            :aria-label="fullLabel(bird)"
                            :aria-pressed="seen.has(bird.id)"
                            :class="[
                                'flex min-h-12 items-center justify-center rounded-lg border px-1 font-mono text-sm font-semibold tabular-nums transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                seen.has(bird.id)
                                    ? 'border-green-600 bg-green-600 text-white hover:bg-green-700'
                                    : 'bg-background text-foreground hover:bg-accent',
                                lastTouched === bird.id ? 'ring-2 ring-primary ring-offset-2 ring-offset-background' : '',
                            ]"
                            @click="toggleSeen(bird)"
                        >
                            <span class="truncate">{{ tileLabel(bird) }}</span>
                        </button>
                    </div>

                    <div v-else class="overflow-hidden rounded-lg border">
                        <button
                            v-for="bird in group.birds"
                            :key="bird.id"
                            type="button"
                            :aria-pressed="seen.has(bird.id)"
                            :class="[
                                'flex w-full items-center gap-3 border-b px-4 py-3 text-left last:border-b-0 focus:outline-none',
                                seen.has(bird.id) ? 'bg-green-50 hover:bg-green-100 dark:bg-green-500/10 dark:hover:bg-green-500/20' : 'hover:bg-accent focus:bg-accent',
                            ]"
                            @click="toggleSeen(bird)"
                        >
                            <span
                                :class="[
                                    'flex size-6 shrink-0 items-center justify-center rounded-md border',
                                    seen.has(bird.id) ? 'border-green-600 bg-green-600 text-white' : 'border-input',
                                ]"
                            >
                                <Check v-if="seen.has(bird.id)" class="size-4" />
                            </span>
                            <PigeonSearchResult :pigeon="bird" class="flex-1">
                                <template #aside>
                                    <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusStyle(bird.status).className]">
                                        {{ statusStyle(bird.status).label }}
                                    </span>
                                </template>
                                <div v-if="bird.location">{{ bird.location }}</div>
                            </PigeonSearchResult>
                        </button>
                    </div>
                </section>

                <!-- Missing birds of this group, in case one came back -->
                <section v-if="missingBirds.length" class="space-y-2 border-t pt-4">
                    <div>
                        <h2 class="text-sm font-medium">Missing in this group · {{ missingBirds.length }}</h2>
                        <p class="text-xs text-muted-foreground">Tap one that came back home.</p>
                    </div>
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(4.5rem,1fr))] gap-1.5">
                        <button
                            v-for="bird in missingBirds"
                            :key="bird.id"
                            type="button"
                            :title="`${fullLabel(bird)}${bird.status_since ? `, missing since ${formatDay(bird.status_since)}` : ''}`"
                            :aria-label="`${fullLabel(bird)}, missing. Came back`"
                            :aria-pressed="back.has(bird.id)"
                            :class="[
                                'flex min-h-12 flex-col items-center justify-center rounded-lg border px-1 font-mono text-sm font-semibold tabular-nums transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                back.has(bird.id)
                                    ? 'border-green-600 bg-green-600 text-white hover:bg-green-700'
                                    : 'border-dashed border-purple-400 text-purple-700 hover:bg-purple-50 dark:text-purple-300 dark:hover:bg-purple-500/10',
                                lastTouched === bird.id ? 'ring-2 ring-primary ring-offset-2 ring-offset-background' : '',
                            ]"
                            @click="toggleBack(bird)"
                        >
                            <span class="truncate">{{ tileLabel(bird) }}</span>
                            <span class="font-sans text-[10px] font-normal">
                                <template v-if="back.has(bird.id)">Back</template>
                                <template v-else-if="bird.status_since">since {{ formatShortDay(bird.status_since) }}</template>
                            </span>
                        </button>
                    </div>
                </section>
            </template>
        </div>

        <!-- Finish: review the birds not seen -->
        <Dialog v-model:open="finishOpen">
            <DialogContent class="flex max-h-[90vh] flex-col sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Finish loft check</DialogTitle>
                    <DialogDescription>
                        {{ label }} · {{ seenCount }} of {{ birds.length }} seen<template v-if="backBirds.length">, {{ backBirds.length }} back home</template>
                    </DialogDescription>
                </DialogHeader>

                <div class="-mx-6 flex-1 space-y-4 overflow-y-auto px-6">
                    <p v-if="!notSeen.length" class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 dark:bg-green-500/10 dark:text-green-300">
                        All {{ birds.length }} birds are home.
                    </p>

                    <div v-else class="space-y-2">
                        <p class="text-sm font-medium">Not seen · {{ notSeen.length }}</p>
                        <p class="text-xs text-muted-foreground">
                            Ticked birds are marked missing. Untick a bird you know is fine (e.g. at the vet) to leave it as it is.
                        </p>
                        <div class="overflow-hidden rounded-lg border">
                            <label
                                v-for="bird in notSeen"
                                :key="bird.id"
                                class="flex cursor-pointer items-start gap-3 border-b px-4 py-3 last:border-b-0 hover:bg-accent"
                            >
                                <Checkbox
                                    class="mt-0.5"
                                    :model-value="markMissing.has(bird.id)"
                                    :aria-label="`Mark ${fullLabel(bird)} missing`"
                                    @update:model-value="toggleMarkMissing(bird.id, $event)"
                                />
                                <PigeonSearchResult :pigeon="bird" class="flex-1">
                                    <template #aside>
                                        <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusStyle(bird.status).className]">
                                            {{ statusStyle(bird.status).label }}
                                        </span>
                                    </template>
                                </PigeonSearchResult>
                            </label>
                        </div>
                    </div>

                    <div v-if="backBirds.length" class="space-y-1">
                        <p class="text-sm font-medium">Back home · {{ backBirds.length }}</p>
                        <p class="text-xs text-muted-foreground">They get back the status they had before they went missing.</p>
                        <p class="font-mono text-sm">{{ backBirds.map(fullLabel).join(', ') }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="check-note">Note</Label>
                        <Textarea id="check-note" v-model="note" rows="2" placeholder="e.g. Training toss, Tarlac 40 km" />
                        <p v-if="errors.note" class="text-sm text-destructive">{{ errors.note }}</p>
                    </div>

                    <p v-for="(message, field) in errors" v-show="field !== 'note'" :key="field" class="text-sm text-destructive">
                        {{ message }}
                    </p>
                </div>

                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button variant="outline" class="w-full sm:w-auto" @click="finishOpen = false">
                        <Undo2 class="h-4 w-4" />
                        Keep counting
                    </Button>
                    <Button class="w-full sm:w-auto" :disabled="saving" @click="save">
                        <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" />
                        {{ saveLabel }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
