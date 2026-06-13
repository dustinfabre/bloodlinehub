<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { index as locationsIndex, store as locationsStore, update as locationsUpdate, destroy as locationsDestroy } from '@/routes/locations';
import { useToast } from '@/composables/useToast';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { MapPin, Plus, Pencil, Trash2, Lock } from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';

interface Location {
    id: number;
    name: string;
    type: 'manual' | 'olr';
    description: string | null;
    olr_race_id: number | null;
    pigeons_count: number;
    pairings_count: number;
    clutches_count: number;
}

const props = defineProps<{
    locations: Location[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Locations', href: locationsIndex().url },
];

const { success, error } = useToast();

const showCreate = ref(false);
const showEdit = ref(false);
const showDelete = ref(false);
const selectedLocation = ref<Location | null>(null);

const createForm = useForm({
    name: '',
    description: '',
});

const editForm = useForm({
    name: '',
    description: '',
});

const openCreate = () => {
    createForm.reset();
    showCreate.value = true;
};

const submitCreate = () => {
    createForm.post(locationsStore().url, {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
            success('Location created successfully!');
        },
    });
};

const openEdit = (loc: Location) => {
    selectedLocation.value = loc;
    editForm.name = loc.name;
    editForm.description = loc.description ?? '';
    showEdit.value = true;
};

const submitEdit = () => {
    if (!selectedLocation.value) return;
    editForm.patch(locationsUpdate({ location: selectedLocation.value.id }).url, {
        preserveScroll: true,
        onSuccess: () => {
            editForm.reset();
            showEdit.value = false;
            selectedLocation.value = null;
            success('Location updated successfully!');
        },
    });
};

const openDelete = (loc: Location) => {
    selectedLocation.value = loc;
    showDelete.value = true;
};

const confirmDelete = () => {
    if (!selectedLocation.value) return;
    router.delete(locationsDestroy({ location: selectedLocation.value.id }).url, {
        preserveScroll: true,
        onSuccess: () => {
            showDelete.value = false;
            selectedLocation.value = null;
            success('Location deleted successfully!');
        },
        onError: (errors) => {
            showDelete.value = false;
            error(errors.location ?? 'Failed to delete location.');
        },
    });
};

const isInUse = (loc: Location) => loc.pigeons_count + loc.pairings_count + loc.clutches_count > 0;
</script>

<template>
    <Head title="Locations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div class="max-w-4xl mx-auto w-full space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight">Locations</h1>
                        <p class="text-muted-foreground mt-1">Manage loft locations for your pigeons</p>
                    </div>
                    <Button @click="openCreate">
                        <Plus class="h-4 w-4 mr-2" />
                        Add Location
                    </Button>
                </div>

                <!-- Location list -->
                <div v-if="locations.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                    <MapPin class="h-12 w-12 text-muted-foreground/40 mb-4" />
                    <h3 class="text-lg font-medium text-muted-foreground">No locations yet</h3>
                    <p class="text-sm text-muted-foreground mt-1 mb-4">Create your first loft location to assign to pigeons.</p>
                    <Button @click="openCreate">
                        <Plus class="h-4 w-4 mr-2" />
                        Add Location
                    </Button>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2">
                    <Card v-for="loc in locations" :key="loc.id">
                        <CardHeader class="pb-2">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <MapPin class="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <CardTitle class="text-base">{{ loc.name }}</CardTitle>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Badge v-if="loc.type === 'olr'" variant="secondary" class="text-xs">OLR</Badge>
                                    <Button
                                        v-if="loc.type === 'manual'"
                                        size="icon"
                                        variant="ghost"
                                        class="h-7 w-7"
                                        @click="openEdit(loc)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="h-7 w-7 text-destructive hover:text-destructive"
                                        :disabled="isInUse(loc)"
                                        @click="openDelete(loc)"
                                    >
                                        <Lock v-if="isInUse(loc)" class="h-3.5 w-3.5" />
                                        <Trash2 v-else class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p v-if="loc.description" class="text-sm text-muted-foreground mb-2">{{ loc.description }}</p>
                            <div class="flex gap-3 text-xs text-muted-foreground">
                                <span>{{ loc.pigeons_count }} pigeon{{ loc.pigeons_count !== 1 ? 's' : '' }}</span>
                                <span>{{ loc.pairings_count }} pairing{{ loc.pairings_count !== 1 ? 's' : '' }}</span>
                            </div>
                            <p v-if="isInUse(loc)" class="text-xs text-amber-600 dark:text-amber-400 mt-1">In use — reassign pigeons before deleting</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Create dialog -->
        <Dialog v-model:open="showCreate">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Add Location</DialogTitle>
                    <DialogDescription>Create a new loft location for your pigeons.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="create-name">Name *</Label>
                        <Input id="create-name" v-model="createForm.name" placeholder="e.g. Breeding Section/Loft" autofocus />
                        <p v-if="createForm.errors.name" class="text-sm text-destructive">{{ createForm.errors.name }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="create-desc">Description (optional)</Label>
                        <Textarea id="create-desc" v-model="createForm.description" placeholder="Additional notes about this location..." rows="2" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCreate = false">Cancel</Button>
                        <Button type="submit" :disabled="createForm.processing">Create</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit dialog -->
        <Dialog v-model:open="showEdit">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Location</DialogTitle>
                    <DialogDescription>Update this location's name or description.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="edit-name">Name *</Label>
                        <Input id="edit-name" v-model="editForm.name" autofocus />
                        <p v-if="editForm.errors.name" class="text-sm text-destructive">{{ editForm.errors.name }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="edit-desc">Description (optional)</Label>
                        <Textarea id="edit-desc" v-model="editForm.description" rows="2" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showEdit = false">Cancel</Button>
                        <Button type="submit" :disabled="editForm.processing">Save</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete confirm dialog -->
        <Dialog v-model:open="showDelete">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Location</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete <strong>{{ selectedLocation?.name }}</strong>?
                        This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showDelete = false">Cancel</Button>
                    <Button variant="destructive" @click="confirmDelete">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
