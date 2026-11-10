<script setup lang="ts">
import FamilyReport from '@/components/lineage/FamilyReport.vue';
import BloodShare from '@/components/pairing-analysis/BloodShare.vue';
import CarryBoth from '@/components/pairing-analysis/CarryBoth.vue';
import CoiSummary from '@/components/pairing-analysis/CoiSummary.vue';
import CommonAncestors from '@/components/pairing-analysis/CommonAncestors.vue';
import PedigreeDepth from '@/components/pairing-analysis/PedigreeDepth.vue';
import { Button } from '@/components/ui/button';
import { birdLabel } from '@/lib/lineage';
import type {
    BirdSummary,
    Family,
    PairingAnalysisReport,
} from '@/types/lineage';
import type { PedigreeReportLinks } from '@/types/pedigree';
import { Link } from '@inertiajs/vue3';
import { FlaskConical, GitFork } from 'lucide-vue-next';
import { computed } from 'vue';

// Below a pedigree's photos: the Pairing Analysis of the pair that bred the bird, then the bird's Lineage.
// On the owner's pedigree page and the shared pedigree page.
const props = defineProps<{
    // Null unless both parents are recorded
    analysis: PairingAnalysisReport | null;
    // Generations of ancestors the COI is worked out from
    generations: number;
    lineage: { bird: BirdSummary; family: Family };
    descendantGenerations: number;
    // The full reports in the app, for the owner
    links?: PedigreeReportLinks | null;
    // On a shared pedigree: no hints about adding birds, and Lineage only when there is family to show
    publicView?: boolean;
}>();

const hasRelatives = computed(() => {
    const family = props.lineage.family;
    return (
        family.descendants.length > 0 ||
        family.mates.length > 0 ||
        Object.values(family.siblings).some((birds) => birds.length > 0)
    );
});

// Visitors only see Lineage when the bird has relatives; the owner always does
const showLineage = computed(() => !!props.links || hasRelatives.value);
</script>

<template>
    <!-- The pair that bred the bird, analysed as on the Pairing Analysis page -->
    <section
        v-if="analysis"
        aria-labelledby="pairing-analysis-heading"
        class="space-y-4 pt-4"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h2 id="pairing-analysis-heading" class="text-xl font-semibold">
                    Pairing Analysis
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    The pair that bred this bird,
                    {{ birdLabel(analysis.sire) }} ×
                    {{ birdLabel(analysis.dam) }}: how related they are, what
                    this bird is line-bred on, and how their other youngsters
                    have done.
                </p>
            </div>
            <Button
                v-if="links?.pairing_analysis"
                as-child
                variant="outline"
                size="sm"
                class="shrink-0 self-start sm:self-auto"
            >
                <Link :href="links.pairing_analysis"
                    ><FlaskConical class="size-4" /> Open in Pairing
                    Analysis</Link
                >
            </Button>
        </div>

        <CoiSummary :analysis="analysis" :generations="generations" bred />
        <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
            <CommonAncestors :ancestors="analysis.common_ancestors" bred />
            <BloodShare :ancestors="analysis.influential_ancestors" bred />
        </div>
        <div class="grid gap-4 sm:gap-6 lg:grid-cols-3">
            <CarryBoth
                class="lg:col-span-2"
                :report="analysis.shared"
                :sire="analysis.sire"
                :dam="analysis.dam"
                bred
            />
            <PedigreeDepth
                class="self-start"
                :completeness="analysis.completeness"
                bred
            />
        </div>
    </section>

    <div
        v-else-if="links"
        class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
    >
        Record both parents of this bird to show the Pairing Analysis of the
        pair that bred it.
        <Link
            :href="links.edit"
            class="font-medium text-foreground underline underline-offset-2"
            >Edit bird</Link
        >
    </div>

    <!-- The bird's own family: offspring, brothers and sisters, mates -->
    <section
        v-if="showLineage"
        aria-labelledby="lineage-heading"
        class="space-y-4 pt-4"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h2 id="lineage-heading" class="text-xl font-semibold">
                    Lineage
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    This bird's offspring down to
                    {{ descendantGenerations }} generations, its brothers and
                    sisters, and its mates.
                </p>
            </div>
            <Button
                v-if="links"
                as-child
                variant="outline"
                size="sm"
                class="shrink-0 self-start sm:self-auto"
            >
                <Link :href="links.lineage"
                    ><GitFork class="size-4" /> Open in Lineage</Link
                >
            </Button>
        </div>
        <FamilyReport
            :bird="lineage.bird"
            :family="lineage.family"
            :public-view="publicView"
        />
    </section>
</template>
