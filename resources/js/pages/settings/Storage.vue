<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, ExternalLink, HardDrive } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { privacy } from '@/routes';
import { edit } from '@/routes/storage';
import { connect, destroy } from '@/routes/storage/google';
import { type BreadcrumbItem } from '@/types';

interface DriveInfo {
    email: string | null;
    folder_url: string | null;
    connected_at: string | null;
    usage: number | null;
    limit: number | null;
}

const props = defineProps<{
    configured: boolean;
    drive: DriveInfo | null;
    imageCount: number;
    status?: string | null;
    error?: string | null;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Storage settings',
        href: edit().url,
    },
];

const disconnecting = ref(false);

function disconnect() {
    router.delete(destroy().url, {
        preserveScroll: true,
        onStart: () => (disconnecting.value = true),
        onFinish: () => (disconnecting.value = false),
    });
}

function formatBytes(bytes: number): string {
    if (bytes < 1024 ** 2) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(0)} MB`;
    if (bytes < 1024 ** 4) return `${(bytes / 1024 ** 3).toFixed(1)} GB`;
    return `${(bytes / 1024 ** 4).toFixed(1)} TB`;
}

const usedPercent = computed(() => {
    const { usage, limit } = props.drive ?? {};
    if (usage == null || !limit) return null;
    return Math.min(100, Math.round((usage / limit) * 100));
});

const imageLabel = computed(() => `${props.imageCount} ${props.imageCount === 1 ? 'image' : 'images'}`);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Storage settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Photo storage"
                    description="Pigeon photos, certificates and photos of relatives are saved to your own Google Drive, so you can keep as many as your Drive holds."
                />

                <Alert v-if="error" variant="destructive">
                    <AlertTriangle />
                    <AlertTitle>Google Drive was not connected</AlertTitle>
                    <AlertDescription>{{ error }}</AlertDescription>
                </Alert>

                <Alert v-else-if="status === 'google-drive-connected' && drive">
                    <CheckCircle2 />
                    <AlertTitle>Google Drive connected</AlertTitle>
                    <AlertDescription>New photos are saved to the BloodlineHub folder in your Drive.</AlertDescription>
                </Alert>

                <Alert v-else-if="status === 'google-drive-disconnected'">
                    <CheckCircle2 />
                    <AlertTitle>Google Drive disconnected</AlertTitle>
                    <AlertDescription>BloodlineHub no longer has access keys for your Drive.</AlertDescription>
                </Alert>

                <!-- Connected -->
                <div v-if="drive" class="space-y-4 rounded-lg border p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted">
                            <HardDrive class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium">Google Drive</p>
                                <Badge variant="secondary">Connected</Badge>
                            </div>
                            <p v-if="drive.email" class="truncate text-sm text-muted-foreground">{{ drive.email }}</p>
                            <p class="text-sm text-muted-foreground">{{ imageLabel }} saved by BloodlineHub</p>
                        </div>
                    </div>

                    <div v-if="drive.usage != null" class="space-y-1.5">
                        <div v-if="usedPercent != null" class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full"
                                :class="usedPercent >= 90 ? 'bg-destructive' : 'bg-primary'"
                                :style="{ width: `${usedPercent}%` }"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="drive.limit">
                                {{ formatBytes(drive.usage) }} of {{ formatBytes(drive.limit) }} used in your Google account
                            </template>
                            <template v-else>{{ formatBytes(drive.usage) }} used, no storage limit</template>
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button v-if="drive.folder_url" variant="outline" as-child>
                            <a :href="drive.folder_url" target="_blank" rel="noopener noreferrer">
                                <ExternalLink class="size-4" />
                                Open folder in Drive
                            </a>
                        </Button>

                        <Dialog>
                            <DialogTrigger as-child>
                                <Button variant="ghost" class="text-destructive hover:text-destructive">Disconnect</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader class="space-y-3">
                                    <DialogTitle>Disconnect Google Drive?</DialogTitle>
                                    <DialogDescription class="space-y-2">
                                        <span class="block">
                                            Your photos stay in the BloodlineHub folder in your Drive, but they won't show in
                                            BloodlineHub, on shared pedigrees, or on listings until you reconnect the same
                                            Google account. You can't add new photos while disconnected.
                                        </span>
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button variant="secondary">Cancel</Button>
                                    </DialogClose>
                                    <Button variant="destructive" :disabled="disconnecting" @click="disconnect">
                                        Disconnect
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                <!-- Not connected -->
                <div v-else class="space-y-4 rounded-lg border p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted">
                            <HardDrive class="size-5" />
                        </div>
                        <div class="space-y-1">
                            <p class="font-medium">Connect Google Drive to add photos</p>
                            <p class="text-sm text-muted-foreground">
                                BloodlineHub creates a <span class="font-medium text-foreground">BloodlineHub</span> folder in
                                your Drive and can only see the files it puts there, never the rest of your Drive. The photos
                                are yours: they stay in your Drive and count toward your Google storage.
                                <Link :href="`${privacy().url}#google-drive`" class="underline underline-offset-4"
                                    >How we use your Google data</Link
                                >
                            </p>
                        </div>
                    </div>

                    <Alert v-if="imageCount > 0">
                        <AlertTriangle />
                        <AlertTitle>{{ imageLabel }} hidden</AlertTitle>
                        <AlertDescription>
                            They are still in your Drive. Reconnect the same Google account to show them again.
                        </AlertDescription>
                    </Alert>

                    <Button v-if="configured" as-child>
                        <a :href="connect().url">Connect Google Drive</a>
                    </Button>
                    <p v-else class="text-sm text-muted-foreground">
                        Photo storage is not set up on this server yet (missing Google OAuth credentials).
                    </p>
                </div>

                <p v-if="drive" class="text-xs text-muted-foreground">
                    To remove BloodlineHub from your Google account entirely, use
                    <a
                        href="https://myaccount.google.com/connections"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="underline underline-offset-4"
                        >your Google account's third-party connections</a
                    >. Removing it there may stop BloodlineHub from showing the photos it already saved, even if you
                    connect again later.
                </p>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
