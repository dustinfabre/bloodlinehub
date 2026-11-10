<script setup lang="ts">
import { vReveal } from '@/lib/reveal';
import { GitMerge } from 'lucide-vue-next';

// A first-cousin pairing, with the labels, bands and meter scale of the Pairing Analysis page. The sire is a little
// inbred himself (3.125%), which is why the relationship comes out just under 12.5%.
const METER_MAX = 0.3;
const COI = 0.0625;

const markers = [
    { value: 0.0625, label: '6.25%' },
    { value: 0.125, label: '12.5%' },
    { value: 0.25, label: '25%' },
];

const stats = [
    { label: 'Relationship of the pair', value: '12.31%' },
    { label: "Sire's own COI", value: '3.13%' },
    { label: "Dam's own COI", value: '0%' },
];

const offset = (value: number) => `${(value / METER_MAX) * 100}%`;
</script>

<template>
    <figure v-reveal="120" class="coi">
        <div class="rounded-lg border border-border bg-card p-4 sm:p-5">
            <p class="text-sm font-medium text-navy">Pairing Analysis</p>

            <div class="mt-3 grid grid-cols-[1fr_auto_1fr] items-end gap-x-3">
                <div class="min-w-0">
                    <p class="text-xs text-muted-foreground">Sire (cock)</p>
                    <p class="text-sm font-medium text-navy tabular-nums">
                        PH 2023-08817
                    </p>
                </div>
                <span class="text-muted-foreground" aria-hidden="true">×</span>
                <div class="min-w-0 text-right">
                    <p class="text-xs text-muted-foreground">Dam (hen)</p>
                    <p class="text-sm font-medium text-navy tabular-nums">
                        PH 2024-01294
                    </p>
                </div>
            </div>

            <div class="mt-4 border-t border-border pt-4">
                <p class="text-xs text-muted-foreground">
                    Inbreeding coefficient of the youngsters (COI)
                </p>
                <p
                    class="mt-1 text-4xl font-semibold tracking-tight text-navy tabular-nums"
                >
                    6.25%
                </p>
                <p
                    class="mt-1 flex items-center gap-1.5 text-sm font-medium text-navy"
                >
                    <GitMerge class="size-4" aria-hidden="true" />
                    Line breeding
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    About first cousins (6.25%) or closer
                </p>

                <!-- Meter: 0 to 30%, with lines at first cousins, half siblings and full siblings -->
                <div class="mt-4" aria-hidden="true">
                    <div class="relative h-2.5 rounded-full bg-navy/10">
                        <div
                            class="coi-fill h-full rounded-full bg-navy"
                            :style="{ width: offset(COI) }"
                        />
                        <span
                            v-for="marker in markers"
                            :key="marker.value"
                            class="absolute top-0 h-full w-0.5 bg-card"
                            :style="{ left: offset(marker.value) }"
                        />
                    </div>
                    <div
                        class="relative mt-1 h-4 text-[11px] text-muted-foreground tabular-nums"
                    >
                        <span class="absolute left-0">0%</span>
                        <span
                            v-for="marker in markers"
                            :key="marker.value"
                            class="absolute -translate-x-1/2"
                            :style="{ left: offset(marker.value) }"
                            >{{ marker.label }}</span
                        >
                    </div>
                </div>
            </div>

            <dl class="mt-4 grid grid-cols-3 gap-3 border-t border-border pt-4">
                <div
                    v-for="stat in stats"
                    :key="stat.label"
                    class="flex flex-col justify-between gap-1"
                >
                    <dt class="text-xs leading-snug text-muted-foreground">
                        {{ stat.label }}
                    </dt>
                    <dd class="text-lg font-semibold text-navy tabular-nums">
                        {{ stat.value }}
                    </dd>
                </div>
            </dl>
        </div>
        <figcaption class="mt-3 text-sm text-muted-foreground">
            A first-cousin pairing in Pairing Analysis, worked out with Wright's
            method from the pedigrees you have recorded.
        </figcaption>
    </figure>
</template>

<style scoped>
/* The bar fills to its value once, after the readout has faded in (v-reveal marks the figure pending, then shown) */
.coi[data-reveal='pending'] .coi-fill {
    width: 0 !important;
}

.coi[data-reveal='shown'] .coi-fill {
    transition: width 900ms var(--ease-crisp, cubic-bezier(0.22, 1, 0.36, 1))
        450ms;
}

@media (prefers-reduced-motion: reduce) {
    .coi .coi-fill {
        transition: none !important;
    }
}
</style>
