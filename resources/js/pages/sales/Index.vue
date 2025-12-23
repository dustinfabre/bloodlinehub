<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { index as salesIndex } from '@/routes/sales';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { type BreadcrumbItem } from '@/types';
import { Mail, Phone, DollarSign, Image as ImageIcon } from 'lucide-vue-next';

interface ParentSummary {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
}

interface Owner {
    id: number;
    name: string;
    email: string;
}

interface Pigeon {
    id: number;
    name: string | null;
    gender: string | null;
    hatch_date: string | null;
    color: string | null;
    ring_number: string | null;
    personal_number: string | null;
    race_type: string;
    photos: string[] | null;
    pedigree_image: string | null;
    sale_price: string | null;
    hide_price: boolean;
    sale_description: string | null;
    sire: ParentSummary | null;
    dam: ParentSummary | null;
    sire_name: string | null;
    dam_name: string | null;
    owner: Owner;
}

interface Props {
    pigeons: Pigeon[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Sales & Auctions',
        href: salesIndex().url,
    },
];

const searchQuery = ref('');
const genderFilter = ref<string>('all');
const raceTypeFilter = ref<string>('all');

const filteredPigeons = computed(() => {
    return props.pigeons.filter((pigeon) => {
        const matchesSearch = searchQuery.value === '' || 
            (pigeon.name?.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
            (pigeon.ring_number?.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
            (pigeon.personal_number?.toLowerCase().includes(searchQuery.value.toLowerCase()));

        const matchesGender = genderFilter.value === 'all' || pigeon.gender === genderFilter.value;
        const matchesRaceType = raceTypeFilter.value === 'all' || pigeon.race_type === raceTypeFilter.value;

        return matchesSearch && matchesGender && matchesRaceType;
    });
});

const pigeonLabel = (pigeon: Pigeon) =>
    pigeon.name || pigeon.ring_number || pigeon.personal_number || `Pigeon #${pigeon.id}`;

const parentLabel = (pigeon: Pigeon, parent: 'sire' | 'dam') => {
    if (pigeon[parent]) {
        const p = pigeon[parent]!;
        return p.name || p.ring_number || p.personal_number || `#${p.id}`;
    }
    if (parent === 'sire' && pigeon.sire_name) return pigeon.sire_name;
    if (parent === 'dam' && pigeon.dam_name) return pigeon.dam_name;
    return 'Unknown';
};

const primaryImage = (pigeon: Pigeon) => {
    if (pigeon.photos && pigeon.photos.length > 0) {
        return `/storage/${pigeon.photos[0]}`;
    }
    if (pigeon.pedigree_image) {
        return `/storage/${pigeon.pedigree_image}`;
    }
    return null;
};

const formatPrice = (price: string | null) => {
    if (!price) return '';
    return `$${parseFloat(price).toFixed(2)}`;
};

const formatAge = (hatchDate: string | null) => {
    if (!hatchDate) return 'Age unknown';
    const birth = new Date(hatchDate);
    const now = new Date();
    const years = now.getFullYear() - birth.getFullYear();
    const months = now.getMonth() - birth.getMonth();
    
    if (years === 0 && months === 0) return 'Less than a month old';
    if (years === 0) return `${months} month${months === 1 ? '' : 's'} old`;
    if (months < 0) return `${years - 1} year${years === 1 ? '' : 's'} old`;
    return years === 1 ? '1 year old' : `${years} years old`;
};

const genderBadgeVariant = (gender: string | null) => {
    if (gender === 'male') return 'default';
    if (gender === 'female') return 'secondary';
    return 'outline';
};
</script>

<template>
    <Head title="Sales & Auctions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">
                        Sales & Auctions
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Browse pigeons available for sale
                    </p>
                </div>
            </div>

            <!-- Filters -->
            <Card>
                <CardHeader>
                    <CardTitle>Filters</CardTitle>
                    <CardDescription>Search and filter available pigeons</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <Input
                                v-model="searchQuery"
                                placeholder="Search by name, ring, or personal number..."
                                type="text"
                            />
                        </div>
                        <div>
                            <select
                                v-model="genderFilter"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option value="all">All genders</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        <div>
                            <select
                                v-model="raceTypeFilter"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option value="all">All race types</option>
                                <option value="short">Short distance</option>
                                <option value="middle">Middle distance</option>
                                <option value="long">Long distance</option>
                                <option value="marathon">Marathon</option>
                                <option value="show">Show/Exhibition</option>
                            </select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Results count -->
            <div class="text-sm text-muted-foreground">
                {{ filteredPigeons.length }} pigeon{{ filteredPigeons.length === 1 ? '' : 's' }} available
            </div>

            <!-- Empty state -->
            <div v-if="filteredPigeons.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed p-12 text-center">
                <DollarSign class="mb-4 h-12 w-12 text-muted-foreground/50" />
                <h3 class="mb-1 text-lg font-medium">No pigeons found</h3>
                <p class="text-sm text-muted-foreground">
                    {{ searchQuery || genderFilter !== 'all' || raceTypeFilter !== 'all' 
                        ? 'Try adjusting your filters' 
                        : 'There are currently no pigeons available for sale' }}
                </p>
            </div>

            <!-- Pigeon grid -->
            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="pigeon in filteredPigeons" :key="pigeon.id" class="overflow-hidden">
                    <!-- Image -->
                    <div class="relative aspect-video w-full overflow-hidden bg-muted">
                        <img
                            v-if="primaryImage(pigeon)"
                            :src="primaryImage(pigeon)!"
                            :alt="pigeonLabel(pigeon)"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <ImageIcon class="h-12 w-12 text-muted-foreground/30" />
                        </div>
                    </div>

                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <CardTitle class="truncate">{{ pigeonLabel(pigeon) }}</CardTitle>
                                <CardDescription class="mt-1">
                                    {{ formatAge(pigeon.hatch_date) }}
                                </CardDescription>
                            </div>
                            <Badge :variant="genderBadgeVariant(pigeon.gender)">
                                {{ pigeon.gender || 'Unknown' }}
                            </Badge>
                        </div>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <!-- Price -->
                        <div v-if="!pigeon.hide_price && pigeon.sale_price" class="text-2xl font-bold text-primary">
                            {{ formatPrice(pigeon.sale_price) }}
                        </div>
                        <div v-else class="text-sm text-muted-foreground">
                            Contact seller for price
                        </div>

                        <!-- Details -->
                        <div class="space-y-2 text-sm">
                            <div v-if="pigeon.color" class="flex items-center justify-between">
                                <span class="text-muted-foreground">Color:</span>
                                <span class="font-medium capitalize">{{ pigeon.color }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">Race type:</span>
                                <span class="font-medium capitalize">{{ pigeon.race_type }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">Sire:</span>
                                <span class="font-medium truncate max-w-[180px]">{{ parentLabel(pigeon, 'sire') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">Dam:</span>
                                <span class="font-medium truncate max-w-[180px]">{{ parentLabel(pigeon, 'dam') }}</span>
                            </div>
                        </div>

                        <!-- Description -->
                        <p v-if="pigeon.sale_description" class="text-sm text-muted-foreground line-clamp-3">
                            {{ pigeon.sale_description }}
                        </p>

                        <!-- Owner info -->
                        <div class="border-t pt-4">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Contact Seller
                            </div>
                            <div class="space-y-1 text-sm">
                                <div class="font-medium">{{ pigeon.owner.name }}</div>
                                <a
                                    :href="`mailto:${pigeon.owner.email}?subject=Inquiry about ${pigeonLabel(pigeon)}`"
                                    class="flex items-center gap-2 text-primary hover:underline"
                                >
                                    <Mail class="h-4 w-4" />
                                    {{ pigeon.owner.email }}
                                </a>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
