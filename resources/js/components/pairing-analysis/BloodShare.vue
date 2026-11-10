<script setup lang="ts">
import BirdRow from '@/components/lineage/BirdRow.vue';
import { Badge } from '@/components/ui/badge';
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
    ancestors: PairingAnalysisReport['influential_ancestors'];
    // The pair has bred the bird this is shown for (its pedigree), rather than a pairing being planned
    bred?: boolean;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Blood share</CardTitle>
            <CardDescription>
                How much of each ancestor
                {{ bred ? 'this bird carries' : 'the youngsters would carry' }}:
                a grandparent gives 25%, a great-grandparent 12.5%. Birds that
                appear more than once are listed first; they are what
                {{ bred ? 'this bird' : 'this pairing' }} is line-bred on.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div v-if="ancestors.length" class="divide-y rounded-lg border">
                <BirdRow
                    v-for="row in ancestors"
                    :key="row.bird.key"
                    :bird="row.bird"
                >
                    <div class="flex flex-wrap items-center gap-1">
                        <Badge
                            v-if="row.positions.length > 1"
                            variant="secondary"
                            >{{ row.positions.length }}× in pedigree</Badge
                        >
                        <span
                            v-for="position in row.positions"
                            :key="position"
                            class="rounded border bg-muted/50 px-1.5 py-0.5 font-mono text-xs"
                            :title="`${bred ? 'This bird' : 'Youngster'}'s ${positionWords(position)}`"
                        >
                            {{ position }}
                        </span>
                    </div>
                    <template #aside>
                        <span class="text-sm font-medium tabular-nums">{{
                            percent(row.share)
                        }}</span>
                    </template>
                </BirdRow>
            </div>
            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                {{
                    bred
                        ? 'Not enough pedigree recorded for the parents yet.'
                        : "Not enough pedigree recorded yet. Add the parents' ancestors to see this."
                }}
            </p>
        </CardContent>
    </Card>
</template>
