<script setup lang="ts">
import PedigreeChart from '@/components/pedigree/PedigreeChart.vue';
import PedigreePhotos from '@/components/pedigree/PedigreePhotos.vue';
import PedigreeSharingPanel from '@/components/pedigree/PedigreeSharingPanel.vue';
import PedigreeViewer from '@/components/pedigree/PedigreeViewer.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit as editPedigreeDetails } from '@/routes/pedigree-details';
import {
    editPedigree,
    edit as editPigeon,
    index as indexRoute,
    pedigree as pedigreeRoute,
    printPedigree,
    show as showRoute,
} from '@/routes/pigeons';
import { type BreadcrumbItem } from '@/types';
import type {
    PedigreeMeta,
    PedigreeNode,
    PedigreeSharing,
} from '@/types/pedigree';
import { Head, Link } from '@inertiajs/vue3';
import { Contact, Mail, MapPin, Pencil, Phone, Printer } from 'lucide-vue-next';
import { computed } from 'vue';

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
    sharing: PedigreeSharing;
    // Any phone, email or address entered in Pedigree details, whether shown or hidden
    hasContactDetails: boolean;
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
                    <Button variant="outline" as-child>
                        <Link :href="editPedigree({ pigeon: pigeon.id }).url">
                            <Pencil class="mr-2 h-4 w-4" />
                            Edit pedigree
                        </Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="editPedigreeDetails().url">
                            <Contact class="mr-2 h-4 w-4" />
                            Contact details
                        </Link>
                    </Button>
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

            <PedigreeSharingPanel
                :pigeon-id="pigeon.id"
                :pigeon-label="pigeon.ring_number || pigeonLabel"
                :share-url="meta.share_url"
                :sharing="sharing"
            />

            <div
                v-if="!hasContactDetails"
                class="flex flex-col gap-3 rounded-lg border bg-muted/40 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex shrink-0 gap-1 pt-0.5 text-muted-foreground"
                        aria-hidden="true"
                    >
                        <Phone class="size-4" />
                        <Mail class="size-4" />
                        <MapPin class="size-4" />
                    </div>
                    <div class="text-sm">
                        <p class="font-medium">
                            No contact details on this pedigree yet
                        </p>
                        <p class="text-muted-foreground">
                            Add your phone number, email and address once in
                            Pedigree details and they appear at the bottom of
                            all your pedigrees, so buyers know how to reach you.
                        </p>
                    </div>
                </div>
                <Button size="sm" class="shrink-0" as-child>
                    <Link :href="editPedigreeDetails().url">
                        Add contact details
                    </Link>
                </Button>
            </div>

            <PedigreePhotos
                :tree="tree"
                :photos="meta.photos"
                :relative-photos="meta.relative_photos"
                :documents="meta.documents"
                :add-photos-href="editPigeon({ pigeon: pigeon.id }).url"
            />

            <PedigreeViewer>
                <PedigreeChart :tree="tree" :meta="meta" linkable />
            </PedigreeViewer>
            <p class="text-xs text-muted-foreground">
                Click a ring number to open that pigeon. Remarks appear on the
                pedigree; notes stay private. Use Edit pedigree to fill in
                missing ancestors.
            </p>
        </div>
    </AppLayout>
</template>
