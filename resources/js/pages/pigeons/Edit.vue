<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as editRoute, index as indexRoute, show, update } from '@/routes/pigeons';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { type BreadcrumbItem } from '@/types';

interface ParentSummary {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
}

interface ParentOption {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    label: string;
}

interface Pigeon {
    id: number;
    name: string | null;
    gender: string | null;
    hatch_date: string | null;
    status: string;
    pigeon_status: string;
    race_type: string;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    remarks: string | null;
    notes: string | null;
    photos: string[];
    pedigree_image: string | null;
    sire: ParentSummary | null;
    dam: ParentSummary | null;
    sire_name: string | null;
    sire_ring_number: string | null;
    sire_color: string | null;
    sire_notes: string | null;
    dam_name: string | null;
    dam_ring_number: string | null;
    dam_color: string | null;
    dam_notes: string | null;
    for_sale: boolean;
    sale_price: string | null;
    hide_price: boolean;
    sale_description: string | null;
}

const props = defineProps<{
    pigeon: Pigeon;
    parentOptions: {
        sires: ParentOption[];
        dams: ParentOption[];
    };
}>();

const pigeonLabel = computed(() => props.pigeon.name || props.pigeon.ring_number || props.pigeon.personal_number || `Pigeon #${props.pigeon.id}`);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: indexRoute().url },
    { title: pigeonLabel.value, href: show({ pigeon: props.pigeon.id }).url },
    { title: 'Edit', href: editRoute({ pigeon: props.pigeon.id }).url },
];

const form = useForm({
    name: props.pigeon.name ?? '',
    gender: props.pigeon.gender ?? '',
    hatch_date: props.pigeon.hatch_date ?? '',
    status: props.pigeon.status ?? 'alive',
    pigeon_status: props.pigeon.pigeon_status ?? 'stock',
    race_type: props.pigeon.race_type ?? 'none',
    ring_number: props.pigeon.ring_number ?? '',
    personal_number: props.pigeon.personal_number ?? '',
    color: props.pigeon.color ?? '',
    remarks: props.pigeon.remarks ?? '',
    notes: props.pigeon.notes ?? '',
    photos: props.pigeon.photos ?? [],
    pedigree_image: props.pigeon.pedigree_image ?? '',
    sire_id: props.pigeon.sire?.id ?? null,
    dam_id: props.pigeon.dam?.id ?? null,
    sire_name: props.pigeon.sire_name ?? '',
    sire_ring_number: props.pigeon.sire_ring_number ?? '',
    sire_color: props.pigeon.sire_color ?? '',
    sire_notes: props.pigeon.sire_notes ?? '',
    dam_name: props.pigeon.dam_name ?? '',
    dam_ring_number: props.pigeon.dam_ring_number ?? '',
    dam_color: props.pigeon.dam_color ?? '',
    dam_notes: props.pigeon.dam_notes ?? '',
    for_sale: props.pigeon.for_sale ?? false,
    sale_price: props.pigeon.sale_price ?? '',
    hide_price: props.pigeon.hide_price ?? false,
    sale_description: props.pigeon.sale_description ?? '',
});

const sireId = ref(form.sire_id ? form.sire_id.toString() : '');
const damId = ref(form.dam_id ? form.dam_id.toString() : '');
const photosText = ref((form.photos ?? []).join('\n'));
const sireFieldsReadonly = ref(!!form.sire_id);
const damFieldsReadonly = ref(!!form.dam_id);

watch(photosText, (value) => { form.photos = value.split('\n').map((line) => line.trim()).filter((line) => line.length > 0); });

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
    } else { sireFieldsReadonly.value = false; }
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
    } else { damFieldsReadonly.value = false; }
});

const photosCount = computed(() => form.photos.length);

const autoSaveTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const lastSaved = ref<Date | null>(null);
const isSaving = ref(false);
const showNotification = ref(false);

const autoSave = () => {
    if (autoSaveTimeout.value) {
        clearTimeout(autoSaveTimeout.value);
    }
    autoSaveTimeout.value = setTimeout(() => {
        isSaving.value = true;
        form.put(update({ pigeon: props.pigeon.id }).url, {
            preserveScroll: true,
            onSuccess: () => {
                lastSaved.value = new Date();
                isSaving.value = false;
                showNotification.value = true;
                setTimeout(() => {
                    showNotification.value = false;
                }, 3000);
            },
            onError: () => {
                isSaving.value = false;
            },
        });
    }, 1500);
};

