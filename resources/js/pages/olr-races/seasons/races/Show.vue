<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { type BreadcrumbItem } from '@/types';
import { Plus, Calendar, MapPin, Cloud, Wind, Pencil, XCircle, CheckCircle, Trash2, Bird, AlertTriangle } from 'lucide-vue-next';

interface Pigeon {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    status?: string;
    pivot?: {
        entry_number: string | null;
        position: number | null;
        arrival_time: string | null;
        speed: number | null;
        notes: string | null;
        did_not_arrive: boolean;
    };
}

interface OlrSeasonRace {
    id: number;
    name: string;
    release_point: string | null;
    distance: number | null;
    distance_unit: string;
    race_date: string | null;
    release_time: string | null;
    weather_conditions: string | null;
    wind_direction: string | null;
    notes: string | null;
    results: Pigeon[];
}

interface OlrSeason {
    id: number;
    name: string;
    entries: Pigeon[];
}

interface OlrRace {
    id: number;
    name: string;
}

const props = defineProps<{
    olrRace: OlrRace;
    season: OlrSeason;
    race: OlrSeasonRace;
    availablePigeons: Pigeon[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'OLR Races', href: '/olr-races' },
    { title: props.olrRace.name, href: `/olr-races/${props.olrRace.id}` },
    { title: props.season.name, href: `/olr-races/${props.olrRace.id}/seasons/${props.season.id}` },
    { title: props.race.name, href: `/olr-races/${props.olrRace.id}/seasons/${props.season.id}/races/${props.race.id}` },
];

const showAddResultModal = ref(false);
const showEditResultModal = ref(false);
const editingPigeon = ref<Pigeon | null>(null);
const selectedPigeonId = ref<string>('');

const addResultForm = useForm({
    pigeon_id: '',
    position: '',
    arrival_time: '',
    speed: '',
    notes: '',
    did_not_arrive: false,
});

const editForm = useForm({
    position: '',
    arrival_time: '',
    speed: '',
    notes: '',
    did_not_arrive: false,
});

// Calculate speed based on release time, arrival time, and distance
const calculateSpeed = (arrivalTime: string | null, releaseTime: string | null, distance: number | null, distanceUnit: string) => {
    if (!arrivalTime || !releaseTime || !distance) return null;
    
    // Convert distance to meters
    let distanceMeters = distance;
    if (distanceUnit === 'km') {
        distanceMeters = distance * 1000;
    } else if (distanceUnit === 'mi') {
        distanceMeters = distance * 1609.34;
    }
    
    // Parse times (HH:MM:SS format)
    const parseTime = (timeStr: string) => {
        const parts = timeStr.split(':').map(Number);
        return parts[0] * 60 + parts[1] + (parts[2] ? parts[2] / 60 : 0); // minutes
    };
    
    const releaseMinutes = parseTime(releaseTime);
    let arrivalMinutes = parseTime(arrivalTime);
    
    // If arrival is before release, assume next day
    if (arrivalMinutes < releaseMinutes) {
        arrivalMinutes += 24 * 60;
    }
    
    const timeDiff = arrivalMinutes - releaseMinutes;
    if (timeDiff <= 0) return null;
    
    // Speed in meters per minute
    const speed = distanceMeters / timeDiff;
    return Math.round(speed * 10000) / 10000;
};

// Auto-calculate speed when arrival time changes for add form
watch(() => addResultForm.arrival_time, (newArrivalTime) => {
    if (newArrivalTime && props.race.release_time && props.race.distance && !addResultForm.did_not_arrive) {
        const calculatedSpeed = calculateSpeed(newArrivalTime, props.race.release_time, props.race.distance, props.race.distance_unit);
        if (calculatedSpeed) {
            addResultForm.speed = calculatedSpeed.toString();
        }
    }
});

