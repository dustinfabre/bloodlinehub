<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, index as indexRoute, show as showRoute, printPedigree } from '@/routes/pigeons';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { type BreadcrumbItem } from '@/types';

interface PedigreeNode {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    gender: string | null;
    hatch_date: string | null;
    label: string;
    sire: PedigreeNode | null;
    dam: PedigreeNode | null;
    sire_name: string | null;
    sire_ring_number: string | null;
    sire_color: string | null;
    dam_name: string | null;
    dam_ring_number: string | null;
    dam_color: string | null;
}

interface ParentSummary {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
}

interface Pigeon {
    id: number;
    name: string | null;
    gender: string | null;
    hatch_date: string | null;
    status: string;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    remarks: string | null;
    notes: string | null;
    photos: string[];
    pedigree_image: string | null;
    sire: ParentSummary | null;
    dam: ParentSummary | null;
    sire_name: string | null;
    sire_ring_number: string | null;
    sire_color: string | null;
    sire_notes: string | null;
    dam_name: string | null;
    dam_ring_number: string | null;
    dam_color: string | null;
    dam_notes: string | null;
}

const props = defineProps<{
    pigeon: Pigeon;
    pedigree: PedigreeNode;
}>();

const pigeonLabel = computed(() => props.pigeon.name || props.pigeon.ring_number || props.pigeon.personal_number || `Pigeon #${props.pigeon.id}`);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: indexRoute().url },
    { title: pigeonLabel.value, href: showRoute({ pigeon: props.pigeon.id }).url },
];

// Status badge styles with new color scheme
const getStatusBadgeStyle = (status: string) => {
    const styles: Record<string, { bg: string; text: string; label: string }> = {
        stock: { bg: 'bg-blue-100', text: 'text-blue-700', label: 'In Stock' },
        racing: { bg: 'bg-green-100', text: 'text-green-700', label: 'Racing' },
        breeding: { bg: 'bg-pink-100', text: 'text-pink-700', label: 'Breeding' },
        injured: { bg: 'bg-orange-100', text: 'text-orange-700', label: 'Injured' },
        deceased: { bg: 'bg-red-100', text: 'text-red-700', label: 'Deceased' },
        flyaway: { bg: 'bg-amber-100', text: 'text-amber-700', label: 'Flyaway' },
        missing: { bg: 'bg-purple-100', text: 'text-purple-700', label: 'Missing' },
    };
    return styles[status] || { bg: 'bg-gray-100', text: 'text-gray-700', label: status };
};

const genderLabel = computed(() => {
    if (props.pigeon.gender === 'male') return 'Cock';
    if (props.pigeon.gender === 'female') return 'Hen';
    return 'Not specified';
});

