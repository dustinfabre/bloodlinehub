<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Plus, Calendar, Trash2, Bird, AlertTriangle } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Pigeon {
    id: number;
    ring_number: string;
    personal_number: string;
    name: string | null;
    gender: string;
    status?: string;
}

interface ClubSeasonEvent {
    id: number;
    name: string;
    event_date: string | null;
    notes: string | null;
    entries: Pigeon[];
}

interface ClubSeason {
    id: number;
    name: string;
}

interface Club {
    id: number;
    name: string;
}

const props = defineProps<{
    club: Club;
    season: ClubSeason;
    event: ClubSeasonEvent;
    availablePigeons: Pigeon[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: props.season.name, href: `/clubs/${props.club.id}/seasons/${props.season.id}` },
    { title: props.event.name, href: `/clubs/${props.club.id}/seasons/${props.season.id}/events/${props.event.id}` },
];

const showAddEntryModal = ref(false);
const searchQuery = ref('');
const selectedPigeonIds = ref<number[]>([]);
const selectAll = ref(false);

const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const getPigeonStatusBadge = (pigeon: Pigeon) => {
    if (pigeon.status === 'missing') return { label: 'Missing', variant: 'destructive' as const };
    if (pigeon.status === 'deceased') return { label: 'Deceased', variant: 'destructive' as const };
    if (pigeon.status === 'flyaway') return { label: 'Flyaway', variant: 'destructive' as const };
    return null;
};

const filteredPigeons = computed(() => {
    if (!searchQuery.value) return props.availablePigeons;
    const query = searchQuery.value.toLowerCase();
    return props.availablePigeons.filter(p =>
        (p.ring_number?.toLowerCase().includes(query)) ||
        (p.personal_number?.toLowerCase().includes(query)) ||
        (p.name?.toLowerCase().includes(query))
    );
});

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedPigeonIds.value = filteredPigeons.value.map(p => p.id);
    } else {
        selectedPigeonIds.value = [];
    }
};

const addEntries = () => {
    if (selectedPigeonIds.value.length === 0) return;

    router.post(`/clubs/${props.club.id}/seasons/${props.season.id}/events/${props.event.id}/entries/bulk`, {
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

const removeEntry = (pigeon: Pigeon) => {
    if (!confirm(`Remove ${pigeon.ring_number || pigeon.name || 'this pigeon'} from this event?`)) return;
    router.delete(`/clubs/${props.club.id}/seasons/${props.season.id}/events/${props.event.id}/entries/${pigeon.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${event.name} - ${season.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-foreground sm:text-2xl">{{ event.name }}</h1>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground sm:gap-4">
                        <span v-if="event.event_date" class="flex items-center gap-1">
                            <Calendar class="h-4 w-4" />
                            {{ formatDate(event.event_date) }}
                        </span>
                        <span v-if="event.notes">{{ event.notes }}</span>
                    </div>
                </div>
                <Button variant="outline" size="sm" as-child class="w-full sm:w-auto">
                    <Link :href="`/clubs/${club.id}/seasons/${season.id}`">
                        Back to Season
                    </Link>
                </Button>
            </div>

            <!-- Entries -->
            <Card>
                <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <Bird class="h-5 w-5" />
                            Entries
                            <Badge variant="outline" class="ml-2">
                                {{ event.entries.length }}
                            </Badge>
                        </CardTitle>
                        <CardDescription>Pigeons entered in this event</CardDescription>
                    </div>
                    <Dialog v-model:open="showAddEntryModal">
                        <DialogTrigger as-child>
                            <Button :disabled="availablePigeons.length === 0" class="w-full sm:w-auto">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Entries
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>Add Entries</DialogTitle>
                                <DialogDescription>
                                    Select pigeons from your team to enter in this event
                                </DialogDescription>
                            </DialogHeader>
                            <div class="space-y-4 py-4">
                                <div class="space-y-2">
                                    <Label for="search">Search Pigeons</Label>
                                    <Input
                                        id="search"
                                        v-model="searchQuery"
                                        placeholder="Search by ring number, name..."
                                        class="w-full"
                                    />
                                </div>

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

                                <div class="max-h-96 overflow-y-auto space-y-2">
                                    <div v-if="filteredPigeons.length === 0" class="text-center py-8 text-muted-foreground">
                                        No pigeons available. Make sure you have pigeons in your team first.
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
                                            </div>
                                        </Label>
                                    </div>
                                </div>

                                <div v-if="selectedPigeonIds.length > 0" class="text-sm text-muted-foreground">
                                    {{ selectedPigeonIds.length }} pigeon(s) selected
                                </div>
                            </div>
                            <DialogFooter class="flex-col gap-2 sm:flex-row">
                                <Button variant="outline" @click="showAddEntryModal = false" class="w-full sm:w-auto">
                                    Cancel
                                </Button>
                                <Button @click="addEntries" :disabled="selectedPigeonIds.length === 0" class="w-full sm:w-auto">
                                    Add {{ selectedPigeonIds.length > 0 ? `${selectedPigeonIds.length} ` : '' }}Entr{{ selectedPigeonIds.length === 1 ? 'y' : 'ies' }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </CardHeader>
                <CardContent>
                    <div v-if="event.entries.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-8 text-center">
                        <Bird class="h-8 w-8 text-muted-foreground/60" />
                        <h3 class="mt-2 font-medium">No entries yet</h3>
                        <p class="text-sm text-muted-foreground">Add pigeons from your team to enter them in this event.</p>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="space-y-2 sm:hidden" v-else>
                        <div
                            v-for="entry in event.entries"
                            :key="entry.id"
                            class="flex items-center justify-between rounded-lg border p-3"
                            :class="{ 'bg-destructive/5': entry.status === 'deceased' || entry.status === 'missing' || entry.status === 'flyaway' }"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium truncate">{{ entry.ring_number || entry.personal_number }} - {{ entry.name || 'Unnamed' }}</span>
                                    <Badge v-if="getPigeonStatusBadge(entry)" :variant="getPigeonStatusBadge(entry)!.variant" class="text-xs">
                                        <AlertTriangle class="mr-1 h-3 w-3" />
                                        {{ getPigeonStatusBadge(entry)!.label }}
                                    </Badge>
                                </div>
                            </div>
                            <Button variant="ghost" size="sm" @click="removeEntry(entry)">
                                <Trash2 class="h-4 w-4 text-destructive" />
                            </Button>
                        </div>
                    </div>

                    <!-- Desktop Table View -->
                    <Table class="hidden sm:table" v-if="event.entries.length > 0">
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
                                v-for="entry in event.entries"
                                :key="entry.id"
                                :class="{ 'bg-destructive/5': entry.status === 'deceased' || entry.status === 'missing' || entry.status === 'flyaway' }"
                            >
                                <TableCell class="font-medium">{{ entry.ring_number || entry.personal_number }}</TableCell>
                                <TableCell>{{ entry.name || '-' }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline" class="capitalize">{{ entry.gender }}</Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="getPigeonStatusBadge(entry) ? 'destructive' : 'outline'"
                                        class="capitalize"
                                    >
                                        {{ entry.status }}
                                    </Badge>
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
        </div>
    </AppLayout>
</template>
