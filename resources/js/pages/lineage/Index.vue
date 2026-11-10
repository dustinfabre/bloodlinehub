<script setup lang="ts">
import BirdRow from '@/components/lineage/BirdRow.vue';
import FamilyReport from '@/components/lineage/FamilyReport.vue';
import PigeonPicker from '@/components/lineage/PigeonPicker.vue';
import SharedDescendants from '@/components/lineage/SharedDescendants.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { birdLabel } from '@/lib/lineage';
import { index as lineageIndex } from '@/routes/lineage';
import { index as pairingAnalysisIndex } from '@/routes/pairing-analysis';
import { pedigree as pedigreeRoute } from '@/routes/pigeons';
import { type BreadcrumbItem } from '@/types';
import type {
    BirdSummary,
    Family,
    PickerPigeon,
    SharedReport,
} from '@/types/lineage';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    FlaskConical,
    GitFork,
    Network,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    mode: 'bird' | 'pair';
    generations: number;
    bird: BirdSummary | null;
    family: Family | null;
    a: BirdSummary | null;
    b: BirdSummary | null;
    shared: SharedReport | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Lineage', href: lineageIndex().url },
];

const visit = (params: Record<string, string | number | undefined>) =>
    router.get(lineageIndex().url, params, { preserveScroll: true });

// One bird
const selectBird = (pigeon: PickerPigeon) => visit({ pigeon: pigeon.id });

const pairingAnalysisLink = (bird: BirdSummary, mate?: BirdSummary | null) => {
    const birds = [bird, mate].filter((b): b is BirdSummary => !!b?.id);
    const sire = birds.find((b) => b.gender === 'male');
    const dam = birds.find((b) => b.gender === 'female');
    return pairingAnalysisIndex({ query: { sire: sire?.id, dam: dam?.id } })
        .url;
};

// Two birds
const selectA = (pigeon: PickerPigeon) =>
    visit({ mode: 'pair', a: pigeon.id, b: props.b?.id ?? undefined });
const selectB = (pigeon: PickerPigeon) =>
    visit({ mode: 'pair', a: props.a?.id ?? undefined, b: pigeon.id });
const swap = () =>
    visit({
        mode: 'pair',
        a: props.b?.id ?? undefined,
        b: props.a?.id ?? undefined,
    });

const canAnalyzePair = computed(
    () =>
        props.a &&
        props.b &&
        new Set([props.a.gender, props.b.gender]).size === 2 &&
        [props.a.gender, props.b.gender].every(
            (g) => g === 'male' || g === 'female',
        ),
);
</script>

