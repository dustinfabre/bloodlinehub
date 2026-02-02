<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Head, Link, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Calendar, Pencil, Trash2, Bird, Cloud, Wind, Clock, Plus, Users, AlertTriangle, Eye } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';

interface Pigeon {
    id: number;
    band_number: string;
    name: string | null;
    sex: string;
    status?: string;
}

interface ClubRaceResult {
    id: number;
    pigeon_id: number;
    position: number | null;
    arrival_time: string | null;
    speed: number | null;
    notes: string | null;
    did_not_arrive: boolean;
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
    results: ClubRaceResult[];
}

interface ClubSeasonEntry {
    id: number;
    pigeon_id: number;
    pigeon: Pigeon;
}

interface ClubSeason {
    id: number;
    name: string;
    entries: ClubSeasonEntry[];
}

interface Club {
    id: number;
    name: string;
}

const props = defineProps<{
    club: Club;
    season: ClubSeason;
    race: ClubSeasonRace;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: props.season.name, href: `/clubs/${props.club.id}/seasons/${props.season.id}` },
    { title: `${props.race.release_point} ${props.race.distance}${props.race.distance_unit}`, href: `/clubs/${props.club.id}/seasons/${props.season.id}/races/${props.race.id}` },
];

const selectedPigeonId = ref<string>('');
const showEditModal = ref(false);
const editingResult = ref<ClubRaceResult | null>(null);
const editForm = ref({
    position: '',
    arrival_time: '',
    speed: '',
    notes: '',
    did_not_arrive: false,
});

const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
};

const formatTime = (time: string | null) => {
    if (!time) return '-';
    return time.slice(0, 5);
};

// Calculate speed based on release time, arrival time, and distance
// Speed is in meters per minute (mpm)
const calculateSpeed = (arrivalTime: string, releaseTime: string | null, distance: number, distanceUnit: string): number | null => {
    if (!releaseTime || !arrivalTime) return null;
    
    // Parse times
    const [releaseHour, releaseMin] = releaseTime.split(':').map(Number);
    const arrivalParts = arrivalTime.split(':');
    const arrivalHour = Number(arrivalParts[0]);
    const arrivalMin = Number(arrivalParts[1]);
    const arrivalSec = arrivalParts[2] ? Number(arrivalParts[2]) : 0;
    
    // Calculate time difference in minutes
    let timeDiffMinutes = (arrivalHour * 60 + arrivalMin + arrivalSec / 60) - (releaseHour * 60 + releaseMin);
    
    // Handle overnight races (if arrival is before release, assume next day)
    if (timeDiffMinutes < 0) {
        timeDiffMinutes += 24 * 60;
    }
    
    if (timeDiffMinutes <= 0) return null;
    
    // Convert distance to meters
    let distanceMeters = distance;
    if (distanceUnit === 'km') {
        distanceMeters = distance * 1000;
    } else if (distanceUnit === 'mi') {
        distanceMeters = distance * 1609.34;
    }
    
    // Calculate speed in meters per minute
    const speed = distanceMeters / timeDiffMinutes;
    return Math.round(speed * 100) / 100;
};

// Auto-compute speed when arrival time changes
watch(() => editForm.value.arrival_time, (newArrival) => {
    if (newArrival && props.race.release_time && !editForm.value.did_not_arrive) {
        const computedSpeed = calculateSpeed(
            newArrival,
            props.race.release_time,
            props.race.distance,
            props.race.distance_unit
        );
        if (computedSpeed) {
            editForm.value.speed = computedSpeed.toString();
        }
    }
});

// Pigeons that are in the season but not yet added to this race (excluding DNA/missing pigeons)
const availablePigeons = computed(() => {
    const resultPigeonIds = props.race.results.map(r => r.pigeon_id);
    return props.season.entries
        .filter(entry => !resultPigeonIds.includes(entry.pigeon_id))
        .filter(entry => entry.pigeon.status === 'alive')
        .map(entry => entry.pigeon);
});

const arrivedCount = computed(() => {
    return props.race.results.filter(r => !r.did_not_arrive).length;
});

const sortedResults = computed(() => {
    return [...props.race.results].sort((a, b) => {
        // Did not arrive goes last
        if (a.did_not_arrive && !b.did_not_arrive) return 1;
        if (!a.did_not_arrive && b.did_not_arrive) return -1;
        // Then by position
        if (a.position && b.position) return a.position - b.position;
        if (a.position) return -1;
        if (b.position) return 1;
        return 0;
    });
});

const getPigeonStatusBadge = (pigeon: Pigeon) => {
    if (pigeon.status === 'missing') return { label: 'Missing', variant: 'destructive' as const };
    if (pigeon.status === 'deceased') return { label: 'Deceased', variant: 'destructive' as const };
    return null;
};

const addResult = () => {
    if (!selectedPigeonId.value) return;
    router.post(`/clubs/${props.club.id}/seasons/${props.season.id}/races/${props.race.id}/results`, {
        pigeon_id: selectedPigeonId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedPigeonId.value = '';
        },
    });
};

