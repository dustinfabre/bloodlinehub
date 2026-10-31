<script setup lang="ts">
import PedigreeChart from '@/components/pedigree/PedigreeChart.vue';
import PedigreeExportActions from '@/components/pedigree/PedigreeExportActions.vue';
import PedigreeViewer from '@/components/pedigree/PedigreeViewer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { pedigreeFilename } from '@/lib/pedigree';
import {
    index as indexRoute,
    pedigree as pedigreeRoute,
    pedigreeVisibility,
    printPedigree,
    show as showRoute,
} from '@/routes/pigeons';
import { type BreadcrumbItem } from '@/types';
import type { PedigreeMeta, PedigreeNode } from '@/types/pedigree';
import { Head, router } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    ExternalLink,
    Globe,
    Lock,
    Printer,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: indexRoute().url },
    {
        title: pigeonLabel.value,
        href: showRoute({ pigeon: props.pigeon.id }).url,
    },
    { title: 'Pedigree', href: pedigreeRoute({ pigeon: props.pigeon.id }).url },
];

const chart = ref<InstanceType<typeof PedigreeChart> | null>(null);
const filename = computed(() =>
    pedigreeFilename(props.pigeon.ring_number, props.meta.serial),
);

const { success, error } = useToast();

const updatingVisibility = ref(false);

const toggleVisibility = () => {
    router.patch(
        pedigreeVisibility({ pigeon: props.pigeon.id }).url,
        { is_public: !props.meta.is_public },
        {
            preserveScroll: true,
            onStart: () => (updatingVisibility.value = true),
            onFinish: () => (updatingVisibility.value = false),
            onSuccess: () =>
                success(
                    props.meta.is_public
                        ? 'Pedigree is now public'
                        : 'Pedigree is now private',
                ),
            onError: () => error('Could not update pedigree visibility'),
        },
    );
};

const copied = ref(false);

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.meta.share_url);
        copied.value = true;
        window.setTimeout(() => (copied.value = false), 2000);
    } catch {
        error('Could not copy the link');
    }
};

const selectAll = (event: FocusEvent) =>
    (event.target as HTMLInputElement).select();
</script>

<template>
    <Head :title="`Pedigree - ${pigeonLabel}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold sm:text-3xl">Pedigree</h1>
                    <p class="text-muted-foreground">
                        {{ pigeonLabel }} ·
                        <span class="font-mono">{{ meta.serial }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <PedigreeExportActions
                        :target="chart?.paper"
                        :filename="filename"
                    />
                    <Button variant="outline" as-child>
                        <a
                            :href="printPedigree({ pigeon: pigeon.id }).url"
                            target="_blank"
                            rel="noopener"
                        >
                            <Printer class="mr-2 h-4 w-4" />
                            Print
                        </a>
                    </Button>
                </div>
            </div>

            <!-- Sharing -->
            <Card>
                <CardContent
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                            :class="
                                meta.is_public
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            <Globe v-if="meta.is_public" class="h-5 w-5" />
                            <Lock v-else class="h-5 w-5" />
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <p class="font-medium">Public view</p>
                                <Badge
                                    :variant="
                                        meta.is_public ? 'default' : 'secondary'
                                    "
                                    >{{
                                        meta.is_public ? 'Public' : 'Private'
                                    }}</Badge
                                >
                            </div>
                            <p class="text-sm text-muted-foreground">
                                <template v-if="meta.is_public"
                                    >Anyone with the link or QR code can view
                                    this pedigree. Private notes are never
                                    shown.</template
                                >
                                <template v-else
                                    >Only you can view this pedigree. Anyone who
                                    scans the QR code will see that it is
                                    private.</template
                                >
                            </p>
                        </div>
                    </div>
                    <Button
                        :variant="meta.is_public ? 'outline' : 'default'"
                        class="shrink-0"
                        :disabled="updatingVisibility"
                        @click="toggleVisibility"
                    >
                        {{ meta.is_public ? 'Make private' : 'Make public' }}
                    </Button>
                </CardContent>
                <CardContent v-if="meta.is_public" class="flex gap-2">
                    <Input
                        :model-value="meta.share_url"
                        readonly
                        class="font-mono text-sm"
                        aria-label="Public pedigree link"
                        @focus="selectAll"
                    />
                    <Button
                        variant="outline"
                        size="icon"
                        class="shrink-0"
                        title="Copy link"
                        @click="copyLink"
                    >
                        <Check v-if="copied" class="h-4 w-4" />
                        <Copy v-else class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="shrink-0"
                        as-child
                    >
                        <a
                            :href="meta.share_url"
                            target="_blank"
                            rel="noopener"
                            title="Open public page"
                        >
                            <ExternalLink class="h-4 w-4" />
                        </a>
                    </Button>
                </CardContent>
            </Card>

            <PedigreeViewer>
                <PedigreeChart ref="chart" :tree="tree" :meta="meta" linkable />
            </PedigreeViewer>
            <p class="text-xs text-muted-foreground">
                Click a ring number to open that pigeon. Remarks appear on the
                pedigree; notes stay private. External parents and highlight
                colors are set on each pigeon's edit page.
            </p>
        </div>
    </AppLayout>
</template>
