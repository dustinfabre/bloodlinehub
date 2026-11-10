<script setup lang="ts">
import GoldenPairBadge from '@/components/GoldenPairBadge.vue';
import BirdRow from '@/components/lineage/BirdRow.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { birdLabel, percent } from '@/lib/lineage';
import { index as lineageIndex } from '@/routes/lineage';
import { show as showPigeon } from '@/routes/pigeons';
import type { BirdSummary, Family } from '@/types/lineage';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

// A bird's descendants, siblings and mates (Lineage). Birds link to their pages only when ids are sent (owner view).
const props = defineProps<{
    bird: BirdSummary;
    family: Family;
    // On a shared pedigree: no hints about how to add birds
    publicView?: boolean;
}>();

const activeGeneration = ref(props.family.descendants[0]?.generation ?? 1);
watch(
    () => props.family,
    (family) =>
        (activeGeneration.value = family.descendants[0]?.generation ?? 1),
);

const activeDescendants = computed(
    () =>
        props.family.descendants.find(
            (group) => group.generation === activeGeneration.value,
        )?.birds ?? [],
);
const descendantTotal = computed(() =>
    props.family.descendants.reduce(
        (sum, group) => sum + group.birds.length,
        0,
    ),
);

const siblingGroups = computed(() => [
    {
        key: 'full',
        title: 'Full siblings',
        birds: props.family.siblings.full,
    },
    {
        key: 'sire',
        title: 'Half siblings (same sire)',
        birds: props.family.siblings.sire_side,
    },
    {
        key: 'dam',
        title: 'Half siblings (same dam)',
        birds: props.family.siblings.dam_side,
    },
]);

const otherParentWord = computed(() =>
    props.bird.gender === 'female'
        ? 'sire'
        : props.bird.gender === 'male'
          ? 'dam'
          : 'mate',
);
</script>

<template>
    <div class="grid gap-4 sm:gap-6 lg:grid-cols-3">
        <!-- Descendants -->
        <Card class="lg:col-span-2">
            <CardHeader>
                <CardTitle>Offspring & descendants</CardTitle>
                <CardDescription>
                    {{ descendantTotal }}
                    {{ descendantTotal === 1 ? 'bird' : 'birds' }}
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
                        :aria-selected="activeGeneration === group.generation"
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors"
                        :class="
                            activeGeneration === group.generation
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:bg-accent'
                        "
                        @click="activeGeneration = group.generation"
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
                            × {{ otherParentWord }}
                            <Link
                                v-if="row.other_parent.id"
                                :href="
                                    showPigeon({
                                        pigeon: row.other_parent.id,
                                    }).url
                                "
                                class="text-primary hover:underline"
                            >
                                {{ birdLabel(row.other_parent) }}
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
                                    :href="showPigeon({ pigeon: via.id }).url"
                                    class="text-primary hover:underline"
                                    >{{ birdLabel(via) }}</Link
                                >
                                <span v-else>{{ birdLabel(via) }}</span>
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
                    No offspring recorded.<template v-if="!publicView">
                        Link birds to this one as their sire or dam to build its
                        family.</template
                    >
                </p>
            </CardContent>
        </Card>

        <div class="flex flex-col gap-4 sm:gap-6">
            <!-- Siblings -->
            <Card>
                <CardHeader>
                    <CardTitle>Siblings</CardTitle>
                    <CardDescription
                        >Birds sharing a sire or dam, including bought-in
                        parents matched by ring number.</CardDescription
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
                                >({{ group.birds.length }})</span
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
                        <p v-else class="text-sm text-muted-foreground">
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
                        >Birds it has produced offspring with.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div
                        v-if="family.mates.length"
                        class="divide-y rounded-lg border"
                    >
                        <div
                            v-for="(mate, index) in family.mates"
                            :key="mate.bird?.key ?? `unknown-${index}`"
                            class="flex items-center justify-between gap-3 p-3"
                        >
                            <div class="min-w-0">
                                <Link
                                    v-if="mate.bird?.id"
                                    :href="
                                        showPigeon({ pigeon: mate.bird.id }).url
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
                                            ? birdLabel(mate.bird)
                                            : 'Unknown mate'
                                    }}</span
                                >
                                <p class="text-sm text-muted-foreground">
                                    {{ mate.offspring }}
                                    {{
                                        mate.offspring === 1
                                            ? 'youngster'
                                            : 'youngsters'
                                    }}<template v-if="mate.proven">
                                        · {{ mate.proven }} proven</template
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
                    <p v-else class="text-sm text-muted-foreground">
                        No offspring recorded
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
