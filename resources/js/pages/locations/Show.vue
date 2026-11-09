<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PigeonSearchResult from '@/components/PigeonSearchResult.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useToast } from '@/composables/useToast';
import { sexSymbol } from '@/lib/pedigree';
import { PIGEON_STATUSES, statusStyle } from '@/lib/pigeon-status';
import { index as locationsIndex, show as locationsShow } from '@/routes/locations';
import { store as locationPigeonsStore, destroy as locationPigeonsDestroy } from '@/routes/locations/pigeons';
import { index as pairingsIndex } from '@/routes/pairings';
import { show as pigeonsShow } from '@/routes/pigeons';
import { type BreadcrumbItem } from '@/types';
import type { PigeonSearchItem } from '@/types/pigeon-search';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRightLeft, Egg, MapPin, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface LocationDetails {
    id: number;
    name: string;
    type: 'manual' | 'olr';
    is_breeding: boolean;
    description: string | null;
    olr_race_id: number | null;
}

type LocationPigeon = PigeonSearchItem & { status: string };

// A bird that can be added here, and where it is now
type Candidate = LocationPigeon & { location_id: number | null; location_name: string | null };

const props = defineProps<{
    location: LocationDetails;
    pigeons: LocationPigeon[];
    // Loaded when the add picker first opens
    candidates?: Candidate[];
    otherLocations: { id: number; name: string }[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Locations', href: locationsIndex().url },
    { title: props.location.name, href: locationsShow({ location: props.location.id }).url },
]);

const { success, error } = useToast();

const goneStatuses = PIGEON_STATUSES.filter((status) => status.gone).map((status) => status.value);
const isGone = (pigeon: LocationPigeon) => goneStatuses.includes(pigeon.status);

const matches = (pigeon: PigeonSearchItem, query: string) => {
    if (!query) return true;
    const q = query.toLowerCase();
    return [pigeon.name, pigeon.ring_number, pigeon.personal_number, pigeon.bloodline, pigeon.color].some((value) =>
        value?.toLowerCase().includes(q),
    );
};

const toggle = (ids: number[], id: number) => {
    const index = ids.indexOf(id);
    if (index > -1) ids.splice(index, 1);
    else ids.push(id);
};

const plural = (count: number) => `${count} pigeon${count === 1 ? '' : 's'}`;

// ---- Birds here ----

const search = ref('');
const showGone = ref(false);
const selectedIds = ref<number[]>([]);

const goneCount = computed(() => props.pigeons.filter(isGone).length);
const inLoftCount = computed(() => props.pigeons.length - goneCount.value);

const visiblePigeons = computed(() =>
    props.pigeons.filter((pigeon) => (showGone.value || !isGone(pigeon)) && matches(pigeon, search.value)),
);

const allVisibleSelected = computed(
    () => visiblePigeons.value.length > 0 && visiblePigeons.value.every((pigeon) => selectedIds.value.includes(pigeon.id)),
);

const toggleAllVisible = () => {
    selectedIds.value = allVisibleSelected.value ? [] : visiblePigeons.value.map((pigeon) => pigeon.id);
};

const removing = ref(false);
const showRemoveConfirm = ref(false);

const remove = (ids: number[]) => {
    if (ids.length === 0) return;
    removing.value = true;
    router.delete(locationPigeonsDestroy({ location: props.location.id }).url, {
        data: { pigeon_ids: ids },
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = selectedIds.value.filter((id) => !ids.includes(id));
            showRemoveConfirm.value = false;
            success(`Removed ${plural(ids.length)} from ${props.location.name}.`);
        },
        onError: () => error('Could not remove those pigeons.'),
        onFinish: () => (removing.value = false),
    });
};

// ---- Add picker ----

const showAdd = ref(false);
const loadingCandidates = ref(false);
const pickerSearch = ref('');
// 'all', 'none' (birds with no location) or the id of the location they are at now
const pickerFrom = ref('all');
const pickedIds = ref<number[]>([]);
const adding = ref(false);

const openAdd = () => {
    pickerSearch.value = '';
    pickerFrom.value = 'all';
    pickedIds.value = [];
    showAdd.value = true;

    // Fetched fresh each time, since adding birds here changes the list
    loadingCandidates.value = true;
    router.reload({
        only: ['candidates'],
        onFinish: () => (loadingCandidates.value = false),
    });
};

const filteredCandidates = computed(() =>
    (props.candidates ?? []).filter((pigeon) => {
        if (pickerFrom.value === 'none' && pigeon.location_id !== null) return false;
        if (pickerFrom.value !== 'all' && pickerFrom.value !== 'none' && String(pigeon.location_id) !== pickerFrom.value) {
            return false;
        }
        return matches(pigeon, pickerSearch.value);
    }),
);

const allCandidatesPicked = computed(
    () => filteredCandidates.value.length > 0 && filteredCandidates.value.every((pigeon) => pickedIds.value.includes(pigeon.id)),
);

