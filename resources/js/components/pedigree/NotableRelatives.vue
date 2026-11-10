<script setup lang="ts">
import MeritBadge from '@/components/MeritBadge.vue';
import { Button } from '@/components/ui/button';
import { byImportance } from '@/lib/merits';
import { relationHint, relationTitle } from '@/lib/pedigree';
import { show as showPigeon } from '@/routes/pigeons';
import type { NotableRelative } from '@/types/pedigree';
import { Link } from '@inertiajs/vue3';
import { Award, Bird } from 'lucide-vue-next';

// Related loft birds that are not on the chart, featured below it by the owner: brothers and sisters, sons and
// daughters, aunts and uncles, each with its photo and merits. The owner is told where to choose them when there
// are none; visitors see nothing then.
defineProps<{
    relatives: NotableRelative[];
    // Owner only: link each relative to its pigeon page
    linkable?: boolean;
    // Owner only: where to choose notable relatives
    chooseHref?: string;
}>();

const MAX_MERITS = 4;

const label = (relative: NotableRelative) =>
    relative.name || relative.ring_number || 'Unnamed';
</script>

<template>
    <section
        v-if="relatives.length"
        class="rounded-xl border bg-card p-4 sm:p-5"
    >
        <h2 class="text-lg font-semibold">Notable relatives</h2>
        <p class="text-sm text-muted-foreground">
            Related birds from the same loft that are not on the chart above.
        </p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <component
                :is="linkable && relative.id ? Link : 'div'"
                v-for="(relative, index) in relatives"
                :key="relative.id ?? `relative-${index}`"
                :href="
                    linkable && relative.id
                        ? showPigeon({ pigeon: relative.id }).url
                        : undefined
                "
                class="flex min-w-0 gap-3 rounded-lg border p-3"
                :class="{
                    'transition-colors hover:bg-accent':
                        linkable && relative.id,
                }"
            >
                <div
                    class="size-20 shrink-0 overflow-hidden rounded-md border bg-muted"
                >
                    <img
                        v-if="relative.photo_url"
                        :src="relative.photo_url"
                        :alt="`${relationTitle(relative.relation, relative.gender)} ${label(relative)}`"
                        loading="lazy"
                        class="size-full object-cover"
                    />
                    <div
                        v-else
                        class="flex size-full items-center justify-center text-muted-foreground"
                    >
                        <Bird class="size-6" />
                    </div>
                </div>
                <div class="min-w-0 flex-1 space-y-1">
                    <p
                        class="text-xs font-semibold tracking-wide text-gold uppercase"
                    >
                        {{ relationTitle(relative.relation, relative.gender) }}
                    </p>
                    <p class="truncate font-medium">
                        {{ label(relative) }}
                        <span
                            v-if="relative.name && relative.ring_number"
                            class="font-mono text-sm font-normal text-muted-foreground"
                            >{{ relative.ring_number }}</span
                        >
                    </p>
                    <p
                        v-if="relationHint(relative.relation)"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ relationHint(relative.relation) }}
                    </p>
                    <div
                        v-if="relative.merits.length"
                        class="flex flex-wrap items-center gap-1 pt-0.5"
                    >
                        <MeritBadge
                            v-for="(merit, meritIndex) in byImportance(
                                relative.merits,
                            ).slice(0, MAX_MERITS)"
                            :key="meritIndex"
                            :merit="merit"
                        />
                        <span
                            v-if="relative.merits.length > MAX_MERITS"
                            class="text-xs text-muted-foreground"
                            >+{{
                                relative.merits.length - MAX_MERITS
                            }}
                            more</span
                        >
                    </div>
                </div>
            </component>
        </div>
    </section>

    <!-- Owner only: none chosen yet -->
    <div
        v-else-if="chooseHref"
        class="flex flex-col gap-3 rounded-xl border border-dashed p-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex items-start gap-3 text-sm">
            <Award class="mt-0.5 size-5 shrink-0 text-muted-foreground" />
            <div>
                <p class="font-medium">No notable relatives yet</p>
                <p class="text-muted-foreground">
                    Show brothers, sisters, sons, daughters, aunts and uncles
                    from your loft that have merits or photos, below the chart.
                    Choose them in Edit pedigree.
                </p>
            </div>
        </div>
        <Button size="sm" variant="outline" class="shrink-0" as-child>
            <Link :href="chooseHref">Choose relatives</Link>
        </Button>
    </div>
</template>
