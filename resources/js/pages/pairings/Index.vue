<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import GoldenPairBadge from '@/components/GoldenPairBadge.vue';
import type { GoldenPairInfo } from '@/types/lineage';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { index as pairingsIndex, create as pairingsCreate, show as pairingsShow, edit as pairingsEdit, destroy as pairingsDestroy } from '@/routes/pairings';
import { show as pigeonsShow } from '@/routes/pigeons';
import { index as locationsIndex } from '@/routes/locations';
import { MapPin } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';
import { type BreadcrumbItem } from '@/types';

interface Pigeon {
    id: number;
    name: string;
    ring_number: string;
    bloodline?: string;
}

interface Pairing {
    id: number;
    pair_name: string;
    status: 'active' | 'inactive';
    current_clutch_number: number;
    started_at: string;
    ended_at?: string;
    sire: Pigeon;
    dam: Pigeon;
    offspring: Pigeon[];
    golden_pair: GoldenPairInfo | null;
    breeding_location: { id: number; name: string } | null;
}

interface BreedingLocationTab {
    id: number;
    name: string;
    pairings_count: number;
}

interface PaginatedPairings {
    data: Pairing[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    pairings: PaginatedPairings;
    // Locations marked as breeding locations, one tab each
    breedingLocations: BreedingLocationTab[];
    // Pairs under the current filters across every location
    allCount: number;
    filters: {
        status?: string;
        pair_name?: string;
        search?: string;
        per_page?: number;
        location?: number | null;
    };
}

const props = defineProps<Props>();
const page = usePage();
const { success } = useToast();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Breeding', href: pairingsIndex().url },
];

const search = ref(props.filters.search || '');
const pairName = ref(props.filters.pair_name || '');
const status = ref(props.filters.status || '');
const perPage = ref(props.filters.per_page || 20);
const location = ref<number | null>(props.filters.location ?? null);
const showFilters = ref(false);
const pairingToDelete = ref<Pairing | null>(null);

const activeFilterCount = computed(() => {
    let count = 0;
    if (props.filters.status) count++;
    if (props.filters.pair_name) count++;
    return count;
});

