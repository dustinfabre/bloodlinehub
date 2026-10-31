<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import PedigreeChart from '@/components/pedigree/PedigreeChart.vue';
import PedigreeExportActions from '@/components/pedigree/PedigreeExportActions.vue';
import PedigreeViewer from '@/components/pedigree/PedigreeViewer.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { pedigreeFilename } from '@/lib/pedigree';
import { home, login } from '@/routes';
import { pedigree as pedigreeRoute } from '@/routes/pigeons';
import { type AppPageProps } from '@/types';
import type { PedigreeMeta, PedigreeNode } from '@/types/pedigree';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// tree/meta are null when the pedigree is private and the viewer is not its owner
const props = defineProps<{
    serial: string;
    isOwner: boolean;
    pigeonId?: number | null;
    tree: PedigreeNode | null;
    meta: PedigreeMeta | null;
}>();

const page = usePage<AppPageProps>();
const isGuest = computed(() => !page.props.auth?.user);

const chart = ref<InstanceType<typeof PedigreeChart> | null>(null);
const filename = computed(() =>
    pedigreeFilename(props.tree?.ring_number ?? null, props.serial),
);
const subjectLabel = computed(
    () => props.tree?.ring_number || props.tree?.name || props.serial,
);
</script>

<template>
    <Head :title="tree ? `Pedigree ${subjectLabel}` : 'Private pedigree'" />

    <div class="min-h-screen bg-muted/40">
        <header class="border-b bg-background">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3"
            >
                <Link
                    :href="home().url"
                    class="flex items-center gap-2 font-semibold"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-5 fill-current" />
                    </span>
                    BloodlineHub
                </Link>
                <span class="font-mono text-sm text-muted-foreground">{{
                    serial
                }}</span>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-4 px-4 py-6">
            <template v-if="tree && meta">
                <div
                    v-if="isOwner && !meta.is_public"
                    class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                >
                    This pedigree is private, so only you can see this page.
                    <Link
                        v-if="pigeonId"
                        :href="pedigreeRoute({ pigeon: pigeonId }).url"
                        class="font-medium underline"
                        >Manage sharing</Link
                    >
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-bold">
                            Pedigree {{ subjectLabel }}
                        </h1>
                        <p class="text-muted-foreground">
                            Bred by {{ meta.breeder }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <PedigreeExportActions
                            :target="chart?.paper"
                            :filename="filename"
                        />
                    </div>
                </div>

                <PedigreeViewer>
                    <PedigreeChart ref="chart" :tree="tree" :meta="meta" />
                </PedigreeViewer>
            </template>

            <div
                v-else
                class="mx-auto mt-10 max-w-md rounded-xl border bg-background p-8 text-center shadow-sm"
            >
                <div
                    class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
                >
                    <Lock class="h-6 w-6" />
                </div>
                <h1 class="text-xl font-semibold">This pedigree is private</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Pedigree <span class="font-mono">{{ serial }}</span> exists,
                    but its owner hasn't made it public.
                </p>
                <Button v-if="isGuest" class="mt-6" as-child>
                    <Link :href="login().url"
                        >Log in to view your own pedigree</Link
                    >
                </Button>
            </div>
        </main>

        <Toaster
            position="top-right"
            :richColors="true"
            :closeButton="true"
            :duration="3000"
        />
    </div>
</template>
