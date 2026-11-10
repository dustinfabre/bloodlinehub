<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { PAPER_HEIGHT, PAPER_WIDTH } from '@/lib/pedigree';
import { useResizeObserver } from '@vueuse/core';
import { Maximize, Minimize } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Shows the fixed-size pedigree sheet scaled down to fit narrow screens, with an option to view it at actual size.
const container = ref<HTMLElement | null>(null);
const availableWidth = ref(PAPER_WIDTH);
const fit = ref(true);

useResizeObserver(container, (entries) => {
    availableWidth.value = entries[0].contentRect.width;
});

const canZoom = computed(() => availableWidth.value < PAPER_WIDTH);
const scale = computed(() =>
    fit.value ? Math.min(1, availableWidth.value / PAPER_WIDTH) : 1,
);
</script>

<template>
    <div class="space-y-2">
        <div v-if="canZoom" class="flex justify-end">
            <!-- Full-width and large on phones, where the scaled-down sheet is hard to read; compact from sm up -->
            <Button
                variant="outline"
                class="h-12 w-full gap-2 text-base font-semibold sm:h-8 sm:w-auto sm:text-sm sm:font-medium"
                :aria-pressed="!fit"
                @click="fit = !fit"
            >
                <Maximize v-if="fit" class="size-5 sm:size-4" />
                <Minimize v-else class="size-5 sm:size-4" />
                {{ fit ? 'Actual size' : 'Fit to screen' }}
            </Button>
        </div>
        <div
            ref="container"
            class="overflow-x-auto rounded-xl border bg-muted/40 p-2 sm:p-4"
        >
            <div
                class="mx-auto overflow-hidden rounded-sm shadow-lg ring-1 ring-black/5"
                :style="{
                    width: `${PAPER_WIDTH * scale}px`,
                    height: `${PAPER_HEIGHT * scale}px`,
                }"
            >
                <div
                    class="origin-top-left"
                    :style="{
                        width: `${PAPER_WIDTH}px`,
                        transform: `scale(${scale})`,
                    }"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
