<script setup lang="ts">
import GoldenPairBadge from '@/components/GoldenPairBadge.vue';
import { Card, CardContent } from '@/components/ui/card';
import { COI_LEVEL_STYLES } from '@/lib/coi';
import { percent } from '@/lib/lineage';
import type { PairingAnalysisReport } from '@/types/lineage';
import { Info } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    analysis: PairingAnalysisReport;
    // Generations of ancestors the figures are worked out from
    generations: number;
    // The pair has bred the bird this is shown for (its pedigree), rather than a pairing being planned
    bred?: boolean;
}>();

// COI meter: 0 to 30% so the 25% marker sits inside the track
const METER_MAX = 0.3;
const markers = [
    { value: 0.0625, label: '6.25%', title: 'First cousins' },
    { value: 0.125, label: '12.5%', title: 'Half siblings' },
    { value: 0.25, label: '25%', title: 'Full siblings' },
];

const style = computed(() => COI_LEVEL_STYLES[props.analysis.assessment.level]);
const meterWidth = computed(
    () => Math.min(props.analysis.coi, METER_MAX) / METER_MAX,
);

// Deepest generation where every ancestor is known
const completeGenerations = computed(() => {
    let complete = 0;
    for (const row of props.analysis.completeness) {
        if (row.known < row.total) break;
        complete = row.generation;
    }
    return complete;
});
</script>

<template>
    <Card>
        <CardContent class="space-y-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="space-y-1">
                    <p class="text-sm text-muted-foreground">
                        {{
                            bred
                                ? 'Inbreeding coefficient of this bird (COI)'
                                : 'Inbreeding coefficient of the youngsters (COI)'
                        }}
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
                        <dd class="text-2xl font-semibold tabular-nums">
                            {{ percent(analysis.relationship) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">
                            Complete generations
                        </dt>
                        <dd class="text-2xl font-semibold tabular-nums">
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
                        <dd class="text-2xl font-semibold tabular-nums">
                            {{ percent(analysis.sire_coi) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">
                            Dam's own COI
                        </dt>
                        <dd class="text-2xl font-semibold tabular-nums">
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
                    :aria-valuenow="Number((analysis.coi * 100).toFixed(2))"
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

            <p class="flex items-start gap-2 text-xs text-muted-foreground">
                <Info class="mt-0.5 size-3.5 shrink-0" />
                Calculated with Wright's method from the pedigrees recorded in
                Bloodline Hub, up to {{ generations }} generations. Unknown
                ancestors count as unrelated, so the true figure can only be the
                same or higher. Bought-in ancestors are matched by ring number.
            </p>
        </CardContent>
    </Card>
</template>
