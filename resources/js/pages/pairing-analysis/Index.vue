<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BreedingPlan from '@/components/lineage/BreedingPlan.vue';
import PigeonPicker from '@/components/lineage/PigeonPicker.vue';
import BloodShare from '@/components/pairing-analysis/BloodShare.vue';
import CarryBoth from '@/components/pairing-analysis/CarryBoth.vue';
import CoiSummary from '@/components/pairing-analysis/CoiSummary.vue';
import CommonAncestors from '@/components/pairing-analysis/CommonAncestors.vue';
import PedigreeDepth from '@/components/pairing-analysis/PedigreeDepth.vue';
import PedigreeChart from '@/components/pedigree/PedigreeChart.vue';
import PedigreeExportActions from '@/components/pedigree/PedigreeExportActions.vue';
import PedigreeViewer from '@/components/pedigree/PedigreeViewer.vue';
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
import { birdLabel } from '@/lib/lineage';
import { pedigreeFilename } from '@/lib/pedigree';
import { index as lineageIndex } from '@/routes/lineage';
import { index as pairingAnalysisIndex } from '@/routes/pairing-analysis';
import {
    create as createPairing,
    show as showPairing,
} from '@/routes/pairings';
import {
    destroy as destroyPlan,
    store as storePlan,
    update as updatePlan,
} from '@/routes/planned-pairings';
import { type BreadcrumbItem } from '@/types';
import type {
    BirdSummary,
    PairingAnalysisReport,
    PickerPigeon,
    PlannedPair,
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
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// The pair's analysis, with their pairings so far (owner page only)
type Analysis = PairingAnalysisReport & {
    pairings: {
        id: number;
        pair_name: string | null;
        status: string;
        started_at: string | null;
    }[];
};

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
    { title: 'Pairing Analysis', href: pairingAnalysisIndex().url },
];

const visit = (sire?: number | null, dam?: number | null) =>
    router.get(
        pairingAnalysisIndex().url,
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

// The sample pedigree is large; breeders who don't read it can fold it away (remembered on this device under
// its key from when this page was Test Pairing)
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
    <Head title="Pairing Analysis" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div>
                <h1 class="text-xl font-semibold text-foreground sm:text-2xl">
                    Pairing Analysis
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
                        <Label for="analysis-sire">Sire (cock)</Label>
                        <PigeonPicker
                            id="analysis-sire"
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
                        <Label for="analysis-dam">Dam (hen)</Label>
                        <PigeonPicker
                            id="analysis-dam"
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

            <template v-else-if="sire && dam">
                <CoiSummary :analysis="analysis" :generations="generations" />

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
                    <CommonAncestors :ancestors="analysis.common_ancestors" />
                    <BloodShare :ancestors="analysis.influential_ancestors" />
                </div>

                <!-- Proof: birds that already carry both -->
                <CarryBoth :report="analysis.shared" :sire="sire" :dam="dam">
                    <template #actions>
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
                    </template>
                </CarryBoth>

                <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
                    <PedigreeDepth :completeness="analysis.completeness" />

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
