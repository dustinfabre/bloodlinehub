<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { type BreadcrumbItem } from '@/types';
import BloodlineMultiSelect from '@/components/BloodlineMultiSelect.vue';
import PigeonSearchResult from '@/components/PigeonSearchResult.vue';
import PigeonStatusMenu from '@/components/PigeonStatusMenu.vue';
import { PIGEON_STATUSES, statusStyle } from '@/lib/pigeon-status';
import type { PigeonSearchItem } from '@/types/pigeon-search';
import MultiSelectFilter, { type FilterOption, type FilterPreset } from '@/components/MultiSelectFilter.vue';
import MeritBadge from '@/components/MeritBadge.vue';
import { byImportance, topMerit } from '@/lib/merits';
import type { Merit, MeritTypeOption } from '@/types/lineage';
import { Plus, Search, SlidersHorizontal, Bird, X, AlertTriangle, LoaderCircle, MapPin } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';

interface DuplicateMatch {
    pigeon: {
        id: number;
        ring_number: string;
        personal_number: string | null;
        name: string | null;
        gender: string | null;
        status: string | null;
        bloodline: string | null;
        photo: string | null;
    };
    similarity: number;
}

interface BloodlineOption {
    id: number;
    name: string;
}

interface SelectedBloodline {
    id: number;
    name: string;
    is_primary: boolean;
}

interface Pigeon {
    id: number;
    name: string | null;
    bloodline: string | null;
    bloodlines?: SelectedBloodline[];
    ring_number: string | null;
    personal_number: string | null;
    gender: string | null;
    status: string;
    // When the bird got its current status, if recorded ("Missing since …")
    status_since: string | null;
    color: string | null;
    hatch_date: string | null;
    remarks: string | null;
    notes: string | null;
    photo_url: string | null;
    location: { id: number; name: string; type: string } | null;
    merits: Merit[];
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

type ParentPigeon = PigeonSearchItem;

interface LocationOption {
    id: number;
    name: string;
    type: string;
}

const props = defineProps<{
    pigeons: PaginatedPigeons;
    bloodlines: BloodlineOption[];
    locations: LocationOption[];
    meritTypes: MeritTypeOption[];
    sorts: { value: string; label: string }[];
    colors: string[];
    parentOptions: { sires: ParentPigeon[]; dams: ParentPigeon[] };
    // In stock, racing, breeding and injured: the list shows these unless another status filter is picked
    inLoftStatuses: string[];
    filters: {
        search?: string | null;
        status: string[];
        bloodline: string[];
        location: string[];
        merit: string[];
        // male / female / none (no gender recorded)
        gender: string[];
        per_page: number;
        sort: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: '/pigeons' },
];

const statusOptions: FilterOption[] = PIGEON_STATUSES.map(({ value, label, dotClass }) => ({ value, label, dotClass }));

// Deceased and missing: their cards say since when
const GONE = PIGEON_STATUSES.filter((status) => status.gone).map((status) => status.value);

const statusPresets = computed<FilterPreset[]>(() => [
    { label: 'In the loft', values: props.inLoftStatuses },
    {
        label: 'No longer in the loft',
        values: statusOptions.map((option) => option.value).filter((value) => !props.inLoftStatuses.includes(value)),
    },
]);

const bloodlineOptions = computed<FilterOption[]>(() =>
    props.bloodlines.map((line) => ({ value: String(line.id), label: line.name })),
);

const locationOptions = computed<FilterOption[]>(() => [
    ...props.locations.map((location) => ({ value: String(location.id), label: location.name })),
    { value: 'none', label: 'No location' },
]);

const meritOptions = computed<FilterOption[]>(() =>
    props.meritTypes.map((type) => ({
        value: type.value,
        label: `${type.label} (${type.code})`,
        dotColor: type.color,
    })),
);

// Gender is optional, so birds without one can be filtered too
const genderOptions: FilterOption[] = [
    { value: 'male', label: 'Cock (male)' },
    { value: 'female', label: 'Hen (female)' },
    { value: 'none', label: 'Ungendered' },
];

// Mobile only: on desktop the filter row is always visible
const showFilters = ref(false);
const search = ref(props.filters.search || '');
const statuses = ref<string[]>([...props.filters.status]);
const bloodlineIds = ref<string[]>([...props.filters.bloodline]);
const locationIds = ref<string[]>([...props.filters.location]);
const meritValues = ref<string[]>([...props.filters.merit]);
const genders = ref<string[]>([...props.filters.gender]);
const perPage = ref(String(props.filters.per_page));
// Sorting is not a filter: "Clear all" leaves it alone
const sort = ref(props.filters.sort || 'recent');
const isFiltering = ref(false);

// The default view: birds in the loft (deceased and missing birds are left out)
const showingInLoft = computed(
    () =>
        statuses.value.length === props.inLoftStatuses.length &&
        props.inLoftStatuses.every((status) => statuses.value.includes(status)),
);

const filterParams = () => ({
    search: search.value.trim() || undefined,
    // No status in the URL means "in the loft"; an empty selection means every status
    status: showingInLoft.value ? undefined : statuses.value.length ? statuses.value : 'all',
    bloodline: bloodlineIds.value.length ? bloodlineIds.value : undefined,
    location: locationIds.value.length ? locationIds.value : undefined,
    merit: meritValues.value.length ? meritValues.value : undefined,
    gender: genders.value.length ? genders.value : undefined,
    per_page: perPage.value === '12' ? undefined : perPage.value,
    sort: sort.value === 'recent' ? undefined : sort.value,
});

// Skip a request when nothing actually changed (e.g. the debounced search firing after "Clear")
let lastApplied = JSON.stringify(filterParams());

const applyFilters = () => {
    const params = filterParams();
    const serialized = JSON.stringify(params);
    if (serialized === lastApplied) return;
    lastApplied = serialized;

    router.get('/pigeons', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (isFiltering.value = true),
        onFinish: () => (isFiltering.value = false),
    });
};

