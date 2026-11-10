<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { usePedigreeExport } from '@/composables/usePedigreeExport';
import { useToast } from '@/composables/useToast';
import { FileDown, ImageDown } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        // The PedigreeChart sheet to export
        target: HTMLElement | null | undefined;
        filename: string;
        // Offer a PDF download as well as the image
        pdf?: boolean;
    }>(),
    { pdf: true },
);

const { exporting, saveImage, savePdf } = usePedigreeExport();
const { error } = useToast();

const run = async (action: typeof saveImage) => {
    if (!props.target || exporting.value) return;
    try {
        await action(props.target, props.filename);
    } catch (e) {
        console.error('Pedigree export failed:', e);
        error('Could not export the pedigree. Please try again.');
    }
};
</script>

<template>
    <Button
        v-if="pdf"
        variant="outline"
        :disabled="!target || !!exporting"
        @click="run(savePdf)"
    >
        <Spinner v-if="exporting === 'pdf'" class="mr-2 h-4 w-4" />
        <FileDown v-else class="mr-2 h-4 w-4" />
        Download PDF
    </Button>
    <Button
        variant="outline"
        :disabled="!target || !!exporting"
        @click="run(saveImage)"
    >
        <Spinner v-if="exporting === 'image'" class="mr-2 h-4 w-4" />
        <ImageDown v-else class="mr-2 h-4 w-4" />
        Save image
    </Button>
</template>
