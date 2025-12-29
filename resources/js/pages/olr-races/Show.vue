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
        entry_number: string | null;
        result: string | null;
        notes: string | null;
    };
}

interface OlrRace {
    id: number;
    name: string;
    organizer: string | null;
    location: string | null;
    country: string | null;
    year: number | null;
    start_date: string | null;
    end_date: string | null;
    description: string | null;
    website_url: string | null;
    status: string;
    pigeons: Pigeon[];
}

const props = defineProps<{
    olrRace: OlrRace;
    availablePigeons: Pigeon[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'OLR Races', href: '/olr-races' },
    { title: props.olrRace.name, href: `/olr-races/${props.olrRace.id}` },
];

const showAddForm = ref(false);
const editingPigeon = ref<number | null>(null);

const addForm = useForm({
    pigeon_id: '',
    entry_number: '',
    notes: '',
});

const editForm = useForm({
    entry_number: '',
    result: '',
    notes: '',
});

const pigeonLabel = (pigeon: Pigeon) =>
    pigeon.name || pigeon.ring_number || pigeon.personal_number || `Pigeon #${pigeon.id}`;

const formatDate = (value: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const statusBadgeVariant = (status: string) => {
    if (status === 'active') return 'default';
    if (status === 'completed') return 'secondary';
    return 'destructive';
};

const submitAdd = () => {
    addForm.post(`/olr-races/${props.olrRace.id}/pigeons`, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAddForm.value = false;
        },
    });
};

const startEdit = (pigeon: Pigeon) => {
    editingPigeon.value = pigeon.id;
    editForm.entry_number = pigeon.pivot?.entry_number || '';
    editForm.result = pigeon.pivot?.result || '';
    editForm.notes = pigeon.pivot?.notes || '';
};

const cancelEdit = () => {
    editingPigeon.value = null;
    editForm.reset();
};

const submitEdit = (pigeonId: number) => {
    editForm.patch(`/olr-races/${props.olrRace.id}/pigeons/${pigeonId}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingPigeon.value = null;
            editForm.reset();
        },
    });
};

const removePigeon = (pigeon: Pigeon) => {
    if (!window.confirm(`Remove ${pigeonLabel(pigeon)} from this race?`)) return;
    router.delete(`/olr-races/${props.olrRace.id}/pigeons/${pigeon.id}`, {
        preserveScroll: true,
    });
};

const handleDelete = () => {
    if (!window.confirm(`Delete "${props.olrRace.name}"? This action cannot be undone.`)) return;
    router.delete(`/olr-races/${props.olrRace.id}`);
};
</script>

<template>
    <Head :title="olrRace.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-semibold text-foreground">
                            {{ olrRace.name }}
                        </h1>
                        <Badge :variant="statusBadgeVariant(olrRace.status)">
                            {{ olrRace.status }}
                        </Badge>
                    </div>
                    <p v-if="olrRace.organizer" class="mt-1 text-sm text-muted-foreground">
                        Organized by {{ olrRace.organizer }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="`/olr-races/${olrRace.id}/edit`">
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
                            <dt class="text-sm font-medium text-muted-foreground">Location</dt>
                            <dd class="mt-1 text-sm text-foreground">
                                {{ [olrRace.location, olrRace.country].filter(Boolean).join(', ') || '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-muted-foreground">Year</dt>
                            <dd class="mt-1 text-sm text-foreground">{{ olrRace.year || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-muted-foreground">Start Date</dt>
                            <dd class="mt-1 text-sm text-foreground">{{ formatDate(olrRace.start_date) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-muted-foreground">End Date</dt>
                            <dd class="mt-1 text-sm text-foreground">{{ formatDate(olrRace.end_date) }}</dd>
                        </div>
                    </dl>
                    <div v-if="olrRace.description" class="mt-4 border-t pt-4">
                        <dt class="text-sm font-medium text-muted-foreground">Description</dt>
                        <dd class="mt-1 text-sm text-foreground whitespace-pre-wrap">{{ olrRace.description }}</dd>
                    </div>
                    <div v-if="olrRace.website_url" class="mt-4 border-t pt-4">
                        <dt class="text-sm font-medium text-muted-foreground">Website</dt>
                        <dd class="mt-1">
                            <a
                                :href="olrRace.website_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm text-primary hover:underline"
                            >
                                {{ olrRace.website_url }}
                            </a>
                        </dd>
                    </div>
                </CardContent>
            </Card>

            <!-- Pigeons in Race -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Entries ({{ olrRace.pigeons.length }})</CardTitle>
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
                            <div class="grid gap-4 sm:grid-cols-3">
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
                                            <template v-if="pigeon.color"> ({{ pigeon.color }})</template>
                                        </option>
                                    </select>
                                    <InputError :message="addForm.errors.pigeon_id" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="entry_number">Entry/Team Number</Label>
                                    <Input
                                        id="entry_number"
                                        v-model="addForm.entry_number"
                                        type="text"
                                        placeholder="e.g., 123"
                                    />
                                    <InputError :message="addForm.errors.entry_number" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="notes">Notes</Label>
                                    <Input
                                        id="notes"
                                        v-model="addForm.notes"
                                        type="text"
                                        placeholder="Optional notes..."
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
                        v-if="olrRace.pigeons.length === 0 && !showAddForm"
                        class="flex flex-col items-center justify-center rounded-lg border border-dashed border-primary/20 bg-primary/5 py-12 text-center"
                    >
                        <h3 class="text-lg font-semibold text-primary">No entries yet</h3>
                        <p class="mt-2 max-w-md text-sm text-muted-foreground">
                            <template v-if="availablePigeons.length > 0">
                                Add pigeons with race type "OLR" to this race.
                            </template>
                            <template v-else>
                                You need pigeons with race type "OLR" to add them to this race.
                                <Link href="/pigeons/create" class="text-primary hover:underline">
                                    Create a pigeon
                                </Link>
                                with race type "OLR" first.
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
                    <div v-else-if="olrRace.pigeons.length > 0" class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b text-left text-sm text-muted-foreground">
                                    <th class="pb-3 font-medium">Pigeon</th>
                                    <th class="pb-3 font-medium">Entry #</th>
                                    <th class="pb-3 font-medium">Result</th>
                                    <th class="pb-3 font-medium">Notes</th>
                                    <th class="pb-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="pigeon in olrRace.pigeons"
                                    :key="pigeon.id"
                                    class="border-b last:border-b-0"
                                >
                                    <template v-if="editingPigeon === pigeon.id">
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
                                                v-model="editForm.entry_number"
                                                type="text"
                                                class="h-8 w-24"
                                            />
                                        </td>
                                        <td class="py-4">
                                            <Input
                                                v-model="editForm.result"
                                                type="text"
                                                class="h-8 w-24"
                                                placeholder="e.g., 1st"
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
                                            <Link
                                                :href="`/pigeons/${pigeon.id}`"
                                                class="font-medium text-foreground hover:text-primary hover:underline"
                                            >
                                                {{ pigeonLabel(pigeon) }}
                                            </Link>
                                            <p v-if="pigeon.color" class="text-sm text-muted-foreground">
                                                {{ pigeon.color }}
                                                <span v-if="pigeon.gender"> · {{ pigeon.gender }}</span>
                                            </p>
                                        </td>
                                        <td class="py-4 text-sm">
                                            {{ pigeon.pivot?.entry_number || '-' }}
                                        </td>
                                        <td class="py-4 text-sm">
                                            <Badge v-if="pigeon.pivot?.result" variant="secondary">
                                                {{ pigeon.pivot.result }}
                                            </Badge>
                                            <span v-else class="text-muted-foreground">-</span>
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
