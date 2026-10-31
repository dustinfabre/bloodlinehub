<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, editPedigree, index as indexRoute, show as showRoute, pedigree as pedigreeRoute } from '@/routes/pigeons';
import { Head, Link } from '@inertiajs/vue3';
import { Network, Pencil } from 'lucide-vue-next';
import { computed } from 'vue';
import { type BreadcrumbItem } from '@/types';

interface ColorTag {
    id: number;
    name: string;
    color: string;
}

interface ParentSummary {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    remarks: string | null;
    notes: string | null;
    color_tag: ColorTag | null;
}

interface ParentDisplay {
    key: 'sire' | 'dam';
    title: string;
    linked: ParentSummary | null;
    label: string;
    details: string;
    remarks: string | null;
    notes: string | null;
    // Notes typed on this pigeon's own record about a linked parent
    recordNotes: string | null;
    highlight: ColorTag | null;
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
    photo_url: string | null;
    pedigree_images: string[] | null;
    sire: ParentSummary | null;
    dam: ParentSummary | null;
    sire_name: string | null;
    sire_ring_number: string | null;
    sire_color: string | null;
    sire_notes: string | null;
    sire_color_tag: ColorTag | null;
    dam_name: string | null;
    dam_ring_number: string | null;
    dam_color: string | null;
    dam_notes: string | null;
    dam_color_tag: ColorTag | null;
    color_tag: ColorTag | null;
    pedigree: { serial: string; is_public: boolean; visibility: 'private' | 'password' | 'public' } | null;
}

