<script setup lang="ts">
import MeritBadge from '@/components/MeritBadge.vue';
import { Badge } from '@/components/ui/badge';
import {
    birdLabel,
    genderName,
    raceRecordText,
    statusStyles,
} from '@/lib/lineage';
import { show as showPigeon } from '@/routes/pigeons';
import type { BirdSummary } from '@/types/lineage';
import { Link } from '@inertiajs/vue3';
import { Flag } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ bird: BirdSummary }>();

const details = computed(() =>
    [
        props.bird.name ? props.bird.ring_number : null,
        props.bird.color,
        props.bird.bloodline,
        props.bird.hatch_date ? props.bird.hatch_date.slice(0, 4) : null,
    ]
        .filter(Boolean)
        .join(' · '),
);

const record = computed(() => raceRecordText(props.bird.results));
const status = computed(() =>
    props.bird.status ? statusStyles[props.bird.status] : null,
);
</script>

<template>
    <!-- Lay out by the row's own width so it also fits narrow side columns -->
    <div class="@container">
        <div
            class="flex flex-col gap-2 p-3 @md:flex-row @md:items-start @md:justify-between"
        >
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <Link
                        v-if="bird.id"
                        :href="showPigeon({ pigeon: bird.id }).url"
                        class="truncate font-medium text-primary hover:underline"
                    >
                        {{ birdLabel(bird) }}
                    </Link>
                    <span v-else class="truncate font-medium">{{
                        birdLabel(bird)
                    }}</span>
                    <Badge
                        v-if="bird.external"
                        variant="outline"
                        class="text-[10px]"
                        >Not in loft</Badge
                    >
                    <MeritBadge
                        v-for="(merit, index) in bird.merits"
                        :key="`${merit.type}-${index}`"
                        :merit="merit"
                    />
                </div>
                <p
                    v-if="details"
                    class="truncate text-sm text-muted-foreground"
                >
                    {{ details }}
                </p>
                <p
                    v-if="record"
                    class="flex items-center gap-1 text-sm text-muted-foreground"
                >
                    <Flag class="size-3.5 shrink-0" aria-hidden="true" />
                    {{ record }}
                </p>
                <slot />
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <slot name="aside" />
                <Badge v-if="genderName(bird.gender)" variant="outline">{{
                    genderName(bird.gender)
                }}</Badge>
                <span
                    v-if="status"
                    :class="[
                        status.className,
                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                    ]"
                >
                    {{ status.label }}
                </span>
            </div>
        </div>
    </div>
</template>