const applyFilters = () => {
    router.get(pairingsIndex().url, {
        search: search.value || undefined,
        pair_name: pairName.value || undefined,
        status: status.value || undefined,
        per_page: perPage.value,
        location: location.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    search.value = '';
    pairName.value = '';
    status.value = '';
    applyFilters();
};

// Show the pairs bred at one breeding location, or every pair
const selectLocation = (id: number | null) => {
    location.value = id;
    applyFilters();
};

const activeLocation = computed(() => props.breedingLocations.find(loc => loc.id === location.value) ?? null);

// A pair created from a location tab is bred there, and its sire and dam are moved there
const createHref = computed(() => pairingsCreate({ query: { location: location.value } }).url);

const handleSearch = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (event.type === 'keyup' && (event as KeyboardEvent).key !== 'Enter') {
        return;
    }
    applyFilters();
};

const deletePairing = (pairing: Pairing) => {
    if (!pairingToDelete.value) return;
    
    router.delete(pairingsDestroy(pairing.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            pairingToDelete.value = null;
            success('Pairing deleted successfully!');
        },
    });
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Pairings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Breeding Pairings</h1>
                    <p class="text-muted-foreground mt-1">Manage your breeding pairs and track offspring</p>
                </div>
                <Button asChild>
                    <Link :href="createHref">
                        {{ activeLocation ? `Create Pairing in ${activeLocation.name}` : 'Create Pairing' }}
                    </Link>
                </Button>
            </div>

            <!-- Breeding location tabs -->
            <div v-if="breedingLocations.length > 0" class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                <div class="flex min-w-max gap-1 border-b" role="tablist" aria-label="Breeding locations">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="location === null"
                        class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                        :class="location === null ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                        @click="selectLocation(null)"
                    >
                        All
                        <Badge variant="secondary" class="px-1.5 text-xs">{{ allCount }}</Badge>
                    </button>
                    <button
                        v-for="loc in breedingLocations"
                        :key="loc.id"
                        type="button"
                        role="tab"
                        :aria-selected="location === loc.id"
                        class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                        :class="location === loc.id ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                        @click="selectLocation(loc.id)"
                    >
                        <MapPin class="h-3.5 w-3.5" />
                        {{ loc.name }}
                        <Badge variant="secondary" class="px-1.5 text-xs">{{ loc.pairings_count }}</Badge>
                    </button>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Tip: mark a location as a breeding location on the
                <Link :href="locationsIndex().url" class="underline underline-offset-2 hover:text-foreground">Locations</Link>
                page to give it its own tab here.
            </p>

            <!-- Search and Filters -->
            <div class="space-y-4">
                <div class="flex gap-3">
                    <div class="flex-1">
                        <Input
                            v-model="search"
                            placeholder="Search pigeons..."
                            @keyup.enter="applyFilters"
                            class="w-full"
                        />
                    </div>
                    <Button @click="showFilters = !showFilters" variant="outline">
                        Filters
                        <Badge v-if="activeFilterCount > 0" variant="secondary" class="ml-2">
                            {{ activeFilterCount }}
                        </Badge>
                    </Button>
                    <Select v-model="perPage" @update:modelValue="applyFilters">
                        <SelectTrigger class="w-32">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="10">10</SelectItem>
                            <SelectItem :value="20">20</SelectItem>
                            <SelectItem :value="50">50</SelectItem>
                            <SelectItem :value="100">100</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Advanced Filters -->
                <Card v-if="showFilters">
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Filter pairings by status and name</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="status">Status</Label>
                            <Select v-model="status">
                                <SelectTrigger id="status">
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All</SelectItem>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label for="pair_name">Pair Name</Label>
                            <Input
                                id="pair_name"
                                v-model="pairName"
                                placeholder="Search by pair name..."
                            />
                        </div>
                    </CardContent>
                    <CardFooter class="flex justify-between">
                        <Button @click="clearFilters" variant="outline">Clear Filters</Button>
                        <Button @click="applyFilters">Apply Filters</Button>
                    </CardFooter>
                </Card>
            </div>

            <!-- Pairings Grid -->
            <div v-if="pairings.data.length > 0" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="pairing in pairings.data" :key="pairing.id" class="hover:shadow-lg transition-shadow">
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <CardTitle class="text-lg">{{ pairing.pair_name }}</CardTitle>
                                <CardDescription>Clutch #{{ pairing.current_clutch_number }}</CardDescription>
                                <GoldenPairBadge
                                    v-if="pairing.golden_pair?.golden"
                                    class="mt-2"
                                    :winners="pairing.golden_pair.winner_count"
                                    :offspring="pairing.golden_pair.offspring"
                                />
                            </div>
                            <Badge :variant="pairing.status === 'active' ? 'default' : 'secondary'">
                                {{ pairing.status }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Sire -->
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Sire (Male)</p>
                            <Link
                                :href="pigeonsShow({ pigeon: pairing.sire.id }).url"
                                class="text-blue-600 hover:underline font-medium"
                            >
                                {{ pairing.sire.name }}
                            </Link>
                            <p class="text-sm text-muted-foreground">{{ pairing.sire.ring_number }}</p>
                            <p v-if="pairing.sire.bloodline" class="text-xs text-muted-foreground">
                                {{ pairing.sire.bloodline }}
                            </p>
                        </div>

                        <Separator />

                        <!-- Dam -->
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Dam (Female)</p>
                            <Link
                                :href="pigeonsShow({ pigeon: pairing.dam.id }).url"
                                class="text-blue-600 hover:underline font-medium"
                            >
                                {{ pairing.dam.name }}
                            </Link>
                            <p class="text-sm text-muted-foreground">{{ pairing.dam.ring_number }}</p>
                            <p v-if="pairing.dam.bloodline" class="text-xs text-muted-foreground">
                                {{ pairing.dam.bloodline }}
                            </p>
                        </div>

                        <Separator />

                        <!-- Info -->
                        <div class="space-y-1 text-sm">
                            <p v-if="pairing.breeding_location && !activeLocation">
                                <span class="font-medium">Location:</span> {{ pairing.breeding_location.name }}
                            </p>
                            <p><span class="font-medium">Started:</span> {{ formatDate(pairing.started_at) }}</p>
                            <p v-if="pairing.ended_at">
                                <span class="font-medium">Ended:</span> {{ formatDate(pairing.ended_at) }}
                            </p>
                            <p><span class="font-medium">Offspring:</span> {{ pairing.offspring.length }}</p>
                        </div>
                    </CardContent>
                    <CardFooter class="flex gap-2">
                        <Button asChild variant="outline" size="sm" class="flex-1">
                            <Link :href="pairingsShow({ pairing: pairing.id }).url">View</Link>
                        </Button>
                        <Button asChild variant="outline" size="sm" class="flex-1">
                            <Link :href="pairingsEdit({ pairing: pairing.id }).url">Edit</Link>
                        </Button>
                        <Dialog>
                            <DialogTrigger as-child>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    @click="pairingToDelete = pairing"
                                >
                                    Delete
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Delete Pairing</DialogTitle>
                                    <DialogDescription>
                                        Are you sure you want to delete this pairing? This action cannot be undone.
                                        {{ pairing.status === 'active' ? 'The pigeons will be set back to Stock status.' : '' }}
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button variant="outline" @click="pairingToDelete = null">Cancel</Button>
                                    <Button variant="destructive" @click="deletePairing(pairing)">Delete</Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </CardFooter>
                </Card>
            </div>

            <!-- Empty State -->
            <Card v-else>
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <p class="text-muted-foreground mb-4">
                        {{ activeLocation ? `No pairings in ${activeLocation.name}.` : 'No pairings found.' }}
                    </p>
                    <Button asChild>
                        <Link :href="createHref">
                            {{ activeLocation ? `Create a Pairing in ${activeLocation.name}` : 'Create Your First Pairing' }}
                        </Link>
                    </Button>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="pairings.last_page > 1" class="flex items-center justify-between">
                <p class="text-sm text-muted-foreground">
                    Showing {{ ((pairings.current_page - 1) * pairings.per_page) + 1 }} to
                    {{ Math.min(pairings.current_page * pairings.per_page, pairings.total) }} of
                    {{ pairings.total }} results
                </p>
                <div class="flex gap-2">
                    <Button
                        v-for="page in pairings.last_page"
                        :key="page"
                        :variant="page === pairings.current_page ? 'default' : 'outline'"
                        size="sm"
                        @click="router.get(pairingsIndex({ query: { ...filters, page } }).url)"
                    >
                        {{ page }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
