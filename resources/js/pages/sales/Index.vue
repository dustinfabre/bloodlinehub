<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as salesIndex, store as salesStore, destroy as salesDestroy } from '@/routes/sales';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { type BreadcrumbItem } from '@/types';
import { Plus, X, DollarSign } from 'lucide-vue-next';

interface ParentSummary {
    id: number;
    name: string | null;
    ring_number: string | null;
}

interface Pigeon {
    id: number;
    name: string | null;
    gender: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    photos: string[] | null;
    sire: ParentSummary | null;
    dam: ParentSummary | null;
}

interface Sale {
    id: number;
    price: string | null;
    hide_price: boolean;
    description: string | null;
    additional_photos: string[] | null;
    created_at: string;
    pigeon: {
        id: number;
        name: string | null;
        gender: string | null;
        ring_number: string | null;
        personal_number: string | null;
        color: string | null;
        photos: string[] | null;
    };
}

interface Props {
    availablePigeons: Pigeon[];
    activeSales: Sale[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Sales Management',
        href: salesIndex().url,
    },
];

const showAddForm = ref(false);
const selectedPigeon = ref<number | null>(null);

const form = useForm({
    pigeon_id: null as number | null,
    price: '',
    hide_price: false,
    description: '',
    additional_photos: [] as string[],
});

const photosText = ref('');

const pigeonLabel = (pigeon: Pigeon) =>
    pigeon.name || pigeon.ring_number || pigeon.personal_number || `Pigeon #${pigeon.id}`;

const selectPigeon = (pigeonId: number) => {
    selectedPigeon.value = pigeonId;
    form.pigeon_id = pigeonId;
    showAddForm.value = true;
};

const cancelAdd = () => {
    showAddForm.value = false;
    selectedPigeon.value = null;
    form.reset();
    photosText.value = '';
};

const submitSale = () => {
    form.additional_photos = photosText.value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0);

    form.post(salesStore().url, {
        preserveScroll: true,
        onSuccess: () => {
            cancelAdd();
        },
    });
};

const removeSale = (saleId: number) => {
    if (!confirm('Remove this pigeon from sales?')) return;
    
    router.delete(salesDestroy({ sale: saleId }).url, {
        preserveScroll: true,
    });
};

const formatPrice = (price: string | null) => {
    if (!price) return '';
    return `$${parseFloat(price).toFixed(2)}`;
};
</script>

<template>
    <Head title="Sales Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">
                        Sales Management
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Add your pigeons to sales and manage active listings
                    </p>
                </div>
            </div>

            <!-- Active Sales -->
            <Card v-if="props.activeSales.length > 0">
                <CardHeader>
                    <CardTitle>Active Sales ({{ props.activeSales.length }})</CardTitle>
                    <CardDescription>Your pigeons currently listed for sale</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Card v-for="sale in props.activeSales" :key="sale.id" class="overflow-hidden">
                            <CardHeader class="p-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <CardTitle class="truncate text-base">{{ pigeonLabel(sale.pigeon) }}</CardTitle>
                                        <CardDescription class="mt-1">
                                            <Badge variant="outline" class="text-xs">{{ sale.pigeon.gender || 'Unknown' }}</Badge>
                                        </CardDescription>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="removeSale(sale.id)"
                                        class="h-8 w-8 p-0"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent class="p-4 pt-0 space-y-2">
                                <div v-if="!sale.hide_price && sale.price" class="text-xl font-bold text-primary">
                                    {{ formatPrice(sale.price) }}
                                </div>
                                <div v-else class="text-sm text-muted-foreground">
                                    Contact for price
                                </div>
                                <p v-if="sale.description" class="text-sm text-muted-foreground line-clamp-2">
                                    {{ sale.description }}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </CardContent>
            </Card>

            <!-- Add to Sales Section -->
            <Card>
                <CardHeader>
                    <CardTitle>Add Pigeon to Sales</CardTitle>
                    <CardDescription>Select a pigeon from your loft to list for sale</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="props.availablePigeons.length === 0" class="text-center py-8 text-muted-foreground">
                        <DollarSign class="mx-auto h-12 w-12 mb-2 opacity-50" />
                        <p>All your pigeons are already listed for sale or you have no pigeons yet.</p>
                    </div>

                    <div v-else-if="!showAddForm" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Card
                            v-for="pigeon in props.availablePigeons"
                            :key="pigeon.id"
                            class="cursor-pointer transition-all hover:border-primary"
                            @click="selectPigeon(pigeon.id)"
                        >
                            <CardHeader class="p-4">
                                <CardTitle class="truncate text-base">{{ pigeonLabel(pigeon) }}</CardTitle>
                                <CardDescription>
                                    <Badge variant="outline" class="text-xs">{{ pigeon.gender || 'Unknown' }}</Badge>
                                    <span v-if="pigeon.color" class="ml-2 text-xs">{{ pigeon.color }}</span>
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="p-4 pt-0">
                                <Button variant="outline" size="sm" class="w-full">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add to Sales
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Add Sale Form -->
                    <div v-else class="space-y-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold">
                                Adding: {{ pigeonLabel(props.availablePigeons.find(p => p.id === selectedPigeon)!) }}
                            </h3>
                            <Button variant="ghost" size="sm" @click="cancelAdd">
                                Cancel
                            </Button>
                        </div>

                        <form @submit.prevent="submitSale" class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="price">Sale Price ($)</Label>
                                    <Input
                                        id="price"
                                        v-model="form.price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                    />
                                </div>
                                <div class="flex items-end space-y-2">
                                    <label class="flex items-center space-x-2">
                                        <input
                                            id="hide_price"
                                            v-model="form.hide_price"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-input text-primary focus:ring-2 focus:ring-primary"
                                        />
                                        <span class="text-sm">Hide price (contact only)</span>
                                    </label>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Description</Label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="3"
                                    class="min-h-[96px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] placeholder:text-muted-foreground"
                                    placeholder="Describe the pigeon's qualities, achievements, breeding potential..."
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="photos">Additional Photo URLs</Label>
                                <textarea
                                    id="photos"
                                    v-model="photosText"
                                    rows="3"
                                    class="min-h-[96px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] placeholder:text-muted-foreground"
                                    placeholder="https://example.com/photo1.jpg&#10;https://example.com/photo2.jpg"
                                />
                                <p class="text-xs text-muted-foreground">One URL per line</p>
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">
                                    Add to Sales
                                </Button>
                                <Button type="button" variant="outline" @click="cancelAdd">
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