// Watch form fields for auto-save
watch(() => form.data(), () => {
    if (!form.processing) {
        autoSave();
    }
}, { deep: true });

const submit = () => { form.put(update({ pigeon: props.pigeon.id }).url, { preserveScroll: true }); };
</script>

<template>
    <Head :title="`Edit ${pigeonLabel}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Edit {{ pigeonLabel }}</h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        <span v-if="isSaving" class="text-blue-600">Saving...</span>
                        <span v-else-if="lastSaved" class="text-green-600">Last saved {{ lastSaved.toLocaleTimeString() }}</span>
                        <span v-else>Changes auto-save as you type</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="indexRoute().url">
                        <Button variant="outline">Back to table</Button>
                    </Link>
                    <Link :href="show({ pigeon: pigeon.id }).url">
                        <Button variant="outline">View pigeon</Button>
                    </Link>
                </div>
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>Edit {{ pigeonLabel }}</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        <span v-if="isSaving" class="text-blue-600">Saving...</span>
                        <span v-else-if="lastSaved" class="text-green-600">Last saved {{ lastSaved.toLocaleTimeString() }}</span>
                        <span v-else>Update comprehensive details and lineage information.</span>
                    </p>
                </CardHeader>
                <CardContent>
                    <form class="space-y-10" @submit.prevent="submit">
                        <!-- Basic Information -->
                        <section class="space-y-6">
                            <div class="space-y-2">
                                <h2 class="text-lg font-semibold text-foreground">Basic Information</h2>
                                <p class="text-sm text-muted-foreground">Core identification and biological data.</p>
                            </div>
                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="name">Name</Label>
                                    <Input id="name" v-model="form.name" autocomplete="off" placeholder="Optional pigeon name" />
                                    <InputError :message="form.errors.name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="gender">Gender</Label>
                                    <select id="gender" v-model="form.gender" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]">
                                        <option value="">Not specified</option>
                                        <option value="male">Male (Cock)</option>
                                        <option value="female">Female (Hen)</option>
                                    </select>
                                    <InputError :message="form.errors.gender" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="hatch_date">Hatch Date</Label>
                                    <Input id="hatch_date" v-model="form.hatch_date" type="date" />
                                    <InputError :message="form.errors.hatch_date" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="status">Status *</Label>
                                    <select id="status" v-model="form.status" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]">
                                        <option value="alive">Alive</option>
                                        <option value="deceased">Deceased</option>
                                        <option value="missing">Missing in race</option>
                                    </select>
                                    <InputError :message="form.errors.status" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="pigeon_status">Pigeon Status *</Label>
                                    <select id="pigeon_status" v-model="form.pigeon_status" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]">
                                        <option value="stock">In Stock</option>
                                        <option value="racing">Racing</option>
                                        <option value="breeding">Breeding</option>
                                    </select>
                                    <InputError :message="form.errors.pigeon_status" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="race_type">Race Type *</Label>
                                    <select id="race_type" v-model="form.race_type" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]">
                                        <option value="none">None / Stock / Breeding</option>
                                        <option value="south">South Race</option>
                                        <option value="north">North Race</option>
                                        <option value="summer">Summer Race</option>
                                        <option value="olr">OLR Race</option>
                                    </select>
                                    <InputError :message="form.errors.race_type" />
                                </div>
                            </div>
                        </section>

                        <!-- Ring & Identification -->
                        <section class="space-y-6">
                            <div class="space-y-2">
                                <h2 class="text-lg font-semibold text-foreground">Ring & Identification</h2>
                                <p class="text-sm text-muted-foreground">Official and personal identification numbers.</p>
                            </div>
                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="ring_number">Ring number *</Label>
                                    <Input id="ring_number" v-model="form.ring_number" required autocomplete="off" placeholder="e.g. PH 2024-12345" />
                                    <InputError :message="form.errors.ring_number" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="personal_number">Personal number</Label>
                                    <Input id="personal_number" v-model="form.personal_number" autocomplete="off" placeholder="Loft reference" />
                                    <InputError :message="form.errors.personal_number" />
                                </div>
                            </div>
                            <div class="grid gap-2 md:w-1/2">
                                <Label for="color">Color</Label>
                                <Input id="color" v-model="form.color" autocomplete="off" placeholder="Blue bar, checkered, etc." />
                                <InputError :message="form.errors.color" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="remarks">Remarks</Label>
                                <textarea id="remarks" v-model="form.remarks" rows="3" class="min-h-[96px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] placeholder:text-muted-foreground" placeholder="Quick notes like race results" />
                                <InputError :message="form.errors.remarks" />
                            </div>
                        </section>

                        <!-- Parents -->
                        <section class="space-y-6">
                            <div class="space-y-2">
                                <h2 class="text-lg font-semibold text-foreground">Parents</h2>
                                <p class="text-sm text-muted-foreground">Link existing breeding/racing birds or capture external details.</p>
                            </div>
                            <div class="grid gap-6 md:grid-cols-2">
                                <!-- Sire -->
                                <div class="space-y-4 rounded-lg border border-border/70 p-4">
                                    <div class="space-y-2">
                                        <Label for="sire_id">Sire (Male parent)</Label>
                                        <select id="sire_id" v-model="sireId" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]">
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
                                        <textarea id="sire_notes" v-model="form.sire_notes" rows="3" class="min-h-[96px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] placeholder:text-muted-foreground" />
                                        <InputError :message="form.errors.sire_notes" />
                                    </div>
                                </div>
                                <!-- Dam -->
                                <div class="space-y-4 rounded-lg border border-border/70 p-4">
                                    <div class="space-y-2">
                                        <Label for="dam_id">Dam (Female parent)</Label>
                                        <select id="dam_id" v-model="damId" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]">
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
                                        <textarea id="dam_notes" v-model="form.dam_notes" rows="3" class="min-h-[96px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] placeholder:text-muted-foreground" />
                                        <InputError :message="form.errors.dam_notes" />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Photos & Notes -->
                        <section class="space-y-6">
                            <div class="space-y-2">
                                <h2 class="text-lg font-semibold text-foreground">Photos & Notes</h2>
                                <p class="text-sm text-muted-foreground">Add visual documentation and detailed notes.</p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="photos">Photo URLs ({{ photosCount }} added)</Label>
                                <textarea id="photos" v-model="photosText" rows="4" class="min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] placeholder:text-muted-foreground" placeholder="https://example.com/pigeon-1.jpg&#10;https://example.com/pigeon-2.jpg" />
                                <InputError :message="form.errors.photos" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="pedigree_image">Pedigree Image URL</Label>
                                <Input id="pedigree_image" v-model="form.pedigree_image" autocomplete="off" placeholder="https://example.com/pedigree.jpg" />
                                <p class="text-xs text-muted-foreground">Upload an existing or handwritten pedigree image</p>
                                <InputError :message="form.errors.pedigree_image" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="notes">Notes</Label>
                                <textarea id="notes" v-model="form.notes" rows="4" class="min-h-[128px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] placeholder:text-muted-foreground" placeholder="Detailed notes about breeding performance, health history, etc." />
                                <InputError :message="form.errors.notes" />
                            </div>
                        </section>

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
                                    <div class="grid gap-2 md:w-1/2">
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
                                        <textarea id="sale_description" v-model="form.sale_description" rows="3" class="min-h-[96px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] placeholder:text-muted-foreground" placeholder="Describe why this pigeon is for sale, special qualities, achievements, etc." />
                                        <InputError :message="form.errors.sale_description" />
                                    </div>
                                </div>
                            </div>
                        </section>
                    </form>
                </CardContent>
            </Card>

            <!-- Notification Popup -->
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div v-if="showNotification" class="fixed bottom-6 right-6 z-50 rounded-lg border border-green-200 bg-green-50 px-4 py-3 shadow-lg">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <p class="text-sm font-medium text-green-900">Changes saved successfully</p>
                    </div>
                </div>
            </Transition>
        </div>
    </AppLayout>
</template>
