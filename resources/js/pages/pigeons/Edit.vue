<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { edit as editRoute, index as indexRoute, show, update } from '@/routes/pigeons';
import { useToast } from '@/composables/useToast';
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
    bloodline: string | null;
    sire?: { ring_number?: string; name?: string; };
    dam?: { ring_number?: string; name?: string; };
    notes?: string;
    remarks?: string;
    label: string;
}

interface Pigeon {
    id: number;
    name: string | null;
    bloodline: string | null;
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
    photo_url: string | null;
    pedigree_images: string[] | null;
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
    bloodlines: string[];
    colors: string[];
}>();

const pigeonLabel = computed(() => props.pigeon.name || props.pigeon.ring_number || props.pigeon.personal_number || `Pigeon #${props.pigeon.id}`);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: indexRoute().url },
    { title: pigeonLabel.value, href: show({ pigeon: props.pigeon.id }).url },
    { title: 'Edit', href: editRoute({ pigeon: props.pigeon.id }).url },
];

const form = useForm({
    ring_number: props.pigeon.ring_number ?? '',
    bloodline: props.pigeon.bloodline ?? '',
    name: props.pigeon.name ?? '',
    personal_number: props.pigeon.personal_number ?? '',
    pigeon_status: props.pigeon.pigeon_status ?? 'stock',
    race_type: props.pigeon.race_type ?? 'none',
    status: props.pigeon.status ?? 'alive',
    hatch_date: props.pigeon.hatch_date ?? '',
    gender: props.pigeon.gender ?? '',
    color: props.pigeon.color ?? '',
    remarks: props.pigeon.remarks ?? '',
    notes: props.pigeon.notes ?? '',
    photo: null as File | null,
    photo_url: props.pigeon.photo_url ?? null,
    pedigree_images: [] as File[],
    existing_pedigree_images: props.pigeon.pedigree_images ?? [],
    remove_pedigree: [] as string[],
    remove_photo: false,
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

const sireId = ref(String(props.pigeon.sire?.id ?? ''));
const damId = ref(String(props.pigeon.dam?.id ?? ''));
const sireSearch = ref('');
const damSearch = ref('');
const showSireDropdown = ref(false);
const showDamDropdown = ref(false);
const photoPreview = ref<string | null>(null);
const pedigreePreviews = ref<string[]>([]);
const sireFieldsReadonly = ref(!!props.pigeon.sire);
const damFieldsReadonly = ref(!!props.pigeon.dam);

const handlePhotoChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        form.photo = file;
        photoPreview.value = URL.createObjectURL(file);
        form.remove_photo = false;
    }
};

const removePhoto = () => {
    if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
    }
    form.photo = null;
    photoPreview.value = null;
    form.remove_photo = true;
};

const handlePedigreeChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files || []);
    const totalImages = form.pedigree_images.length + form.existing_pedigree_images.length - form.remove_pedigree.length;
    if (files.length + totalImages > 2) {
        alert('You can only upload up to 2 pedigree images');
        return;
    }
    form.pedigree_images = [...form.pedigree_images, ...files];
    pedigreePreviews.value = [...pedigreePreviews.value, ...files.map(file => URL.createObjectURL(file))];
};

const removePedigreeImage = (index: number) => {
    URL.revokeObjectURL(pedigreePreviews.value[index]);
    form.pedigree_images = form.pedigree_images.filter((_, i) => i !== index);
    pedigreePreviews.value = pedigreePreviews.value.filter((_, i) => i !== index);
};

const removeExistingPedigree = (url: string) => {
    form.remove_pedigree = [...form.remove_pedigree, url];
};

// Auto-uppercase ring_number, name, and bloodline
watch(() => form.ring_number, (value) => {
    if (value) form.ring_number = value.toUpperCase();
});

watch(() => form.name, (value) => {
    if (value) form.name = value.toUpperCase();
});

watch(() => form.bloodline, (value) => {
    if (value) form.bloodline = value.toUpperCase();
});

// Searchable sire/dam filtering
const filteredSires = computed(() => {
    if (!sireSearch.value) return props.parentOptions.sires;
    const search = sireSearch.value.toLowerCase();
    return props.parentOptions.sires.filter(s => 
        s.name?.toLowerCase().includes(search) ||
        s.ring_number?.toLowerCase().includes(search) ||
        s.bloodline?.toLowerCase().includes(search) ||
        s.color?.toLowerCase().includes(search)
    );
});

const filteredDams = computed(() => {
    if (!damSearch.value) return props.parentOptions.dams;
    const search = damSearch.value.toLowerCase();
    return props.parentOptions.dams.filter(d => 
        d.name?.toLowerCase().includes(search) ||
        d.ring_number?.toLowerCase().includes(search) ||
        d.bloodline?.toLowerCase().includes(search) ||
        d.color?.toLowerCase().includes(search)
    );
});

