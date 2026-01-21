<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Plus, Calendar, Users, Flag, Pencil, Trash2, Bird, Eye, AlertTriangle } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Pigeon {
    id: number;
    band_number: string;
    name: string | null;
    sex: string;
    status?: string;
    pigeon_status?: string;
}

interface ClubSeasonEntry {
    id: number;
    pigeon_id: number;
    notes: string | null;
    pigeon: Pigeon;
}

interface ClubSeasonRace {
    id: number;
    release_point: string;
    distance: number;
    distance_unit: string;
    race_date: string;
    release_time: string | null;
    weather_conditions: string | null;
    wind_direction: string | null;
    arrived_count: number;
    total_entries: number;
}

interface ClubSeason {
    id: number;
    name: string;
    year: number;
    start_date: string | null;
    end_date: string | null;
    status: string;
    entries: ClubSeasonEntry[];
    races: ClubSeasonRace[];
}

interface Club {
    id: number;
    name: string;
}

const props = defineProps<{
    club: Club;
    season: ClubSeason;
    availablePigeons: Pigeon[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: props.season.name, href: `/clubs/${props.club.id}/seasons/${props.season.id}` },
];

const showAddEntryModal = ref(false);
const showAddRaceModal = ref(false);
const selectedPigeonId = ref<string>('');
const searchQuery = ref('');
const selectedPigeonIds = ref<number[]>([]);
const selectAll = ref(false);

const raceForm = useForm({
    release_point: '',
    distance: '',
    distance_unit: 'km',
    race_date: new Date().toISOString().split('T')[0],
    release_time: '',
    weather_conditions: '',
    wind_direction: '',
});

const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatTime = (time: string | null) => {
    if (!time) return '-';
    return time.slice(0, 5);
};

const statusBadgeVariant = (status: string) => {
    if (status === 'active') return 'default';
    if (status === 'completed') return 'secondary';
    return 'destructive';
};

const getPigeonStatusBadge = (pigeon: Pigeon) => {
    if (pigeon.status === 'missing') return { label: 'Missing', variant: 'destructive' as const };
    if (pigeon.status === 'deceased') return { label: 'Deceased', variant: 'destructive' as const };
    return null;
};

const activeEntriesCount = computed(() => {
    return props.season.entries.filter(e => e.pigeon.status === 'alive').length;
});

const filteredPigeons = computed(() => {
    if (!searchQuery.value) return props.availablePigeons;
    const query = searchQuery.value.toLowerCase();
    return props.availablePigeons.filter(p => 
        (p.ring_number?.toLowerCase().includes(query)) ||
        (p.personal_number?.toLowerCase().includes(query)) ||
        (p.name?.toLowerCase().includes(query)) ||
        (p.bloodline?.toLowerCase().includes(query)) ||
        (p.color?.toLowerCase().includes(query))
    );
});

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedPigeonIds.value = filteredPigeons.value.map(p => p.id);
    } else {
        selectedPigeonIds.value = [];
    }
};

const addBulkEntries = () => {
    if (selectedPigeonIds.value.length === 0) return;
    
    router.post(`/clubs/${props.club.id}/seasons/${props.season.id}/entries/bulk`, {
        pigeon_ids: selectedPigeonIds.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedPigeonIds.value = [];
            selectAll.value = false;
            searchQuery.value = '';
            showAddEntryModal.value = false;
        },
    });
};

const addEntry = () => {
    if (selectedPigeonIds.value.length > 0) {
        addBulkEntries();
    } else if (selectedPigeonId.value) {
        router.post(`/clubs/${props.club.id}/seasons/${props.season.id}/entries`, {
            pigeon_id: selectedPigeonId.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedPigeonId.value = '';
                showAddEntryModal.value = false;
            },
        });
    }
};

const removeEntry = (entry: ClubSeasonEntry) => {
    if (!confirm(`Remove ${entry.pigeon.ring_number || entry.pigeon.name || 'this pigeon'} from this season?`)) return;
    router.delete(`/clubs/${props.club.id}/seasons/${props.season.id}/entries/${entry.id}`, {
        preserveScroll: true,
    });
};

