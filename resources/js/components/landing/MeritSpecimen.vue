<script setup lang="ts">
import { meritChipStyle } from '@/lib/merits';
import { vReveal } from '@/lib/reveal';
import { Award, Plus, Trophy, X } from 'lucide-vue-next';

type Category = 'racing' | 'breeding';

// Codes, labels and colors from app/Enums/MeritType.php
const merits: {
    code: string;
    label: string;
    category: Category;
    color: string;
}[] = [
    {
        code: 'OA',
        label: 'Overall Winner',
        category: 'racing',
        color: '#EBC75E',
    },
    {
        code: 'HS',
        label: 'Hot Spot Winner',
        category: 'racing',
        color: '#F8907C',
    },
    {
        code: 'SS',
        label: 'Superset Winner',
        category: 'racing',
        color: '#00666C',
    },
    { code: 'FIN', label: 'Finisher', category: 'racing', color: '#76D8CC' },
    {
        code: 'SB',
        label: 'Super Breeder',
        category: 'breeding',
        color: '#5D339A',
    },
];

const HOT_SPOT = merits[1];

// MeritBadge's shape, icons and fixed merit colors
const CHIP_CLASS =
    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-black/10 ring-inset';
</script>

<template>
    <figure v-reveal="120">
        <div class="rounded-lg border border-border bg-card">
            <div class="p-4 sm:p-5">
                <p class="text-sm font-medium text-navy">Merits</p>
                <dl
                    class="mt-3 grid grid-cols-[auto_1fr] items-center gap-x-3 gap-y-2.5"
                >
                    <template v-for="merit in merits" :key="merit.code">
                        <dt>
                            <span
                                :class="CHIP_CLASS"
                                :style="meritChipStyle(merit)"
                            >
                                <Award
                                    v-if="merit.category === 'breeding'"
                                    class="size-3"
                                    aria-hidden="true"
                                />
                                <Trophy
                                    v-else
                                    class="size-3"
                                    aria-hidden="true"
                                />
                                {{ merit.code }}
                            </span>
                        </dt>
                        <dd class="text-sm text-navy">{{ merit.label }}</dd>
                    </template>
                </dl>
            </div>

            <div class="border-t border-border p-4 sm:p-5">
                <p class="text-sm font-medium text-navy">
                    Suggested from results
                </p>
                <div
                    class="mt-3 flex items-center justify-between gap-3 rounded-md border border-dashed border-border p-3"
                >
                    <div class="min-w-0 space-y-1">
                        <div
                            class="flex flex-wrap items-center gap-x-2 gap-y-1"
                        >
                            <span
                                :class="CHIP_CLASS"
                                :style="meritChipStyle(HOT_SPOT)"
                            >
                                <Trophy class="size-3" aria-hidden="true" />
                                {{ HOT_SPOT.label }}
                            </span>
                            <span class="text-xs text-muted-foreground"
                                >One Loft Race · 2026</span
                            >
                        </div>
                        <p class="text-sm text-navy">
                            4th in the hot spot, Tarlac
                        </p>
                    </div>
                    <!-- Stand-ins for the Add and Dismiss buttons; nothing here is clickable -->
                    <div
                        class="flex shrink-0 items-center gap-1"
                        aria-hidden="true"
                    >
                        <span
                            class="inline-flex h-8 items-center gap-1 rounded-md border border-border px-2.5 text-sm font-medium text-navy"
                        >
                            <Plus class="size-4" />
                            Add
                        </span>
                        <span
                            class="inline-flex size-8 items-center justify-center text-muted-foreground"
                        >
                            <X class="size-4" />
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <figcaption class="mt-3 text-sm text-muted-foreground">
            Merit codes in their fixed colors, as the pigeon list and pedigrees
            show them, and a merit suggested from a race result.
        </figcaption>
    </figure>
</template>
