<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { RefreshCw, WifiOff, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

type UpdateServiceWorker = (reloadPage?: boolean) => Promise<void>;

const online = ref(true);
const updateServiceWorker = ref<UpdateServiceWorker | null>(null);

const syncConnection = () => {
    online.value = navigator.onLine;
};

const handleUpdateAvailable = (event: Event) => {
    updateServiceWorker.value = (
        event as CustomEvent<{ updateServiceWorker: UpdateServiceWorker }>
    ).detail.updateServiceWorker;
};

const applyUpdate = async () => {
    await updateServiceWorker.value?.(true);
};

onMounted(() => {
    syncConnection();
    window.addEventListener('online', syncConnection);
    window.addEventListener('offline', syncConnection);
    window.addEventListener('pwa:update-available', handleUpdateAvailable);
});

onBeforeUnmount(() => {
    window.removeEventListener('online', syncConnection);
    window.removeEventListener('offline', syncConnection);
    window.removeEventListener('pwa:update-available', handleUpdateAvailable);
});
</script>

<template>
    <div
        v-if="!online"
        role="status"
        class="fixed inset-x-3 top-[4.25rem] z-50 flex min-h-11 items-center justify-center gap-2 rounded-md bg-amber-600 px-4 py-2 text-center text-sm font-medium text-white shadow-lg md:top-4 md:right-4 md:left-auto md:max-w-sm"
    >
        <WifiOff class="size-4 shrink-0" aria-hidden="true" />
        You are offline. Viewing and saving records requires a connection.
    </div>

    <div
        v-if="updateServiceWorker"
        role="status"
        class="fixed inset-x-3 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-50 flex items-center gap-3 rounded-md border border-border bg-popover p-3 text-popover-foreground shadow-xl md:inset-x-auto md:right-4 md:bottom-4 md:w-96"
    >
        <RefreshCw class="size-5 shrink-0 text-primary" aria-hidden="true" />
        <p class="min-w-0 flex-1 text-sm font-medium">
            A new version of BloodlineHub is ready.
        </p>
        <Button size="sm" class="min-h-11" @click="applyUpdate">Update</Button>
        <Button
            size="icon"
            variant="ghost"
            class="min-h-11 min-w-11"
            aria-label="Dismiss update"
            @click="updateServiceWorker = null"
        >
            <X class="size-4" aria-hidden="true" />
        </Button>
    </div>
</template>
