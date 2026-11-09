<script setup lang="ts">
import GoldenPairBadge from '@/components/GoldenPairBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useToast } from '@/composables/useToast';
import { COI_LEVEL_STYLES } from '@/lib/coi';
import { birdLabel, percent, statusStyles } from '@/lib/lineage';
import { create as createPairing } from '@/routes/pairings';
import { destroy as destroyPlan } from '@/routes/planned-pairings';
import { index as testPairingIndex } from '@/routes/test-pairing';
import type { BirdSummary, PlannedPair } from '@/types/lineage';
import { Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ClipboardList,
    FlaskConical,
    Heart,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    plans: PlannedPair[];
    // The pair open on the page, highlighted in the list
    currentSire: number | null;
    currentDam: number | null;
}>();

const { success } = useToast();
const removing = ref<number | null>(null);

// Birds that can't breed this season
const UNAVAILABLE = ['deceased', 'missing'];

const warnings = (plan: PlannedPair) =>
    [
        ...([plan.sire, plan.dam] as BirdSummary[])
            .filter((bird) => bird.status && UNAVAILABLE.includes(bird.status))
            .map(
                (bird) =>
                    `${birdLabel(bird)} is ${statusStyles[bird.status!]?.label.toLowerCase() ?? bird.status}`,
            ),
        plan.sire_plans > 1
            ? `${birdLabel(plan.sire)} is in ${plan.sire_plans} plans`
            : null,
        plan.dam_plans > 1
            ? `${birdLabel(plan.dam)} is in ${plan.dam_plans} plans`
            : null,
    ].filter((warning): warning is string => warning !== null);

const isCurrent = (plan: PlannedPair) =>
    plan.sire.id === props.currentSire && plan.dam.id === props.currentDam;

const remove = (plan: PlannedPair) => {
    router.delete(destroyPlan({ plannedPairing: plan.id }).url, {
        preserveScroll: true,
        onStart: () => (removing.value = plan.id),
        onFinish: () => (removing.value = null),
        onSuccess: () => success('Removed from your breeding plan'),
    });
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <ClipboardList class="size-5" />
                Breeding plan
            </CardTitle>
            <CardDescription>
                Pairs you saved while testing, to decide on next season. COI and
                Golden Pair status update as your loft changes.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <p
                v-if="!plans.length"
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No pairs saved yet. Test a pair above and save it to your plan.
            </p>

            <ul v-else class="divide-y rounded-lg border">
                <li
                    v-for="plan in plans"
                    :key="plan.id"
                    class="@container p-3"
                    :class="{ 'bg-accent/60': isCurrent(plan) }"
                >
                    <div
                        class="flex flex-col gap-3 @xl:flex-row @xl:items-center @xl:justify-between"
                    >
                        <div class="min-w-0 space-y-1">
                            <div
                                class="flex flex-wrap items-center gap-x-2 gap-y-1"
                            >
                                <span class="font-medium">
                                    {{ birdLabel(plan.sire) }}
                                    <span class="text-muted-foreground">×</span>
                                    {{ birdLabel(plan.dam) }}
                                </span>
                                <GoldenPairBadge
                                    v-if="plan.golden"
                                    :winners="plan.winner_count"
                                />
                            </div>
                            <p
                                class="flex items-center gap-1.5 text-sm font-medium"
                                :class="
                                    COI_LEVEL_STYLES[plan.assessment.level].text
                                "
                            >
                                <component
                                    :is="
                                        COI_LEVEL_STYLES[plan.assessment.level]
                                            .icon
                                    "
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                COI {{ percent(plan.coi) }} ·
                                {{ plan.assessment.label }}
                            </p>
                            <p
                                v-if="plan.notes"
                                class="text-sm whitespace-pre-line text-muted-foreground"
                            >
                                {{ plan.notes }}
                            </p>
                            <p
                                v-for="warning in warnings(plan)"
                                :key="warning"
                                class="flex items-center gap-1.5 text-sm text-amber-700 dark:text-amber-300"
                            >
                                <AlertTriangle class="size-3.5 shrink-0" />
                                {{ warning }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            <Button
                                v-if="!isCurrent(plan)"
                                size="sm"
                                variant="outline"
                                as-child
                            >
                                <Link
                                    :href="
                                        testPairingIndex({
                                            query: {
                                                sire: plan.sire.id ?? undefined,
                                                dam: plan.dam.id ?? undefined,
                                            },
                                        }).url
                                    "
                                    preserve-scroll
                                >
                                    <FlaskConical class="size-4" />
                                    Open
                                </Link>
                            </Button>
                            <Button size="sm" variant="outline" as-child>
                                <Link
                                    :href="
                                        createPairing({
                                            query: {
                                                sire_id:
                                                    plan.sire.id ?? undefined,
                                                dam_id:
                                                    plan.dam.id ?? undefined,
                                            },
                                        }).url
                                    "
                                >
                                    <Heart class="size-4" />
                                    Pair
                                </Link>
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8 text-muted-foreground hover:text-destructive"
                                :disabled="removing === plan.id"
                                :aria-label="`Remove ${birdLabel(plan.sire)} × ${birdLabel(plan.dam)} from the plan`"
                                @click="remove(plan)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
