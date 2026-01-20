<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { index as indexRoute, store } from '@/routes/pigeons';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { type BreadcrumbItem } from '@/types';

interface ParentOption {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    label: string;
}

const props = defineProps<{
    parentOptions: {
        sires: ParentOption[];
        dams: ParentOption[];
    };
    bloodlines: string[];
    colors: string[];
    prefill?: {
        sire_id?: string;
        dam_id?: string;
        pairing_id?: string;
        clutch_id?: string;
        hatch_date?: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: indexRoute().url },
    { title: 'Add pigeon', href: store().url },
];

const form = useForm({
    ring_number: '',
    bloodline: '',
    name: '',
    personal_number: '',
    pigeon_status: 'stock',
    race_type: 'none',
    status: 'alive',
    hatch_date: props.prefill?.hatch_date || '',
    gender: '',
    color: '',
    remarks: '',
    notes: '',
    photos: [] as string[],
    pedigree_image: '',
    sire_id: null as number | null,
    dam_id: null as number | null,
    sire_name: '',
    sire_ring_number: '',
    sire_color: '',
    sire_notes: '',
    dam_name: '',
    dam_ring_number: '',
    dam_color: '',
    dam_notes: '',
    for_sale: false,
    sale_price: '',
    hide_price: false,
    sale_description: '',
    pairing_id: null as number | null,
    clutch_id: null as number | null,
});

const sireId = ref(props.prefill?.sire_id || '');
const damId = ref(props.prefill?.dam_id || '');
const photosText = ref('');
const sireFieldsReadonly = ref(false);
const damFieldsReadonly = ref(false);

// Set pairing_id if provided
if (props.prefill?.pairing_id) {
    form.pairing_id = Number(props.prefill.pairing_id);
}

// Set clutch_id if provided
if (props.prefill?.clutch_id) {
    form.clutch_id = Number(props.prefill.clutch_id);
}

watch(photosText, (value) => {
    form.photos = value.split('\n').map((line) => line.trim()).filter((line) => line.length > 0);
});

watch(sireId, (value) => {
    form.sire_id = value ? Number(value) : null;
    if (value) {
        const selectedSire = props.parentOptions.sires.find(s => s.id === Number(value));
        if (selectedSire) {
            form.sire_name = selectedSire.name || '';
            form.sire_ring_number = selectedSire.ring_number || '';
            form.sire_color = selectedSire.color || '';
            sireFieldsReadonly.value = true;
        }
    } else {
        sireFieldsReadonly.value = false;
    }
});

watch(damId, (value) => {
    form.dam_id = value ? Number(value) : null;
    if (value) {
        const selectedDam = props.parentOptions.dams.find(d => d.id === Number(value));
        if (selectedDam) {
            form.dam_name = selectedDam.name || '';
            form.dam_ring_number = selectedDam.ring_number || '';
            form.dam_color = selectedDam.color || '';
            damFieldsReadonly.value = true;
        }
    } else {
        damFieldsReadonly.value = false;
    }
});

const photosCount = computed(() => form.photos.length);

const submit = () => {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            photosText.value = '';
            sireId.value = '';
            damId.value = '';
            sireFieldsReadonly.value = false;
            damFieldsReadonly.value = false;
        },
    });
};
</script>

