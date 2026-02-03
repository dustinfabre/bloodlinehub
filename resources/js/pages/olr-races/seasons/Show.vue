<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { type BreadcrumbItem } from '@/types';
import { Plus, Calendar, Users, Flag, Pencil, Trash2, Bird, Eye, AlertTriangle } from 'lucide-vue-next';

interface Pigeon {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    status?: string;
    pivot?: {
        entry_number: string | null;
        notes: string | null;
    };
}

interface OlrSeasonRace {
    id: number;
    name: string;
    release_point: string | null;
    distance: number | null;
    distance_unit: string;
    race_date: string | null;
    arrived_count: number;
    total_entries: number;
}

interface OlrSeason {
    id: number;
    name: string;
    year: number;
    start_date: string | null;
    end_date: string | null;
    status: string;
    entries: Pigeon[];
    races: OlrSeasonRace[];
}

interface OlrRace {
    id: number;
    name: string;
}

const props = defineProps<{
    olrRace: OlrRace;
    season: OlrSeason;
    availablePigeons: Pigeon[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'OLR Races', href: '/olr-races' },
    { title: props.olrRace.name, href: `/olr-races/${props.olrRace.id}` },
    { title: props.season.name, href: `/olr-races/${props.olrRace.id}/seasons/${props.season.id}` },
];

const showAddEntryModal = ref(false);
const showAddRaceModal = ref(false);
const selectedPigeonId = ref<string>('');

const addEntryForm = useForm({
    pigeon_id: '',
    entry_number: '',
    notes: '',
});

const raceForm = useForm({
    name: '',
    release_point: '',
    distance: '',
    distance_unit: 'km',
    race_date: new Date().toISOString().split('T')[0],
    release_time: '',
    weather_conditions: '',
    wind_direction: '',
});

const pigeonLabel = (pigeon: Pigeon) =>
    pigeon.name || pigeon.ring_number || pigeon.personal_number || `Pigeon #${pigeon.id}`;

const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
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
    return props.season.entries.filter(e => e.status === 'alive').length;
});

const submitAddEntry = () => {
    addEntryForm.pigeon_id = selectedPigeonId.value;
    addEntryForm.post(`/olr-races/${props.olrRace.id}/seasons/${props.season.id}/entries`, {
        preserveScroll: true,
        onSuccess: () => {
            addEntryForm.reset();
            selectedPigeonId.value = '';
            showAddEntryModal.value = false;
        },
    });
};

const removeEntry = (pigeon: Pigeon) => {
    if (!confirm(`Remove ${pigeonLabel(pigeon)} from this season? This will also remove results from all races.`)) return;
    router.delete(`/olr-races/${props.olrRace.id}/seasons/${props.season.id}/entries/${pigeon.id}`, {
        preserveScroll: true,
    });
};

const updateEntryStatus = (pigeon: Pigeon, newStatus: string) => {
    router.patch(`/olr-races/${props.olrRace.id}/seasons/${props.season.id}/entries/${pigeon.id}`, {
        status: newStatus,
    }, {
        preserveScroll: true,
    });
};

const submitRaceForm = () => {
    raceForm.post(`/olr-races/${props.olrRace.id}/seasons/${props.season.id}/races`, {
        onSuccess: () => {
            showAddRaceModal.value = false;
            raceForm.reset();
        },
    });
};

const handleDeleteRace = (race: OlrSeasonRace) => {
    if (!confirm(`Delete race "${race.name}"? This will delete all results.`)) return;
    router.delete(`/olr-races/${props.olrRace.id}/seasons/${props.season.id}/races/${race.id}`);
};
</script>