<template>
    <Head title="Lineage" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="text-xl font-semibold text-foreground sm:text-2xl"
                    >
                        Lineage
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Find a bird's children, grandchildren and siblings, or
                        every bird bred from two birds.
                    </p>
                </div>
                <div
                    class="inline-flex self-start rounded-lg border p-1 sm:self-auto"
                    role="tablist"
                >
                    <Link
                        :href="lineageIndex().url"
                        role="tab"
                        :aria-selected="mode === 'bird'"
                        class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            mode === 'bird'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        <GitFork class="size-4" />
                        One bird
                    </Link>
                    <Link
                        :href="
                            lineageIndex({
                                query: {
                                    mode: 'pair',
                                    a: bird?.id ?? undefined,
                                },
                            }).url
                        "
                        role="tab"
                        :aria-selected="mode === 'pair'"
                        class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            mode === 'pair'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        <Users class="size-4" />
                        Two birds
                    </Link>
                </div>
            </div>

            <!-- ONE BIRD -->
            <template v-if="mode === 'bird'">
                <Card class="gap-0 py-4">
                    <CardContent class="space-y-1.5 px-4">
                        <Label for="lineage-bird">Bird</Label>
                        <PigeonPicker
                            id="lineage-bird"
                            :selected="bird"
                            placeholder="Search a bird by ring number or name..."
                            @select="selectBird"
                            @clear="visit({})"
                        />
                    </CardContent>
                </Card>

                <div
                    v-if="!bird"
                    class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                >
                    Pick a bird to see its offspring down to
                    {{ generations }} generations, its siblings and its mates.
                </div>

                <template v-else-if="family">
                    <Card class="gap-0 py-0">
                        <BirdRow :bird="bird">
                            <p class="text-sm text-muted-foreground">
                                <template
                                    v-for="(parent, index) in [
                                        family.sire,
                                        family.dam,
                                    ]"
                                    :key="index"
                                >
                                    <span v-if="index"> · </span>
                                    {{ index ? 'Dam' : 'Sire' }}:
                                    <Link
                                        v-if="parent?.id"
                                        :href="
                                            lineageIndex({
                                                query: { pigeon: parent.id },
                                            }).url
                                        "
                                        class="text-primary hover:underline"
                                    >
                                        {{ birdLabel(parent) }}
                                    </Link>
                                    <span v-else>{{
                                        parent ? birdLabel(parent) : 'unknown'
                                    }}</span>
                                </template>
                            </p>
                        </BirdRow>
                        <div class="flex flex-wrap gap-2 border-t p-3">
                            <Button
                                v-if="bird.id"
                                as-child
                                variant="outline"
                                size="sm"
                            >
                                <Link
                                    :href="
                                        pedigreeRoute({ pigeon: bird.id }).url
                                    "
                                    ><Network class="size-4" /> Pedigree</Link
                                >
                            </Button>
                            <Button
                                v-if="
                                    bird.gender === 'male' ||
                                    bird.gender === 'female'
                                "
                                as-child
                                variant="outline"
                                size="sm"
                            >
                                <Link :href="pairingAnalysisLink(bird)"
                                    ><FlaskConical class="size-4" /> Pairing
                                    Analysis</Link
                                >
                            </Button>
                        </div>
                    </Card>

                    <FamilyReport :bird="bird" :family="family" />
                </template>
            </template>

            <!-- TWO BIRDS -->
            <template v-else>
                <Card class="gap-0 py-4">
                    <CardContent
                        class="grid items-end gap-3 px-4 sm:grid-cols-[1fr_auto_1fr]"
                    >
                        <div class="space-y-1.5">
                            <Label for="lineage-a">First bird</Label>
                            <PigeonPicker
                                id="lineage-a"
                                :selected="a"
                                @select="selectA"
                                @clear="
                                    visit({
                                        mode: 'pair',
                                        b: b?.id ?? undefined,
                                    })
                                "
                            />
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="justify-self-center"
                            :disabled="!a && !b"
                            aria-label="Swap birds"
                            @click="swap"
                        >
                            <ArrowLeftRight class="size-4" />
                        </Button>
                        <div class="space-y-1.5">
                            <Label for="lineage-b">Second bird</Label>
                            <PigeonPicker
                                id="lineage-b"
                                :selected="b"
                                @select="selectB"
                                @clear="
                                    visit({
                                        mode: 'pair',
                                        a: a?.id ?? undefined,
                                    })
                                "
                            />
                        </div>
                    </CardContent>
                </Card>

                <div
                    v-if="!a || !b"
                    class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                >
                    Pick two birds to list every bird that descends from both:
                    the offspring of a pair, their grandchildren and greats, or
                    birds where two separate lines were crossed.
                </div>
                <div
                    v-else-if="a.key === b.key"
                    class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                >
                    Pick two different birds.
                </div>

                <Card v-else-if="shared">
                    <CardHeader>
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <CardTitle
                                    >Bred from {{ birdLabel(a) }} and
                                    {{ birdLabel(b) }}</CardTitle
                                >
                                <CardDescription>
                                    {{ shared.total }}
                                    {{
                                        shared.total === 1
                                            ? 'bird carries'
                                            : 'birds carry'
                                    }}
                                    both, {{ shared.direct }} of them direct
                                    offspring and {{ shared.proven }} proven.
                                    Percentages show the expected share of each
                                    bird's genes.
                                </CardDescription>
                            </div>
                            <Button
                                v-if="canAnalyzePair"
                                as-child
                                variant="outline"
                                size="sm"
                                class="shrink-0"
                            >
                                <Link :href="pairingAnalysisLink(a, b)"
                                    ><FlaskConical class="size-4" /> Analyze
                                    this pairing</Link
                                >
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <SharedDescendants
                            :report="shared"
                            :a-label="birdLabel(a)"
                            :b-label="birdLabel(b)"
                            empty-text="No bird in your loft descends from both of these birds yet."
                        />
                    </CardContent>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