// Format selected pigeon - simplified display (name/ring + bloodline)
const formatSelectedParent = (parent: ParentOption) => {
    const parts = [];
    if (parent.name) parts.push(parent.name);
    if (parent.ring_number) parts.push(parent.ring_number);
    if (parent.bloodline) parts.push(`- ${parent.bloodline}`);
    return parts.join(' ');
};

const selectSire = (sire: ParentOption) => {
    sireId.value = sire.id.toString();
    sireSearch.value = formatSelectedParent(sire);
    showSireDropdown.value = false;
};

const selectDam = (dam: ParentOption) => {
    damId.value = dam.id.toString();
    damSearch.value = formatSelectedParent(dam);
    showDamDropdown.value = false;
};

const handleSireBlur = () => {
    window.setTimeout(() => showSireDropdown.value = false, 200);
};

const handleDamBlur = () => {
    window.setTimeout(() => showDamDropdown.value = false, 200);
};

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

const { success } = useToast();

const submit = () => {
    form.transform((data) => ({
        ...data,
        for_sale: data.for_sale ? 1 : 0,
        hide_price: data.hide_price ? 1 : 0,
    })).patch(update({ pigeon: props.pigeon.id }).url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => success('Pigeon updated successfully!'),
    });
};
</script>

<template>
    <Head :title="`Edit ${pigeonLabel}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle>Edit {{ pigeonLabel }}</CardTitle>
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="show({ pigeon: pigeon.id }).url">View</Link>
                        </Button>
                    </div>
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
                                    <Input id="ring_number" v-model="form.ring_number" required autocomplete="off" placeholder="e.g. PH 2024-12345" />
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
                                    <div class="space-y-2 relative">
                                        <Label for="sire_search">Sire (Male parent)</Label>
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
                                                type="button"
                                                @click="() => { sireId = ''; sireSearch = ''; showSireDropdown = false; }"
                                                class="w-full px-4 py-2 text-left hover:bg-gray-50 border-b focus:outline-none focus:bg-gray-50 text-sm text-gray-600"
                                            >
                                                Unlinked / External
                                            </button>
                                            <button
                                                v-for="sire in filteredSires"
                                                :key="sire.id"
                                                type="button"
                                                @click="selectSire(sire)"
                                                class="w-full px-4 py-3 text-left hover:bg-gray-50 border-b last:border-b-0 focus:outline-none focus:bg-gray-50"
                                            >
                                                <div class="font-medium text-sm">{{ sire.name || 'Unnamed' }} - {{ sire.ring_number || sire.personal_number }}</div>
                                                <div class="text-xs text-gray-600 mt-1 space-y-0.5">
                                                    <div v-if="sire.bloodline || sire.color">
                                                        {{ [sire.bloodline, sire.color].filter(Boolean).join(' • ') }}
                                                    </div>
                                                    <div v-if="sire.sire || sire.dam" class="text-gray-500">
                                                        <span v-if="sire.sire">S: {{ sire.sire.ring_number || sire.sire.name }}</span>
                                                        <span v-if="sire.sire && sire.dam"> | </span>
                                                        <span v-if="sire.dam">D: {{ sire.dam.ring_number || sire.dam.name }}</span>
                                                    </div>
                                                    <div v-if="sire.notes" class="text-gray-500 italic truncate">{{ sire.notes }}</div>
                                                    <div v-if="sire.remarks" class="text-gray-500 italic truncate">{{ sire.remarks }}</div>
                                                </div>
                                            </button>
                                        </div>
                                        <div v-if="showSireDropdown && filteredSires.length === 0" class="absolute z-50 w-full mt-1 bg-white border rounded-lg shadow-lg p-4 text-center text-gray-500">
                                            No sires found
                                        </div>
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
                                    <div class="space-y-2 relative">
                                        <Label for="dam_search">Dam (Female parent)</Label>
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
                                                type="button"
                                                @click="() => { damId = ''; damSearch = ''; showDamDropdown = false; }"
                                                class="w-full px-4 py-2 text-left hover:bg-gray-50 border-b focus:outline-none focus:bg-gray-50 text-sm text-gray-600"
                                            >
                                                Unlinked / External
                                            </button>
                                            <button
                                                v-for="dam in filteredDams"
                                                :key="dam.id"
                                                type="button"
                                                @click="selectDam(dam)"
                                                class="w-full px-4 py-3 text-left hover:bg-gray-50 border-b last:border-b-0 focus:outline-none focus:bg-gray-50"
                                            >
                                                <div class="font-medium text-sm">{{ dam.name || 'Unnamed' }} - {{ dam.ring_number || dam.personal_number }}</div>
                                                <div class="text-xs text-gray-600 mt-1 space-y-0.5">
                                                    <div v-if="dam.bloodline || dam.color">
                                                        {{ [dam.bloodline, dam.color].filter(Boolean).join(' • ') }}
                                                    </div>
                                                    <div v-if="dam.sire || dam.dam" class="text-gray-500">
                                                        <span v-if="dam.sire">S: {{ dam.sire.ring_number || dam.sire.name }}</span>
                                                        <span v-if="dam.sire && dam.dam"> | </span>
                                                        <span v-if="dam.dam">D: {{ dam.dam.ring_number || dam.dam.name }}</span>
                                                    </div>
                                                    <div v-if="dam.notes" class="text-gray-500 italic truncate">{{ dam.notes }}</div>
                                                    <div v-if="dam.remarks" class="text-gray-500 italic truncate">{{ dam.remarks }}</div>
                                                </div>
                                            </button>
                                        </div>
                                        <div v-if="showDamDropdown && filteredDams.length === 0" class="absolute z-50 w-full mt-1 bg-white border rounded-lg shadow-lg p-4 text-center text-gray-500">
                                            No dams found
                                        </div>
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
                                <!-- Photo Upload -->
                                <div class="grid gap-2">
                                    <Label for="photo">Pigeon Photo (Max 1)</Label>
                                    
                                    <!-- Show existing photo if not removed -->
                                    <div v-if="form.photo_url && !form.remove_photo && !photoPreview" class="relative mt-2 inline-block">
                                        <img :src="form.photo_url" alt="Current photo" class="w-32 h-32 object-cover rounded-md" />
                                        <Button 
                                            type="button" 
                                            size="sm" 
                                            variant="destructive" 
                                            class="absolute -top-2 -right-2"
                                            @click="removePhoto"
                                        >
                                            X
                                        </Button>
                                    </div>
                                    
                                    <!-- Upload new photo -->
                                    <Input 
                                        v-if="!form.photo_url || form.remove_photo || photoPreview"
                                        id="photo" 
                                        type="file" 
                                        accept="image/*" 
                                        @change="handlePhotoChange"
                                    />
                                    
                                    <!-- Show new photo preview -->
                                    <div v-if="photoPreview" class="relative mt-2 inline-block">
                                        <img :src="photoPreview" alt="Photo preview" class="w-32 h-32 object-cover rounded-md" />
                                        <Button 
                                            type="button" 
                                            size="sm" 
                                            variant="destructive" 
                                            class="absolute -top-2 -right-2"
                                            @click="removePhoto"
                                        >
                                            X
                                        </Button>
                                    </div>
                                    
                                    <p class="text-xs text-muted-foreground">Images will be optimized and converted to WebP format</p>
                                    <InputError :message="form.errors.photo" />
                                </div>
                                
                                <!-- Pedigree Images Upload -->
                                <div class="grid gap-2">
                                    <Label for="pedigree_images">Pedigree Images (Max 2)</Label>
                                    
                                    <!-- Show existing pedigree images -->
                                    <div v-if="form.existing_pedigree_images.length > 0" class="grid grid-cols-2 gap-2 mt-2">
                                        <div 
                                            v-for="(url, index) in form.existing_pedigree_images" 
                                            :key="`existing-${index}`" 
                                            class="relative"
                                            v-show="!form.remove_pedigree.includes(url)"
                                        >
                                            <img :src="url" alt="Existing pedigree" class="w-full h-32 object-cover rounded-md" />
                                            <Button 
                                                type="button" 
                                                size="sm" 
                                                variant="destructive" 
                                                class="absolute -top-2 -right-2"
                                                @click="removeExistingPedigree(url)"
                                            >
                                                X
                                            </Button>
                                        </div>
                                    </div>
                                    
                                    <!-- Upload new pedigree images -->
                                    <Input 
                                        id="pedigree_images" 
                                        type="file" 
                                        accept="image/*" 
                                        multiple
                                        @change="handlePedigreeChange"
                                    />
                                    
                                    <!-- Show new pedigree previews -->
                                    <div v-if="pedigreePreviews.length > 0" class="grid grid-cols-2 gap-2 mt-2">
                                        <div v-for="(preview, index) in pedigreePreviews" :key="`new-${index}`" class="relative">
                                            <img :src="preview" alt="Pedigree preview" class="w-full h-32 object-cover rounded-md" />
                                            <Button 
                                                type="button" 
                                                size="sm" 
                                                variant="destructive" 
                                                class="absolute -top-2 -right-2"
                                                @click="removePedigreeImage(index)"
                                            >
                                                X
                                            </Button>
                                        </div>
                                    </div>
                                    
                                    <p class="text-xs text-muted-foreground">Upload existing or handwritten pedigree images</p>
                                    <InputError :message="form.errors.pedigree_images" />
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
                                <Link :href="show({ pigeon: pigeon.id }).url">Cancel</Link>
                            </Button>
                            <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                                Save changes
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
