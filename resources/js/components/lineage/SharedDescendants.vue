<script setup lang="ts">
import BirdRow from '@/components/lineage/BirdRow.vue';
import { Button } from '@/components/ui/button';
import { birdLabel, percent, relationName } from '@/lib/lineage';
import { show as showPigeon } from '@/routes/pigeons';
import type {
    DescentLine,
    SharedDescendant,
    SharedReport,
} from '@/types/lineage';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    report: SharedReport;
    aLabel: string;
    bLabel: string;
    emptyText?: string;
}>();

type View = 'all' | 'direct' | 'proven';

const view = ref<View>('all');
const limit = ref(30);

const isProven = (row: SharedDescendant) =>
    row.bird.merit_score > 0 || (row.bird.results?.top10 ?? 0) > 0;

const rows = computed(() =>
    props.report.birds.filter((row) =>
        view.value === 'direct'
            ? row.direct
            : view.value === 'proven'
              ? isProven(row)
              : true,
    ),
);

watch(view, () => (limit.value = 30));

const views = computed(() => [
    { value: 'all' as View, label: 'All', count: props.report.total },
    {
        value: 'direct' as View,
        label: 'Direct offspring',
        count: props.report.direct,
    },
    { value: 'proven' as View, label: 'Proven', count: props.report.proven },
]);

const lineText = (line: DescentLine, ancestor: string) =>
    `${relationName(line.generation)} of ${ancestor}`;
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap gap-1" role="tablist">
            <button
                v-for="option in views"
                :key="option.value"
                type="button"
                role="tab"
                :aria-selected="view === option.value"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors"
                :class="
                    view === option.value
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-accent'
                "
                @click="view = option.value"
            >
                {{ option.label }}
                <span class="text-xs opacity-80">{{ option.count }}</span>
            </button>
        </div>

        <div v-if="rows.length" class="divide-y rounded-lg border">
            <BirdRow
                v-for="row in rows.slice(0, limit)"
                :key="row.bird.key"
                :bird="row.bird"
            >
                <p v-if="row.direct" class="text-sm">
                    <span class="font-medium">Offspring of this pair</span>
                </p>
                <template v-else>
                    <p
                        v-for="[line, ancestor] in [
                            [row.from_a, aLabel],
                            [row.from_b, bLabel],
                        ] as [DescentLine, string][]"
                        :key="ancestor"
                        class="text-sm"
                    >
                        <span class="font-medium">{{
                            lineText(line, ancestor)
                        }}</span>
                        <template v-if="line.via.length">
                            <span class="text-muted-foreground"> via </span>
                            <template
                                v-for="(via, index) in line.via"
                                :key="via.key"
                            >
                                <span
                                    v-if="index"
                                    class="text-muted-foreground"
                                >
                                    →
                                </span>
                                <Link
                                    v-if="via.id"
                                    :href="showPigeon({ pigeon: via.id }).url"
                                    class="text-primary hover:underline"
                                    >{{ birdLabel(via) }}</Link
                                >
                                <span v-else>{{ birdLabel(via) }}</span>
                            </template>
                        </template>
                    </p>
                </template>
                <template #aside>
                    <span
                        class="text-xs text-muted-foreground"
                        :title="`Expected share of genes from ${aLabel} and ${bLabel}`"
                    >
                        {{ percent(row.from_a.share) }} /
                        {{ percent(row.from_b.share) }}
                    </span>
                </template>
            </BirdRow>
        </div>
        <p
            v-else
            class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{
                view === 'all'
                    ? (emptyText ?? 'No birds descend from both.')
                    : view === 'direct'
                      ? 'No direct offspring recorded.'
                      : 'None of these birds has a merit or a top-10 result yet.'
            }}
        </p>

        <div v-if="rows.length > limit" class="text-center">
            <Button variant="outline" size="sm" @click="limit += 30"
                >Show more ({{ rows.length - limit }} left)</Button
            >
        </div>
    </div>
</template>
