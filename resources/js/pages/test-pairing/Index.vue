<script setup lang="ts">
import GoldenPairBadge from '@/components/GoldenPairBadge.vue';
import InputError from '@/components/InputError.vue';
import BirdRow from '@/components/lineage/BirdRow.vue';
import BreedingPlan from '@/components/lineage/BreedingPlan.vue';
import PigeonPicker from '@/components/lineage/PigeonPicker.vue';
import SharedDescendants from '@/components/lineage/SharedDescendants.vue';
import PedigreeChart from '@/components/pedigree/PedigreeChart.vue';
import PedigreeExportActions from '@/components/pedigree/PedigreeExportActions.vue';
import PedigreeViewer from '@/components/pedigree/PedigreeViewer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { COI_LEVEL_STYLES } from '@/lib/coi';
import { birdLabel, percent, positionWords } from '@/lib/lineage';
import { pedigreeFilename } from '@/lib/pedigree';
import { index as lineageIndex } from '@/routes/lineage';
import {
    create as createPairing,
    show as showPairing,
} from '@/routes/pairings';
import {
    destroy as destroyPlan,
    store as storePlan,
    update as updatePlan,
} from '@/routes/planned-pairings';
import { index as testPairingIndex } from '@/routes/test-pairing';
import { type BreadcrumbItem } from '@/types';
import type {
    Assessment,
    BirdSummary,
    GoldenPairInfo,
    PickerPigeon,
    PlannedPair,
    SharedReport,
} from '@/types/lineage';
import type { PedigreeMeta, PedigreeNode } from '@/types/pedigree';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useStorage } from '@vueuse/core';
import {
    AlertTriangle,
    ArrowLeftRight,
    BookmarkCheck,
    BookmarkPlus,
    ChevronDown,
    Heart,
    Info,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Analysis {
    coi: number;
    relationship: number;
    sire_coi: number;
    dam_coi: number;
    assessment: Assessment;
    common_ancestors: {
        bird: BirdSummary;
        contribution: number;
        lines: { sire: string; dam: string }[];
    }[];
    influential_ancestors: {
        bird: BirdSummary;
        share: number;
        positions: string[];
    }[];
    completeness: { generation: number; known: number; total: number }[];
    shared: SharedReport;
    golden_pair: GoldenPairInfo;
    pairings: {
        id: number;
        pair_name: string | null;
        status: string;
        started_at: string | null;
    }[];
}

const props = defineProps<{
    sire: BirdSummary | null;
    dam: BirdSummary | null;
    generations: number;
    analysis: Analysis | null;
    // The open pair's entry in the breeding plan, if saved
    plan: { id: number; notes: string | null } | null;
    plans: PlannedPair[];
    // The pedigree a youngster of the pair would have
    sample: { tree: PedigreeNode; meta: PedigreeMeta } | null;
}>();

const { success } = useToast();

const planForm = useForm({ notes: props.plan?.notes ?? '' });

watch(
    () => props.plan,
    (plan) => {
        planForm.defaults({ notes: plan?.notes ?? '' });
        planForm.reset();
        planForm.clearErrors();
    },
);

const savePlan = () => {
    if (!props.sire?.id || !props.dam?.id) return;
    const options = {
        preserveScroll: true,
        onSuccess: () =>
            success(
                props.plan ? 'Plan note saved' : 'Saved to your breeding plan',
            ),
    };
    if (props.plan) {
        planForm.patch(
            updatePlan({ plannedPairing: props.plan.id }).url,
            options,
        );
    } else {
        planForm
            .transform((data) => ({
                ...data,
                sire_id: props.sire!.id,
                dam_id: props.dam!.id,
            }))
            .post(storePlan().url, options);
    }
};

const removePlan = () => {
    if (!props.plan) return;
    router.delete(destroyPlan({ plannedPairing: props.plan.id }).url, {
        preserveScroll: true,
        onSuccess: () => success('Removed from your breeding plan'),
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Test Pairing', href: testPairingIndex().url },
];

const visit = (sire?: number | null, dam?: number | null) =>
    router.get(
        testPairingIndex().url,
        { sire: sire ?? undefined, dam: dam ?? undefined },
        { preserveScroll: true },
    );

const selectSire = (pigeon: PickerPigeon) => visit(pigeon.id, props.dam?.id);
const selectDam = (pigeon: PickerPigeon) => visit(props.sire?.id, pigeon.id);
const swap = () => visit(props.dam?.id, props.sire?.id);

const genderWarning = computed(() => {
    if (props.sire?.gender === 'female')
        return `${birdLabel(props.sire)} is recorded as a hen.`;
    if (props.dam?.gender === 'male')
        return `${birdLabel(props.dam)} is recorded as a cock.`;
    return null;
});

// COI meter: 0 to 30% so the 25% marker sits inside the track
const METER_MAX = 0.3;
const markers = [
    { value: 0.0625, label: '6.25%', title: 'First cousins' },
    { value: 0.125, label: '12.5%', title: 'Half siblings' },
    { value: 0.25, label: '25%', title: 'Full siblings' },
];

const levelStyles = COI_LEVEL_STYLES;

const style = computed(() =>
    props.analysis ? levelStyles[props.analysis.assessment.level] : null,
);
const meterWidth = computed(() =>
    props.analysis ? Math.min(props.analysis.coi, METER_MAX) / METER_MAX : 0,
);

const generationName = (generation: number) =>
    (
        ({
            1: 'Parents',
            2: 'Grandparents',
            3: 'Great-grandparents',
        }) as Record<number, string>
    )[generation] ?? `${generation - 2}× great-grandparents`;

// Deepest generation where every ancestor is known
const completeGenerations = computed(() => {
    let complete = 0;
    for (const row of props.analysis?.completeness ?? []) {
        if (row.known < row.total) break;
        complete = row.generation;
    }
    return complete;
});

// The sample pedigree is large; breeders who don't read it can fold it away (remembered on this device)
const showSample = useStorage('test-pairing.show-sample-pedigree', true);
const sampleChart = ref<InstanceType<typeof PedigreeChart> | null>(null);
const sampleFilename = computed(() =>
    pedigreeFilename(
        [props.sire?.ring_number, props.dam?.ring_number]
            .filter(Boolean)
            .join('-x-') || null,
        'sample',
    ),
);

const createPairingUrl = computed(
    () =>
        createPairing({
            query: {
                sire_id: props.sire?.id ?? undefined,
                dam_id: props.dam?.id ?? undefined,
            },
        }).url,
);
</script>

<template>
    <Head title="Test Pairing" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div>
                <h1 class="text-xl font-semibold text-foreground sm:text-2xl">
                    Test Pairing
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Check a pairing before you make it: how related the two
                    birds are, which ancestors the youngsters would be line-bred
                    on, and how birds that already carry both have performed.
                </p>
            </div>

            <Card class="gap-0 py-4">
                <CardContent
                    class="grid items-end gap-3 px-4 sm:grid-cols-[1fr_auto_1fr]"
                >
                    <div class="space-y-1.5">
                        <Label for="test-sire">Sire (cock)</Label>
                        <PigeonPicker
                            id="test-sire"
                            :selected="sire"
                            gender="male"
                            in-loft
                            placeholder="Search cocks..."
                            @select="selectSire"
                            @clear="visit(null, dam?.id)"
                        />
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="justify-self-center"
                        :disabled="!sire && !dam"
                        aria-label="Swap sire and dam"
                        @click="swap"
                    >
                        <ArrowLeftRight class="size-4" />
                    </Button>
                    <div class="space-y-1.5">
                        <Label for="test-dam">Dam (hen)</Label>
                        <PigeonPicker
                            id="test-dam"
                            :selected="dam"
                            gender="female"
                            in-loft
                            placeholder="Search hens..."
                            @select="selectDam"
                            @clear="visit(sire?.id, null)"
                        />
                    </div>
                </CardContent>
            </Card>

            <p
                v-if="genderWarning"
                class="flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300"
            >
                <AlertTriangle class="size-4 shrink-0" />
                {{ genderWarning }} The figures still show how related the two
                birds are.
            </p>

            <div
                v-if="!analysis"
                class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
            >
                Pick a sire and a dam to see the inbreeding coefficient of their
                youngsters, their common ancestors and the birds in your loft
                that already carry both.
            </div>

            <template v-else-if="sire && dam && style">
                <!-- Headline -->
                <Card>
                    <CardContent class="space-y-6">
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="space-y-1">
                                <p class="text-sm text-muted-foreground">
                                    Inbreeding coefficient of the youngsters
                                    (COI)
                                </p>
                                <p
                                    class="text-5xl font-semibold tracking-tight tabular-nums"
                                >
                                    {{ percent(analysis.coi) }}
                                </p>
                                <p
                                    class="flex items-center gap-1.5 font-medium"
                                    :class="style.text"
                                >
                                    <component
                                        :is="style.icon"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    {{ analysis.assessment.label }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ analysis.assessment.equivalent }}
                                </p>
                                <GoldenPairBadge
                                    v-if="analysis.golden_pair.golden"
                                    class="mt-1"
                                    :winners="analysis.golden_pair.winner_count"
                                    :offspring="analysis.golden_pair.offspring"
                                />
                            </div>
                            <dl
                                class="grid grid-cols-2 gap-x-8 gap-y-4 sm:grid-cols-4 lg:grid-cols-2"
                            >
                                <div>
                                    <dt class="text-sm text-muted-foreground">
                                        Relationship of the pair
                                    </dt>
                                    <dd
                                        class="text-2xl font-semibold tabular-nums"
                                    >
                                        {{ percent(analysis.relationship) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-muted-foreground">
                                        Complete generations
                                    </dt>
                                    <dd
                                        class="text-2xl font-semibold tabular-nums"
                                    >
                                        {{ completeGenerations }}
                                        <span
                                            class="text-base font-normal text-muted-foreground"
                                            >of {{ generations }}</span
                                        >
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-muted-foreground">
                                        Sire's own COI
                                    </dt>
                                    <dd
                                        class="text-2xl font-semibold tabular-nums"
                                    >
                                        {{ percent(analysis.sire_coi) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-muted-foreground">
                                        Dam's own COI
                                    </dt>
                                    <dd
                                        class="text-2xl font-semibold tabular-nums"
                                    >
                                        {{ percent(analysis.dam_coi) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Meter -->
                        <div>
                            <div
                                class="relative h-3 w-full rounded-full"
                                :class="style.track"
                                role="meter"
                                :aria-valuenow="
                                    Number((analysis.coi * 100).toFixed(2))
                                "
                                aria-valuemin="0"
                                :aria-valuemax="METER_MAX * 100"
                                :aria-label="`COI ${percent(analysis.coi)}, ${analysis.assessment.label}`"
                                :title="`COI ${percent(analysis.coi)}`"
                            >
                                <div
                                    class="h-full rounded-full transition-[width]"
                                    :class="style.fill"
                                    :style="{ width: `${meterWidth * 100}%` }"
                                />
                                <span
                                    v-for="marker in markers"
                                    :key="marker.value"
                                    class="absolute top-0 h-full w-0.5 bg-background"
                                    :style="{
                                        left: `${(marker.value / METER_MAX) * 100}%`,
                                    }"
                                    aria-hidden="true"
                                />
                            </div>
                            <div
                                class="relative mt-1 h-4 text-xs text-muted-foreground"
                                aria-hidden="true"
                            >
                                <span class="absolute left-0">0%</span>
                                <span
                                    v-for="marker in markers"
                                    :key="marker.value"
                                    class="absolute -translate-x-1/2 tabular-nums"
                                    :style="{
                                        left: `${(marker.value / METER_MAX) * 100}%`,
                                    }"
                                    :title="marker.title"
                                >
                                    {{ marker.label }}
                                </span>
                            </div>
                        </div>

                        <p
                            class="flex items-start gap-2 text-xs text-muted-foreground"
                        >
                            <Info class="mt-0.5 size-3.5 shrink-0" />
                            Calculated with Wright's method from the pedigrees
                            recorded in Bloodline Hub, up to
                            {{ generations }} generations. Unknown ancestors
                            count as unrelated, so the true figure can only be
                            the same or higher. Bought-in ancestors are matched
                            by ring number.
                        </p>
                    </CardContent>
                </Card>

                <!-- Sample pedigree: the same pairing, read as the youngster's pedigree -->
                <Card v-if="sample">
                    <CardHeader>
                        <div
                            class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div>
                                <CardTitle>Sample pedigree</CardTitle>
                                <CardDescription>
                                    The pedigree a youngster of
                                    {{ birdLabel(sire) }} ×
                                    {{ birdLabel(dam) }} would have, from the
                                    pedigrees recorded for both. Ancestors that
                                    appear more than once are outlined in gold.
                                </CardDescription>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <PedigreeExportActions
                                    v-if="showSample"
                                    :target="sampleChart?.paper"
                                    :filename="sampleFilename"
                                />
                                <Button
                                    variant="ghost"
                                    :aria-expanded="showSample"
                                    aria-controls="sample-pedigree"
                                    @click="showSample = !showSample"
                                >
                                    <ChevronDown
                                        class="size-4 transition-transform"
                                        :class="{ 'rotate-180': showSample }"
                                    />
                                    {{ showSample ? 'Hide' : 'Show' }}
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent v-if="showSample" id="sample-pedigree">
                        <PedigreeViewer>
                            <PedigreeChart
                                ref="sampleChart"
                                :tree="sample.tree"
                                :meta="sample.meta"
                                sample
                                mark-repeated
                                linkable
                            />
                        </PedigreeViewer>
                    </CardContent>
                </Card>

                <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
                    <!-- Common ancestors -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Common ancestors</CardTitle>
                            <CardDescription
                                >Birds found in both pedigrees, and how much
                                each adds to the COI.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="analysis.common_ancestors.length"
                                class="divide-y rounded-lg border"
                            >
                                <BirdRow
                                    v-for="row in analysis.common_ancestors"
                                    :key="row.bird.key"
                                    :bird="row.bird"
                                >
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="(line, index) in row.lines"
                                            :key="index"
                                            class="rounded border bg-muted/50 px-1.5 py-0.5 font-mono text-xs"
                                            :title="`Youngster's ${positionWords(line.sire)} and ${positionWords(line.dam)}`"
                                        >
                                            {{ line.sire }} × {{ line.dam }}
                                        </span>
                                    </div>
                                    <template #aside>
                                        <span
                                            class="text-sm font-medium tabular-nums"
                                            >+{{
                                                percent(row.contribution)
                                            }}</span
                                        >
                                    </template>
                                </BirdRow>
                            </div>
                            <p
                                v-else
                                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                            >
                                No common ancestors in the recorded pedigrees.
                                This pairing is an outcross.
                            </p>
                            <p
                                v-if="analysis.common_ancestors.length"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                Codes are positions in the youngster's pedigree:
                                S = sire, D = dam. "SDS" is the sire's dam's
                                sire.
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Influential ancestors -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Blood share</CardTitle>
                            <CardDescription>
                                How much of each ancestor the youngsters would
                                carry: a grandparent gives 25%, a
                                great-grandparent 12.5%. Birds that appear more
                                than once are listed first; they are what this
                                pairing is line-bred on.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="analysis.influential_ancestors.length"
                                class="divide-y rounded-lg border"
                            >
                                <BirdRow
                                    v-for="row in analysis.influential_ancestors"
                                    :key="row.bird.key"
                                    :bird="row.bird"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-1"
                                    >
                                        <Badge
                                            v-if="row.positions.length > 1"
                                            variant="secondary"
                                            >{{ row.positions.length }}× in
                                            pedigree</Badge
                                        >
                                        <span
                                            v-for="position in row.positions"
                                            :key="position"
                                            class="rounded border bg-muted/50 px-1.5 py-0.5 font-mono text-xs"
                                            :title="`Youngster's ${positionWords(position)}`"
                                        >
                                            {{ position }}
                                        </span>
                                    </div>
                                    <template #aside>
                                        <span
                                            class="text-sm font-medium tabular-nums"
                                            >{{ percent(row.share) }}</span
                                        >
                                    </template>
                                </BirdRow>
                            </div>
                            <p
                                v-else
                                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                            >
                                Not enough pedigree recorded yet. Add the
                                parents' ancestors to see this.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Proof: birds that already carry both -->
                <Card>
                    <CardHeader>
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <CardTitle
                                    >Birds that already carry both</CardTitle
                                >
                                <CardDescription>
                                    <template v-if="analysis.shared.total">
                                        {{ analysis.shared.total }}
                                        {{
                                            analysis.shared.total === 1
                                                ? 'bird descends'
                                                : 'birds descend'
                                        }}
                                        from both {{ birdLabel(sire) }} and
                                        {{ birdLabel(dam) }};
                                        {{ analysis.shared.proven }} proven by
                                        merits or top-10 results. This is the
                                        track record of the cross so far.
                                    </template>
                                    <template v-else
                                        >Nothing in your loft combines these two
                                        birds yet, so this would be a new
                                        cross.</template
                                    >
                                </CardDescription>
                            </div>
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="shrink-0"
                            >
                                <Link
                                    :href="
                                        lineageIndex({
                                            query: {
                                                mode: 'pair',
                                                a: sire.id ?? undefined,
                                                b: dam.id ?? undefined,
                                            },
                                        }).url
                                    "
                                >
                                    <Users class="size-4" /> Open in Lineage
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent v-if="analysis.shared.total">
                        <SharedDescendants
                            :report="analysis.shared"
                            :a-label="birdLabel(sire)"
                            :b-label="birdLabel(dam)"
                        />
                    </CardContent>
                </Card>

                <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
                    <!-- Pedigree depth -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Pedigree depth</CardTitle>
                            <CardDescription
                                >Known ancestors of the youngsters per
                                generation. More depth gives a more reliable
                                COI.</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-for="row in analysis.completeness"
                                :key="row.generation"
                                class="grid grid-cols-[9rem_1fr_3.5rem] items-center gap-3 text-sm"
                            >
                                <span class="truncate text-muted-foreground">{{
                                    generationName(row.generation)
                                }}</span>
                                <div
                                    class="h-2 rounded-full bg-sky-100 dark:bg-sky-950"
                                    aria-hidden="true"
                                >
                                    <div
                                        class="h-full rounded-full bg-sky-600 dark:bg-sky-400"
                                        :style="{
                                            width: `${(row.known / row.total) * 100}%`,
                                        }"
                                    />
                                </div>
                                <span class="text-right tabular-nums"
                                    >{{ row.known }}/{{ row.total }}</span
                                >
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Pair it -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Pair them</CardTitle>
                            <CardDescription>
                                <template v-if="analysis.pairings.length"
                                    >These birds have been paired
                                    before.</template
                                >
                                <template v-else
                                    >These birds have not been paired
                                    yet.</template
                                >
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-if="analysis.pairings.length"
                                class="divide-y rounded-lg border"
                            >
                                <Link
                                    v-for="pairing in analysis.pairings"
                                    :key="pairing.id"
                                    :href="
                                        showPairing({ pairing: pairing.id }).url
                                    "
                                    class="flex items-center justify-between gap-3 p-3 hover:bg-accent"
                                >
                                    <span class="font-medium">{{
                                        pairing.pair_name ||
                                        `Pairing #${pairing.id}`
                                    }}</span>
                                    <span class="text-sm text-muted-foreground">
                                        {{
                                            pairing.status === 'active'
                                                ? 'Active'
                                                : 'Ended'
                                        }}<template v-if="pairing.started_at">
                                            · {{ pairing.started_at }}</template
                                        >
                                    </span>
                                </Link>
                            </div>
                            <form
                                class="space-y-2 rounded-lg border p-3"
                                @submit.prevent="savePlan"
                            >
                                <Label
                                    for="plan-notes"
                                    class="flex items-center gap-2"
                                >
                                    <BookmarkCheck
                                        v-if="plan"
                                        class="size-4 text-gold"
                                    />
                                    <BookmarkPlus v-else class="size-4" />
                                    {{
                                        plan
                                            ? 'In your breeding plan'
                                            : 'Save to your breeding plan'
                                    }}
                                </Label>
                                <Textarea
                                    id="plan-notes"
                                    v-model="planForm.notes"
                                    rows="2"
                                    placeholder="Note, e.g. pair after the derby; hoping for a cock"
                                />
                                <InputError
                                    :message="
                                        planForm.errors.notes ||
                                        (
                                            planForm.errors as Record<
                                                string,
                                                string
                                            >
                                        ).dam_id
                                    "
                                />
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :variant="plan ? 'outline' : 'default'"
                                        :disabled="
                                            planForm.processing ||
                                            (!!plan && !planForm.isDirty)
                                        "
                                    >
                                        {{
                                            plan ? 'Save note' : 'Save to plan'
                                        }}
                                    </Button>
                                    <Button
                                        v-if="plan"
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        class="text-destructive hover:text-destructive"
                                        @click="removePlan"
                                    >
                                        Remove from plan
                                    </Button>
                                </div>
                            </form>
                            <Button as-child>
                                <Link :href="createPairingUrl"
                                    ><Heart class="size-4" /> Create this
                                    pairing</Link
                                >
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <BreedingPlan
                :plans="plans"
                :current-sire="sire?.id ?? null"
                :current-dam="dam?.id ?? null"
            />
        </div>
    </AppLayout>
</template>
