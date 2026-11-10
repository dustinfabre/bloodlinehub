<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { PairingAnalysisReport } from '@/types/lineage';

defineProps<{
    completeness: PairingAnalysisReport['completeness'];
    // The pair has bred the bird this is shown for (its pedigree), rather than a pairing being planned
    bred?: boolean;
}>();

const generationName = (generation: number) =>
    (
        ({
            1: 'Parents',
            2: 'Grandparents',
            3: 'Great-grandparents',
        }) as Record<number, string>
    )[generation] ?? `${generation - 2}× great-grandparents`;
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Pedigree depth</CardTitle>
            <CardDescription
                >Known ancestors of
                {{ bred ? 'this bird' : 'the youngsters' }} per generation. More
                depth gives a more reliable COI.</CardDescription
            >
        </CardHeader>
        <CardContent class="space-y-3">
            <div
                v-for="row in completeness"
                :key="row.generation"
                class="grid grid-cols-[9rem_1fr_3.5rem] items-center gap-3 text-sm"
            >
                <span class="truncate text-muted-foreground">{{
                    generationName(row.generation)
                }}</span>
                <div
                    class="h-2 rounded-full bg-sky-100 dark:bg-sky-950"
                    aria-hidden="true"
                >
                    <div
                        class="h-full rounded-full bg-sky-600 dark:bg-sky-400"
                        :style="{
                            width: `${(row.known / row.total) * 100}%`,
                        }"
                    />
                </div>
                <span class="text-right tabular-nums"
                    >{{ row.known }}/{{ row.total }}</span
                >
            </div>
        </CardContent>
    </Card>
</template>