const hatchDateFormatted = computed(() => {
    if (!props.pigeon.hatch_date) return 'Not recorded';
    return new Date(props.pigeon.hatch_date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

const getLabel = (node: PedigreeNode | null): string => {
    if (!node) return 'Unknown';
    return node.label;
};
</script>

<template>
    <Head :title="pigeonLabel" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold">{{ pigeonLabel }}</h1>
                <div class="flex gap-2">
                    <Link :href="printPedigree({ pigeon: pigeon.id }).url" target="_blank">
                        <Button variant="outline">
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print Pedigree
                        </Button>
                    </Link>
                    <Link :href="edit({ pigeon: pigeon.id }).url">
                        <Button>Edit pigeon</Button>
                    </Link>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- General Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>General Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="pigeon.name">
                            <p class="text-sm text-muted-foreground">Name</p>
                            <p class="font-medium">{{ pigeon.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Gender</p>
                            <Badge variant="outline">{{ genderLabel }}</Badge>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Hatch Date</p>
                            <p class="font-medium">{{ hatchDateFormatted }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Status</p>
                            <span 
                                :class="[getStatusBadgeStyle(pigeon.status).bg, getStatusBadgeStyle(pigeon.status).text, 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium']"
                            >
                                {{ getStatusBadgeStyle(pigeon.status).label }}
                            </span>
                        </div>
                        <div v-if="pigeon.ring_number">
                            <p class="text-sm text-muted-foreground">Ring Number</p>
                            <p class="font-medium">{{ pigeon.ring_number }}</p>
                        </div>
                        <div v-if="pigeon.personal_number">
                            <p class="text-sm text-muted-foreground">Personal Number</p>
                            <p class="font-medium">{{ pigeon.personal_number }}</p>
                        </div>
                        <div v-if="pigeon.color">
                            <p class="text-sm text-muted-foreground">Color</p>
                            <p class="font-medium">{{ pigeon.color }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Parents -->
                <Card>
                    <CardHeader>
                        <CardTitle>Parents</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div v-if="pigeon.sire">
                            <p class="text-sm font-semibold text-muted-foreground">Sire (Father)</p>
                            <Link :href="showRoute({ pigeon: pigeon.sire.id }).url" class="text-primary hover:underline">
                                {{ pigeon.sire.name || pigeon.sire.ring_number || pigeon.sire.personal_number || `Pigeon #${pigeon.sire.id}` }}
                            </Link>
                            <p v-if="pigeon.sire.color" class="text-sm text-muted-foreground">{{ pigeon.sire.color }}</p>
                        </div>
                        <div v-else-if="pigeon.sire_name || pigeon.sire_ring_number">
                            <p class="text-sm font-semibold text-muted-foreground">Sire (Father) - Reference</p>
                            <p class="font-medium">{{ pigeon.sire_name || 'Unknown' }}</p>
                            <p v-if="pigeon.sire_ring_number" class="text-sm text-muted-foreground">{{ pigeon.sire_ring_number }}</p>
                            <p v-if="pigeon.sire_color" class="text-sm text-muted-foreground">{{ pigeon.sire_color }}</p>
                        </div>

                        <div v-if="pigeon.dam">
                            <p class="text-sm font-semibold text-muted-foreground">Dam (Mother)</p>
                            <Link :href="showRoute({ pigeon: pigeon.dam.id }).url" class="text-primary hover:underline">
                                {{ pigeon.dam.name || pigeon.dam.ring_number || pigeon.dam.personal_number || `Pigeon #${pigeon.dam.id}` }}
                            </Link>
                            <p v-if="pigeon.dam.color" class="text-sm text-muted-foreground">{{ pigeon.dam.color }}</p>
                        </div>
                        <div v-else-if="pigeon.dam_name || pigeon.dam_ring_number">
                            <p class="text-sm font-semibold text-muted-foreground">Dam (Mother) - Reference</p>
                            <p class="font-medium">{{ pigeon.dam_name || 'Unknown' }}</p>
                            <p v-if="pigeon.dam_ring_number" class="text-sm text-muted-foreground">{{ pigeon.dam_ring_number }}</p>
                            <p v-if="pigeon.dam_color" class="text-sm text-muted-foreground">{{ pigeon.dam_color }}</p>
                        </div>

                        <p v-if="!pigeon.sire && !pigeon.sire_name && !pigeon.dam && !pigeon.dam_name" class="text-sm text-muted-foreground">No parent information available</p>
                    </CardContent>
                </Card>

                <!-- Additional Info -->
                <Card v-if="pigeon.remarks || pigeon.notes" class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Additional Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="pigeon.remarks">
                            <p class="text-sm text-muted-foreground">Remarks</p>
                            <p class="whitespace-pre-wrap">{{ pigeon.remarks }}</p>
                        </div>
                        <div v-if="pigeon.notes">
                            <p class="text-sm text-muted-foreground">Notes</p>
                            <p class="whitespace-pre-wrap">{{ pigeon.notes }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Photos -->
                <Card v-if="pigeon.photos && pigeon.photos.length > 0" class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Photos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                            <a v-for="(photo, index) in pigeon.photos" :key="index" :href="photo" target="_blank" class="overflow-hidden rounded-lg border">
                                <img :src="photo" :alt="`${pigeonLabel} photo ${index + 1}`" class="h-48 w-full object-cover transition-transform hover:scale-105" />
                            </a>
                        </div>
                    </CardContent>
                </Card>

                <!-- Pedigree -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Pedigree Chart</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <!-- Uploaded Pedigree Image -->
                        <div v-if="pigeon.pedigree_image" class="mb-6">
                            <a :href="pigeon.pedigree_image" target="_blank" class="block overflow-hidden rounded-lg border">
                                <img :src="pigeon.pedigree_image" :alt="`${pigeonLabel} pedigree`" class="w-full object-contain transition-transform hover:scale-105" />
                            </a>
                            <p class="mt-2 text-center text-sm text-muted-foreground">Click to view full size</p>
                        </div>

                        <!-- Generated Pedigree Tree -->
                        <div class="overflow-x-auto">
                            <div class="min-w-[900px] space-y-4">
                                <!-- Generation 2 (Parents) -->
                                <div class="grid grid-cols-2 gap-3">
                                    <!-- Sire -->
                                    <div v-if="pedigree.sire || pedigree.sire_name" class="rounded border border-blue-200 bg-blue-50/50 p-3">
                                        <div class="flex items-center gap-2">
                                            <Link v-if="pedigree.sire" :href="showRoute({ pigeon: pedigree.sire.id }).url" class="font-medium text-primary hover:underline">
                                                {{ getLabel(pedigree.sire) }}
                                            </Link>
                                            <span v-else class="font-medium">{{ pedigree.sire_name || 'Unknown Sire' }}</span>
                                            <Badge variant="outline" class="text-xs">Sire</Badge>
                                        </div>
                                        <p v-if="pedigree.sire?.color || pedigree.sire_color" class="text-sm text-muted-foreground">
                                            {{ pedigree.sire?.color || pedigree.sire_color }}
                                        </p>
                                    </div>
                                    <div v-else class="rounded border border-dashed p-3 text-center text-sm text-muted-foreground">
                                        Sire unknown
                                    </div>

                                    <!-- Dam -->
                                    <div v-if="pedigree.dam || pedigree.dam_name" class="rounded border border-pink-200 bg-pink-50/50 p-3">
                                        <div class="flex items-center gap-2">
                                            <Link v-if="pedigree.dam" :href="showRoute({ pigeon: pedigree.dam.id }).url" class="font-medium text-primary hover:underline">
                                                {{ getLabel(pedigree.dam) }}
                                            </Link>
                                            <span v-else class="font-medium">{{ pedigree.dam_name || 'Unknown Dam' }}</span>
                                            <Badge variant="outline" class="text-xs">Dam</Badge>
                                        </div>
                                        <p v-if="pedigree.dam?.color || pedigree.dam_color" class="text-sm text-muted-foreground">
                                            {{ pedigree.dam?.color || pedigree.dam_color }}
                                        </p>
                                    </div>
                                    <div v-else class="rounded border border-dashed p-3 text-center text-sm text-muted-foreground">
                                        Dam unknown
                                    </div>
                                </div>

                                <!-- Generation 3 (Grandparents) -->
                                <div class="grid grid-cols-4 gap-2">
                                    <!-- Sire's Sire -->
                                    <div v-if="pedigree.sire?.sire || pedigree.sire?.sire_name" class="rounded border border-slate-200 bg-slate-50/50 p-2">
                                        <Link v-if="pedigree.sire?.sire" :href="showRoute({ pigeon: pedigree.sire.sire.id }).url" class="text-sm font-medium text-primary hover:underline">
                                            {{ getLabel(pedigree.sire.sire) }}
                                        </Link>
                                        <span v-else class="text-sm font-medium">{{ pedigree.sire?.sire_name || 'Unknown' }}</span>
                                    </div>
                                    <div v-else class="rounded border border-dashed p-2 text-center text-xs text-muted-foreground">—</div>

                                    <!-- Sire's Dam -->
                                    <div v-if="pedigree.sire?.dam || pedigree.sire?.dam_name" class="rounded border border-slate-200 bg-slate-50/50 p-2">
                                        <Link v-if="pedigree.sire?.dam" :href="showRoute({ pigeon: pedigree.sire.dam.id }).url" class="text-sm font-medium text-primary hover:underline">
                                            {{ getLabel(pedigree.sire.dam) }}
                                        </Link>
                                        <span v-else class="text-sm font-medium">{{ pedigree.sire?.dam_name || 'Unknown' }}</span>
                                    </div>
                                    <div v-else class="rounded border border-dashed p-2 text-center text-xs text-muted-foreground">—</div>

                                    <!-- Dam's Sire -->
                                    <div v-if="pedigree.dam?.sire || pedigree.dam?.sire_name" class="rounded border border-slate-200 bg-slate-50/50 p-2">
                                        <Link v-if="pedigree.dam?.sire" :href="showRoute({ pigeon: pedigree.dam.sire.id }).url" class="text-sm font-medium text-primary hover:underline">
                                            {{ getLabel(pedigree.dam.sire) }}
                                        </Link>
                                        <span v-else class="text-sm font-medium">{{ pedigree.dam?.sire_name || 'Unknown' }}</span>
                                    </div>
                                    <div v-else class="rounded border border-dashed p-2 text-center text-xs text-muted-foreground">—</div>

                                    <!-- Dam's Dam -->
                                    <div v-if="pedigree.dam?.dam || pedigree.dam?.dam_name" class="rounded border border-slate-200 bg-slate-50/50 p-2">
                                        <Link v-if="pedigree.dam?.dam" :href="showRoute({ pigeon: pedigree.dam.dam.id }).url" class="text-sm font-medium text-primary hover:underline">
                                            {{ getLabel(pedigree.dam.dam) }}
                                        </Link>
                                        <span v-else class="text-sm font-medium">{{ pedigree.dam?.dam_name || 'Unknown' }}</span>
                                    </div>
                                    <div v-else class="rounded border border-dashed p-2 text-center text-xs text-muted-foreground">—</div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