const addAllEntries = () => {
    if (availablePigeons.value.length === 0) return;
    if (!confirm(`Add all ${availablePigeons.value.length} remaining entries to this race?`)) return;
    router.post(`/clubs/${props.club.id}/seasons/${props.season.id}/races/${props.race.id}/results/add-all`, {}, {
        preserveScroll: true,
    });
};

const removeResult = (result: ClubRaceResult) => {
    if (!confirm(`Remove ${result.pigeon.ring_number || result.pigeon.name || 'this pigeon'} from this race?`)) return;
    router.delete(`/clubs/${props.club.id}/seasons/${props.season.id}/races/${props.race.id}/results/${result.id}`, {
        preserveScroll: true,
    });
};

const openEditModal = (result: ClubRaceResult) => {
    editingResult.value = result;
    editForm.value = {
        position: result.position?.toString() || '',
        arrival_time: result.arrival_time || '',
        speed: result.speed?.toString() || '',
        notes: result.notes || '',
        did_not_arrive: result.did_not_arrive,
    };
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingResult.value = null;
};

const saveResult = () => {
    if (!editingResult.value) return;
    router.put(`/clubs/${props.club.id}/seasons/${props.season.id}/races/${props.race.id}/results/${editingResult.value.id}`, {
        position: editForm.value.position || null,
        arrival_time: editForm.value.arrival_time || null,
        speed: editForm.value.speed || null,
        notes: editForm.value.notes || null,
        did_not_arrive: editForm.value.did_not_arrive,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeEditModal();
        },
    });
};

