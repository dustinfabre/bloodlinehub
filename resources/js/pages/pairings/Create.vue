<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { index as pairingsIndex, store as pairingsStore } from '@/routes/pairings';
import { type BreadcrumbItem } from '@/types';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

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

const openSire = ref(false);
const openDam = ref(false);
const sireSearch = ref('');
const damSearch = ref('');

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

const submit = () => {
    form.post(pairingsStore().url, {
        preserveScroll: true,
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
                        <div class="space-y-2">
                            <Label for="sire_id">Sire (Male) *</Label>
                            <Popover v-model:open="openSire">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openSire"
                                        class="w-full justify-between"
                                    >
                                        {{ selectedSire ? formatPigeonLabel(selectedSire) : "Select a sire..." }}
                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-full p-0" align="start">
                                    <Command>
                                        <CommandInput v-model="sireSearch" placeholder="Search sires..." />
                                        <CommandList>
                                            <CommandEmpty>No sire found.</CommandEmpty>
                                            <CommandGroup>
                                                <CommandItem
                                                    v-for="sire in filteredSires"
                                                    :key="sire.id"
                                                    :value="sire.id.toString()"
                                                    @select="() => {
                                                        form.sire_id = sire.id.toString();
                                                        openSire = false;
                                                    }"
                                                >
                                                    <Check
                                                        :class="cn(
                                                            'mr-2 h-4 w-4',
                                                            form.sire_id === sire.id.toString() ? 'opacity-100' : 'opacity-0'
                                                        )"
                                                    />
                                                    <div class="flex flex-col">
                                                        <span class="font-medium">{{ sire.name }} - {{ sire.ring_number }}</span>
                                                        <span v-if="sire.bloodline || sire.color" class="text-xs text-muted-foreground">
                                                            {{ [sire.bloodline, sire.color].filter(Boolean).join(' • ') }}
                                                        </span>
                                                    </div>
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                            <p v-if="form.errors.sire_id" class="text-sm text-destructive">
                                {{ form.errors.sire_id }}
                            </p>
                        </div>

                        <!-- Dam Selection -->
                        <div class="space-y-2">
                            <Label for="dam_id">Dam (Female) *</Label>
                            <Popover v-model:open="openDam">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="openDam"
                                        class="w-full justify-between"
                                    >
                                        {{ selectedDam ? formatPigeonLabel(selectedDam) : "Select a dam..." }}
                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-full p-0" align="start">
                                    <Command>
                                        <CommandInput v-model="damSearch" placeholder="Search dams..." />
                                        <CommandList>
                                            <CommandEmpty>No dam found.</CommandEmpty>
                                            <CommandGroup>
                                                <CommandItem
                                                    v-for="dam in filteredDams"
                                                    :key="dam.id"
                                                    :value="dam.id.toString()"
                                                    @select="() => {
                                                        form.dam_id = dam.id.toString();
                                                        openDam = false;
                                                    }"
                                                >
                                                    <Check
                                                        :class="cn(
                                                            'mr-2 h-4 w-4',
                                                            form.dam_id === dam.id.toString() ? 'opacity-100' : 'opacity-0'
                                                        )"
                                                    />
                                                    <div class="flex flex-col">
                                                        <span class="font-medium">{{ dam.name }} - {{ dam.ring_number }}</span>
                                                        <span v-if="dam.bloodline || dam.color" class="text-xs text-muted-foreground">
                                                            {{ [dam.bloodline, dam.color].filter(Boolean).join(' • ') }}
                                                        </span>
                                                    </div>
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
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
