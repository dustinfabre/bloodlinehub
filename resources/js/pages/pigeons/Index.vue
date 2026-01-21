<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { type BreadcrumbItem } from '@/types';
import { Plus, Search, Filter, Eye, Pencil, Trash2, Bird, X } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';

interface Pigeon {
    id: number;
    name: string | null;
    bloodline: string | null;
    ring_number: string | null;
    personal_number: string | null;
    gender: string | null;
    status: string;
    pigeon_status: string;
    race_type: string;
    color: string | null;
    hatch_date: string | null;
    sire: {
        id: number;
        ring_number: string | null;
        personal_number: string | null;
        color: string | null;
    } | null;
    dam: {
        id: number;
        ring_number: string | null;
        personal_number: string | null;
        color: string | null;
    } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedPigeons {
    data: Pigeon[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    pigeons: PaginatedPigeons;
    bloodlines: string[];
    colors: string[];
    filters: {
        search?: string;
        status?: string;
        pigeon_status?: string;
        race_type?: string;
        bloodline?: string;
        per_page: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: '/pigeons' },
];

const showFilters = ref(false);
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const pigeonStatus = ref(props.filters.pigeon_status || '');
const raceType = ref(props.filters.race_type || '');
const bloodline = ref(props.filters.bloodline || '');
const perPage = ref(String(props.filters.per_page));

const applyFilters = () => {
    router.get('/pigeons', {
        search: search.value || undefined,
        status: status.value || undefined,
        pigeon_status: pigeonStatus.value || undefined,
        race_type: raceType.value || undefined,
        bloodline: bloodline.value || undefined,
        per_page: perPage.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    search.value = '';
    status.value = '';
    pigeonStatus.value = '';
    raceType.value = '';
    bloodline.value = '';
    perPage.value = '10';
    applyFilters();
};

watch(perPage, () => {
    applyFilters();
});

const pigeonLabel = (pigeon: Pigeon) =>
    pigeon.name || pigeon.ring_number || pigeon.personal_number || `Pigeon #${pigeon.id}`;

const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const statusBadgeVariant = (status: string) => {
    if (status === 'alive') return 'default';
    if (status === 'deceased') return 'secondary';
    if (status === 'missing') return 'destructive';
    return 'outline';
};

const pigeonStatusBadgeVariant = (status: string) => {
    if (status === 'racing') return 'default';
    if (status === 'breeding') return 'secondary';
    return 'outline';
};

const raceTypeBadge = (type: string) => {
    if (type === 'olr') return { label: 'OLR', variant: 'default' as const };
    if (type === 'south') return { label: 'South', variant: 'secondary' as const };
    if (type === 'north') return { label: 'North', variant: 'secondary' as const };
    if (type === 'summer') return { label: 'Summer', variant: 'secondary' as const };
    return { label: 'None', variant: 'outline' as const };
};

const { success } = useToast();

const handleDelete = (pigeon: Pigeon) => {
    if (!confirm(`Delete ${pigeonLabel(pigeon)}? This action cannot be undone.`)) return;
    router.delete(`/pigeons/${pigeon.id}`, {
        preserveScroll: true,
        onSuccess: () => success('Pigeon deleted successfully!'),
    });
};

const hasActiveFilters = () => {
    return search.value || status.value || pigeonStatus.value || raceType.value || bloodline.value;
};
</script>

<template>
    <Head title="Pigeons" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-foreground sm:text-2xl">Pigeons</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Manage your pigeon records ({{ pigeons.total }})
                    </p>
                </div>
                <Button as-child class="w-full sm:w-auto">
                    <Link href="/pigeons/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Pigeon
                    </Link>
                </Button>
            </div>

            <!-- Search and Filters -->
            <Card>
                <CardHeader class="pb-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex-1 max-w-md">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="search"
                                    @keyup.enter="applyFilters"
                                    placeholder="Search by name, ring number, bloodline..."
                                    class="pl-9"
                                />
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button variant="outline" @click="applyFilters" class="flex-1 sm:flex-none">
                                <Search class="mr-2 h-4 w-4" />
                                Search
                            </Button>
                            <Button variant="outline" @click="showFilters = !showFilters" class="flex-1 sm:flex-none">
                                <Filter class="mr-2 h-4 w-4" />
                                Filters
                                <Badge v-if="hasActiveFilters()" variant="secondary" class="ml-2">
                                    {{ [search, status, pigeonStatus, raceType, bloodline].filter(Boolean).length }}
                                </Badge>
                            </Button>
                            <Button
                                v-if="hasActiveFilters()"
                                variant="ghost"
                                @click="clearFilters"
                                class="flex-1 sm:flex-none"
                            >
                                <X class="mr-2 h-4 w-4" />
                                Clear
                            </Button>
                        </div>
                    </div>

                    <!-- Advanced Filters -->
                    <div v-if="showFilters" class="mt-4 grid gap-4 border-t pt-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="space-y-2">
                            <Label for="status">Status</Label>
                            <Select v-model="status">
                                <SelectTrigger id="status">
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All statuses</SelectItem>
                                    <SelectItem value="alive">Alive</SelectItem>
                                    <SelectItem value="deceased">Deceased</SelectItem>
                                    <SelectItem value="missing">Missing</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label for="pigeon_status">Pigeon Status</Label>
                            <Select v-model="pigeonStatus">
                                <SelectTrigger id="pigeon_status">
                                    <SelectValue placeholder="All types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All types</SelectItem>
                                    <SelectItem value="racing">Racing</SelectItem>
                                    <SelectItem value="breeding">Breeding</SelectItem>
                                    <SelectItem value="stock">Stock</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label for="race_type">Race Type</Label>
                            <Select v-model="raceType">
                                <SelectTrigger id="race_type">
                                    <SelectValue placeholder="All types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All types</SelectItem>
                                    <SelectItem value="olr">OLR</SelectItem>
                                    <SelectItem value="south">South</SelectItem>
                                    <SelectItem value="north">North</SelectItem>
                                    <SelectItem value="summer">Summer</SelectItem>
                                    <SelectItem value="none">None</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label for="bloodline">Bloodline</Label>
                            <Select v-model="bloodline">
                                <SelectTrigger id="bloodline">
                                    <SelectValue placeholder="All bloodlines" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All bloodlines</SelectItem>
                                    <SelectItem
                                        v-for="line in bloodlines"
                                        :key="line"
                                        :value="line"
                                    >
                                        {{ line }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            <!-- Pagination Controls -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <Label for="per_page" class="text-sm text-muted-foreground whitespace-nowrap">Show:</Label>
                    <Select v-model="perPage">
                        <SelectTrigger id="per_page" class="w-24">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="12">12</SelectItem>
                            <SelectItem value="24">24</SelectItem>
                            <SelectItem value="58">58</SelectItem>
                            <SelectItem value="116">116</SelectItem>
                        </SelectContent>
                    </Select>
                    <span class="text-sm text-muted-foreground">
                        of {{ pigeons.total }} pigeons
                    </span>
                </div>
                
                <div class="text-sm text-muted-foreground">
                    Page {{ pigeons.current_page }} of {{ pigeons.last_page }}
                </div>
            </div>

            <!-- No results message -->
            <Card v-if="pigeons.data.length === 0">
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <Bird class="h-12 w-12 text-muted-foreground/60" />
                    <h3 class="mt-4 font-semibold">No pigeons found</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <template v-if="hasActiveFilters()">
                            Try adjusting your filters or search query.
                        </template>
                        <template v-else>
                            Get started by adding your first pigeon.
                        </template>
                    </p>
                    <Button v-if="!hasActiveFilters()" as-child class="mt-4">
                        <Link href="/pigeons/create">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Pigeon
                        </Link>
                    </Button>
                </CardContent>
            </Card>

            <!-- Pigeon Cards -->
            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="pigeon in pigeons.data"
                    :key="pigeon.id"
                    class="group relative overflow-hidden transition-all hover:shadow-md"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <CardTitle class="text-base truncate">
                                    {{ pigeonLabel(pigeon) }}
                                </CardTitle>
                                <CardDescription v-if="pigeon.bloodline" class="mt-1">
                                    {{ pigeon.bloodline }}
                                </CardDescription>
                            </div>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity sm:opacity-100">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="`/pigeons/${pigeon.id}`">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="`/pigeons/${pigeon.id}/edit`">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="sm" @click="handleDelete(pigeon)">
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1 mt-2">
                            <Badge :variant="statusBadgeVariant(pigeon.status)" class="text-xs">
                                {{ pigeon.status }}
                            </Badge>
                            <Badge :variant="pigeonStatusBadgeVariant(pigeon.pigeon_status)" class="text-xs">
                                {{ pigeon.pigeon_status }}
                            </Badge>
                            <Badge :variant="raceTypeBadge(pigeon.race_type).variant" class="text-xs">
                                {{ raceTypeBadge(pigeon.race_type).label }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div v-if="pigeon.ring_number" class="flex justify-between">
                            <span class="text-muted-foreground">Ring Number:</span>
                            <span class="font-mono">{{ pigeon.ring_number }}</span>
                        </div>
                        <div v-if="pigeon.personal_number" class="flex justify-between">
                            <span class="text-muted-foreground">Personal #:</span>
                            <span class="font-mono">{{ pigeon.personal_number }}</span>
                        </div>
                        <div v-if="pigeon.color" class="flex justify-between">
                            <span class="text-muted-foreground">Color:</span>
                            <span>{{ pigeon.color }}</span>
                        </div>
                        <div v-if="pigeon.hatch_date" class="flex justify-between">
                            <span class="text-muted-foreground">Hatch Date:</span>
                            <span>{{ formatDate(pigeon.hatch_date) }}</span>
                        </div>
                        <div v-if="pigeon.gender" class="flex justify-between">
                            <span class="text-muted-foreground">Gender:</span>
                            <span class="capitalize">{{ pigeon.gender }}</span>
                        </div>
                        <div v-if="pigeon.sire" class="flex justify-between border-t pt-2 mt-2">
                            <span class="text-muted-foreground">Sire:</span>
                            <Link
                                :href="`/pigeons/${pigeon.sire.id}`"
                                class="font-mono text-xs hover:text-primary hover:underline"
                            >
                                {{ pigeon.sire.ring_number || pigeon.sire.personal_number }}
                            </Link>
                        </div>
                        <div v-if="pigeon.dam" class="flex justify-between">
                            <span class="text-muted-foreground">Dam:</span>
                            <Link
                                :href="`/pigeons/${pigeon.dam.id}`"
                                class="font-mono text-xs hover:text-primary hover:underline"
                            >
                                {{ pigeon.dam.ring_number || pigeon.dam.personal_number }}
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div v-if="pigeons.last_page > 1" class="flex flex-wrap items-center justify-center gap-1">
                <template v-for="(link, index) in pigeons.links" :key="index">
                    <Button
                        v-if="link.url"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        @click="router.visit(link.url)"
                        :disabled="!link.url"
                        v-html="link.label"
                        class="min-w-[2.5rem]"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