const toggleDNA = (result: ClubRaceResult) => {
    router.put(`/clubs/${props.club.id}/seasons/${props.season.id}/races/${props.race.id}/results/${result.id}`, {
        position: result.position,
        arrival_time: result.arrival_time,
        speed: result.speed,
        notes: result.notes,
        did_not_arrive: !result.did_not_arrive,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${race.release_point} ${race.distance}${race.distance_unit} - ${season.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        <h1 class="text-xl font-semibold text-foreground sm:text-2xl">
                            {{ race.release_point }} {{ race.distance }}{{ race.distance_unit }}
                        </h1>
                        <Badge variant="secondary" class="font-mono">
                            {{ arrivedCount }}/{{ race.results.length }}
                        </Badge>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground sm:gap-4">
                        <span class="flex items-center gap-1">
                            <Calendar class="h-4 w-4" />
                            {{ formatDate(race.race_date) }}
                        </span>
                        <span v-if="race.release_time" class="flex items-center gap-1">
                            <Clock class="h-4 w-4" />
                            Release: {{ formatTime(race.release_time) }}
                        </span>
                    </div>
                </div>
                <Button variant="outline" size="sm" as-child class="w-full sm:w-auto">
                    <Link :href="`/clubs/${club.id}/seasons/${season.id}/races/${race.id}/edit`">
                        <Pencil class="mr-2 h-4 w-4" />
                        Edit Race
                    </Link>
                </Button>
            </div>

            <!-- Race Conditions -->
            <div v-if="race.weather_conditions || race.wind_direction" class="flex flex-wrap gap-2">
                <Badge v-if="race.weather_conditions" variant="outline" class="flex items-center gap-1">
                    <Cloud class="h-3 w-3" />
                    {{ race.weather_conditions }}
                </Badge>
                <Badge v-if="race.wind_direction" variant="outline" class="flex items-center gap-1">
                    <Wind class="h-3 w-3" />
                    Wind: {{ race.wind_direction }}
                </Badge>
            </div>

            <!-- Results -->
            <Card>
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <Bird class="h-5 w-5" />
                            Race Results
                        </CardTitle>
                        <CardDescription>Track arrival times and positions for each pigeon</CardDescription>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Add Result Form -->
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
                        <Select v-model="selectedPigeonId" class="flex-1">
                            <SelectTrigger class="w-full sm:w-[300px]">
                                <SelectValue placeholder="Select a pigeon to add..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="pigeon in availablePigeons"
                                    :key="pigeon.id"
                                    :value="String(pigeon.id)"
                                >
                                    {{ pigeon.ring_number || pigeon.personal_number || 'No Ring' }} - {{ pigeon.name || 'Unnamed' }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <div class="flex gap-2">
                            <Button @click="addResult" :disabled="!selectedPigeonId" class="flex-1 sm:flex-none">
                                <Plus class="mr-2 h-4 w-4" />
                                Add
                            </Button>
                            <Button
                                v-if="availablePigeons.length > 0"
                                variant="outline"
                                @click="addAllEntries"
                                class="flex-1 sm:flex-none"
                            >
                                <Users class="mr-2 h-4 w-4" />
                                Add All ({{ availablePigeons.length }})
                            </Button>
                        </div>
                    </div>

                    <div v-if="race.results.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-8 text-center">
                        <Bird class="h-8 w-8 text-muted-foreground/60" />
                        <h3 class="mt-2 font-medium">No results yet</h3>
                        <p class="text-sm text-muted-foreground">Add pigeons from this season's entries to record their results.</p>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="space-y-2 sm:hidden" v-else>
                        <div
                            v-for="result in sortedResults"
                            :key="result.id"
                            class="rounded-lg border p-3"
                            :class="{ 'opacity-50 bg-muted/50': result.did_not_arrive, 'bg-destructive/5': result.pigeon.status !== 'alive' }"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span v-if="result.position" class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                                            {{ result.position }}
                                        </span>
                                        <span class="font-medium">{{ result.pigeon.band_number }}</span>
                                        <Badge v-if="getPigeonStatusBadge(result.pigeon)" :variant="getPigeonStatusBadge(result.pigeon)!.variant" class="text-xs">
                                            {{ getPigeonStatusBadge(result.pigeon)!.label }}
                                        </Badge>
                                    </div>
                                    <p class="text-sm text-muted-foreground">{{ result.pigeon.name || '-' }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                        <span v-if="result.arrival_time">Arrival: {{ formatTime(result.arrival_time) }}</span>
                                        <span v-if="result.speed">Speed: {{ result.speed }} mpm</span>
                                    </div>
                                    <p v-if="result.notes" class="mt-1 text-xs text-muted-foreground truncate">{{ result.notes }}</p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-1 text-xs">
                                        <Checkbox
                                            :checked="result.did_not_arrive"
                                            @update:checked="toggleDNA(result)"
                                        />
                                        <span>DNA</span>
                                    </div>
                                    <div class="flex gap-1">
                                        <Button variant="ghost" size="sm" @click="openEditModal(result)">
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="sm" @click="removeResult(result)">
                                            <Trash2 class="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table View -->
                    <Table class="hidden sm:table" v-if="race.results.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[60px]">Pos</TableHead>
                                <TableHead>Band Number</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Arrival</TableHead>
                                <TableHead>Speed</TableHead>
                                <TableHead>Notes</TableHead>
                                <TableHead class="w-[60px]">DNA</TableHead>
                                <TableHead class="w-[100px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="result in sortedResults"
                                :key="result.id"
                                :class="{ 'opacity-50': result.did_not_arrive, 'bg-destructive/5': result.pigeon.status !== 'alive' }"
                            >
                                <TableCell class="font-bold">
                                    {{ result.position || '-' }}
                                </TableCell>
                                <TableCell class="font-medium">{{ result.pigeon.ring_number || result.pigeon.personal_number }}</TableCell>
                                <TableCell>{{ result.pigeon.name || '-' }}</TableCell>
                                <TableCell>
                                    <Badge v-if="getPigeonStatusBadge(result.pigeon)" :variant="getPigeonStatusBadge(result.pigeon)!.variant" class="text-xs">
                                        <AlertTriangle class="mr-1 h-3 w-3" />
                                        {{ getPigeonStatusBadge(result.pigeon)!.label }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{ result.arrival_time ? formatTime(result.arrival_time) : '-' }}</TableCell>
                                <TableCell>{{ result.speed ? `${result.speed} mpm` : '-' }}</TableCell>
                                <TableCell class="max-w-[150px] truncate">{{ result.notes || '-' }}</TableCell>
                                <TableCell>
                                    <Checkbox
                                        :checked="result.did_not_arrive"
                                        @update:checked="toggleDNA(result)"
                                    />
                                </TableCell>
                                <TableCell>
                                    <div class="flex gap-1">
                                        <Button variant="ghost" size="sm" @click="openEditModal(result)">
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="sm" @click="removeResult(result)">
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
        <Dialog v-model:open="showEditModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Result</DialogTitle>
                    <DialogDescription v-if="editingResult">
                        {{ editingResult.pigeon.ring_number || editingResult.pigeon.personal_number }} - {{ editingResult.pigeon.name || 'Unnamed' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="grid gap-4 grid-cols-2">
                        <div class="space-y-2">
                            <Label for="edit-position">Position</Label>
                            <Input
                                id="edit-position"
                                v-model="editForm.position"
                                type="number"
                                placeholder="#"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="edit-arrival">Arrival Time</Label>
                            <Input
                                id="edit-arrival"
                                v-model="editForm.arrival_time"
                                type="time"
                                step="1"
                            />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="edit-speed">
                            Speed (mpm)
                            <span v-if="race.release_time" class="text-xs text-muted-foreground ml-1">Auto-calculated</span>
                        </Label>
                        <Input
                            id="edit-speed"
                            v-model="editForm.speed"
                            type="number"
                            step="0.01"
                            placeholder="meters per minute"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="edit-notes">Notes</Label>
                        <Input
                            id="edit-notes"
                            v-model="editForm.notes"
                            placeholder="Optional notes..."
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="edit-dna"
                            :checked="editForm.did_not_arrive"
                            @update:checked="editForm.did_not_arrive = $event as boolean"
                        />
                        <Label for="edit-dna" class="cursor-pointer">Did Not Arrive (DNA)</Label>
                    </div>
                </div>
                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button variant="outline" @click="closeEditModal" class="w-full sm:w-auto">
                        Cancel
                    </Button>
                    <Button @click="saveResult" class="w-full sm:w-auto">
                        Save Changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
