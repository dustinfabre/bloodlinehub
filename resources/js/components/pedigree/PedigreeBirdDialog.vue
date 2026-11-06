<script setup lang="ts">
import MeritBadge from '@/components/MeritBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { byImportance } from '@/lib/merits';
import {
    formatPedigreeDate,
    lineageSteps,
    relationName,
    sexSymbol,
} from '@/lib/pedigree';
import { show as showRoute } from '@/routes/pigeons';
import type { PedigreeNode } from '@/types/pedigree';
import { Link } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';

// Everything recorded about one bird on a pedigree, opened by clicking its card
const props = defineProps<{
    // Chart position from the pigeon: '' for the pigeon itself, 'SD' for its sire's dam, ...
    position: string;
    node: PedigreeNode | null;
    // Link loft birds to their pigeon pages (owner view only)
    linkable?: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

// Position decides the sex of ancestors; the pigeon itself uses its own gender
const gender = computed(() => {
    if (!props.position) return props.node?.gender ?? null;
    return props.position.endsWith('S') ? 'male' : 'female';
});

const sexLabel = computed(() =>
    gender.value === 'male' ? 'Cock' : gender.value === 'female' ? 'Hen' : null,
);

const title = computed(() =>
    props.position ? relationName(props.position) : 'This pigeon',
);

const merits = computed(() => byImportance(props.node?.merits ?? []));

const details = computed(() => {
    const node = props.node;
    if (!node) return [];
    return [
        { label: 'Ring number', value: node.ring_number },
        { label: 'Name', value: node.name },
        { label: 'Sex', value: sexLabel.value },
        { label: 'Color', value: node.color },
        { label: 'Bloodline', value: node.bloodline },
        {
            label: 'Hatched',
            value: node.hatch_date ? formatPedigreeDate(node.hatch_date) : null,
        },
    ].filter((row) => row.value);
});

const href = computed(() =>
    props.linkable && props.node?.id && !props.node.external
        ? showRoute({ pigeon: props.node.id }).url
        : null,
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="position">
                    {{ ['Pigeon', ...lineageSteps(position)].join(' › ') }}
                </DialogDescription>
                <DialogDescription v-else>
                    The pigeon this pedigree is for
                </DialogDescription>
            </DialogHeader>

            <template v-if="node">
                <div class="flex items-center gap-3 rounded-xl bg-navy p-4">
                    <span
                        v-if="sexSymbol(gender)"
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-base font-bold"
                        :class="
                            gender === 'male'
                                ? 'text-[#9cc0ea]'
                                : 'text-[#f0a9bf]'
                        "
                        :aria-label="sexLabel ?? undefined"
                        >{{ sexSymbol(gender) }}</span
                    >
                    <div class="min-w-0">
                        <div
                            class="truncate text-lg font-bold text-gold tabular-nums"
                        >
                            {{ node.ring_number || node.name || 'Unknown' }}
                        </div>
                        <div
                            v-if="node.ring_number && node.name"
                            class="truncate text-sm font-semibold text-white"
                        >
                            {{ node.name }}
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                    <template v-for="row in details" :key="row.label">
                        <dt class="text-muted-foreground">{{ row.label }}</dt>
                        <dd class="font-medium break-words">{{ row.value }}</dd>
                    </template>
                </dl>

                <section v-if="merits.length" class="grid gap-2">
                    <h3 class="text-sm font-semibold">Merits</h3>
                    <ul class="grid gap-1.5">
                        <li
                            v-for="(merit, index) in merits"
                            :key="`${merit.type}-${merit.year}-${index}`"
                            class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm"
                        >
                            <MeritBadge :merit="merit" full />
                            <span class="text-muted-foreground">{{
                                [merit.level_label, merit.year, merit.detail]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}</span>
                        </li>
                    </ul>
                </section>

                <section v-if="node.remarks" class="grid gap-2">
                    <h3 class="text-sm font-semibold">Remarks</h3>
                    <p
                        class="rounded-lg bg-muted/60 p-3 text-sm break-words whitespace-pre-line"
                    >
                        {{ node.remarks }}
                    </p>
                </section>

                <p
                    v-if="!details.length && !merits.length && !node.remarks"
                    class="text-sm text-muted-foreground"
                >
                    Nothing more is recorded about this bird.
                </p>

                <Button v-if="href" variant="outline" as-child>
                    <Link :href="href">
                        <ExternalLink class="size-4" />
                        Open pigeon page
                    </Link>
                </Button>
            </template>
        </DialogContent>
    </Dialog>
</template>
