<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { type BreadcrumbItem } from '@/types';

interface Pigeon {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    gender: string | null;
    pivot?: {
        arrival_time: string | null;
        speed: number | null;
        position: number | null;
        notes: string | null;
    };
}

interface ClubRace {
    id: number;
    name: string;
    club_name: string | null;
    release_point: string | null;
    distance: number | null;
    distance_unit: string;
    race_date: string | null;
    release_time: string | null;
    description: string | null;
    weather_conditions: string | null;
    wind_direction: string | null;
    status: string;
    pigeons: Pigeon[];
}

const props = defineProps<{
    clubRace: ClubRace;
    availablePigeons: Pigeon[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Club Races', href: '/club-races' },
    { title: props.clubRace.name, href: `/club-races/${props.clubRace.id}` },
];

const showAddForm = ref(false);
const editingPigeon = ref<number | null>(null);

const addForm = useForm({
    pigeon_id: '',
    arrival_time: '',
    speed: '',
    position: '',
    notes: '',
});

const editForm = useForm({
    arrival_time: '',
    speed: '',
    position: '',
    notes: '',
});

const pigeonLabel = (pigeon: Pigeon) =>
    pigeon.name || pigeon.ring_number || pigeon.personal_number || `Pigeon #${pigeon.id}`;

const formatDate = (value: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatTime = (value: string | null) => {
    if (!value) return '-';
    return value;
};

const statusBadgeVariant = (status: string) => {
    if (status === 'upcoming') return 'default';
    if (status === 'completed') return 'secondary';
    return 'destructive';
};

const submitAdd = () => {
    addForm.post(`/club-races/${props.clubRace.id}/pigeons`, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAddForm.value = false;
        },
    });
};

const startEdit = (pigeon: Pigeon) => {
    editingPigeon.value = pigeon.id;
    editForm.arrival_time = pigeon.pivot?.arrival_time || '';
    editForm.speed = pigeon.pivot?.speed?.toString() || '';
    editForm.position = pigeon.pivot?.position?.toString() || '';
    editForm.notes = pigeon.pivot?.notes || '';
};

const cancelEdit = () => {
    editingPigeon.value = null;
    editForm.reset();
};

