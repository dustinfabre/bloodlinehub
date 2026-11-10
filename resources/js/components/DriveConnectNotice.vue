<script setup lang="ts">
import { HardDrive } from 'lucide-vue-next';
import { computed } from 'vue';

import { Button } from '@/components/ui/button';
import { connect } from '@/routes/storage/google';

// Shown in place of image uploads until the user connects the Google Drive their photos are saved to
const props = defineProps<{
    // Remind the user that leaving the page drops what they typed
    unsavedChanges?: boolean;
}>();

// Come back to this page after Google's consent screen
const connectUrl = computed(() => {
    const returnTo = typeof window !== 'undefined' ? window.location.pathname + window.location.search : undefined;

    return connect({ query: returnTo ? { return_to: returnTo } : {} }).url;
});
</script>

<template>
    <div class="flex flex-col gap-3 rounded-lg border border-dashed p-4 sm:flex-row sm:items-center">
        <div class="flex flex-1 items-start gap-3">
            <HardDrive class="mt-0.5 size-5 shrink-0 text-muted-foreground" />
            <div class="space-y-1">
                <p class="text-sm font-medium">Connect Google Drive to add photos</p>
                <p class="text-xs text-muted-foreground">
                    Photos and certificates are saved to a BloodlineHub folder in your own Drive.
                    <template v-if="props.unsavedChanges">Unsaved changes on this page will be lost.</template>
                </p>
            </div>
        </div>
        <Button variant="outline" size="sm" as-child>
            <a :href="connectUrl">Connect Google Drive</a>
        </Button>
    </div>
</template>
