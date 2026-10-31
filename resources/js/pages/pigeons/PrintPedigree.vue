<script setup lang="ts">
import PedigreeChart from '@/components/pedigree/PedigreeChart.vue';
import { Button } from '@/components/ui/button';
import type { PedigreeMeta, PedigreeNode } from '@/types/pedigree';
import { Head } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';

interface Pigeon {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
}

const props = defineProps<{
    pigeon: Pigeon;
    tree: PedigreeNode;
    meta: PedigreeMeta;
}>();

const pigeonLabel = computed(
    () =>
        props.pigeon.name ||
        props.pigeon.ring_number ||
        props.pigeon.personal_number ||
        `Pigeon #${props.pigeon.id}`,
);

const print = () => window.print();

const imageLoaded = (image: HTMLImageElement) =>
    image.complete
        ? Promise.resolve()
        : new Promise<void>((resolve) => {
              image.addEventListener('load', () => resolve(), { once: true });
              image.addEventListener('error', () => resolve(), { once: true });
          });

onMounted(async () => {
    // Wait for fonts and the photo so the print preview isn't missing them
    await Promise.all([
        document.fonts.ready,
        ...Array.from(document.images).map(imageLoaded),
    ]);
    print();
});
</script>

<template>
    <Head :title="`Print pedigree - ${pigeonLabel}`" />

    <div
        class="pedigree-print-page flex min-h-screen flex-col items-center gap-4 bg-muted/40 p-6"
    >
        <div
            class="flex w-full max-w-[1122px] flex-wrap items-center justify-between gap-3 print:hidden"
        >
            <p class="text-sm text-muted-foreground">
                A4 landscape. To keep a PDF copy, choose "Save as PDF" as the
                printer.
            </p>
            <Button @click="print">
                <Printer class="mr-2 h-4 w-4" />
                Print
            </Button>
        </div>
        <div class="shadow-lg print:shadow-none">
            <PedigreeChart :tree="tree" :meta="meta" />
        </div>
    </div>
</template>

<style>
@page {
    size: A4 landscape;
    margin: 0;
}

@media print {
    html,
    body {
        margin: 0;
        padding: 0;
        background: #ffffff !important;
    }

    .pedigree-print-page {
        display: block !important;
        min-height: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
    }
}
</style>
