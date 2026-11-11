<script setup lang="ts">
import NotableRelatives from '@/components/pedigree/NotableRelatives.vue';
import PedigreeBreedingReport from '@/components/pedigree/PedigreeBreedingReport.vue';
import PedigreeChart from '@/components/pedigree/PedigreeChart.vue';
import PedigreeExportActions from '@/components/pedigree/PedigreeExportActions.vue';
import PedigreePhotos from '@/components/pedigree/PedigreePhotos.vue';
import PedigreeSharingPanel from '@/components/pedigree/PedigreeSharingPanel.vue';
import PedigreeViewer from '@/components/pedigree/PedigreeViewer.vue';
import PremiumBadge from '@/components/PremiumBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { pedigreeFilename } from '@/lib/pedigree';
import { GENERATED_AT, usePremium } from '@/lib/premium';
import { index as billing } from '@/routes/billing';
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
    BirdSummary,
    Family,
    PairingAnalysisReport,
} from '@/types/lineage';
import type {
    PedigreeMeta,
    PedigreeNode,
    PedigreeReportLinks,
    PedigreeSharing,
} from '@/types/pedigree';
import { Head, Link } from '@inertiajs/vue3';
import {
    Contact,
    ImageDown,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Printer,
    QrCode,
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
    // The owner has Premium: Save image, sharing online, contact details, COI and Pairing Analysis
    premium: boolean;
    tree: PedigreeNode;
    meta: PedigreeMeta;
    sharing: PedigreeSharing;
    // Any phone, email or address entered in Pedigree details, whether shown or hidden
    hasContactDetails: boolean;
    // Below the photos: the Pairing Analysis of the bird's parents (null unless both are recorded) and its Lineage
    analysis: PairingAnalysisReport | null;
    generations: number;
    lineage: { bird: BirdSummary; family: Family };
    descendantGenerations: number;
    ownerLinks: PedigreeReportLinks;
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

const { featureUrl } = usePremium();

// The sheet to export for Save image
const chart = ref<InstanceType<typeof PedigreeChart> | null>(null);
const filename = computed(() =>
    pedigreeFilename(props.pigeon.ring_number, props.meta.serial),
);
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
                    <!-- Save image only: the PDF download was taken off; Print can still save a PDF -->
                    <PedigreeExportActions
                        v-if="premium"
                        :target="chart?.paper"
                        :filename="filename"
                        :pdf="false"
                    />
                    <Button v-else variant="outline" as-child>
                        <Link :href="featureUrl('pedigree-export')">
                            <ImageDown class="mr-2 h-4 w-4" />
                            Save image
                            <PremiumBadge icon-only class="ml-1" />
                        </Link>
                    </Button>
                </div>
            </div>

            <PedigreeSharingPanel
                v-if="premium"
                :pigeon-id="pigeon.id"
                :pigeon-label="pigeon.ring_number || pigeonLabel"
                :share-url="meta.share_url"
                :sharing="sharing"
            />

            <!-- Without Premium: what the pedigree is missing, in one place -->
            <div
                v-else
                class="flex flex-col gap-3 rounded-lg border border-amber-500/30 bg-amber-500/5 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3">
                    <QrCode
                        class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-300"
                        aria-hidden="true"
                    />
                    <div class="text-sm">
                        <p
                            class="flex flex-wrap items-center gap-2 font-medium"
                        >
                            Share it online with a QR code
                            <PremiumBadge />
                        </p>
                        <p class="text-muted-foreground">
                            With Premium every pedigree gets a QR code and its
                            own page online, your contact details and the bird's
                            inbreeding (COI), and you can save it as an image.
                            Without it the pedigree says "Generated at
                            {{ GENERATED_AT }}".
                        </p>
                    </div>
                </div>
                <Button size="sm" class="shrink-0" as-child>
                    <Link :href="billing()">Get Premium</Link>
                </Button>
            </div>

            <div
                v-if="premium && !hasContactDetails"
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

            <!-- The pedigree first, then the photos, certificates and notable relatives that go with it, then the
                 Pairing Analysis of its parents and its Lineage -->
            <PedigreeViewer>
                <PedigreeChart ref="chart" :tree="tree" :meta="meta" linkable />
            </PedigreeViewer>
            <p class="text-xs text-muted-foreground">
                Click a ring number to open that pigeon. Remarks appear on the
                pedigree; notes stay private. Use Edit pedigree to fill in
                missing ancestors.
            </p>

            <PedigreePhotos
                :tree="tree"
                :photos="meta.photos"
                :relative-photos="meta.relative_photos"
                :documents="meta.documents"
                :add-photos-href="editPigeon({ pigeon: pigeon.id }).url"
            />

            <NotableRelatives
                :relatives="meta.notable_relatives"
                linkable
                :choose-href="`${editPedigree({ pigeon: pigeon.id }).url}#notable-relatives`"
            />

            <PedigreeBreedingReport
                :analysis="analysis"
                :generations="generations"
                :lineage="lineage"
                :descendant-generations="descendantGenerations"
                :links="ownerLinks"
            />
        </div>
    </AppLayout>
</template>