// Auto-calculate speed when arrival time changes for edit form
watch(() => editForm.arrival_time, (newArrivalTime) => {
    if (newArrivalTime && props.race.release_time && props.race.distance && !editForm.did_not_arrive) {
        const calculatedSpeed = calculateSpeed(newArrivalTime, props.race.release_time, props.race.distance, props.race.distance_unit);
        if (calculatedSpeed) {
            editForm.speed = calculatedSpeed.toString();
        }
    }
});

const pigeonLabel = (pigeon: Pigeon) =>
    pigeon.name || pigeon.ring_number || pigeon.personal_number || `Pigeon #${pigeon.id}`;

const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
};

const getPigeonStatusBadge = (pigeon: Pigeon) => {
    if (pigeon.status === 'missing') return { label: 'Missing', variant: 'destructive' as const };
    if (pigeon.status === 'deceased') return { label: 'Deceased', variant: 'destructive' as const };
    return null;
};

const arrivedCount = computed(() => 
    props.race.results.filter(p => !p.pivot?.did_not_arrive && p.pivot?.arrival_time).length
);

// Filter out non-alive pigeons from available pigeons (already done on backend, but just in case)
const filteredAvailablePigeons = computed(() => 
    props.availablePigeons.filter(p => p.status === 'alive' || !p.status)
);

const sortedResults = computed(() => {
    return [...props.race.results].sort((a, b) => {
        // Did not arrive goes last
        if (a.pivot?.did_not_arrive && !b.pivot?.did_not_arrive) return 1;
        if (!a.pivot?.did_not_arrive && b.pivot?.did_not_arrive) return -1;
        // Then by position
        if (a.pivot?.position && b.pivot?.position) return a.pivot.position - b.pivot.position;
        if (a.pivot?.position) return -1;
        if (b.pivot?.position) return 1;
        return 0;
    });
});

const submitAddResult = () => {
    addResultForm.pigeon_id = selectedPigeonId.value;
    addResultForm.post(`/olr-races/${props.olrRace.id}/seasons/${props.season.id}/races/${props.race.id}/results`, {
        preserveScroll: true,
        onSuccess: () => {
            addResultForm.reset();
            selectedPigeonId.value = '';
            showAddResultModal.value = false;
        },
    });
};

const startEditResult = (pigeon: Pigeon) => {
    editingPigeon.value = pigeon;
    editForm.position = pigeon.pivot?.position?.toString() || '';
    editForm.arrival_time = pigeon.pivot?.arrival_time || '';
    editForm.speed = pigeon.pivot?.speed?.toString() || '';
    editForm.notes = pigeon.pivot?.notes || '';
    editForm.did_not_arrive = pigeon.pivot?.did_not_arrive || false;
    showEditResultModal.value = true;
};