const props = defineProps<{
    pigeon: Pigeon;
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

const tagStyle = (tag: ColorTag) => ({
    backgroundColor: tag.color + '20',
    color: tag.color,
    border: '1px solid ' + tag.color,
});

const buildParent = (key: 'sire' | 'dam', title: string): ParentDisplay | null => {
    const linked = props.pigeon[key];
    if (linked) {
        return {
            key,
            title,
            linked,
            label: linked.name || linked.ring_number || linked.personal_number || `Pigeon #${linked.id}`,
            details: [linked.name ? linked.ring_number : null, linked.color].filter(Boolean).join(' · '),
            remarks: linked.remarks,
            notes: linked.notes,
            recordNotes: props.pigeon[`${key}_notes`],
            highlight: linked.color_tag,
        };
    }

    const name = props.pigeon[`${key}_name`];
    const ringNumber = props.pigeon[`${key}_ring_number`];
    const color = props.pigeon[`${key}_color`];
    const remarks = props.pigeon[`${key}_notes`];
    if (!name && !ringNumber && !color && !remarks) return null;

    // External parents only have remarks, which are shown on the pedigree
    return {
        key,
        title,
        linked: null,
        label: name || ringNumber || 'Unknown',
        details: [name ? ringNumber : null, color].filter(Boolean).join(' · '),
        remarks,
        notes: null,
        recordNotes: null,
        highlight: props.pigeon[`${key}_color_tag`],
    };
};

const parents = computed(() =>
    [buildParent('sire', 'Sire (Father)'), buildParent('dam', 'Dam (Mother)')].filter((parent): parent is ParentDisplay => parent !== null),
);
</script>

<template>
    <Head :title="pigeonLabel" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold">{{ pigeonLabel }}</h1>
                <div class="flex gap-2">
                    <Link :href="pedigreeRoute({ pigeon: pigeon.id }).url">
                        <Button variant="outline">
                            <Network class="mr-2 h-4 w-4" />
                            View Pedigree
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
                        <!-- Photo -->
                        <div v-if="pigeon.photo_url" class="mb-4">
                            <a :href="pigeon.photo_url" target="_blank" class="block overflow-hidden rounded-lg border">
                                <img 
                                    :src="pigeon.photo_url" 
                                    :alt="pigeonLabel" 
                                    class="h-48 w-full object-cover transition-transform hover:scale-105" 
                                />
                            </a>
                        </div>
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
                        <div v-if="pigeon.color_tag">
                            <p class="text-sm text-muted-foreground">Pedigree Highlight</p>
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium" :style="tagStyle(pigeon.color_tag)">
                                <span class="size-2 rounded-full" :style="{ backgroundColor: pigeon.color_tag.color }"></span>
                                {{ pigeon.color_tag.name }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Parents -->
                <Card>
                    <CardHeader>
                        <CardTitle>Parents</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div v-for="parent in parents" :key="parent.key" class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-muted-foreground">{{ parent.title }}</p>
                                <Badge v-if="!parent.linked" variant="outline" class="text-xs">External</Badge>
                                <span v-if="parent.highlight" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium" :style="tagStyle(parent.highlight)">
                                    <span class="size-2 rounded-full" :style="{ backgroundColor: parent.highlight.color }"></span>
                                    {{ parent.highlight.name }}
                                </span>
                            </div>
                            <Link v-if="parent.linked" :href="showRoute({ pigeon: parent.linked.id }).url" class="font-medium text-primary hover:underline">
                                {{ parent.label }}
                            </Link>
                            <p v-else class="font-medium">{{ parent.label }}</p>
                            <p v-if="parent.details" class="text-sm text-muted-foreground">{{ parent.details }}</p>
                            <div v-if="parent.remarks" class="rounded-md bg-muted/50 p-3">
                                <p class="text-xs font-medium text-muted-foreground">Remarks · shown on pedigree</p>
                                <p class="mt-1 whitespace-pre-wrap text-sm">{{ parent.remarks }}</p>
                            </div>
                            <div v-if="parent.notes" class="rounded-md bg-muted/50 p-3">
                                <p class="text-xs font-medium text-muted-foreground">Notes · private</p>
                                <p class="mt-1 whitespace-pre-wrap text-sm">{{ parent.notes }}</p>
                            </div>
                            <div v-if="parent.recordNotes" class="rounded-md bg-muted/50 p-3">
                                <p class="text-xs font-medium text-muted-foreground">Notes on this pigeon's record</p>
                                <p class="mt-1 whitespace-pre-wrap text-sm">{{ parent.recordNotes }}</p>
                            </div>
                        </div>

                        <p v-if="parents.length === 0" class="text-sm text-muted-foreground">No parent information available</p>
                    </CardContent>
                </Card>

                <!-- Additional Info -->
                <Card v-if="pigeon.remarks || pigeon.notes" class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Additional Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="pigeon.remarks">
                            <p class="text-sm text-muted-foreground">Remarks · shown on pedigree</p>
                            <p class="whitespace-pre-wrap">{{ pigeon.remarks }}</p>
                        </div>
                        <div v-if="pigeon.notes">
                            <p class="text-sm text-muted-foreground">Notes · private</p>
                            <p class="whitespace-pre-wrap">{{ pigeon.notes }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Pedigree -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-2">
                            <CardTitle>Pedigree</CardTitle>
                            <Badge v-if="pigeon.pedigree" :variant="pigeon.pedigree.visibility === 'private' ? 'secondary' : 'default'">
                                {{ { public: 'Public', password: 'Password', private: 'Private' }[pigeon.pedigree.visibility] }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="flex flex-col gap-3 rounded-lg border border-dashed p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium">5-generation pedigree chart</p>
                                <p class="text-sm text-muted-foreground">
                                    View it, download it as a PDF or image, and share it with a QR code.
                                    <template v-if="pigeon.pedigree">No. <span class="font-mono">{{ pigeon.pedigree.serial }}</span></template>
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <Link :href="editPedigree({ pigeon: pigeon.id }).url">
                                    <Button variant="outline">
                                        <Pencil class="mr-2 h-4 w-4" />
                                        Edit Pedigree
                                    </Button>
                                </Link>
                                <Link :href="pedigreeRoute({ pigeon: pigeon.id }).url">
                                    <Button>
                                        <Network class="mr-2 h-4 w-4" />
                                        View Pedigree
                                    </Button>
                                </Link>
                            </div>
                        </div>

                        <!-- Uploaded Pedigree Images -->
                        <div v-if="pigeon.pedigree_images && pigeon.pedigree_images.length > 0">
                            <p class="text-sm font-medium text-muted-foreground mb-2">Uploaded Pedigree Images</p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <a v-for="(image, index) in pigeon.pedigree_images" :key="index" :href="image" target="_blank" class="block overflow-hidden rounded-lg border">
                                    <img :src="image" :alt="`${pigeonLabel} pedigree ${index + 1}`" class="w-full object-contain transition-transform hover:scale-105" />
                                </a>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