const toggleAllCandidates = () => {
    const shown = filteredCandidates.value.map((pigeon) => pigeon.id);
    pickedIds.value = allCandidatesPicked.value
        ? pickedIds.value.filter((id) => !shown.includes(id))
        : [...new Set([...pickedIds.value, ...shown])];
};

// Picked birds that are at another location now and will be moved here
const pickedTransfers = computed(
    () => (props.candidates ?? []).filter((pigeon) => pickedIds.value.includes(pigeon.id) && pigeon.location_id !== null).length,
);

const add = () => {
    if (pickedIds.value.length === 0) return;
    const count = pickedIds.value.length;
    const transfers = pickedTransfers.value;
    adding.value = true;
    router.post(
        locationPigeonsStore({ location: props.location.id }).url,
        { pigeon_ids: pickedIds.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showAdd.value = false;
                pickedIds.value = [];
                success(
                    `Added ${plural(count)} to ${props.location.name}.` +
                        (transfers > 0 ? ` ${transfers} moved from another location.` : ''),
                );
            },
            onError: (errors) => error(errors.pigeon_ids ?? 'Could not add those pigeons.'),
            onFinish: () => (adding.value = false),
        },
    );
};
</script>

<template>
    <Head :title="location.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div class="mx-auto w-full max-w-4xl space-y-6">
                <!-- Header -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <MapPin class="h-6 w-6 shrink-0 text-muted-foreground" />
                            <h1 class="truncate text-3xl font-bold tracking-tight">{{ location.name }}</h1>
                            <Badge v-if="location.type === 'olr'" variant="secondary">OLR</Badge>
                            <Badge v-if="location.is_breeding" variant="secondary" class="gap-1">
                                <Egg class="h-3 w-3" />
                                Breeding
                            </Badge>
                        </div>
                        <p v-if="location.description" class="mt-1 text-muted-foreground">{{ location.description }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ plural(inLoftCount) }} here
                            <template v-if="goneCount > 0"> · {{ goneCount }} deceased or missing</template>
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <Button v-if="location.is_breeding" asChild variant="outline">
                            <Link :href="pairingsIndex({ query: { location: location.id } }).url">View pairs</Link>
                        </Button>
                        <Button @click="openAdd">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Pigeons
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader class="gap-3">
                        <div>
                            <CardTitle>Pigeons</CardTitle>
                            <CardDescription>Birds kept at this location. Removing a bird leaves it with no location.</CardDescription>
                        </div>
                        <div v-if="pigeons.length > 0" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <Input v-model="search" placeholder="Search by ring, name, bloodline, color..." class="sm:flex-1" />
                            <Button
                                v-if="goneCount > 0"
                                variant="outline"
                                size="sm"
                                class="shrink-0"
                                @click="showGone = !showGone"
                            >
                                {{ showGone ? 'Hide' : 'Show' }} deceased &amp; missing ({{ goneCount }})
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <!-- Empty -->
                        <div
                            v-if="pigeons.length === 0"
                            class="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center"
                        >
                            <MapPin class="h-10 w-10 text-muted-foreground/40" />
                            <h3 class="mt-4 font-semibold">No pigeons here yet</h3>
                            <p class="mt-1 text-sm text-muted-foreground">Add birds to keep track of where they are.</p>
                            <Button class="mt-4" @click="openAdd">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Pigeons
                            </Button>
                        </div>

                        <template v-else>
                            <!-- Selection bar -->
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b pb-3">
                                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium">
                                    <Checkbox
                                        :model-value="allVisibleSelected"
                                        :disabled="visiblePigeons.length === 0"
                                        @update:model-value="toggleAllVisible"
                                    />
                                    Select all ({{ visiblePigeons.length }})
                                </label>
                                <Button
                                    v-if="selectedIds.length > 0"
                                    variant="outline"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    :disabled="removing"
                                    @click="showRemoveConfirm = true"
                                >
                                    Remove {{ selectedIds.length }} from location
                                </Button>
                            </div>

                            <p v-if="visiblePigeons.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                                No pigeons match your search.
                            </p>

                            <ul class="divide-y">
                                <li
                                    v-for="pigeon in visiblePigeons"
                                    :key="pigeon.id"
                                    class="flex items-center gap-3 py-3"
                                    :class="{ 'opacity-70': isGone(pigeon) }"
                                >
                                    <Checkbox
                                        :model-value="selectedIds.includes(pigeon.id)"
                                        :aria-label="`Select ${pigeon.ring_number || pigeon.name || 'pigeon'}`"
                                        @update:model-value="toggle(selectedIds, pigeon.id)"
                                    />
                                    <Link :href="pigeonsShow({ pigeon: pigeon.id }).url" class="min-w-0 flex-1 rounded-md hover:bg-accent/50 -mx-1 px-1 py-0.5">
                                        <PigeonSearchResult :pigeon="pigeon">
                                            <template #aside>
                                                <span v-if="sexSymbol(pigeon.gender ?? null)" class="shrink-0 text-sm text-muted-foreground">{{ sexSymbol(pigeon.gender ?? null) }}</span>
                                            </template>
                                            <!-- Under the title, so the ring number isn't cut off on phones. Most birds are in stock; only the others are labelled. -->
                                            <div v-if="pigeon.status !== 'stock'" class="pt-0.5">
                                                <span :class="['inline-block rounded-full px-2 py-0.5 text-xs font-medium', statusStyle(pigeon.status).className]">
                                                    {{ statusStyle(pigeon.status).label }}
                                                </span>
                                            </div>
                                        </PigeonSearchResult>
                                    </Link>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive"
                                        :disabled="removing"
                                        :title="`Remove from ${location.name}`"
                                        :aria-label="`Remove ${pigeon.ring_number || pigeon.name || 'pigeon'} from ${location.name}`"
                                        @click="remove([pigeon.id])"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </li>
                            </ul>
                        </template>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Add pigeons -->
        <Dialog v-model:open="showAdd">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add Pigeons to {{ location.name }}</DialogTitle>
                    <DialogDescription>Birds at another location are moved here.</DialogDescription>
                </DialogHeader>

                <div class="space-y-3">
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Input v-model="pickerSearch" placeholder="Search by ring, name, bloodline, color..." class="sm:flex-1" autofocus />
                        <select
                            v-model="pickerFrom"
                            aria-label="Where the birds are now"
                            class="flex h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:w-48"
                        >
                            <option value="all">From anywhere</option>
                            <option value="none">No location</option>
                            <option v-for="loc in otherLocations" :key="loc.id" :value="String(loc.id)">From {{ loc.name }}</option>
                        </select>
                    </div>

                    <div v-if="loadingCandidates && !candidates" class="flex items-center justify-center gap-2 py-12 text-sm text-muted-foreground">
                        <Spinner />
                        Loading pigeons...
                    </div>

                    <template v-else>
                        <label
                            v-if="filteredCandidates.length > 0"
                            class="flex cursor-pointer items-center gap-2 border-b pb-2 text-sm font-medium"
                        >
                            <Checkbox :model-value="allCandidatesPicked" @update:model-value="toggleAllCandidates" />
                            Select all shown ({{ filteredCandidates.length }})
                        </label>

                        <div class="max-h-96 space-y-2 overflow-y-auto">
                            <p v-if="filteredCandidates.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                                {{ (candidates ?? []).length === 0 ? 'Every pigeon in the loft is already here.' : 'No pigeons found.' }}
                            </p>
                            <label
                                v-for="pigeon in filteredCandidates"
                                :key="pigeon.id"
                                class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 hover:bg-accent/50"
                                :class="{ 'border-primary bg-accent/40': pickedIds.includes(pigeon.id) }"
                            >
                                <Checkbox
                                    :model-value="pickedIds.includes(pigeon.id)"
                                    @update:model-value="toggle(pickedIds, pigeon.id)"
                                />
                                <PigeonSearchResult :pigeon="pigeon" class="flex-1">
                                    <template #aside>
                                        <span v-if="sexSymbol(pigeon.gender ?? null)" class="text-sm text-muted-foreground">{{ sexSymbol(pigeon.gender ?? null) }}</span>
                                    </template>
                                    <div v-if="pigeon.location_name" class="flex items-center gap-1 text-amber-700 dark:text-amber-400">
                                        <ArrowRightLeft class="h-3 w-3" />
                                        Now at {{ pigeon.location_name }}, will be moved here
                                    </div>
                                    <div v-else>No location</div>
                                </PigeonSearchResult>
                            </label>
                        </div>
                    </template>
                </div>

                <DialogFooter class="flex-col gap-2 sm:flex-row sm:items-center">
                    <p v-if="pickedIds.length > 0" class="text-sm text-muted-foreground sm:mr-auto">
                        {{ plural(pickedIds.length) }} selected<template v-if="pickedTransfers > 0">, {{ pickedTransfers }} will be moved</template>
                    </p>
                    <Button variant="outline" @click="showAdd = false">Cancel</Button>
                    <Button :disabled="pickedIds.length === 0 || adding" @click="add">
                        Add {{ pickedIds.length > 0 ? plural(pickedIds.length) : 'Pigeons' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Confirm removing the selected birds -->
        <Dialog v-model:open="showRemoveConfirm">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Remove from {{ location.name }}?</DialogTitle>
                    <DialogDescription>
                        {{ plural(selectedIds.length) }} will be left with no location. You can add them back, or add them
                        to another location, at any time.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showRemoveConfirm = false">Cancel</Button>
                    <Button variant="destructive" :disabled="removing" @click="remove([...selectedIds])">Remove</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
