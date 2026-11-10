<script setup lang="ts">
import BirdRow from '@/components/lineage/BirdRow.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { percent, positionWords } from '@/lib/lineage';
import type { PairingAnalysisReport } from '@/types/lineage';

defineProps<{
    ancestors: PairingAnalysisReport['common_ancestors'];
    // The pair has bred the bird this is shown for (its pedigree), rather than a pairing being planned
    bred?: boolean;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Common ancestors</CardTitle>
            <CardDescription
                >Birds found in both the sire's and the dam's pedigree, and how
                much each adds to the COI.</CardDescription
            >
        </CardHeader>
        <CardContent>
            <div v-if="ancestors.length" class="divide-y rounded-lg border">
                <BirdRow
                    v-for="row in ancestors"
                    :key="row.bird.key"
                    :bird="row.bird"
                >
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-for="(line, index) in row.lines"
                            :key="index"
                            class="rounded border bg-muted/50 px-1.5 py-0.5 font-mono text-xs"
                            :title="`${bred ? 'This bird' : 'Youngster'}'s ${positionWords(line.sire)} and ${positionWords(line.dam)}`"
                        >
                            {{ line.sire }} × {{ line.dam }}
                        </span>
                    </div>
                    <template #aside>
                        <span class="text-sm font-medium tabular-nums"
                            >+{{ percent(row.contribution) }}</span
                        >
                    </template>
                </BirdRow>
            </div>
            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No common ancestors in the recorded pedigrees.
                {{
                    bred
                        ? 'This bird is an outcross.'
                        : 'This pairing is an outcross.'
                }}
            </p>
            <p
                v-if="ancestors.length"
                class="mt-2 text-xs text-muted-foreground"
            >
                Codes are positions in
                {{ bred ? "this bird's" : "the youngster's" }} pedigree: S =
                sire, D = dam. "SDS" is the sire's dam's sire.
            </p>
        </CardContent>
    </Card>
</template>
