<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { index as pairingsIndex, edit as pairingsEdit, endSession as pairingsEndSession, show as pairingsShow } from '@/routes/pairings';
import { create as pigeonsCreate, show as pigeonsShow, store } from '@/routes/pigeons';
import { store as clutchStore, update as clutchUpdate, destroy as clutchDestroy } from '@/routes/pairings/clutches';
import { type BreadcrumbItem } from '@/types';
import { useToast } from '@/composables/useToast';

interface Pigeon {
    id: number;
    name: string;
    ring_number: string;
    bloodline?: string;
    hatch_date?: string;
    clutch_id?: number;
}

interface Clutch {
    id: number;
    pairing_id: number;
    clutch_number: number;
    eggs_laid_date?: string;
    hatched_date?: string;
    status: 'pending' | 'successful' | 'unsuccessful';
    notes?: string;
    created_at: string;
    updated_at: string;
}

interface Pairing {
    id: number;
    pair_name: string;
    status: 'active' | 'inactive';
    current_clutch_number: number;
    started_at: string;
    ended_at?: string;
    sire: Pigeon;
    dam: Pigeon;
    offspring: Pigeon[];
    clutches: Clutch[];
}

interface Props {
    pairing: Pairing;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Breeding', href: pairingsIndex().url },
    { title: props.pairing.pair_name, href: pairingsShow({ pairing: props.pairing.id }).url },
];

const showAddClutch = ref(false);
const showEditClutch = ref(false);
const showDeleteClutch = ref(false);
const showEndSession = ref(false);
const showAddOffspring = ref(false);
const selectedClutch = ref<Clutch | null>(null);

const { success } = useToast();

const getTodayDate = () => {
    const today = new Date();
    return today.toISOString().split('T')[0];
};

const formatDateForInput = (date: string | undefined) => {
    if (!date) return '';
    // Handle both YYYY-MM-DD and full ISO date formats
    return date.split('T')[0];
};

const clutchForm = useForm({
    eggs_laid_date: getTodayDate(),
    hatched_date: '',
    notes: '',
});

const editClutchForm = useForm({
    eggs_laid_date: '',
    hatched_date: '',
    status: 'pending' as 'pending' | 'successful' | 'unsuccessful',
    notes: '',
});

const offspringForm = useForm({
    ring_number: '',
    personal_number: '',
    color: '',
    gender: '',
    sire_id: null as number | null,
    dam_id: null as number | null,
    pairing_id: null as number | null,
    clutch_id: null as number | null,
    hatch_date: '',
    name: '',
    bloodline: '',
    pigeon_status: 'stock',
    race_type: 'none',
    status: 'alive',
    remarks: '',
    notes: '',
});

const addClutch = () => {
    clutchForm.post(clutchStore({ pairing: props.pairing.id }).url, {
        preserveScroll: true,
        onSuccess: () => {
            clutchForm.reset();
            showAddClutch.value = false;
            success('Clutch added successfully!');
        },
    });
};

const openEditClutch = (clutch: Clutch) => {
    selectedClutch.value = clutch;
    editClutchForm.eggs_laid_date = formatDateForInput(clutch.eggs_laid_date);
    editClutchForm.hatched_date = clutch.hatched_date ? formatDateForInput(clutch.hatched_date) : getTodayDate();
    editClutchForm.status = clutch.status;
    editClutchForm.notes = clutch.notes || '';
    showEditClutch.value = true;
};

const updateClutch = () => {
    if (!selectedClutch.value) return;
    
    editClutchForm.patch(clutchUpdate({ pairing: props.pairing.id, clutch: selectedClutch.value.id }).url, {
        preserveScroll: true,
        onSuccess: () => {
            editClutchForm.reset();
            showEditClutch.value = false;
            selectedClutch.value = null;
            success('Clutch updated successfully!');
        },
    });
};

const openDeleteClutch = (clutch: Clutch) => {
    selectedClutch.value = clutch;
    showDeleteClutch.value = true;
};

