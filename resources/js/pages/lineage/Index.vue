<script setup lang="ts">
import GoldenPairBadge from '@/components/GoldenPairBadge.vue';
import BirdRow from '@/components/lineage/BirdRow.vue';
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
import { birdLabel, percent } from '@/lib/lineage';
import { index as lineageIndex } from '@/routes/lineage';
import {
    pedigree as pedigreeRoute,
    show as showPigeon,
} from '@/routes/pigeons';
import { index as testPairingIndex } from '@/routes/test-pairing';
import { type BreadcrumbItem } from '@/types';
import type {
    BirdSummary,
    MiniBird,
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
import { computed, ref, watch } from 'vue';

interface DescendantRow {
    bird: BirdSummary;
    via: MiniBird[];
    other_parent: MiniBird | null;
    share: number;
}

interface Family {
    sire: BirdSummary | null;
    dam: BirdSummary | null;
    descendants: {
        generation: number;
        label: string;
        birds: DescendantRow[];
    }[];
    siblings: {
        full: BirdSummary[];
        sire_side: BirdSummary[];
        dam_side: BirdSummary[];
    };
    mates: {
        bird: BirdSummary | null;
        offspring: number;
        proven: number;
        // Youngsters with a winning merit; two or more make a Golden Pair
        winners: number;
        golden: boolean;
    }[];
}

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

const activeGeneration = ref(props.family?.descendants[0]?.generation ?? 1);
watch(
    () => props.family,
    (family) =>
        (activeGeneration.value = family?.descendants[0]?.generation ?? 1),
);

const activeDescendants = computed(
    () =>
        props.family?.descendants.find(
            (group) => group.generation === activeGeneration.value,
        )?.birds ?? [],
);
const descendantTotal = computed(
    () =>
        props.family?.descendants.reduce(
            (sum, group) => sum + group.birds.length,
            0,
        ) ?? 0,
);

const siblingGroups = computed(() =>
    props.family
        ? [
              {
                  key: 'full',
                  title: 'Full siblings',
                  hint: 'Same sire and dam',
                  birds: props.family.siblings.full,
              },
              {
                  key: 'sire',
                  title: 'Half siblings (same sire)',
                  hint: null,
                  birds: props.family.siblings.sire_side,
              },
              {
                  key: 'dam',
                  title: 'Half siblings (same dam)',
                  hint: null,
                  birds: props.family.siblings.dam_side,
              },
          ]
        : [],
);

const testPairingLink = (bird: BirdSummary, mate?: BirdSummary | null) => {
    const birds = [bird, mate].filter((b): b is BirdSummary => !!b?.id);
    const sire = birds.find((b) => b.gender === 'male');
    const dam = birds.find((b) => b.gender === 'female');
    return testPairingIndex({ query: { sire: sire?.id, dam: dam?.id } }).url;
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

const canTestPair = computed(
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
                                <Link :href="testPairingLink(bird)"
                                    ><FlaskConical class="size-4" /> Test pair
                                    this bird</Link
                                >
                            </Button>
                        </div>
                    </Card>

                    <div class="grid gap-4 sm:gap-6 lg:grid-cols-3">
                        <!-- Descendants -->
                        <Card class="lg:col-span-2">
                            <CardHeader>
                                <CardTitle>Offspring & descendants</CardTitle>
                                <CardDescription>
                                    {{ descendantTotal }}
                                    {{
                                        descendantTotal === 1 ? 'bird' : 'birds'
                                    }}
                                    across {{ family.descendants.length }}
                                    {{
                                        family.descendants.length === 1
                                            ? 'generation'
                                            : 'generations'
                                    }}. Proven birds are listed first.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    v-if="family.descendants.length"
                                    class="flex flex-wrap gap-1"
                                    role="tablist"
                                >
                                    <button
                                        v-for="group in family.descendants"
                                        :key="group.generation"
                                        type="button"
                                        role="tab"
                                        :aria-selected="
                                            activeGeneration ===
                                            group.generation
                                        "
                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors"
                                        :class="
                                            activeGeneration ===
                                            group.generation
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'hover:bg-accent'
                                        "
                                        @click="
                                            activeGeneration = group.generation
                                        "
                                    >
                                        {{ group.label }}
                                        <span class="text-xs opacity-80">{{
                                            group.birds.length
                                        }}</span>
                                    </button>
                                </div>

                                <div
                                    v-if="activeDescendants.length"
                                    class="divide-y rounded-lg border"
                                >
                                    <BirdRow
                                        v-for="row in activeDescendants"
                                        :key="row.bird.key"
                                        :bird="row.bird"
                                    >
                                        <p
                                            v-if="row.other_parent"
                                            class="text-sm text-muted-foreground"
                                        >
                                            ×
                                            {{
                                                bird.gender === 'female'
                                                    ? 'sire'
                                                    : bird.gender === 'male'
                                                      ? 'dam'
                                                      : 'mate'
                                            }}
                                            <Link
                                                v-if="row.other_parent.id"
                                                :href="
                                                    showPigeon({
                                                        pigeon: row.other_parent
                                                            .id,
                                                    }).url
                                                "
                                                class="text-primary hover:underline"
                                            >
                                                {{
                                                    birdLabel(row.other_parent)
                                                }}
                                            </Link>
                                            <span v-else>{{
                                                birdLabel(row.other_parent)
                                            }}</span>
                                        </p>
                                        <p
                                            v-else-if="row.via.length"
                                            class="text-sm text-muted-foreground"
                                        >
                                            via
                                            <template
                                                v-for="(via, index) in row.via"
                                                :key="via.key"
                                            >
                                                <span v-if="index"> → </span>
                                                <Link
                                                    v-if="via.id"
                                                    :href="
                                                        showPigeon({
                                                            pigeon: via.id,
                                                        }).url
                                                    "
                                                    class="text-primary hover:underline"
                                                    >{{ birdLabel(via) }}</Link
                                                >
                                                <span v-else>{{
                                                    birdLabel(via)
                                                }}</span>
                                            </template>
                                        </p>
                                        <template #aside>
                                            <span
                                                class="text-xs text-muted-foreground"
                                                :title="`Expected share of ${birdLabel(bird)}'s genes`"
                                            >
                                                {{ percent(row.share) }}
                                            </span>
                                        </template>
                                    </BirdRow>
                                </div>
                                <p
                                    v-else
                                    class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                                >
                                    No offspring recorded. Link birds to this
                                    one as their sire or dam to build its
                                    family.
                                </p>
                            </CardContent>
                        </Card>

                        <div class="flex flex-col gap-4 sm:gap-6">
                            <!-- Siblings -->
                            <Card>
                                <CardHeader>
                                    <CardTitle>Siblings</CardTitle>
                                    <CardDescription
                                        >Birds sharing a sire or dam, including
                                        bought-in parents matched by ring
                                        number.</CardDescription
                                    >
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div
                                        v-for="group in siblingGroups"
                                        :key="group.key"
                                        class="space-y-2"
                                    >
                                        <p class="text-sm font-medium">
                                            {{ group.title }}
                                            <span class="text-muted-foreground"
                                                >({{
                                                    group.birds.length
                                                }})</span
                                            >
                                        </p>
                                        <div
                                            v-if="group.birds.length"
                                            class="divide-y rounded-lg border"
                                        >
                                            <BirdRow
                                                v-for="sibling in group.birds"
                                                :key="sibling.key"
                                                :bird="sibling"
                                            />
                                        </div>
                                        <p
                                            v-else
                                            class="text-sm text-muted-foreground"
                                        >
                                            None recorded
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Mates -->
                            <Card>
                                <CardHeader>
                                    <CardTitle>Mates</CardTitle>
                                    <CardDescription
                                        >Birds it has produced offspring
                                        with.</CardDescription
                                    >
                                </CardHeader>
                                <CardContent>
                                    <div
                                        v-if="family.mates.length"
                                        class="divide-y rounded-lg border"
                                    >
                                        <div
                                            v-for="(
                                                mate, index
                                            ) in family.mates"
                                            :key="
                                                mate.bird?.key ??
                                                `unknown-${index}`
                                            "
                                            class="flex items-center justify-between gap-3 p-3"
                                        >
                                            <div class="min-w-0">
                                                <Link
                                                    v-if="mate.bird?.id"
                                                    :href="
                                                        showPigeon({
                                                            pigeon: mate.bird
                                                                .id,
                                                        }).url
                                                    "
                                                    class="block truncate font-medium text-primary hover:underline"
                                                >
                                                    {{ birdLabel(mate.bird) }}
                                                </Link>
                                                <span
                                                    v-else
                                                    class="block truncate font-medium"
                                                    >{{
                                                        mate.bird
                                                            ? birdLabel(
                                                                  mate.bird,
                                                              )
                                                            : 'Unknown mate'
                                                    }}</span
                                                >
                                                <p
                                                    class="text-sm text-muted-foreground"
                                                >
                                                    {{ mate.offspring }}
                                                    {{
                                                        mate.offspring === 1
                                                            ? 'youngster'
                                                            : 'youngsters'
                                                    }}<template
                                                        v-if="mate.proven"
                                                    >
                                                        ·
                                                        {{ mate.proven }}
                                                        proven</template
                                                    >
                                                </p>
                                                <GoldenPairBadge
                                                    v-if="mate.golden"
                                                    class="mt-1"
                                                    :winners="mate.winners"
                                                    :offspring="mate.offspring"
                                                />
                                            </div>
                                            <Button
                                                v-if="mate.bird?.id && bird.id"
                                                as-child
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    :href="
                                                        lineageIndex({
                                                            query: {
                                                                mode: 'pair',
                                                                a: bird.id,
                                                                b: mate.bird.id,
                                                            },
                                                        }).url
                                                    "
                                                    >Family</Link
                                                >
                                            </Button>
                                        </div>
                                    </div>
                                    <p
                                        v-else
                                        class="text-sm text-muted-foreground"
                                    >
                                        No offspring recorded
                                    </p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
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
                                v-if="canTestPair"
                                as-child
                                variant="outline"
                                size="sm"
                                class="shrink-0"
                            >
                                <Link :href="testPairingLink(a, b)"
                                    ><FlaskConical class="size-4" /> Test this
                                    pairing</Link
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