const debouncedSearch = useDebounceFn(applyFilters, 350);

watch(search, debouncedSearch);
watch([statuses, bloodlineIds, locationIds, meritValues, genders, perPage, sort], applyFilters, { deep: true });

const activeFilterCount = computed(
    () =>
        (search.value.trim() ? 1 : 0) +
        (showingInLoft.value ? 0 : Math.max(statuses.value.length, 1)) +
        bloodlineIds.value.length +
        locationIds.value.length +
        meritValues.value.length +
        genders.value.length,
);

// Count shown on the mobile "Filters" toggle (search is visible outside the panel)
const panelFilterCount = computed(() => activeFilterCount.value - (search.value.trim() ? 1 : 0));

const clearFilters = () => {
    search.value = '';
    statuses.value = [...props.inLoftStatuses];
    bloodlineIds.value = [];
    locationIds.value = [];
    meritValues.value = [];
    genders.value = [];
};

const pigeonLabel = (pigeon: Pigeon) =>
    pigeon.name || pigeon.ring_number || pigeon.personal_number || `Pigeon #${pigeon.id}`;

const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

// A bird with merits gets a left border in the color of its most significant one, as on its pedigree
const meritAccent = (pigeon: Pigeon) => {
    const best = topMerit(pigeon.merits);
    return best ? { borderLeftWidth: '4px', borderLeftColor: best.color } : {};
};

const { success } = useToast();

// Delete confirmation modal state
const showDeleteModal = ref(false);
const pigeonToDelete = ref<Pigeon | null>(null);
const deleteConfirmInput = ref('');
const isDeleting = ref(false);

const deleteConfirmationRing = computed(() => {
    if (!pigeonToDelete.value) return '';
    return pigeonToDelete.value.ring_number || pigeonToDelete.value.personal_number || `#${pigeonToDelete.value.id}`;
});