const submitEdit = (pigeonId: number) => {
    editForm.patch(`/club-races/${props.clubRace.id}/pigeons/${pigeonId}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingPigeon.value = null;
            editForm.reset();
        },
    });
};

const removePigeon = (pigeon: Pigeon) => {
    if (!window.confirm(`Remove ${pigeonLabel(pigeon)} from this race?`)) return;
    router.delete(`/club-races/${props.clubRace.id}/pigeons/${pigeon.id}`, {
        preserveScroll: true,
    });
};

const handleDelete = () => {
    if (!window.confirm(`Delete "${props.clubRace.name}"? This action cannot be undone.`)) return;
    router.delete(`/club-races/${props.clubRace.id}`);
};

// Sort pigeons by position (nulls last)
const sortedPigeons = [...props.clubRace.pigeons].sort((a, b) => {
    if (a.pivot?.position && b.pivot?.position) {
        return a.pivot.position - b.pivot.position;
    }
    if (a.pivot?.position) return -1;
    if (b.pivot?.position) return 1;
    return 0;
});
</script>

<template>
    <Head :title="clubRace.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-semibold text-foreground">
                            {{ clubRace.name }}
                        </h1>
                        <Badge :variant="statusBadgeVariant(clubRace.status)">
                            {{ clubRace.status }}
                        </Badge>
                    </div>
                    <p v-if="clubRace.club_name" class="mt-1 text-sm text-muted-foreground">
                        {{ clubRace.club_name }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="`/club-races/${clubRace.id}/edit`">
                            Edit
                        </Link>
                    </Button>
                    <Button variant="destructive" @click="handleDelete">
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
                    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-sm font-medium text-muted-foreground">Release Point</dt>
                            <dd class="mt-1 text-sm text-foreground">{{ clubRace.release_point || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-muted-foreground">Distance</dt>
                            <dd class="mt-1 text-sm text-foreground">
                                {{ clubRace.distance ? `${clubRace.distance} ${clubRace.distance_unit}` : '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-muted-foreground">Race Date</dt>
                            <dd class="mt-1 text-sm text-foreground">{{ formatDate(clubRace.race_date) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-muted-foreground">Release Time</dt>
                            <dd class="mt-1 text-sm text-foreground">{{ formatTime(clubRace.release_time) }}</dd>
                        </div>
                    </dl>
                    <dl v-if="clubRace.weather_conditions || clubRace.wind_direction" class="mt-4 grid gap-4 border-t pt-4 sm:grid-cols-2">
                        <div v-if="clubRace.weather_conditions">
                            <dt class="text-sm font-medium text-muted-foreground">Weather</dt>
                            <dd class="mt-1 text-sm text-foreground">{{ clubRace.weather_conditions }}</dd>
                        </div>
                        <div v-if="clubRace.wind_direction">
                            <dt class="text-sm font-medium text-muted-foreground">Wind</dt>
                            <dd class="mt-1 text-sm text-foreground">{{ clubRace.wind_direction }}</dd>
                        </div>
                    </dl>
                    <div v-if="clubRace.description" class="mt-4 border-t pt-4">
                        <dt class="text-sm font-medium text-muted-foreground">Description</dt>
                        <dd class="mt-1 text-sm text-foreground whitespace-pre-wrap">{{ clubRace.description }}</dd>
                    </div>
                </CardContent>
            </Card>

            <!-- Pigeons in Race -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Results ({{ clubRace.pigeons.length }})</CardTitle>
                        <CardDescription>Pigeons participating in this race</CardDescription>
                    </div>
                    <Button
                        v-if="availablePigeons.length > 0 && !showAddForm"
                        @click="showAddForm = true"
                    >
                        Add Pigeon
                    </Button>
                </CardHeader>
                <CardContent>
                    <!-- Add Pigeon Form -->
                    <div v-if="showAddForm" class="mb-6 rounded-lg border bg-muted/50 p-4">
                        <h4 class="mb-4 font-medium">Add Pigeon to Race</h4>
                        <form @submit.prevent="submitAdd" class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                <div class="space-y-2">
                                    <Label for="pigeon_id">Pigeon *</Label>
                                    <select
                                        id="pigeon_id"
                                        v-model="addForm.pigeon_id"
                                        required
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <option value="">Select a pigeon...</option>
                                        <option
                                            v-for="pigeon in availablePigeons"
                                            :key="pigeon.id"
                                            :value="pigeon.id"
                                        >
                                            {{ pigeonLabel(pigeon) }}
                                        </option>
                                    </select>
                                    <InputError :message="addForm.errors.pigeon_id" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="position">Position</Label>
                                    <Input
                                        id="position"
                                        v-model="addForm.position"
                                        type="number"
                                        min="1"
                                        placeholder="e.g., 1"
                                    />
                                    <InputError :message="addForm.errors.position" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="arrival_time">Arrival Time</Label>
                                    <Input
                                        id="arrival_time"
                                        v-model="addForm.arrival_time"
                                        type="time"
                                        step="1"
                                    />
                                    <InputError :message="addForm.errors.arrival_time" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="speed">Speed (m/min)</Label>
                                    <Input
                                        id="speed"
                                        v-model="addForm.speed"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        placeholder="e.g., 1200.5"
                                    />
                                    <InputError :message="addForm.errors.speed" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="notes">Notes</Label>
                                    <Input
                                        id="notes"
                                        v-model="addForm.notes"
                                        type="text"
                                        placeholder="Optional..."
                                    />
                                    <InputError :message="addForm.errors.notes" />
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button type="submit" :disabled="addForm.processing">
                                    {{ addForm.processing ? 'Adding...' : 'Add' }}
                                </Button>
                                <Button type="button" variant="outline" @click="showAddForm = false">
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </div>

                    <!-- No pigeons message -->
                    <div
                        v-if="clubRace.pigeons.length === 0 && !showAddForm"
                        class="flex flex-col items-center justify-center rounded-lg border border-dashed border-primary/20 bg-primary/5 py-12 text-center"
                    >
                        <h3 class="text-lg font-semibold text-primary">No entries yet</h3>
                        <p class="mt-2 max-w-md text-sm text-muted-foreground">
                            <template v-if="availablePigeons.length > 0">
                                Add racing pigeons to record their results.
                            </template>
                            <template v-else>
                                You need pigeons with status "Racing" to add them to this race.
                                <Link href="/pigeons/create" class="text-primary hover:underline">
                                    Create a pigeon
                                </Link>
                                with pigeon status "Racing" first.
                            </template>
                        </p>
                        <Button
                            v-if="availablePigeons.length > 0"
                            class="mt-4"
                            @click="showAddForm = true"
                        >
                            Add Pigeon
                        </Button>
                    </div>

                    <!-- Pigeons Table -->
                    <div v-else-if="clubRace.pigeons.length > 0" class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b text-left text-sm text-muted-foreground">
                                    <th class="pb-3 font-medium">Pos</th>
                                    <th class="pb-3 font-medium">Pigeon</th>
                                    <th class="pb-3 font-medium">Arrival</th>
                                    <th class="pb-3 font-medium">Speed (m/min)</th>
                                    <th class="pb-3 font-medium">Notes</th>
                                    <th class="pb-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="pigeon in sortedPigeons"
                                    :key="pigeon.id"
                                    class="border-b last:border-b-0"
                                >
                                    <template v-if="editingPigeon === pigeon.id">
                                        <td class="py-4">
                                            <Input
                                                v-model="editForm.position"
                                                type="number"
                                                min="1"
                                                class="h-8 w-16"
                                            />
                                        </td>
                                        <td class="py-4">
                                            <Link
                                                :href="`/pigeons/${pigeon.id}`"
                                                class="font-medium text-foreground hover:text-primary hover:underline"
                                            >
                                                {{ pigeonLabel(pigeon) }}
                                            </Link>
                                        </td>
                                        <td class="py-4">
                                            <Input
                                                v-model="editForm.arrival_time"
                                                type="time"
                                                step="1"
                                                class="h-8 w-28"
                                            />
                                        </td>
                                        <td class="py-4">
                                            <Input
                                                v-model="editForm.speed"
                                                type="number"
                                                step="0.0001"
                                                min="0"
                                                class="h-8 w-28"
                                            />
                                        </td>
                                        <td class="py-4">
                                            <Input
                                                v-model="editForm.notes"
                                                type="text"
                                                class="h-8 w-full"
                                            />
                                        </td>
                                        <td class="py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    @click="submitEdit(pigeon.id)"
                                                    :disabled="editForm.processing"
                                                >
                                                    Save
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    @click="cancelEdit"
                                                >
                                                    Cancel
                                                </Button>
                                            </div>
                                        </td>
                                    </template>
                                    <template v-else>
                                        <td class="py-4">
                                            <Badge v-if="pigeon.pivot?.position" variant="secondary" class="font-mono">
                                                {{ pigeon.pivot.position }}
                                            </Badge>
                                            <span v-else class="text-muted-foreground">-</span>
                                        </td>
                                        <td class="py-4">
                                            <Link
                                                :href="`/pigeons/${pigeon.id}`"
                                                class="font-medium text-foreground hover:text-primary hover:underline"
                                            >
                                                {{ pigeonLabel(pigeon) }}
                                            </Link>
                                            <p v-if="pigeon.color" class="text-sm text-muted-foreground">
                                                {{ pigeon.color }}
                                            </p>
                                        </td>
                                        <td class="py-4 text-sm font-mono">
                                            {{ pigeon.pivot?.arrival_time || '-' }}
                                        </td>
                                        <td class="py-4 text-sm font-mono">
                                            {{ pigeon.pivot?.speed ? pigeon.pivot.speed.toFixed(4) : '-' }}
                                        </td>
                                        <td class="py-4 text-sm text-muted-foreground">
                                            {{ pigeon.pivot?.notes || '-' }}
                                        </td>
                                        <td class="py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    @click="startEdit(pigeon)"
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    @click="removePigeon(pigeon)"
                                                >
                                                    Remove
                                                </Button>
                                            </div>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