const submitEditResult = () => {
    if (!editingPigeon.value) return;
    editForm.patch(`/olr-races/${props.olrRace.id}/seasons/${props.season.id}/races/${props.race.id}/results/${editingPigeon.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditResultModal.value = false;
            editingPigeon.value = null;
            editForm.reset();
        },
    });
};

const removeResult = (pigeon: Pigeon) => {
    if (!confirm(`Remove ${pigeonLabel(pigeon)} from this race?`)) return;
    router.delete(`/olr-races/${props.olrRace.id}/seasons/${props.season.id}/races/${props.race.id}/results/${pigeon.id}`, {
        preserveScroll: true,
    });
};

const addAllEntries = () => {
    if (!confirm(`Add all ${filteredAvailablePigeons.value.length} remaining entries to this race?`)) return;
    router.post(`/olr-races/${props.olrRace.id}/seasons/${props.season.id}/races/${props.race.id}/add-all-entries`, {}, {
        preserveScroll: true,
    });
};

const handleDelete = () => {
    if (!confirm(`Delete race "${props.race.name}"? This will delete all results.`)) return;
    router.delete(`/olr-races/${props.olrRace.id}/seasons/${props.season.id}/races/${props.race.id}`);
};
</script>

<template>
    <Head :title="race.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        <h1 class="text-xl font-semibold text-foreground sm:text-2xl">
                            {{ race.release_point || race.name }}
                            <span v-if="race.distance" class="text-muted-foreground">
                                {{ race.distance }}{{ race.distance_unit }}
                            </span>
                        </h1>
                        <Badge variant="outline" class="font-mono text-base sm:text-lg">
                            {{ arrivedCount }}/{{ race.results.length }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ olrRace.name }} • {{ season.name }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child size="sm" class="flex-1 sm:flex-none">
                        <Link :href="`/olr-races/${olrRace.id}/seasons/${season.id}/races/${race.id}/edit`">
                            <Pencil class="mr-2 h-4 w-4" />
                            Edit
                        </Link>
                    </Button>
                    <Button variant="destructive" size="sm" @click="handleDelete" class="flex-1 sm:flex-none">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </div>
            </div>

            <!-- Race Details -->
            <Card>
                <CardHeader>
                    <CardTitle>Race Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-4 grid-cols-2 lg:grid-cols-4">
                        <div v-if="race.race_date">
                            <dt class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Calendar class="h-4 w-4" />
                                Date
                            </dt>
                            <dd class="mt-1 text-sm text-foreground">{{ formatDate(race.race_date) }}</dd>
                        </div>
                        <div v-if="race.release_time">
                            <dt class="text-sm font-medium text-muted-foreground">Release Time</dt>
                            <dd class="mt-1 text-sm text-foreground font-mono">{{ race.release_time }}</dd>
                        </div>
                        <div v-if="race.weather_conditions">
                            <dt class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Cloud class="h-4 w-4" />
                                Weather
                            </dt>
                            <dd class="mt-1 text-sm text-foreground">{{ race.weather_conditions }}</dd>
                        </div>
                        <div v-if="race.wind_direction">
                            <dt class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Wind class="h-4 w-4" />
                                Wind
                            </dt>
                            <dd class="mt-1 text-sm text-foreground">{{ race.wind_direction }}</dd>
                        </div>
                    </dl>
                    <div v-if="race.notes" class="mt-4 border-t pt-4">
                        <dt class="text-sm font-medium text-muted-foreground">Notes</dt>
                        <dd class="mt-1 text-sm text-foreground whitespace-pre-wrap">{{ race.notes }}</dd>
                    </div>
                </CardContent>
            </Card>

            <!-- Race Results -->
            <Card>
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle>Results ({{ race.results.length }})</CardTitle>
                        <CardDescription>Pigeon results for this race</CardDescription>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="filteredAvailablePigeons.length > 0"
                            variant="outline"
                            size="sm"
                            @click="addAllEntries"
                            class="flex-1 sm:flex-none"
                        >
                            Add All ({{ filteredAvailablePigeons.length }})
                        </Button>
                        <Dialog v-model:open="showAddResultModal">
                            <DialogTrigger as-child>
                                <Button :disabled="filteredAvailablePigeons.length === 0" class="flex-1 sm:flex-none">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add Result
                                </Button>
                            </DialogTrigger>
                            <DialogContent class="sm:max-w-md">
                                <DialogHeader>
                                    <DialogTitle>Add Pigeon Result</DialogTitle>
                                    <DialogDescription>
                                        Add a pigeon's result for this race. Speed is auto-calculated.
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
                                                    v-for="pigeon in filteredAvailablePigeons"
                                                    :key="pigeon.id"
                                                    :value="String(pigeon.id)"
                                                >
                                                    <span v-if="pigeon.pivot?.entry_number" class="font-mono text-xs mr-2">[{{ pigeon.pivot.entry_number }}]</span>
                                                    {{ pigeonLabel(pigeon) }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="space-y-2">
                                            <Label for="position">Position</Label>
                                            <Input
                                                id="position"
                                                v-model="addResultForm.position"
                                                type="number"
                                                min="1"
                                                placeholder="1"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="arrival_time">Arrival Time</Label>
                                            <Input
                                                id="arrival_time"
                                                v-model="addResultForm.arrival_time"
                                                type="time"
                                                step="1"
                                            />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="speed">Speed (m/min)</Label>
                                        <Input
                                            id="speed"
                                            v-model="addResultForm.speed"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            placeholder="Auto-calculated from arrival time"
                                        />
                                        <p class="text-xs text-muted-foreground">Speed is auto-calculated when you enter arrival time</p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="notes">Notes</Label>
                                        <Input
                                            id="notes"
                                            v-model="addResultForm.notes"
                                            placeholder="Optional..."
                                        />
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <Checkbox
                                            id="did_not_arrive"
                                            :checked="addResultForm.did_not_arrive"
                                            @update:checked="addResultForm.did_not_arrive = $event"
                                        />
                                        <Label for="did_not_arrive" class="text-sm font-normal">Did Not Arrive (DNA)</Label>
                                    </div>
                                </div>
                                <DialogFooter class="flex-col gap-2 sm:flex-row">
                                    <Button variant="outline" @click="showAddResultModal = false" class="w-full sm:w-auto">
                                        Cancel
                                    </Button>
                                    <Button @click="submitAddResult" :disabled="!selectedPigeonId || addResultForm.processing" class="w-full sm:w-auto">
                                        Add Result
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- No results message -->
                    <div
                        v-if="race.results.length === 0"
                        class="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center"
                    >
                        <Bird class="h-10 w-10 text-muted-foreground/60" />
                        <h3 class="mt-4 font-semibold">No results yet</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            <template v-if="filteredAvailablePigeons.length > 0">
                                Add pigeon results to record their performance.
                            </template>
                            <template v-else>
                                All season entries have been added to this race.
                            </template>
                        </p>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="space-y-2 sm:hidden" v-else>
                        <div
                            v-for="pigeon in sortedResults"
                            :key="pigeon.id"
                            class="rounded-lg border p-3"
                            :class="{ 'bg-destructive/5': pigeon.pivot?.did_not_arrive || pigeon.status !== 'alive' }"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2">
                                    <div
                                        v-if="pigeon.pivot?.position"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-primary-foreground text-sm font-medium"
                                    >
                                        {{ pigeon.pivot.position }}
                                    </div>
                                    <div
                                        v-else
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-muted-foreground text-sm"
                                    >
                                        -
                                    </div>
                                    <div>
                                        <Link
                                            :href="`/pigeons/${pigeon.id}`"
                                            class="font-medium hover:text-primary hover:underline"
                                        >
                                            {{ pigeonLabel(pigeon) }}
                                        </Link>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <Badge v-if="pigeon.pivot?.did_not_arrive" variant="destructive" class="text-xs">
                                                <XCircle class="mr-1 h-3 w-3" />
                                                DNA
                                            </Badge>
                                            <Badge v-else-if="pigeon.pivot?.arrival_time" variant="default" class="text-xs">
                                                <CheckCircle class="mr-1 h-3 w-3" />
                                                {{ pigeon.pivot.arrival_time }}
                                            </Badge>
                                            <Badge v-if="getPigeonStatusBadge(pigeon)" :variant="getPigeonStatusBadge(pigeon)!.variant" class="text-xs">
                                                <AlertTriangle class="mr-1 h-3 w-3" />
                                                {{ getPigeonStatusBadge(pigeon)!.label }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="sm" @click="startEditResult(pigeon)">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button variant="ghost" size="sm" @click="removeResult(pigeon)">
                                        <Trash2 class="h-4 w-4 text-destructive" />
                                    </Button>
                                </div>
                            </div>
                            <div v-if="pigeon.pivot?.speed && !pigeon.pivot?.did_not_arrive" class="mt-2 text-sm text-muted-foreground">
                                Speed: <span class="font-mono">{{ pigeon.pivot.speed.toFixed(2) }}</span> m/min
                            </div>
                            <div v-if="pigeon.pivot?.notes" class="mt-1 text-sm text-muted-foreground">
                                {{ pigeon.pivot.notes }}
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table View -->
                    <Table class="hidden sm:table" v-if="race.results.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[60px]">Pos</TableHead>
                                <TableHead>Pigeon</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Arrival</TableHead>
                                <TableHead>Speed (m/min)</TableHead>
                                <TableHead>Notes</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="pigeon in sortedResults"
                                :key="pigeon.id"
                                :class="{ 'bg-destructive/5': pigeon.pivot?.did_not_arrive || pigeon.status !== 'alive' }"
                            >
                                <TableCell>
                                    <Badge v-if="pigeon.pivot?.position" variant="secondary" class="font-mono">
                                        {{ pigeon.pivot.position }}
                                    </Badge>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/pigeons/${pigeon.id}`"
                                            class="font-medium hover:text-primary hover:underline"
                                        >
                                            {{ pigeonLabel(pigeon) }}
                                        </Link>
                                        <Badge v-if="getPigeonStatusBadge(pigeon)" :variant="getPigeonStatusBadge(pigeon)!.variant" class="text-xs">
                                            <AlertTriangle class="mr-1 h-3 w-3" />
                                            {{ getPigeonStatusBadge(pigeon)!.label }}
                                        </Badge>
                                    </div>
                                    <p v-if="pigeon.color" class="text-sm text-muted-foreground">
                                        {{ pigeon.color }}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <Badge v-if="pigeon.pivot?.did_not_arrive" variant="destructive" class="gap-1">
                                        <XCircle class="h-3 w-3" />
                                        DNA
                                    </Badge>
                                    <Badge v-else-if="pigeon.pivot?.arrival_time" variant="default" class="gap-1">
                                        <CheckCircle class="h-3 w-3" />
                                        Arrived
                                    </Badge>
                                    <span v-else class="text-muted-foreground">-</span>
                                </TableCell>
                                <TableCell class="font-mono text-sm">
                                    {{ pigeon.pivot?.arrival_time || '-' }}
                                </TableCell>
                                <TableCell class="font-mono text-sm">
                                    {{ pigeon.pivot?.speed ? pigeon.pivot.speed.toFixed(4) : '-' }}
                                </TableCell>
                                <TableCell class="text-sm text-muted-foreground max-w-[150px] truncate">
                                    {{ pigeon.pivot?.notes || '-' }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button variant="ghost" size="sm" @click="startEditResult(pigeon)">
                                            Edit
                                        </Button>
                                        <Button variant="ghost" size="sm" @click="removeResult(pigeon)">
                                            <Trash2 class="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Edit Result Modal -->
        <Dialog v-model:open="showEditResultModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Result</DialogTitle>
                    <DialogDescription v-if="editingPigeon">
                        Update result for {{ pigeonLabel(editingPigeon) }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="edit_position">Position</Label>
                            <Input
                                id="edit_position"
                                v-model="editForm.position"
                                type="number"
                                min="1"
                                placeholder="1"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="edit_arrival_time">Arrival Time</Label>
                            <Input
                                id="edit_arrival_time"
                                v-model="editForm.arrival_time"
                                type="time"
                                step="1"
                            />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="edit_speed">Speed (m/min)</Label>
                        <Input
                            id="edit_speed"
                            v-model="editForm.speed"
                            type="number"
                            step="0.0001"
                            min="0"
                        />
                        <p class="text-xs text-muted-foreground">Speed is auto-calculated when you change arrival time</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="edit_notes">Notes</Label>
                        <Input
                            id="edit_notes"
                            v-model="editForm.notes"
                            placeholder="Optional..."
                        />
                    </div>
                    <div class="flex items-center space-x-2">
                        <Checkbox
                            id="edit_did_not_arrive"
                            :checked="editForm.did_not_arrive"
                            @update:checked="editForm.did_not_arrive = $event"
                        />
                        <Label for="edit_did_not_arrive" class="text-sm font-normal">Did Not Arrive (DNA)</Label>
                    </div>
                </div>
                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button variant="outline" @click="showEditResultModal = false" class="w-full sm:w-auto">
                        Cancel
                    </Button>
                    <Button @click="submitEditResult" :disabled="editForm.processing" class="w-full sm:w-auto">
                        Save Changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