const submitRaceForm = () => {
    raceForm.post(`/clubs/${props.club.id}/seasons/${props.season.id}/races`, {
        onSuccess: () => {
            showAddRaceModal.value = false;
            raceForm.reset();
        },
    });
};

const handleDeleteRace = (race: ClubSeasonRace) => {
    if (!confirm(`Delete race "${race.release_point}"? This will delete all results.`)) return;
    router.delete(`/clubs/${props.club.id}/seasons/${props.season.id}/races/${race.id}`);
};
</script>

<template>
    <Head :title="`${season.name} - ${club.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        <h1 class="text-xl font-semibold text-foreground sm:text-2xl">{{ season.name }}</h1>
                        <Badge :variant="statusBadgeVariant(season.status)">
                            {{ season.status }}
                        </Badge>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground sm:gap-4">
                        <span class="flex items-center gap-1">
                            <Calendar class="h-4 w-4" />
                            {{ season.year }}
                        </span>
                        <span v-if="season.start_date || season.end_date">
                            {{ formatDate(season.start_date) }} - {{ formatDate(season.end_date) }}
                        </span>
                    </div>
                </div>
                <Button variant="outline" size="sm" as-child class="w-full sm:w-auto">
                    <Link :href="`/clubs/${club.id}/seasons/${season.id}/edit`">
                        <Pencil class="mr-2 h-4 w-4" />
                        Edit Season
                    </Link>
                </Button>
            </div>

            <!-- Season Entries -->
            <Card>
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <Users class="h-5 w-5" />
                            Season Entries
                            <Badge variant="outline" class="ml-2">
                                {{ activeEntriesCount }}/{{ season.entries.length }} active
                            </Badge>
                        </CardTitle>
                        <CardDescription>Pigeons entered in this racing season</CardDescription>
                    </div>
                    <Dialog v-model:open="showAddEntryModal">
                        <DialogTrigger as-child>
                            <Button :disabled="availablePigeons.length === 0" class="w-full sm:w-auto">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Entry
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>Add Pigeon Entries</DialogTitle>
                                <DialogDescription>
                                    Search and select pigeons to add to this season
                                </DialogDescription>
                            </DialogHeader>
                            <div class="space-y-4 py-4">
                                <!-- Search Input -->
                                <div class="space-y-2">
                                    <Label for="search">Search Pigeons</Label>
                                    <Input 
                                        id="search" 
                                        v-model="searchQuery" 
                                        placeholder="Search by ring number, name, bloodline, color..."
                                        class="w-full"
                                    />
                                </div>

                                <!-- Select All Checkbox -->
                                <div v-if="filteredPigeons.length > 0" class="flex items-center space-x-2 py-2 border-b">
                                    <input 
                                        type="checkbox" 
                                        id="select-all"
                                        v-model="selectAll"
                                        @change="toggleSelectAll"
                                        class="h-4 w-4 rounded border-gray-300"
                                    />
                                    <Label for="select-all" class="font-medium cursor-pointer">
                                        Select All ({{ filteredPigeons.length }})
                                    </Label>
                                </div>

                                <!-- Pigeon List with Checkboxes -->
                                <div class="max-h-96 overflow-y-auto space-y-2">
                                    <div v-if="filteredPigeons.length === 0" class="text-center py-8 text-muted-foreground">
                                        No pigeons found
                                    </div>
                                    <div 
                                        v-for="pigeon in filteredPigeons" 
                                        :key="pigeon.id"
                                        class="flex items-center space-x-3 p-3 rounded-lg border hover:bg-accent/50 cursor-pointer"
                                        @click="() => {
                                            const index = selectedPigeonIds.indexOf(pigeon.id);
                                            if (index > -1) {
                                                selectedPigeonIds.splice(index, 1);
                                            } else {
                                                selectedPigeonIds.push(pigeon.id);
                                            }
                                        }"
                                    >
                                        <input 
                                            type="checkbox" 
                                            :id="`pigeon-${pigeon.id}`"
                                            :checked="selectedPigeonIds.includes(pigeon.id)"
                                            class="h-4 w-4 rounded border-gray-300"
                                            @click.stop
                                            @change="() => {
                                                const index = selectedPigeonIds.indexOf(pigeon.id);
                                                if (index > -1) {
                                                    selectedPigeonIds.splice(index, 1);
                                                } else {
                                                    selectedPigeonIds.push(pigeon.id);
                                                }
                                            }"
                                        />
                                        <Label :for="`pigeon-${pigeon.id}`" class="flex-1 cursor-pointer">
                                            <div class="font-medium">{{ pigeon.ring_number || pigeon.personal_number || 'No Ring' }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                {{ pigeon.name || 'Unnamed' }}
                                                <span v-if="pigeon.bloodline"> • {{ pigeon.bloodline }}</span>
                                                <span v-if="pigeon.color"> • {{ pigeon.color }}</span>
                                            </div>
                                        </Label>
                                    </div>
                                </div>

                                <!-- Selected Count -->
                                <div v-if="selectedPigeonIds.length > 0" class="text-sm text-muted-foreground">
                                    {{ selectedPigeonIds.length }} pigeon(s) selected
                                </div>
                            </div>
                            <DialogFooter class="flex-col gap-2 sm:flex-row">
                                <Button variant="outline" @click="showAddEntryModal = false" class="w-full sm:w-auto">
                                    Cancel
                                </Button>
                                <Button @click="addEntry" :disabled="selectedPigeonIds.length === 0" class="w-full sm:w-auto">
                                    Add {{ selectedPigeonIds.length > 0 ? `${selectedPigeonIds.length} ` : '' }}Entr{{ selectedPigeonIds.length === 1 ? 'y' : 'ies' }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </CardHeader>
                <CardContent>
                    <div v-if="season.entries.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-8 text-center">
                        <Bird class="h-8 w-8 text-muted-foreground/60" />
                        <h3 class="mt-2 font-medium">No entries yet</h3>
                        <p class="text-sm text-muted-foreground">Add pigeons to this season to start tracking races.</p>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="space-y-2 sm:hidden" v-else>
                        <div
                            v-for="entry in season.entries"
                            :key="entry.id"
                            class="flex items-center justify-between rounded-lg border p-3"
                            :class="{ 'bg-destructive/5': entry.pigeon.status !== 'alive' }"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium truncate">{{ entry.pigeon.ring_number || entry.pigeon.personal_number }} - {{ entry.pigeon.name || 'Unnamed' }}</span>
                                    <Badge v-if="getPigeonStatusBadge(entry.pigeon)" :variant="getPigeonStatusBadge(entry.pigeon)!.variant" class="text-xs">
                                        <AlertTriangle class="mr-1 h-3 w-3" />
                                        {{ getPigeonStatusBadge(entry.pigeon)!.label }}
                                    </Badge>
                                </div>
                                <p class="text-sm text-muted-foreground truncate">{{ entry.pigeon.name || '-' }}</p>
                            </div>
                            <Button variant="ghost" size="sm" @click="removeEntry(entry)">
                                <Trash2 class="h-4 w-4 text-destructive" />
                            </Button>
                        </div>
                    </div>

                    <!-- Desktop Table View -->
                    <Table class="hidden sm:table" v-if="season.entries.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Band Number</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Sex</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="w-[100px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="entry in season.entries"
                                :key="entry.id"
                                :class="{ 'bg-destructive/5': entry.pigeon.status !== 'alive' }"
                            >
                                <TableCell class="font-medium">{{ entry.pigeon.ring_number || entry.pigeon.personal_number }}</TableCell>
                                <TableCell>{{ entry.pigeon.name || '-' }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline" class="capitalize">{{ entry.pigeon.gender }}</Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge v-if="getPigeonStatusBadge(entry.pigeon)" :variant="getPigeonStatusBadge(entry.pigeon)!.variant">
                                        <AlertTriangle class="mr-1 h-3 w-3" />
                                        {{ getPigeonStatusBadge(entry.pigeon)!.label }}
                                    </Badge>
                                    <Badge v-else variant="secondary">Active</Badge>
                                </TableCell>
                                <TableCell>
                                    <Button variant="ghost" size="sm" @click="removeEntry(entry)">
                                        <Trash2 class="h-4 w-4 text-destructive" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Season Races -->
            <Card>
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <Flag class="h-5 w-5" />
                            Races
                        </CardTitle>
                        <CardDescription>Racing events in this season</CardDescription>
                    </div>
                    <Dialog v-model:open="showAddRaceModal">
                        <DialogTrigger as-child>
                            <Button class="w-full sm:w-auto">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Race
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Add New Race</DialogTitle>
                                <DialogDescription>
                                    Create a new race for this season
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submitRaceForm" class="space-y-4 py-4">
                                <div class="space-y-2">
                                    <Label for="release_point">Release Point *</Label>
                                    <Input
                                        id="release_point"
                                        v-model="raceForm.release_point"
                                        placeholder="e.g., Lucena"
                                        required
                                    />
                                </div>
                                <div class="grid gap-4 grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="distance">Distance *</Label>
                                        <Input
                                            id="distance"
                                            v-model="raceForm.distance"
                                            type="number"
                                            step="0.01"
                                            placeholder="100"
                                            required
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="distance_unit">Unit</Label>
                                        <Select v-model="raceForm.distance_unit">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="km">km</SelectItem>
                                                <SelectItem value="mi">mi</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div class="grid gap-4 grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="race_date">Race Date</Label>
                                        <Input
                                            id="race_date"
                                            v-model="raceForm.race_date"
                                            type="date"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="release_time">Release Time</Label>
                                        <Input
                                            id="release_time"
                                            v-model="raceForm.release_time"
                                            type="time"
                                        />
                                    </div>
                                </div>
                                <div class="grid gap-4 grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="weather">Weather</Label>
                                        <Input
                                            id="weather"
                                            v-model="raceForm.weather_conditions"
                                            placeholder="e.g., Clear"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="wind">Wind</Label>
                                        <Input
                                            id="wind"
                                            v-model="raceForm.wind_direction"
                                            placeholder="e.g., NE"
                                        />
                                    </div>
                                </div>
                                <DialogFooter class="flex-col gap-2 sm:flex-row">
                                    <Button type="button" variant="outline" @click="showAddRaceModal = false" class="w-full sm:w-auto">
                                        Cancel
                                    </Button>
                                    <Button type="submit" :disabled="raceForm.processing" class="w-full sm:w-auto">
                                        Create Race
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </CardHeader>
                <CardContent>
                    <div v-if="season.races.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-8 text-center">
                        <Flag class="h-8 w-8 text-muted-foreground/60" />
                        <h3 class="mt-2 font-medium">No races yet</h3>
                        <p class="text-sm text-muted-foreground">Add races to track results for entered pigeons.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="race in season.races"
                            :key="race.id"
                            class="flex flex-col gap-3 rounded-lg border p-4 transition-colors hover:bg-muted/50 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link
                                        :href="`/clubs/${club.id}/seasons/${season.id}/races/${race.id}`"
                                        class="font-medium hover:text-primary hover:underline"
                                    >
                                        {{ race.release_point }} {{ race.distance }}{{ race.distance_unit }}
                                    </Link>
                                    <Badge variant="secondary">
                                        {{ race.arrived_count }}/{{ race.total_entries }}
                                    </Badge>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground sm:gap-4">
                                    <span class="flex items-center gap-1">
                                        <Calendar class="h-3 w-3" />
                                        {{ formatDate(race.race_date) }}
                                    </span>
                                    <span v-if="race.release_time">
                                        Release: {{ formatTime(race.release_time) }}
                                    </span>
                                    <span v-if="race.weather_conditions" class="text-xs">
                                        {{ race.weather_conditions }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button variant="default" size="sm" as-child class="flex-1 sm:flex-none">
                                    <Link :href="`/clubs/${club.id}/seasons/${season.id}/races/${race.id}`">
                                        <Eye class="mr-1 h-3 w-3" />
                                        View
                                    </Link>
                                </Button>
                                <Button variant="outline" size="sm" as-child class="flex-1 sm:flex-none">
                                    <Link :href="`/clubs/${club.id}/seasons/${season.id}/races/${race.id}/edit`">
                                        <Pencil class="mr-1 h-3 w-3" />
                                        Edit
                                    </Link>
                                </Button>
                                <Button variant="destructive" size="sm" @click="handleDeleteRace(race)" class="flex-1 sm:flex-none">
                                    <Trash2 class="mr-1 h-3 w-3" />
                                    Delete
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
