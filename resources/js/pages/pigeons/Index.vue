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
import { Plus, Search, Filter, Eye, Pencil, Trash2, Bird, X, AlertTriangle } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';
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
    color: string | null;
    hatch_date: string | null;
    color_tag?: {
        id: number;
        name: string;
        color: string;
    } | null;
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

interface ColorTag {
    id: number;
    name: string;
    color: string;
}

const props = defineProps<{
    pigeons: PaginatedPigeons;
    bloodlines: BloodlineOption[];
    colorTags: ColorTag[];
    colors: string[];
    filters: {
        search?: string;
        status?: string;
        bloodline?: string;
        gender?: string;
        per_page: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: '/pigeons' },
];

const showFilters = ref(false);
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');
const bloodline = ref(props.filters.bloodline || 'all');
const gender = ref(props.filters.gender || 'all');
const perPage = ref(String(props.filters.per_page));

const applyFilters = () => {
    router.get('/pigeons', {
        search: search.value || undefined,
        status: status.value === 'all' ? undefined : status.value,
        bloodline: bloodline.value === 'all' ? undefined : bloodline.value,
        gender: gender.value === 'all' ? undefined : gender.value,
        per_page: perPage.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    search.value = '';
    status.value = 'all';
    bloodline.value = 'all';
    gender.value = 'all';
    perPage.value = '12';
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

const hasActiveFilters = () => {
    return search.value || (status.value && status.value !== 'all') || (bloodline.value && bloodline.value !== 'all') || (gender.value && gender.value !== 'all');
};

// Quick Add modal state
const showQuickAddModal = ref(false);
const isQuickAdding = ref(false);
const quickAddForm = ref({
    ring_number: '',
    gender: '' as string,
    status: 'stock',
});
const quickAddErrors = ref<Record<string, string>>({});

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
        status: 'stock',
    };
    quickAddErrors.value = {};
    showQuickAddModal.value = true;
};

const closeQuickAddModal = () => {
    showQuickAddModal.value = false;
    quickAddForm.value = {
        ring_number: '',
        gender: '',
        status: 'stock',
    };
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
        status: quickAddForm.value.status,
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
                                    {{ [search, status !== 'all' && status, bloodline !== 'all' && bloodline, gender !== 'all' && gender].filter(Boolean).length }}
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
                    <div v-if="showFilters" class="mt-4 grid gap-4 border-t pt-4 sm:grid-cols-3">
                        <div class="space-y-2">
                            <Label for="gender">Gender</Label>
                            <Select v-model="gender">
                                <SelectTrigger id="gender">
                                    <SelectValue placeholder="All genders" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All genders</SelectItem>
                                    <SelectItem value="male">Male (Cock)</SelectItem>
                                    <SelectItem value="female">Female (Hen)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label for="status">Status</Label>
                            <Select v-model="status">
                                <SelectTrigger id="status">
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All statuses</SelectItem>
                                    <SelectItem value="stock">In Stock</SelectItem>
                                    <SelectItem value="racing">Racing</SelectItem>
                                    <SelectItem value="breeding">Breeding</SelectItem>
                                    <SelectItem value="injured">Injured</SelectItem>
                                    <SelectItem value="deceased">Deceased</SelectItem>
                                    <SelectItem value="flyaway">Flyaway</SelectItem>
                                    <SelectItem value="missing">Missing</SelectItem>
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
                                    <SelectItem value="all">All bloodlines</SelectItem>
                                    <SelectItem
                                        v-for="line in bloodlines"
                                        :key="line.id"
                                        :value="String(line.id)"
                                    >
                                        {{ line.name }}
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
                    class="overflow-hidden transition-all hover:shadow-md"
                    :style="pigeon.color_tag ? { borderLeftWidth: '4px', borderLeftColor: pigeon.color_tag.color } : {}"
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
                        </div>
                        <div class="flex flex-wrap gap-1 mt-2">
                            <span 
                                :class="[getStatusBadgeStyle(pigeon.status).bg, getStatusBadgeStyle(pigeon.status).text, 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium']"
                            >
                                {{ getStatusBadgeStyle(pigeon.status).label }}
                            </span>
                            <span 
                                v-if="pigeon.color_tag"
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :style="{ backgroundColor: pigeon.color_tag.color + '20', color: pigeon.color_tag.color, border: '1px solid ' + pigeon.color_tag.color }"
                            >
                                <span class="size-2 rounded-full" :style="{ backgroundColor: pigeon.color_tag.color }"></span>
                                {{ pigeon.color_tag.name }}
                            </span>
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
            <DialogContent class="sm:max-w-md">
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
                    <div class="space-y-2">
                        <Label for="quick-status">Status *</Label>
                        <select 
                            id="quick-status" 
                            v-model="quickAddForm.status" 
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="stock">In Stock</option>
                            <option value="racing">Racing</option>
                            <option value="breeding">Breeding</option>
                            <option value="injured">Injured</option>
                            <option value="deceased">Deceased</option>
                            <option value="flyaway">Flyaway</option>
                            <option value="missing">Missing</option>
                        </select>
                        <p v-if="quickAddErrors.status" class="text-sm text-destructive">{{ quickAddErrors.status }}</p>
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
