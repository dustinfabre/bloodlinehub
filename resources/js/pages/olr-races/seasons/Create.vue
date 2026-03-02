<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { ref, computed } from 'vue';

interface Pigeon {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
}

interface OlrRace {
    id: number;
    name: string;
}

const props = defineProps<{
    olrRace: OlrRace;
    availablePigeons: Pigeon[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'OLR Races', href: '/olr-races' },
    { title: props.olrRace.name, href: `/olr-races/${props.olrRace.id}` },
    { title: 'Create Season', href: `/olr-races/${props.olrRace.id}/seasons/create` },
];

const currentYear = new Date().getFullYear();

const searchQuery = ref('');
const selectedPigeonIds = ref<number[]>([]);
const selectAll = ref(false);

const form = useForm({
    name: `Season ${currentYear}`,
    year: currentYear,
    start_date: '',
    end_date: '',
    status: 'active',
    pigeon_ids: [] as number[],
});

const pigeonLabel = (pigeon: Pigeon) =>
    pigeon.name || pigeon.ring_number || pigeon.personal_number || `Pigeon #${pigeon.id}`;

const filteredPigeons = computed(() => {
    if (!searchQuery.value) return props.availablePigeons;
    const query = searchQuery.value.toLowerCase();
    return props.availablePigeons.filter(p => 
        (p.ring_number?.toLowerCase().includes(query)) ||
        (p.personal_number?.toLowerCase().includes(query)) ||
        (p.name?.toLowerCase().includes(query)) ||
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

const submit = () => {
    form.pigeon_ids = selectedPigeonIds.value;
    form.post(`/olr-races/${props.olrRace.id}/seasons`);
};
</script>

<template>
    <Head title="Create Season" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Create Season</h1>
                <p class="text-sm text-muted-foreground">Add a new season to {{ olrRace.name }}</p>
            </div>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Season Details</CardTitle>
                    <CardDescription>Enter the details for this season</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="name">Name *</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    required
                                    placeholder="e.g., Season 2025"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="year">Year *</Label>
                                <Input
                                    id="year"
                                    v-model="form.year"
                                    type="number"
                                    required
                                    min="2000"
                                    max="2100"
                                />
                                <InputError :message="form.errors.year" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="start_date">Start Date</Label>
                                <Input
                                    id="start_date"
                                    v-model="form.start_date"
                                    type="date"
                                />
                                <InputError :message="form.errors.start_date" />
                            </div>

                            <div class="space-y-2">
                                <Label for="end_date">End Date</Label>
                                <Input
                                    id="end_date"
                                    v-model="form.end_date"
                                    type="date"
                                />
                                <InputError :message="form.errors.end_date" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="status">Status *</Label>
                            <Select v-model="form.status">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="completed">Completed</SelectItem>
                                    <SelectItem value="cancelled">Cancelled</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.status" />
                        </div>

                        <!-- Pigeon Selection (Optional) -->
                        <div v-if="availablePigeons.length > 0" class="space-y-4 border-t pt-4">
                            <div>
                                <Label class="text-base font-semibold">Add Pigeons (Optional)</Label>
                                <p class="text-sm text-muted-foreground mt-1">Select pigeons to add to this season now, or add them later.</p>
                            </div>

                            <!-- Search -->
                            <div class="space-y-2">
                                <Input 
                                    v-model="searchQuery" 
                                    placeholder="Search by ring number, name, color..."
                                    class="w-full"
                                />
                            </div>

                            <!-- Select All -->
                            <div v-if="filteredPigeons.length > 0" class="flex items-center space-x-2">
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

                            <!-- Pigeon List -->
                            <div class="max-h-64 overflow-y-auto space-y-2 border rounded-lg p-2">
                                <div v-if="filteredPigeons.length === 0" class="text-center py-4 text-muted-foreground text-sm">
                                    No pigeons found
                                </div>
                                <div 
                                    v-for="pigeon in filteredPigeons" 
                                    :key="pigeon.id"
                                    class="flex items-center space-x-3 p-2 rounded hover:bg-accent/50 cursor-pointer"
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
                                        <div class="font-medium text-sm">{{ pigeon.ring_number || pigeon.personal_number || 'No Ring' }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ pigeon.name || 'Unnamed' }}
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

                        <div class="flex gap-3">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Creating...' : 'Create Season' }}
                            </Button>
                            <Button type="button" variant="outline" as-child>
                                <Link :href="`/olr-races/${olrRace.id}`">Cancel</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