const canConfirmDelete = computed(() => {
    return deleteConfirmInput.value.toUpperCase() === deleteConfirmationRing.value.toUpperCase();
});

const openDeleteModal = (pigeon: Pigeon) => {
    pigeonToDelete.value = pigeon;
    deleteConfirmInput.value = '';
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    pigeonToDelete.value = null;
    deleteConfirmInput.value = '';
    isDeleting.value = false;
};

const confirmDelete = () => {
    if (!pigeonToDelete.value || !canConfirmDelete.value) return;
    
    isDeleting.value = true;
    router.delete(`/pigeons/${pigeonToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            success('Pigeon deleted successfully!');
            closeDeleteModal();
        },
        onError: () => {
            isDeleting.value = false;
        },
    });
};

// Quick Add modal state
const showQuickAddModal = ref(false);
const isQuickAdding = ref(false);
const quickAddForm = ref({
    ring_number: '',
    gender: '' as string,
    sire_id: '' as string,
    dam_id: '' as string,
    notes: '',
});
const quickAddBloodlines = ref<{ id: number; name: string; is_primary: boolean }[]>([]);
const quickAddErrors = ref<Record<string, string>>({});

// Quick Add sire/dam search
const quickSireSearch = ref('');
const quickDamSearch = ref('');
const showQuickSireDropdown = ref(false);
const showQuickDamDropdown = ref(false);

const filteredQuickSires = computed(() => {
    if (!quickSireSearch.value) return props.parentOptions.sires;
    const search = quickSireSearch.value.toLowerCase();
    return props.parentOptions.sires.filter(s =>
        s.name?.toLowerCase().includes(search) ||
        s.ring_number?.toLowerCase().includes(search) ||
        s.personal_number?.toLowerCase().includes(search) ||
        s.bloodline?.toLowerCase().includes(search) ||
        s.color?.toLowerCase().includes(search)
    );
});

const filteredQuickDams = computed(() => {
    if (!quickDamSearch.value) return props.parentOptions.dams;
    const search = quickDamSearch.value.toLowerCase();
    return props.parentOptions.dams.filter(d =>
        d.name?.toLowerCase().includes(search) ||
        d.ring_number?.toLowerCase().includes(search) ||
        d.personal_number?.toLowerCase().includes(search) ||
        d.bloodline?.toLowerCase().includes(search) ||
        d.color?.toLowerCase().includes(search)
    );
});

const selectedQuickSire = computed(() => props.parentOptions.sires.find(s => s.id.toString() === quickAddForm.value.sire_id));
const selectedQuickDam = computed(() => props.parentOptions.dams.find(d => d.id.toString() === quickAddForm.value.dam_id));

const formatParentPigeon = (pigeon: ParentPigeon) => {
    const parts: string[] = [];
    if (pigeon.name) parts.push(pigeon.name);
    if (pigeon.ring_number) parts.push(pigeon.ring_number);
    if (pigeon.bloodline) parts.push(`- ${pigeon.bloodline}`);
    return parts.join(' ') || `Pigeon #${pigeon.id}`;
};

const selectQuickSire = (sire: ParentPigeon) => {
    quickAddForm.value.sire_id = sire.id.toString();
    quickSireSearch.value = formatParentPigeon(sire);
    showQuickSireDropdown.value = false;
};

const selectQuickDam = (dam: ParentPigeon) => {
    quickAddForm.value.dam_id = dam.id.toString();
    quickDamSearch.value = formatParentPigeon(dam);
    showQuickDamDropdown.value = false;
};

const clearQuickSire = () => {
    quickAddForm.value.sire_id = '';
    quickSireSearch.value = '';
};

const clearQuickDam = () => {
    quickAddForm.value.dam_id = '';
    quickDamSearch.value = '';
};

const handleQuickSireBlur = () => {
    window.setTimeout(() => showQuickSireDropdown.value = false, 200);
};

const handleQuickDamBlur = () => {
    window.setTimeout(() => showQuickDamDropdown.value = false, 200);
};

// Quick Add duplicate checking
const quickAddCheckingRing = ref(false);
const quickAddExactMatch = ref<DuplicateMatch['pigeon'] | null>(null);
const quickAddSimilarMatches = ref<DuplicateMatch[]>([]);
const quickAddDuplicateDismissed = ref(false);

const checkQuickAddRingNumber = async () => {
    if (!quickAddForm.value.ring_number || quickAddForm.value.ring_number.length < 3) {
        quickAddExactMatch.value = null;
        quickAddSimilarMatches.value = [];
        return;
    }

    quickAddCheckingRing.value = true;
    try {
        const response = await axios.get('/pigeons/check-ring-number', {
            params: { ring_number: quickAddForm.value.ring_number }
        });
        
        quickAddExactMatch.value = response.data.exact_match;
        quickAddSimilarMatches.value = response.data.similar_matches || [];
    } catch (error) {
        console.error('Error checking ring number:', error);
    } finally {
        quickAddCheckingRing.value = false;
    }
};

// Reset duplicate check when ring number changes
watch(() => quickAddForm.value.ring_number, () => {
    quickAddExactMatch.value = null;
    quickAddSimilarMatches.value = [];
    quickAddDuplicateDismissed.value = false;
});

const openQuickAddModal = () => {
    quickAddForm.value = {
        ring_number: '',
        gender: '',
        sire_id: '',
        dam_id: '',
        notes: '',
    };
    quickAddBloodlines.value = [];
    quickSireSearch.value = '';
    quickDamSearch.value = '';
    quickAddErrors.value = {};
    showQuickAddModal.value = true;
};

const closeQuickAddModal = () => {
    showQuickAddModal.value = false;
    quickAddForm.value = {
        ring_number: '',
        gender: '',
        sire_id: '',
        dam_id: '',
        notes: '',
    };
    quickAddBloodlines.value = [];
    quickSireSearch.value = '';
    quickDamSearch.value = '';
    quickAddErrors.value = {};
    isQuickAdding.value = false;
};

const submitQuickAdd = () => {
    quickAddErrors.value = {};
    
    // Basic validation
    if (!quickAddForm.value.ring_number.trim()) {
        quickAddErrors.value.ring_number = 'Ring number is required';
        return;
    }
    
    isQuickAdding.value = true;
    router.post('/pigeons', {
        ring_number: quickAddForm.value.ring_number.toUpperCase(),
        gender: quickAddForm.value.gender || null,
        status: 'stock',
        sire_id: quickAddForm.value.sire_id || null,
        dam_id: quickAddForm.value.dam_id || null,
        bloodlines: quickAddBloodlines.value,
        notes: quickAddForm.value.notes || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            success('Pigeon added successfully!');
            closeQuickAddModal();
        },
        onError: (errors) => {
            quickAddErrors.value = errors as Record<string, string>;
            isQuickAdding.value = false;
        },
    });
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
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Button variant="outline" @click="openQuickAddModal" class="w-full sm:w-auto">
                        <Plus class="mr-2 h-4 w-4" />
                        Quick Add
                    </Button>
                    <Button as-child class="w-full sm:w-auto">
                        <Link href="/pigeons/create">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Pigeon
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Search and Filters: results update as you type or pick a filter -->
            <Card class="gap-0 py-4">
                <CardContent class="space-y-4 px-4">
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="search"
                                type="search"
                                placeholder="Search by name, ring number, bloodline, color..."
                                class="pr-9 pl-9"
                                aria-label="Search pigeons"
                            />
                            <LoaderCircle
                                v-if="isFiltering"
                                class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin text-muted-foreground"
                            />
                            <button
                                v-else-if="search"
                                type="button"
                                class="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                aria-label="Clear search"
                                @click="search = ''"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <Button
                            variant="outline"
                            class="lg:hidden"
                            :aria-expanded="showFilters"
                            aria-controls="pigeon-filters"
                            @click="showFilters = !showFilters"
                        >
                            <SlidersHorizontal class="h-4 w-4" />
                            <span class="hidden sm:inline">Filters</span>
                            <Badge v-if="panelFilterCount" variant="secondary">{{ panelFilterCount }}</Badge>
                        </Button>
                    </div>

                    <div
                        id="pigeon-filters"
                        :class="[showFilters ? 'grid' : 'hidden', 'gap-3 sm:grid-cols-2 lg:grid lg:grid-cols-3 xl:grid-cols-5']"
                    >
                        <div class="space-y-1.5">
                            <Label for="filter-status" class="text-xs text-muted-foreground">Status</Label>
                            <MultiSelectFilter
                                id="filter-status"
                                v-model="statuses"
                                :options="statusOptions"
                                :presets="statusPresets"
                                placeholder="All statuses"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="filter-bloodline" class="text-xs text-muted-foreground">Bloodline</Label>
                            <MultiSelectFilter
                                id="filter-bloodline"
                                v-model="bloodlineIds"
                                :options="bloodlineOptions"
                                placeholder="All bloodlines"
                                empty-text="No bloodlines yet"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="filter-location" class="text-xs text-muted-foreground">Location</Label>
                            <MultiSelectFilter
                                id="filter-location"
                                v-model="locationIds"
                                :options="locationOptions"
                                placeholder="All locations"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="filter-merit" class="text-xs text-muted-foreground">Merit</Label>
                            <MultiSelectFilter
                                id="filter-merit"
                                v-model="meritValues"
                                :options="meritOptions"
                                placeholder="Any merit"
                                :searchable="false"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="filter-gender" class="text-xs text-muted-foreground">Gender</Label>
                            <MultiSelectFilter
                                id="filter-gender"
                                v-model="genders"
                                :options="genderOptions"
                                placeholder="Any gender"
                                :searchable="false"
                            />
                        </div>
                    </div>

                    <div v-if="activeFilterCount" class="flex items-center justify-between gap-2 border-t pt-3 text-sm">
                        <span class="text-muted-foreground">
                            {{ pigeons.total }} {{ pigeons.total === 1 ? 'match' : 'matches' }}
                        </span>
                        <Button variant="ghost" size="sm" @click="clearFilters">
                            <X class="h-4 w-4" />
                            Clear all
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Pagination Controls -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="flex items-center gap-2">
                        <Label for="sort" class="text-sm text-muted-foreground whitespace-nowrap">Sort:</Label>
                        <Select v-model="sort">
                            <SelectTrigger id="sort" class="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in sorts" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-center gap-2">
                        <Label for="per_page" class="text-sm text-muted-foreground whitespace-nowrap">Show:</Label>
                        <Select v-model="perPage">
                            <SelectTrigger id="per_page" class="w-24">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="12">12</SelectItem>
                                <SelectItem value="24">24</SelectItem>
                                <SelectItem value="48">48</SelectItem>
                                <SelectItem value="96">96</SelectItem>
                            </SelectContent>
                        </Select>
                        <span class="text-sm text-muted-foreground">
                            of {{ pigeons.total }} {{ showingInLoft ? 'pigeons in the loft' : 'pigeons' }}
                        </span>
                    </div>
                    <button
                        v-if="showingInLoft"
                        type="button"
                        class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        @click="statuses = []"
                    >
                        Include deceased and missing
                    </button>
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
                        <template v-if="activeFilterCount">
                            Try adjusting your filters or search query.
                        </template>
                        <template v-else>
                            Get started by adding your first pigeon.
                        </template>
                    </p>
                    <Button v-if="!activeFilterCount" as-child class="mt-4">
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
                    class="overflow-hidden transition-all hover:shadow-md"
                    :style="meritAccent(pigeon)"
                >
                    <!-- Photo at top of card -->
                    <div v-if="pigeon.photo_url" class="relative aspect-square bg-muted">
                        <img 
                            :src="pigeon.photo_url" 
                            :alt="pigeonLabel(pigeon)" 
                            class="h-full w-full object-cover"
                        />
                        <div class="absolute bottom-2 left-2 right-2 flex flex-wrap gap-1">
                            <PigeonStatusMenu :pigeon="pigeon" :label="pigeonLabel(pigeon)" class="shadow-sm" />
                        </div>
                    </div>
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
                        </div>
                        <!-- Status (a menu to change it) goes here when there is no photo to sit on -->
                        <div v-if="!pigeon.photo_url" class="flex flex-wrap gap-1 mt-2">
                            <PigeonStatusMenu :pigeon="pigeon" :label="pigeonLabel(pigeon)" />
                        </div>
                        <div v-if="pigeon.merits.length" class="mt-2 flex flex-wrap gap-1">
                            <MeritBadge v-for="(merit, index) in byImportance(pigeon.merits)" :key="`${merit.type}-${index}`" :merit="merit" />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div v-if="pigeon.status_since && GONE.includes(pigeon.status)" class="flex justify-between">
                            <span class="text-muted-foreground">{{ statusStyle(pigeon.status).label }} since:</span>
                            <span>{{ formatDate(pigeon.status_since) }}</span>
                        </div>
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
                        <div v-if="pigeon.location" class="flex justify-between gap-2">
                            <span class="text-muted-foreground">Location:</span>
                            <span class="inline-flex min-w-0 items-center gap-1">
                                <MapPin class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                <span class="truncate">{{ pigeon.location.name }}</span>
                            </span>
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
                        <!-- Remarks -->
                        <div v-if="pigeon.remarks" class="border-t pt-2 mt-2">
                            <p class="text-xs text-muted-foreground italic line-clamp-2">{{ pigeon.remarks }}</p>
                        </div>
                    </CardContent>
                    <CardFooter class="flex gap-2">
                        <Button asChild variant="outline" size="sm" class="flex-1">
                            <Link :href="`/pigeons/${pigeon.id}`">View</Link>
                        </Button>
                        <Button asChild variant="outline" size="sm" class="flex-1">
                            <Link :href="`/pigeons/${pigeon.id}/edit`">Edit</Link>
                        </Button>
                        <Button variant="destructive" size="sm" @click="openDeleteModal(pigeon)">
                            Delete
                        </Button>
                    </CardFooter>
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

        <!-- Delete Confirmation Modal -->
        <Dialog :open="showDeleteModal" @update:open="closeDeleteModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <AlertTriangle class="h-5 w-5" />
                        Delete Pigeon
                    </DialogTitle>
                    <DialogDescription class="text-left">
                        You are about to delete pigeon with ring number: <strong>{{ deleteConfirmationRing }}</strong>.<br />
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="confirm-delete">To confirm, type the ring number below:</Label>
                        <Input
                            id="confirm-delete"
                            v-model="deleteConfirmInput"
                            :placeholder="`Type ${deleteConfirmationRing} to confirm`"
                            class="font-mono uppercase"
                            autocomplete="off"
                        />
                    </div>
                </div>
                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button variant="outline" @click="closeDeleteModal" class="w-full sm:w-auto">
                        Cancel
                    </Button>
                    <Button 
                        variant="destructive" 
                        :disabled="!canConfirmDelete || isDeleting"
                        @click="confirmDelete"
                        class="w-full sm:w-auto"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Quick Add Modal -->
        <Dialog :open="showQuickAddModal" @update:open="closeQuickAddModal">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Plus class="h-5 w-5" />
                        Quick Add Pigeon
                    </DialogTitle>
                    <DialogDescription>
                        Add a pigeon with minimal information. You can edit full details later.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitQuickAdd" class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="quick-ring-number">Ring Number *</Label>
                        <div class="relative">
                            <Input
                                id="quick-ring-number"
                                v-model="quickAddForm.ring_number"
                                placeholder="Ring number or type 'PERSO' for personal pigeons"
                                class="font-mono uppercase"
                                :class="{ 'border-destructive': quickAddExactMatch }"
                                autocomplete="off"
                                @blur="checkQuickAddRingNumber"
                            />
                            <span v-if="quickAddCheckingRing" class="absolute right-3 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-4 w-4 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </span>
                        </div>
                        <p v-if="quickAddErrors.ring_number" class="text-sm text-destructive">{{ quickAddErrors.ring_number }}</p>
                        
                        <!-- Exact duplicate error -->
                        <div v-if="quickAddExactMatch" class="rounded-md border border-destructive bg-destructive/10 p-3 text-sm">
                            <p class="font-medium text-destructive">A pigeon with ring number {{ quickAddExactMatch.ring_number }} already exists.</p>
                            <div class="mt-2 flex items-center gap-2 text-muted-foreground">
                                <span v-if="quickAddExactMatch.name">{{ quickAddExactMatch.name }}</span>
                                <span v-if="quickAddExactMatch.gender">• {{ quickAddExactMatch.gender === 'male' ? 'Cock' : 'Hen' }}</span>
                                <span v-if="quickAddExactMatch.status">• {{ quickAddExactMatch.status }}</span>
                            </div>
                            <Link :href="`/pigeons/${quickAddExactMatch.id}`" class="mt-2 inline-flex items-center text-sm font-medium text-primary hover:underline">
                                View / Edit this pigeon →
                            </Link>
                        </div>
                        
                        <!-- Similar matches warning -->
                        <div v-if="!quickAddExactMatch && quickAddSimilarMatches.length > 0 && !quickAddDuplicateDismissed" class="rounded-md border border-amber-500 bg-amber-50 dark:bg-amber-950/20 p-3 text-sm">
                            <p class="font-medium text-amber-700 dark:text-amber-400 flex items-center gap-2">
                                <AlertTriangle class="h-4 w-4" />
                                Possible duplicate(s) found
                            </p>
                            <div class="mt-2 space-y-2">
                                <div v-for="match in quickAddSimilarMatches" :key="match.pigeon.id" class="flex items-center justify-between text-muted-foreground">
                                    <span>{{ match.pigeon.ring_number }} ({{ match.similarity }}% similar)</span>
                                    <Link :href="`/pigeons/${match.pigeon.id}`" class="text-xs font-medium text-primary hover:underline">
                                        View
                                    </Link>
                                </div>
                            </div>
                            <Button 
                                type="button" 
                                variant="ghost" 
                                size="sm" 
                                @click="quickAddDuplicateDismissed = true" 
                                class="mt-2 h-7 text-xs"
                            >
                                Continue anyway
                            </Button>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="quick-gender">Gender</Label>
                        <select 
                            id="quick-gender" 
                            v-model="quickAddForm.gender" 
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">Not specified</option>
                            <option value="male">Male (Cock)</option>
                            <option value="female">Female (Hen)</option>
                        </select>
                        <p v-if="quickAddErrors.gender" class="text-sm text-destructive">{{ quickAddErrors.gender }}</p>
                    </div>

                    <!-- Sire Selection -->
                    <div class="space-y-2 relative">
                        <Label for="quick-sire-search">Sire (Father)</Label>
                        <div class="relative">
                            <Input
                                id="quick-sire-search"
                                v-model="quickSireSearch"
                                @focus="showQuickSireDropdown = true"
                                @blur="handleQuickSireBlur"
                                placeholder="Search sires by name, ring, bloodline, or color..."
                                autocomplete="off"
                                class="w-full"
                                :class="{ 'pr-8': selectedQuickSire }"
                            />
                            <button
                                v-if="selectedQuickSire"
                                type="button"
                                @click="clearQuickSire"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <div
                            v-if="showQuickSireDropdown && filteredQuickSires.length > 0"
                            class="absolute z-50 w-full mt-1 bg-popover border rounded-lg shadow-lg max-h-48 overflow-auto"
                        >
                            <button
                                v-for="sire in filteredQuickSires"
                                :key="sire.id"
                                type="button"
                                @click="selectQuickSire(sire)"
                                class="w-full px-4 py-3 text-left hover:bg-accent border-b last:border-b-0 focus:outline-none focus:bg-accent"
                            >
                                <PigeonSearchResult :pigeon="sire" />
                            </button>
                        </div>
                        <div v-if="showQuickSireDropdown && filteredQuickSires.length === 0 && quickSireSearch" class="absolute z-50 w-full mt-1 bg-popover border rounded-lg shadow-lg p-4 text-center text-muted-foreground text-sm">
                            No sires found
                        </div>
                        <p v-if="quickAddErrors.sire_id" class="text-sm text-destructive">{{ quickAddErrors.sire_id }}</p>
                    </div>

                    <!-- Dam Selection -->
                    <div class="space-y-2 relative">
                        <Label for="quick-dam-search">Dam (Mother)</Label>
                        <div class="relative">
                            <Input
                                id="quick-dam-search"
                                v-model="quickDamSearch"
                                @focus="showQuickDamDropdown = true"
                                @blur="handleQuickDamBlur"
                                placeholder="Search dams by name, ring, bloodline, or color..."
                                autocomplete="off"
                                class="w-full"
                                :class="{ 'pr-8': selectedQuickDam }"
                            />
                            <button
                                v-if="selectedQuickDam"
                                type="button"
                                @click="clearQuickDam"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <div
                            v-if="showQuickDamDropdown && filteredQuickDams.length > 0"
                            class="absolute z-50 w-full mt-1 bg-popover border rounded-lg shadow-lg max-h-48 overflow-auto"
                        >
                            <button
                                v-for="dam in filteredQuickDams"
                                :key="dam.id"
                                type="button"
                                @click="selectQuickDam(dam)"
                                class="w-full px-4 py-3 text-left hover:bg-accent border-b last:border-b-0 focus:outline-none focus:bg-accent"
                            >
                                <PigeonSearchResult :pigeon="dam" />
                            </button>
                        </div>
                        <div v-if="showQuickDamDropdown && filteredQuickDams.length === 0 && quickDamSearch" class="absolute z-50 w-full mt-1 bg-popover border rounded-lg shadow-lg p-4 text-center text-muted-foreground text-sm">
                            No dams found
                        </div>
                        <p v-if="quickAddErrors.dam_id" class="text-sm text-destructive">{{ quickAddErrors.dam_id }}</p>
                    </div>

                    <!-- Bloodline -->
                    <div class="space-y-2">
                        <Label>Bloodline</Label>
                        <BloodlineMultiSelect
                            v-model="quickAddBloodlines"
                            :bloodlines="bloodlines"
                        />
                        <p v-if="quickAddErrors.bloodlines" class="text-sm text-destructive">{{ quickAddErrors.bloodlines }}</p>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label for="quick-notes">Notes <span class="font-normal text-muted-foreground">(private, not shown on pedigree)</span></Label>
                        <textarea
                            id="quick-notes"
                            v-model="quickAddForm.notes"
                            placeholder="Any quick notes..."
                            rows="2"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        ></textarea>
                        <p v-if="quickAddErrors.notes" class="text-sm text-destructive">{{ quickAddErrors.notes }}</p>
                    </div>
                    <DialogFooter class="flex-col gap-2 sm:flex-row pt-4">
                        <Button type="button" variant="outline" @click="closeQuickAddModal" class="w-full sm:w-auto">
                            Cancel
                        </Button>
                        <Button 
                            type="submit"
                            :disabled="isQuickAdding || !!quickAddExactMatch || (quickAddSimilarMatches.length > 0 && !quickAddDuplicateDismissed)"
                            class="w-full sm:w-auto"
                        >
                            {{ isQuickAdding ? 'Adding...' : 'Add Pigeon' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