<template>
    <Head title="Add pigeon" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <Card>
                <CardHeader>
                    <CardTitle>Add a new pigeon</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-8" @submit.prevent="submit">
                        <!-- Identification -->
                        <section class="space-y-6">
                            <div class="space-y-2">
                                <h2 class="text-lg font-semibold text-foreground">Identification</h2>
                                <p class="text-sm text-muted-foreground">Core identification numbers and bloodline.</p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="ring_number">Ring Number *</Label>
                                    <Input id="ring_number" v-model="form.ring_number" required autofocus autocomplete="off" placeholder="e.g. PH 2024-12345" />
                                    <InputError :message="form.errors.ring_number" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="bloodline">Bloodline</Label>
                                    <Input id="bloodline" v-model="form.bloodline" autocomplete="off" placeholder="e.g. Janssen, Kipp" list="bloodline-list" />
                                    <datalist id="bloodline-list">
                                        <option v-for="line in bloodlines" :key="line" :value="line" />
                                    </datalist>
                                    <InputError :message="form.errors.bloodline" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="name">Name</Label>
                                    <Input id="name" v-model="form.name" autocomplete="off" placeholder="Optional pigeon name" />
                                    <InputError :message="form.errors.name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="personal_number">Personal Ring Number</Label>
                                    <Input id="personal_number" v-model="form.personal_number" autocomplete="off" placeholder="Loft reference" />
                                    <InputError :message="form.errors.personal_number" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="pigeon_status">Pigeon Status *</Label>
                                    <select id="pigeon_status" v-model="form.pigeon_status" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                        <option value="stock">In Stock</option>
                                        <option value="racing">Racing</option>
                                        <option value="breeding">Breeding</option>
                                    </select>
                                    <InputError :message="form.errors.pigeon_status" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="race_type">Race Type *</Label>
                                    <select id="race_type" v-model="form.race_type" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                        <option value="none">None / Stock / Breeding</option>
                                        <option value="olr">OLR</option>
                                        <option value="south">South</option>
                                        <option value="north">North</option>
                                        <option value="summer">Summer</option>
                                    </select>
                                    <InputError :message="form.errors.race_type" />
                                </div>
                            </div>
                        </section>

                        <Separator />

                        <!-- Information -->
                        <section class="space-y-6">
                            <div class="space-y-2">
                                <h2 class="text-lg font-semibold text-foreground">Information</h2>
                                <p class="text-sm text-muted-foreground">Biological and physical characteristics.</p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="status">Status *</Label>
                                    <select id="status" v-model="form.status" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                        <option value="alive">Alive</option>
                                        <option value="deceased">Deceased</option>
                                        <option value="missing">Missing in race</option>
                                        <option value="flyaway">Fly away</option>
                                    </select>
                                    <InputError :message="form.errors.status" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="hatch_date">Hatch Date</Label>
                                    <Input id="hatch_date" v-model="form.hatch_date" type="date" />
                                    <InputError :message="form.errors.hatch_date" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="gender">Gender</Label>
                                    <select id="gender" v-model="form.gender" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                        <option value="">Not specified</option>
                                        <option value="male">Male (Cock)</option>
                                        <option value="female">Female (Hen)</option>
                                    </select>
                                    <InputError :message="form.errors.gender" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="color">Color</Label>
                                    <Input id="color" v-model="form.color" autocomplete="off" placeholder="Blue bar, checkered, etc." list="color-list" />
                                    <datalist id="color-list">
                                        <option v-for="colorOption in colors" :key="colorOption" :value="colorOption" />
                                    </datalist>
                                    <InputError :message="form.errors.color" />
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <Label for="remarks">Remarks</Label>
                                <textarea id="remarks" v-model="form.remarks" rows="3" class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 placeholder:text-muted-foreground" placeholder="Quick notes like race results" />
                                <InputError :message="form.errors.remarks" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="notes">Notes</Label>
                                <textarea id="notes" v-model="form.notes" rows="4" class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 placeholder:text-muted-foreground" placeholder="Detailed notes about breeding performance, health history, etc." />
                                <InputError :message="form.errors.notes" />
                            </div>
                        </section>

                        <Separator />

                        <!-- Parents -->
                        <section class="space-y-6">
                            <div class="space-y-2">
                                <h2 class="text-lg font-semibold text-foreground">Parents</h2>
                                <p class="text-sm text-muted-foreground">Link existing breeding/racing birds or capture external details.</p>
                            </div>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <!-- Sire -->
                                <div class="space-y-4 rounded-lg border border-border/70 p-4">
                                    <div class="space-y-2">
                                        <Label for="sire_id">Sire (Male parent)</Label>
                                        <select id="sire_id" v-model="sireId" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                            <option value="">Unlinked / External</option>
                                            <option v-for="option in props.parentOptions.sires" :key="`sire-${option.id}`" :value="option.id.toString()">{{ option.label || `Pigeon #${option.id}` }}</option>
                                        </select>
                                        <InputError :message="form.errors.sire_id" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="sire_name">Sire name</Label>
                                        <Input id="sire_name" v-model="form.sire_name" autocomplete="off" :readonly="sireFieldsReadonly" :class="sireFieldsReadonly ? 'bg-muted/50 cursor-not-allowed' : ''" />
                                        <InputError :message="form.errors.sire_name" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="sire_ring_number">Sire ring number</Label>
                                        <Input id="sire_ring_number" v-model="form.sire_ring_number" autocomplete="off" :readonly="sireFieldsReadonly" :class="sireFieldsReadonly ? 'bg-muted/50 cursor-not-allowed' : ''" />
                                        <InputError :message="form.errors.sire_ring_number" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="sire_color">Sire color</Label>
                                        <Input id="sire_color" v-model="form.sire_color" autocomplete="off" :readonly="sireFieldsReadonly" :class="sireFieldsReadonly ? 'bg-muted/50 cursor-not-allowed' : ''" />
                                        <InputError :message="form.errors.sire_color" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="sire_notes">Sire notes</Label>
                                        <textarea id="sire_notes" v-model="form.sire_notes" rows="3" class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 placeholder:text-muted-foreground" />
                                        <InputError :message="form.errors.sire_notes" />
                                    </div>
                                </div>
                                <!-- Dam -->
                                <div class="space-y-4 rounded-lg border border-border/70 p-4">
                                    <div class="space-y-2">
                                        <Label for="dam_id">Dam (Female parent)</Label>
                                        <select id="dam_id" v-model="damId" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                            <option value="">Unlinked / External</option>
                                            <option v-for="option in props.parentOptions.dams" :key="`dam-${option.id}`" :value="option.id.toString()">{{ option.label || `Pigeon #${option.id}` }}</option>
                                        </select>
                                        <InputError :message="form.errors.dam_id" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="dam_name">Dam name</Label>
                                        <Input id="dam_name" v-model="form.dam_name" autocomplete="off" :readonly="damFieldsReadonly" :class="damFieldsReadonly ? 'bg-muted/50 cursor-not-allowed' : ''" />
                                        <InputError :message="form.errors.dam_name" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="dam_ring_number">Dam ring number</Label>
                                        <Input id="dam_ring_number" v-model="form.dam_ring_number" autocomplete="off" :readonly="damFieldsReadonly" :class="damFieldsReadonly ? 'bg-muted/50 cursor-not-allowed' : ''" />
                                        <InputError :message="form.errors.dam_ring_number" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="dam_color">Dam color</Label>
                                        <Input id="dam_color" v-model="form.dam_color" autocomplete="off" :readonly="damFieldsReadonly" :class="damFieldsReadonly ? 'bg-muted/50 cursor-not-allowed' : ''" />
                                        <InputError :message="form.errors.dam_color" />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="dam_notes">Dam notes</Label>
                                        <textarea id="dam_notes" v-model="form.dam_notes" rows="3" class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 placeholder:text-muted-foreground" />
                                        <InputError :message="form.errors.dam_notes" />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <Separator />

                        <!-- Photos & Pedigree -->
                        <section class="space-y-6">
                            <div class="space-y-2">
                                <h2 class="text-lg font-semibold text-foreground">Photos & Pedigree</h2>
                                <p class="text-sm text-muted-foreground">Add visual documentation.</p>
                            </div>
                            <div class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="photos">Photo URLs ({{ photosCount}} added)</Label>
                                    <textarea id="photos" v-model="photosText" rows="4" class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 placeholder:text-muted-foreground" placeholder="https://example.com/pigeon-1.jpg&#10;https://example.com/pigeon-2.jpg" />
                                    <p class="text-xs text-muted-foreground">Enter one URL per line</p>
                                    <InputError :message="form.errors.photos" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="pedigree_image">Pedigree Image URL</Label>
                                    <Input id="pedigree_image" v-model="form.pedigree_image" autocomplete="off" placeholder="https://example.com/pedigree.jpg" />
                                    <p class="text-xs text-muted-foreground">Upload an existing or handwritten pedigree image</p>
                                    <InputError :message="form.errors.pedigree_image" />
                                </div>
                            </div>
                        </section>

                        <Separator />

                        <!-- Sales Settings -->
                        <section class="space-y-6">
                            <div class="space-y-2">
                                <h2 class="text-lg font-semibold text-foreground">Sales Settings</h2>
                                <p class="text-sm text-muted-foreground">Mark this pigeon for sale and set pricing options.</p>
                            </div>
                            <div class="grid gap-6">
                                <div class="flex items-center space-x-2">
                                    <input id="for_sale" v-model="form.for_sale" type="checkbox" class="h-4 w-4 rounded border-input text-primary focus:ring-2 focus:ring-primary" />
                                    <Label for="for_sale" class="font-medium">Mark as for sale</Label>
                                </div>
                                <div v-if="form.for_sale" class="space-y-4 rounded-lg border border-border/70 bg-muted/20 p-4">
                                    <div class="grid gap-2 sm:w-1/2">
                                        <Label for="sale_price">Sale Price ($)</Label>
                                        <Input id="sale_price" v-model="form.sale_price" type="number" step="0.01" min="0" autocomplete="off" placeholder="0.00" />
                                        <InputError :message="form.errors.sale_price" />
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input id="hide_price" v-model="form.hide_price" type="checkbox" class="h-4 w-4 rounded border-input text-primary focus:ring-2 focus:ring-primary" />
                                        <Label for="hide_price" class="font-normal">Hide price (buyers must contact for pricing)</Label>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="sale_description">Sale Description</Label>
                                        <textarea id="sale_description" v-model="form.sale_description" rows="3" class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 placeholder:text-muted-foreground" placeholder="Describe why this pigeon is for sale, special qualities, achievements, etc." />
                                        <InputError :message="form.errors.sale_description" />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                            <Button variant="outline" type="button" as-child class="w-full sm:w-auto">
                                <a :href="indexRoute().url">Cancel</a>
                            </Button>
                            <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                                Save pigeon
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
