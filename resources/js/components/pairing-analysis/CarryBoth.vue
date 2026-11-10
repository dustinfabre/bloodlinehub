<script setup lang="ts">
import SharedDescendants from '@/components/lineage/SharedDescendants.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { birdLabel } from '@/lib/lineage';
import type { BirdSummary, SharedReport } from '@/types/lineage';

// The proof of a cross: birds that already descend from both the sire and the dam
defineProps<{
    report: SharedReport;
    sire: BirdSummary;
    dam: BirdSummary;
    // The pair has bred the bird this is shown for (its pedigree); the report leaves that bird out
    bred?: boolean;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <CardTitle>{{
                        bred
                            ? 'Other birds from this pair'
                            : 'Birds that already carry both'
                    }}</CardTitle>
                    <CardDescription>
                        <template v-if="report.total">
                            {{ report.total }}
                            {{ bred ? 'other' : '' }}
                            {{
                                report.total === 1
                                    ? 'bird descends'
                                    : 'birds descend'
                            }}
                            from both {{ birdLabel(sire) }} and
                            {{ birdLabel(dam) }}; {{ report.proven }} proven by
                            merits or top-10 results. This is the track record
                            of the cross so far.
                        </template>
                        <template v-else-if="bred"
                            >No other bird in the loft descends from both of
                            this bird's parents yet.</template
                        >
                        <template v-else
                            >Nothing in your loft combines these two birds yet,
                            so this would be a new cross.</template
                        >
                    </CardDescription>
                </div>
                <slot name="actions" />
            </div>
        </CardHeader>
        <CardContent v-if="report.total">
            <SharedDescendants
                :report="report"
                :a-label="birdLabel(sire)"
                :b-label="birdLabel(dam)"
            />
        </CardContent>
    </Card>
</template>