const deleteClutch = () => {
    if (!selectedClutch.value) return;
    
    router.delete(clutchDestroy({ pairing: props.pairing.id, clutch: selectedClutch.value.id }).url, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteClutch.value = false;
            selectedClutch.value = null;
            success('Clutch deleted successfully!');
        },
    });
};

const addOffspringToClutch = (clutch: Clutch) => {
    selectedClutch.value = clutch;
    offspringForm.sire_id = props.pairing.sire.id;
    offspringForm.dam_id = props.pairing.dam.id;
    offspringForm.pairing_id = props.pairing.id;
    offspringForm.clutch_id = clutch.id;
    offspringForm.hatch_date = clutch.hatched_date || '';
    offspringForm.bloodline = props.pairing.sire.bloodline || '';
    showAddOffspring.value = true;
};

const submitOffspring = () => {
    offspringForm.transform((data) => ({
        ...data,
        for_sale: 0,
        hide_price: 0,
    })).post(store().url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            showAddOffspring.value = false;
            offspringForm.reset();
            selectedClutch.value = null;
            success('Offspring added successfully!');
        },
    });
};

const endSession = () => {
    router.post(pairingsEndSession({ pairing: props.pairing.id }).url, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showEndSession.value = false;
            success('Breeding session ended successfully!');
        },
    });
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getClutchOffspring = (clutchId: number) => {
    return props.pairing.offspring.filter(o => o.clutch_id === clutchId);
};

const getStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' => {
    switch (status) {
        case 'successful':
            return 'default';
        case 'unsuccessful':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const getDaysBetween = (startDate: string, endDate?: string) => {
    const start = new Date(startDate);
    const end = endDate ? new Date(endDate) : new Date();
    const diffTime = Math.abs(end.getTime() - start.getTime());
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
};

const getClutchAgeInfo = (clutch: Clutch) => {
    // Don't show age info if status is successful
    if (clutch.status === 'successful') {
        return null;
    }

    if (!clutch.eggs_laid_date) {
        return null;
    }

    // If eggs haven't hatched yet, show egg age
    if (!clutch.hatched_date) {
        const days = getDaysBetween(clutch.eggs_laid_date);
        let variant: 'default' | 'secondary' | 'destructive' = 'default';
        let text = `${days} day${days !== 1 ? 's' : ''} in incubation`;
        
        if (days >= 22) {
            variant = 'destructive';
            text += ' (Overdue!)';
        } else if (days >= 18) {
            variant = 'secondary'; // Will style as orange
            text += ' (Due soon)';
        }
        
        return { text, variant, isOverdue: days >= 22, isDueSoon: days >= 18 && days < 22 };
    }

    // If hatched, show chick age
    const days = getDaysBetween(clutch.hatched_date);
    return {
        text: `${days} day${days !== 1 ? 's' : ''} old`,
        variant: 'default' as 'default' | 'secondary' | 'destructive',
        isOverdue: false,
        isDueSoon: false,
    };
};
</script>

<template>
    <Head :title="`Pairing: ${pairing.pair_name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div class="max-w-5xl mx-auto w-full space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-3xl font-bold tracking-tight">{{ pairing.pair_name }}</h1>
                            <Badge :variant="pairing.status === 'active' ? 'default' : 'secondary'">
                                {{ pairing.status }}
                            </Badge>
                        </div>
                        <p class="text-muted-foreground mt-1">{{ pairing.clutches.length }} clutch(es)</p>
                    </div>
                    <div class="flex gap-2">
                        <Button asChild variant="outline">
                            <Link :href="pairingsIndex().url">Back</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link :href="pairingsEdit({ pairing: pairing.id }).url">Edit</Link>
                        </Button>
                    </div>
                </div>

                <!-- Pairing Details -->
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Sire Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-lg">Sire (Male)</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <div>
                                <Link
                                    :href="pigeonsShow({ pigeon: pairing.sire.id }).url"
                                    class="text-xl font-bold text-blue-600 hover:underline"
                                >
                                    {{ pairing.sire.name }}
                                </Link>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                <span class="font-medium">Ring:</span> {{ pairing.sire.ring_number }}
                            </p>
                            <p v-if="pairing.sire.bloodline" class="text-sm text-muted-foreground">
                                <span class="font-medium">Bloodline:</span> {{ pairing.sire.bloodline }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Dam Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-lg">Dam (Female)</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <div>
                                <Link
                                    :href="pigeonsShow({ pigeon: pairing.dam.id }).url"
                                    class="text-xl font-bold text-blue-600 hover:underline"
                                >
                                    {{ pairing.dam.name }}
                                </Link>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                <span class="font-medium">Ring:</span> {{ pairing.dam.ring_number }}
                            </p>
                            <p v-if="pairing.dam.bloodline" class="text-sm text-muted-foreground">
                                <span class="font-medium">Bloodline:</span> {{ pairing.dam.bloodline }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Session Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>Session Information</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Started</p>
                            <p class="text-lg font-semibold">{{ formatDate(pairing.started_at) }}</p>
                        </div>
                        <div v-if="pairing.ended_at">
                            <p class="text-sm font-medium text-muted-foreground">Ended</p>
                            <p class="text-lg font-semibold">{{ formatDate(pairing.ended_at) }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">Total Offspring</p>
                            <p class="text-lg font-semibold">{{ pairing.offspring.length }}</p>
                        </div>
                    </CardContent>
                    <CardFooter v-if="pairing.status === 'active'">
                        <Dialog v-model:open="showEndSession">
                            <DialogTrigger as-child>
                                <Button variant="outline" class="w-full">End Breeding Session</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>End Breeding Session</DialogTitle>
                                    <DialogDescription>
                                        Are you sure you want to end this breeding session? Both pigeons will be set back to "Stock" status.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button variant="outline" @click="showEndSession = false">Cancel</Button>
                                    <Button @click="endSession">End Session</Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </CardFooter>
                </Card>

                <!-- Clutches Section -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Clutches</CardTitle>
                                <CardDescription>Track each clutch separately with dates and offspring</CardDescription>
                            </div>
                            <Dialog v-model:open="showAddClutch">
                                <DialogTrigger as-child>
                                    <Button v-if="pairing.status === 'active'">Add Clutch</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Add New Clutch</DialogTitle>
                                        <DialogDescription>
                                            Record when eggs were laid and when they hatched
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form @submit.prevent="addClutch" class="space-y-4">
                                        <div class="space-y-2">
                                            <Label for="eggs_laid_date">Eggs Laid Date</Label>
                                            <Input
                                                id="eggs_laid_date"
                                                v-model="clutchForm.eggs_laid_date"
                                                type="date"
                                            />
                                            <p v-if="clutchForm.errors.eggs_laid_date" class="text-sm text-red-600">
                                                {{ clutchForm.errors.eggs_laid_date }}
                                            </p>
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="hatched_date">Hatched Date (Optional)</Label>
                                            <Input
                                                id="hatched_date"
                                                v-model="clutchForm.hatched_date"
                                                type="date"
                                            />
                                            <p v-if="clutchForm.errors.hatched_date" class="text-sm text-red-600">
                                                {{ clutchForm.errors.hatched_date }}
                                            </p>
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="notes">Notes (Optional)</Label>
                                            <Textarea
                                                id="notes"
                                                v-model="clutchForm.notes"
                                                placeholder="Any additional notes about this clutch..."
                                                rows="3"
                                            />
                                        </div>
                                        <DialogFooter>
                                            <Button type="button" variant="outline" @click="showAddClutch = false">
                                                Cancel
                                            </Button>
                                            <Button type="submit" :disabled="clutchForm.processing">
                                                Add Clutch
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="pairing.clutches.length > 0" class="space-y-4">
                            <div
                                v-for="clutch in pairing.clutches"
                                :key="clutch.id"
                                class="border rounded-lg p-4 space-y-3"
                            >
                                <!-- Clutch Header -->
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h3 class="font-semibold text-lg">Clutch #{{ clutch.clutch_number }}</h3>
                                            <Badge :variant="getStatusVariant(clutch.status)">
                                                {{ clutch.status }}
                                            </Badge>
                                            <Badge
                                                v-if="getClutchAgeInfo(clutch)"
                                                :class="{
                                                    'bg-orange-500 text-white hover:bg-orange-600': getClutchAgeInfo(clutch)?.isDueSoon,
                                                    'bg-red-500 text-white hover:bg-red-600': getClutchAgeInfo(clutch)?.isOverdue,
                                                }"
                                                :variant="getClutchAgeInfo(clutch)?.isOverdue || getClutchAgeInfo(clutch)?.isDueSoon ? 'default' : 'outline'"
                                            >
                                                {{ getClutchAgeInfo(clutch)?.text }}
                                            </Badge>
                                        </div>
                                        <div class="text-sm text-muted-foreground mt-1 space-y-1">
                                            <p v-if="clutch.eggs_laid_date">
                                                <span class="font-medium">Eggs Laid:</span> {{ formatDate(clutch.eggs_laid_date) }}
                                            </p>
                                            <p v-if="clutch.hatched_date">
                                                <span class="font-medium">Hatched:</span> {{ formatDate(clutch.hatched_date) }}
                                            </p>
                                            <p v-if="clutch.notes" class="text-xs italic">{{ clutch.notes }}</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="openEditClutch(clutch)"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="openDeleteClutch(clutch)"
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </div>

                                <!-- Offspring for this clutch -->
                                <div class="pl-4 border-l-2 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-muted-foreground">
                                            Offspring ({{ getClutchOffspring(clutch.id).length }})
                                        </p>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            @click="addOffspringToClutch(clutch)"
                                        >
                                            Add Offspring
                                        </Button>
                                    </div>
                                    <div v-if="getClutchOffspring(clutch.id).length > 0" class="space-y-2">
                                        <div
                                            v-for="offspring in getClutchOffspring(clutch.id)"
                                            :key="offspring.id"
                                            class="flex items-center justify-between p-2 rounded bg-muted/50"
                                        >
                                            <div>
                                                <Link
                                                    :href="pigeonsShow({ pigeon: offspring.id }).url"
                                                    class="text-sm font-medium text-blue-600 hover:underline"
                                                >
                                                    {{ offspring.name }}
                                                </Link>
                                                <p class="text-xs text-muted-foreground">{{ offspring.ring_number }}</p>
                                            </div>
                                            <Button asChild variant="ghost" size="sm">
                                                <Link :href="pigeonsShow({ pigeon: offspring.id }).url">View</Link>
                                            </Button>
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-muted-foreground italic">
                                        No offspring recorded for this clutch yet
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-muted-foreground">
                            <p class="mb-4">No clutches recorded yet.</p>
                            <Button v-if="pairing.status === 'active'" @click="showAddClutch = true" variant="outline">
                                Add First Clutch
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Edit Clutch Dialog -->
                <Dialog v-model:open="showEditClutch">
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Edit Clutch #{{ selectedClutch?.clutch_number }}</DialogTitle>
                            <DialogDescription>
                                Update clutch dates, status, and notes
                            </DialogDescription>
                        </DialogHeader>
                        <form @submit.prevent="updateClutch" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="edit_eggs_laid_date">Eggs Laid Date</Label>
                                <Input
                                    id="edit_eggs_laid_date"
                                    v-model="editClutchForm.eggs_laid_date"
                                    type="date"
                                />
                                <p v-if="editClutchForm.errors.eggs_laid_date" class="text-sm text-red-600">
                                    {{ editClutchForm.errors.eggs_laid_date }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="edit_hatched_date">Hatched Date</Label>
                                <Input
                                    id="edit_hatched_date"
                                    v-model="editClutchForm.hatched_date"
                                    type="date"
                                />
                                <p v-if="editClutchForm.errors.hatched_date" class="text-sm text-red-600">
                                    {{ editClutchForm.errors.hatched_date }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="edit_status">Status</Label>
                                <Select v-model="editClutchForm.status">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="pending">Pending</SelectItem>
                                            <SelectItem value="successful">Successful</SelectItem>
                                            <SelectItem value="unsuccessful">Unsuccessful</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <p v-if="editClutchForm.errors.status" class="text-sm text-red-600">
                                    {{ editClutchForm.errors.status }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="edit_notes">Notes</Label>
                                <Textarea
                                    id="edit_notes"
                                    v-model="editClutchForm.notes"
                                    placeholder="Any additional notes..."
                                    rows="3"
                                />
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" @click="showEditClutch = false">
                                    Cancel
                                </Button>
                                <Button type="submit" :disabled="editClutchForm.processing">
                                    Update Clutch
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <!-- Delete Clutch Dialog -->
                <Dialog v-model:open="showDeleteClutch">
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Clutch #{{ selectedClutch?.clutch_number }}</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete this clutch? This action cannot be undone.
                                <span v-if="selectedClutch && getClutchOffspring(selectedClutch.id).length > 0" class="block mt-2 text-red-600 font-medium">
                                    Warning: This clutch has {{ getClutchOffspring(selectedClutch.id).length }} offspring. They will no longer be linked to this clutch.
                                </span>
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" @click="showDeleteClutch = false">Cancel</Button>
                            <Button variant="destructive" @click="deleteClutch">Delete</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <!-- Add Offspring Dialog -->
                <Dialog v-model:open="showAddOffspring">
                    <DialogContent class="max-w-md">
                        <DialogHeader>
                            <DialogTitle>Add Offspring to Clutch #{{ selectedClutch?.clutch_number }}</DialogTitle>
                            <DialogDescription>
                                Enter basic information for the new pigeon. Parents will be linked automatically.
                            </DialogDescription>
                        </DialogHeader>
                        <form @submit.prevent="submitOffspring" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="offspring_ring_number">Ring Number *</Label>
                                <Input
                                    id="offspring_ring_number"
                                    v-model="offspringForm.ring_number"
                                    required
                                    autofocus
                                    autocomplete="off"
                                    placeholder="e.g. PH 2024-12345"
                                />
                                <p v-if="offspringForm.errors.ring_number" class="text-sm text-red-600">
                                    {{ offspringForm.errors.ring_number }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="offspring_personal_number">Personal Ring Number</Label>
                                <Input
                                    id="offspring_personal_number"
                                    v-model="offspringForm.personal_number"
                                    autocomplete="off"
                                    placeholder="Loft reference"
                                />
                                <p v-if="offspringForm.errors.personal_number" class="text-sm text-red-600">
                                    {{ offspringForm.errors.personal_number }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="offspring_gender">Gender</Label>
                                <Select v-model="offspringForm.gender">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Not specified" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="">Not specified</SelectItem>
                                            <SelectItem value="male">Male (Cock)</SelectItem>
                                            <SelectItem value="female">Female (Hen)</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <p v-if="offspringForm.errors.gender" class="text-sm text-red-600">
                                    {{ offspringForm.errors.gender }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="offspring_color">Color</Label>
                                <Input
                                    id="offspring_color"
                                    v-model="offspringForm.color"
                                    autocomplete="off"
                                    placeholder="e.g. Blue Bar, Red Checker"
                                />
                                <p v-if="offspringForm.errors.color" class="text-sm text-red-600">
                                    {{ offspringForm.errors.color }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-muted p-3 text-sm">
                                <p class="font-medium mb-1">Auto-filled:</p>
                                <p class="text-muted-foreground">Sire: {{ pairing.sire.ring_number }}</p>
                                <p class="text-muted-foreground">Dam: {{ pairing.dam.ring_number }}</p>
                                <p class="text-muted-foreground" v-if="selectedClutch?.hatched_date">Hatch Date: {{ formatDate(selectedClutch.hatched_date) }}</p>
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" @click="showAddOffspring = false">
                                    Cancel
                                </Button>
                                <Button type="submit" :disabled="offspringForm.processing">
                                    Add Offspring
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <!-- Info Box -->
                <Card class="bg-blue-50 border-blue-200">
                    <CardContent class="pt-6">
                        <p class="text-sm text-blue-900">
                            <span class="font-medium">Breeding Workflow:</span> Add a new clutch when eggs are laid. 
                            Update it when they hatch. Add offspring to the specific clutch. Mark the clutch as 
                            successful or unsuccessful. Repeat for each new clutch.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>