<template>
    <Head :title="season.name" />

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
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ olrRace.name }} • {{ season.year }}
                    </p>
                    <p v-if="season.start_date || season.end_date" class="text-sm text-muted-foreground">
                        {{ formatDate(season.start_date) }} - {{ formatDate(season.end_date) }}
                    </p>
                </div>
                <Button variant="outline" size="sm" as-child class="w-full sm:w-auto">
                    <Link :href="`/olr-races/${olrRace.id}/seasons/${season.id}/edit`">
                        <Pencil class="mr-2 h-4 w-4" />
                        Edit
                    </Link>
                </Button>
            </div>

            <!-- Season Entries -->
            <Card>
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <Users class="h-5 w-5" />
                            Entries
                            <Badge variant="outline" class="ml-2">
                                {{ activeEntriesCount }}/{{ season.entries.length }} active
                            </Badge>
                        </CardTitle>
                        <CardDescription>Pigeons entered in this season</CardDescription>
                    </div>
                    <Dialog v-model:open="showAddEntryModal">
                        <DialogTrigger as-child>
                            <Button :disabled="availablePigeons.length === 0" class="w-full sm:w-auto">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Entry
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Add Pigeon Entry</DialogTitle>
                                <DialogDescription>
                                    Select a pigeon to add to this season
                                </DialogDescription>
                            </DialogHeader>
                            <div class="space-y-4 py-4">
                                <div class="space-y-2">
                                    <Label for="pigeon">Select Pigeon *</Label>
                                    <Select v-model="selectedPigeonId">
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="Select a pigeon..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="pigeon in availablePigeons"
                                                :key="pigeon.id"
                                                :value="String(pigeon.id)"
                                            >
                                                {{ pigeonLabel(pigeon) }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label for="entry_number">Entry Number</Label>
                                    <Input
                                        id="entry_number"
                                        v-model="addEntryForm.entry_number"
                                        placeholder="e.g., 001"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="notes">Notes</Label>
                                    <Input
                                        id="notes"
                                        v-model="addEntryForm.notes"
                                        placeholder="Optional..."
                                    />
                                </div>
                            </div>
                            <DialogFooter class="flex-col gap-2 sm:flex-row">
                                <Button variant="outline" @click="showAddEntryModal = false" class="w-full sm:w-auto">
                                    Cancel
                                </Button>
                                <Button @click="submitAddEntry" :disabled="!selectedPigeonId || addEntryForm.processing" class="w-full sm:w-auto">
                                    Add Entry
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </CardHeader>
                <CardContent>
                    <!-- No entries message -->
                    <div
                        v-if="season.entries.length === 0"
                        class="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center"
                    >
                        <Bird class="h-10 w-10 text-muted-foreground/60" />
                        <h3 class="mt-4 font-semibold">No entries yet</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            <template v-if="availablePigeons.length > 0">
                                Add pigeons to this season to participate in races.
                            </template>
                            <template v-else>
                                You need pigeons with race type "OLR" to add them to this season.
                            </template>
                        </p>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="space-y-2 sm:hidden" v-else>
                        <div
                            v-for="pigeon in season.entries"
                            :key="pigeon.id"
                            class="flex items-center justify-between rounded-lg border p-3"
                            :class="{ 'bg-destructive/5': pigeon.status !== 'alive' }"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <Badge v-if="pigeon.pivot?.entry_number" variant="secondary" class="font-mono text-xs">
                                        {{ pigeon.pivot.entry_number }}
                                    </Badge>
                                    <Link
                                        :href="`/pigeons/${pigeon.id}`"
                                        class="font-medium hover:text-primary hover:underline truncate"
                                    >
                                        {{ pigeonLabel(pigeon) }}
                                    </Link>
                                    <Badge v-if="getPigeonStatusBadge(pigeon)" :variant="getPigeonStatusBadge(pigeon)!.variant" class="text-xs">
                                        <AlertTriangle class="mr-1 h-3 w-3" />
                                        {{ getPigeonStatusBadge(pigeon)!.label }}
                                    </Badge>
                                </div>
                                <p v-if="pigeon.color" class="text-sm text-muted-foreground">{{ pigeon.color }}</p>
                            </div>
                            <Button variant="ghost" size="sm" @click="removeEntry(pigeon)">
                                <Trash2 class="h-4 w-4 text-destructive" />
                            </Button>
                        </div>
                    </div>

                    <!-- Desktop Table View -->
                    <Table class="hidden sm:table" v-if="season.entries.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[80px]">Entry #</TableHead>
                                <TableHead>Pigeon</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Notes</TableHead>
                                <TableHead class="w-[100px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="pigeon in season.entries"
                                :key="pigeon.id"
                                :class="{ 'bg-destructive/5': pigeon.status !== 'alive' }"
                            >
                                <TableCell>
                                    <Badge v-if="pigeon.pivot?.entry_number" variant="secondary" class="font-mono">
                                        {{ pigeon.pivot.entry_number }}
                                    </Badge>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell>
                                    <Link
                                        :href="`/pigeons/${pigeon.id}`"
                                        class="font-medium hover:text-primary hover:underline"
                                    >Select :model-value="pigeon.status" @update:model-value="(value) => updateEntryStatus(pigeon, value)">
                                        <SelectTrigger class="w-[140px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="stock">Stock</SelectItem>
                                            <SelectItem value="racing">Racing</SelectItem>
                                            <SelectItem value="breeding">Breeding</SelectItem>
                                            <SelectItem value="injured">Injured</SelectItem>
                                            <SelectItem value="deceased">Deceased</SelectItem>
                                            <SelectItem value="flyaway">Flyaway</SelectItem>
                                            <SelectItem value="missing">Missing</SelectItem>
                                        </SelectContent>
                                    </Select
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <Badge v-if="getPigeonStatusBadge(pigeon)" :variant="getPigeonStatusBadge(pigeon)!.variant">
                                        <AlertTriangle class="mr-1 h-3 w-3" />
                                        {{ getPigeonStatusBadge(pigeon)!.label }}
                                    </Badge>
                                    <Badge v-else variant="secondary">Active</Badge>
                                </TableCell>
                                <TableCell class="text-sm text-muted-foreground">
                                    {{ pigeon.pivot?.notes || '-' }}
                                </TableCell>
                                <TableCell>
                                    <Button variant="ghost" size="sm" @click="removeEntry(pigeon)">
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
                            Races ({{ season.races.length }})
                        </CardTitle>
                        <CardDescription>Individual races in this season</CardDescription>
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
                                    <Label for="race_name">Race Name *</Label>
                                    <Input
                                        id="race_name"
                                        v-model="raceForm.name"
                                        placeholder="e.g., Race 1 - Lucena"
                                        required
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="release_point">Release Point</Label>
                                    <Input
                                        id="release_point"
                                        v-model="raceForm.release_point"
                                        placeholder="e.g., Lucena"
                                    />
                                </div>
                                <div class="grid gap-4 grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="distance">Distance</Label>
                                        <Input
                                            id="distance"
                                            v-model="raceForm.distance"
                                            type="number"
                                            step="0.01"
                                            placeholder="100"
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
                    <div v-if="season.races.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center">
                        <Flag class="h-10 w-10 text-muted-foreground/60" />
                        <h3 class="mt-4 font-semibold">No races yet</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Add races to record results for this season.</p>
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
                                        :href="`/olr-races/${olrRace.id}/seasons/${season.id}/races/${race.id}`"
                                        class="font-medium hover:text-primary hover:underline"
                                    >
                                        {{ race.release_point || race.name }}
                                        <span v-if="race.distance" class="text-muted-foreground">
                                            {{ race.distance }}{{ race.distance_unit }}
                                        </span>
                                    </Link>
                                    <Badge variant="outline" class="font-mono">
                                        {{ race.arrived_count }}/{{ race.total_entries }}
                                    </Badge>
                                </div>
                                <div v-if="race.race_date" class="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                                    <Calendar class="h-3 w-3" />
                                    {{ formatDate(race.race_date) }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button variant="default" size="sm" as-child class="flex-1 sm:flex-none">
                                    <Link :href="`/olr-races/${olrRace.id}/seasons/${season.id}/races/${race.id}`">
                                        <Eye class="mr-1 h-3 w-3" />
                                        View
                                    </Link>
                                </Button>
                                <Button variant="outline" size="sm" as-child class="flex-1 sm:flex-none">
                                    <Link :href="`/olr-races/${olrRace.id}/seasons/${season.id}/races/${race.id}/edit`">
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
