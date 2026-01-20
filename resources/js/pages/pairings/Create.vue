<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { index as pairingsIndex, store as pairingsStore } from '@/routes/pairings';
import { useToast } from '@/composables/useToast';
import { type BreadcrumbItem } from '@/types';

interface Pigeon {
    id: number;
    name: string;
    ring_number: string;
    bloodline?: string;
    color?: string;
}

interface Props {
    sires: Pigeon[];
    dams: Pigeon[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Breeding', href: pairingsIndex().url },
    { title: 'Create Pairing', href: pairingsStore().url },
];

const form = useForm({
    sire_id: '',
    dam_id: '',
    pair_name: '',
});

const sireSearch = ref('');
const damSearch = ref('');
const showSireDropdown = ref(false);
const showDamDropdown = ref(false);

const filteredSires = computed(() => {
    if (!sireSearch.value) return props.sires;
    const search = sireSearch.value.toLowerCase();
    return props.sires.filter(s => 
        s.name.toLowerCase().includes(search) ||
        s.ring_number.toLowerCase().includes(search) ||
        s.bloodline?.toLowerCase().includes(search) ||
        s.color?.toLowerCase().includes(search)
    );
});

const filteredDams = computed(() => {
    if (!damSearch.value) return props.dams;
    const search = damSearch.value.toLowerCase();
    return props.dams.filter(d => 
        d.name.toLowerCase().includes(search) ||
        d.ring_number.toLowerCase().includes(search) ||
        d.bloodline?.toLowerCase().includes(search) ||
        d.color?.toLowerCase().includes(search)
    );
});

const selectedSire = computed(() => props.sires.find(s => s.id.toString() === form.sire_id));
const selectedDam = computed(() => props.dams.find(d => d.id.toString() === form.dam_id));

const formatPigeonLabel = (pigeon: Pigeon) => {
    let label = `${pigeon.name} - ${pigeon.ring_number}`;
    const details = [];
    if (pigeon.bloodline) details.push(pigeon.bloodline);
    if (pigeon.color) details.push(pigeon.color);
    if (details.length) label += ` (${details.join(', ')})`;
    return label;
};

const selectSire = (sire: Pigeon) => {
    form.sire_id = sire.id.toString();
    sireSearch.value = formatPigeonLabel(sire);
    showSireDropdown.value = false;
};

const selectDam = (dam: Pigeon) => {
    form.dam_id = dam.id.toString();
    damSearch.value = formatPigeonLabel(dam);
    showDamDropdown.value = false;
};

const handleSireBlur = () => {
    window.setTimeout(() => showSireDropdown.value = false, 200);
};

const handleDamBlur = () => {
    window.setTimeout(() => showDamDropdown.value = false, 200);
};

const { success } = useToast();

const submit = () => {
    form.post(pairingsStore().url, {
        preserveScroll: true,
        onSuccess: () => success('Breeding pair created successfully!'),
    });
};
</script>

<template>
    <Head title="Create Pairing" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div class="max-w-3xl mx-auto w-full space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Create Breeding Pairing</h1>
                    <p class="text-muted-foreground mt-1">Set up a new breeding pair</p>
                </div>
                <Button asChild variant="outline">
                    <Link :href="pairingsIndex().url">Back to Pairings</Link>
                </Button>
            </div>

            <form @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>Pairing Details</CardTitle>
                        <CardDescription>Select the sire and dam for this breeding pair</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Sire Selection -->
                        <div class="space-y-2 relative">
                            <Label for="sire_search">Sire (Male) *</Label>
                            <Input
                                id="sire_search"
                                v-model="sireSearch"
                                @focus="showSireDropdown = true"
                                @blur="handleSireBlur"
                                placeholder="Search sires by name, ring, bloodline, or color..."
                                autocomplete="off"
                            />
                            <div
                                v-if="showSireDropdown && filteredSires.length > 0"
                                class="absolute z-50 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-60 overflow-auto"
                            >
                                <button
                                    v-for="sire in filteredSires"
                                    :key="sire.id"
                                    type="button"
                                    @click="selectSire(sire)"
                                    class="w-full px-4 py-2 text-left hover:bg-gray-100 border-b last:border-b-0 focus:outline-none focus:bg-gray-100"
                                >
                                    <div class="font-medium">{{ sire.name }} - {{ sire.ring_number }}</div>
                                    <div v-if="sire.bloodline || sire.color" class="text-xs text-gray-500">
                                        {{ [sire.bloodline, sire.color].filter(Boolean).join(' • ') }}
                                    </div>
                                </button>
                            </div>
                            <div v-if="showSireDropdown && filteredSires.length === 0" class="absolute z-50 w-full mt-1 bg-white border rounded-lg shadow-lg p-4 text-center text-gray-500">
                                No sires found
                            </div>
                            <p v-if="form.errors.sire_id" class="text-sm text-destructive">
                                {{ form.errors.sire_id }}
                            </p>
                        </div>

                        <!-- Dam Selection -->
                        <div class="space-y-2 relative">
                            <Label for="dam_search">Dam (Female) *</Label>
                            <Input
                                id="dam_search"
                                v-model="damSearch"
                                @focus="showDamDropdown = true"
                                @blur="handleDamBlur"
                                placeholder="Search dams by name, ring, bloodline, or color..."
                                autocomplete="off"
                            />
                            <div
                                v-if="showDamDropdown && filteredDams.length > 0"
                                class="absolute z-50 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-60 overflow-auto"
                            >
                                <button
                                    v-for="dam in filteredDams"
                                    :key="dam.id"
                                    type="button"
                                    @click="selectDam(dam)"
                                    class="w-full px-4 py-2 text-left hover:bg-gray-100 border-b last:border-b-0 focus:outline-none focus:bg-gray-100"
                                >
                                    <div class="font-medium">{{ dam.name }} - {{ dam.ring_number }}</div>
                                    <div v-if="dam.bloodline || dam.color" class="text-xs text-gray-500">
                                        {{ [dam.bloodline, dam.color].filter(Boolean).join(' • ') }}
                                    </div>
                                </button>
                            </div>
                            <div v-if="showDamDropdown && filteredDams.length === 0" class="absolute z-50 w-full mt-1 bg-white border rounded-lg shadow-lg p-4 text-center text-gray-500">
                                No dams found
                            </div>
                            <p v-if="form.errors.dam_id" class="text-sm text-destructive">
                                {{ form.errors.dam_id }}
                            </p>
                        </div>

                        <!-- Pair Name -->
                        <div class="space-y-2">
                            <Label for="pair_name">Pair Name (Optional)</Label>
                            <Input
                                id="pair_name"
                                v-model="form.pair_name"
                                placeholder="e.g., A1, Golden Pair, etc."
                            />
                            <p class="text-sm text-muted-foreground">
                                Leave blank to auto-generate (e.g., "Pair #1")
                            </p>
                            <p v-if="form.errors.pair_name" class="text-sm text-destructive">
                                {{ form.errors.pair_name }}
                            </p>
                        </div>

                        <!-- Info Box -->
                        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
                            <p class="font-medium mb-2">What happens when you create a pairing:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Both pigeons will be set to "Breeding" status</li>
                                <li>A new clutch session will be created</li>
                                <li>You can add offspring directly from the pairing view</li>
                                <li>When you end the session, pigeons return to "Stock" status</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex justify-end gap-3 mt-6">
                    <Button type="button" variant="outline" asChild>
                        <Link :href="pairingsIndex().url">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Pairing' }}
                    </Button>
                </div>
            </form>
            </div>
        </div>
    </AppLayout>
</template>
