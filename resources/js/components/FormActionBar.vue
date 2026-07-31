<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { LoaderCircle, Save } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        cancelHref: string;
        submitLabel?: string;
        processingLabel?: string;
        processing?: boolean;
        disabled?: boolean;
    }>(),
    {
        submitLabel: 'Save',
        processingLabel: 'Saving...',
        processing: false,
        disabled: false,
    },
);
</script>

<template>
    <div
        class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] gap-2 border-t border-border/80 bg-background/95 p-3 shadow-[0_-8px_24px_rgba(15,23,20,0.08)] backdrop-blur md:static md:flex md:justify-end md:border-0 md:bg-transparent md:p-0 md:shadow-none"
    >
        <Button
            variant="outline"
            type="button"
            as-child
            class="min-h-11 w-full md:w-auto"
        >
            <Link :href="cancelHref">Cancel</Link>
        </Button>
        <Button
            type="submit"
            class="min-h-11 w-full md:w-auto"
            :disabled="disabled || processing"
        >
            <LoaderCircle
                v-if="processing"
                class="size-4 animate-spin"
                aria-hidden="true"
            />
            <Save v-else class="size-4" aria-hidden="true" />
            {{ processing ? processingLabel : submitLabel }}
        </Button>
    </div>
</template>
