<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { PIGEON_STATUSES } from '@/lib/pigeon-status';
import InputError from '@/components/InputError.vue';
import BloodlineMultiSelect from '@/components/BloodlineMultiSelect.vue';
import FormActionBar from '@/components/FormActionBar.vue';
import PhotoListInput from '@/components/PhotoListInput.vue';
import { photoFiles, photoOrder, photoRelations, savedPhotoItems, type PhotoItem } from '@/lib/images';
import DriveConnectNotice from '@/components/DriveConnectNotice.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { listLabels } from '@/lib/pedigree';
import type { PedigreeSyncReport } from '@/types/pedigree';
import { edit as editRoute, editPedigree, index as indexRoute, show, show as showRoute, update } from '@/routes/pigeons';
import { useToast } from '@/composables/useToast';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { BadgeCheck, Link2, Network } from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';
import PigeonSearchResult from '@/components/PigeonSearchResult.vue';
import type { PigeonSearchItem } from '@/types/pigeon-search';
import axios from 'axios';

interface ParentSummary {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
}

type ParentOption = PigeonSearchItem;

interface DuplicateMatch {
    pigeon: {
        id: number;
        ring_number: string;
        personal_number: string | null;
        name: string | null;
        gender: string | null;
        status: string | null;
        bloodline: string | null;
        photo: string | null;
    };
    similarity: number;
}

interface BloodlineOption {
    id: number;
    name: string;
}

interface SelectedBloodline {
    id: number;
    name: string;
    is_primary: boolean;
}

interface Pigeon {
    id: number;
    name: string | null;
    bloodline: string | null;
    bloodlines: SelectedBloodline[];
    gender: string | null;
    hatch_date: string | null;
    status: string;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    remarks: string | null;
    notes: string | null;
    photo_url: string | null;
    // Every photo, the main one first
    photos: string[];
    pedigree_images: string[] | null;
    // Image URL => chart position of the relative it shows; the rest are certificates
    pedigree_image_relations: Record<string, string>;
    bred_by: string | null;
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
    location_id: number | null;
}

interface LocationOption {
    id: number;
    name: string;
    type: string;
}

const props = defineProps<{
    pigeon: Pigeon;
    parentOptions: {
        sires: ParentOption[];
        dams: ParentOption[];
    };
    bloodlines: BloodlineOption[];
    colors: string[];
    locations: LocationOption[];
    // Images are saved to the user's Google Drive (Settings → Storage)
    driveConnected: boolean;
    // Other pedigrees that record the same outside sire or dam (matched by ring number)
    outsideLinks: { sire: string[]; dam: string[] };
    syncReport: PedigreeSyncReport | null;
}>();

const pigeonLabel = computed(() => props.pigeon.name || props.pigeon.ring_number || props.pigeon.personal_number || `Pigeon #${props.pigeon.id}`);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: indexRoute().url },
    { title: pigeonLabel.value, href: show({ pigeon: props.pigeon.id }).url },
    { title: 'Edit', href: editRoute({ pigeon: props.pigeon.id }).url },
];

const form = useForm({
    ring_number: props.pigeon.ring_number ?? '',
    bloodline: props.pigeon.bloodline ?? '', // Legacy field
    bloodlines: props.pigeon.bloodlines ?? [] as SelectedBloodline[],
    name: props.pigeon.name ?? '',
    personal_number: props.pigeon.personal_number ?? '',
    status: props.pigeon.status ?? 'stock',
    hatch_date: props.pigeon.hatch_date ?? '',
    gender: props.pigeon.gender ?? '',
    color: props.pigeon.color ?? '',
    remarks: props.pigeon.remarks ?? '',
    notes: props.pigeon.notes ?? '',
    // Sent as photos[] + photo_order (the first is the main photo) and pedigree_images[] + remove_pedigree[]
    photo_items: savedPhotoItems(props.pigeon.photos) as PhotoItem[],
    certificate_items: savedPhotoItems(props.pigeon.pedigree_images, props.pigeon.pedigree_image_relations) as PhotoItem[],
    bred_by: props.pigeon.bred_by ?? '',
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
    // Carry changes to an outside sire or dam over to its records in other pedigrees
    sync_sire: true,
    sync_dam: true,
    for_sale: props.pigeon.for_sale ?? false,
    sale_price: props.pigeon.sale_price ?? '',
    hide_price: props.pigeon.hide_price ?? false,
    sale_description: props.pigeon.sale_description ?? '',
    location_id: props.pigeon.location_id ?? null as number | null,
});

// Track selected bloodlines separately (reactive binding for component)
const selectedBloodlines = ref<SelectedBloodline[]>(props.pigeon.bloodlines ?? []);

// Sync selectedBloodlines to form.bloodlines
watch(selectedBloodlines, (newValue) => {
    form.bloodlines = newValue;
}, { deep: true });

const sireId = ref(String(props.pigeon.sire?.id ?? ''));
const damId = ref(String(props.pigeon.dam?.id ?? ''));
const sireSearch = ref('');
const damSearch = ref('');
const showSireDropdown = ref(false);
const showDamDropdown = ref(false);
const sireFieldsReadonly = ref(!!props.pigeon.sire);
const damFieldsReadonly = ref(!!props.pigeon.dam);

// Ring number duplicate checking
const checkingRingNumber = ref(false);
const exactDuplicateMatch = ref<DuplicateMatch['pigeon'] | null>(null);
const similarMatches = ref<DuplicateMatch[]>([]);
const showDuplicateWarning = ref(false);
const duplicateCheckDismissed = ref(false);
const originalRingNumber = props.pigeon.ring_number?.toUpperCase() ?? '';

// Check ring number for duplicates (exclude current pigeon)
const checkRingNumber = async () => {
    // Skip check if ring number hasn't changed
    if (form.ring_number.toUpperCase() === originalRingNumber) {
        exactDuplicateMatch.value = null;
        similarMatches.value = [];
        return;
    }

    if (!form.ring_number || form.ring_number.length < 3) {
        exactDuplicateMatch.value = null;
        similarMatches.value = [];
        return;
    }

    checkingRingNumber.value = true;
    try {
        const response = await axios.get('/pigeons/check-ring-number', {
            params: { 
                ring_number: form.ring_number,
                exclude_id: props.pigeon.id 
            }
        });
        
        exactDuplicateMatch.value = response.data.exact_match;
        similarMatches.value = response.data.similar_matches || [];
        
        // Show warning dialog if we have similar matches (but not exact - exact blocks in template)
        if (!response.data.exact_match && similarMatches.value.length > 0 && !duplicateCheckDismissed.value) {
            showDuplicateWarning.value = true;
        }
    } catch (error) {
        console.error('Error checking ring number:', error);
    } finally {
        checkingRingNumber.value = false;
    }
};

const dismissDuplicateWarning = () => {
    showDuplicateWarning.value = false;
    duplicateCheckDismissed.value = true;
};

// Reset duplicate check when ring number changes
watch(() => form.ring_number, () => {
    exactDuplicateMatch.value = null;
    similarMatches.value = [];
    duplicateCheckDismissed.value = false;
});

// Errors for a list field arrive per file too (photos.0, photos.1, ...)
const fieldError = (field: string) => {
    const errors = form.errors as Record<string, string | undefined>;
    return errors[field] ?? Object.entries(errors).find(([key]) => key.startsWith(`${field}.`))?.[1];
};

// Auto-uppercase ring_number, name, and bloodline
watch(() => form.ring_number, (value) => {
    if (value) form.ring_number = value.toUpperCase();
});

watch(() => form.name, (value) => {
    if (value) form.name = value.toUpperCase();
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

const submit = (event?: SubmitEvent) => {
    // The "Save & ... pedigree" button continues to the pedigree editor after saving
    const continueToPedigree = (event?.submitter as HTMLElement | null)?.dataset.next === 'pedigree';
    form.transform(({ photo_items, certificate_items, ...data }) => {
        const photos = photoFiles(photo_items);
        const pedigreeImages = photoFiles(certificate_items);
        const keptCertificates = new Set(certificate_items.map((item) => item.saved));
        return {
        ...data,
        photos,
        photo_order: photoOrder(photo_items),
        pedigree_images: pedigreeImages,
        pedigree_image_relations: photoRelations(certificate_items),
        remove_pedigree: (props.pigeon.pedigree_images ?? []).filter((url) => !keptCertificates.has(url)),
        upload_count: photos.length + pedigreeImages.length,
        for_sale: data.for_sale ? 1 : 0,
        hide_price: data.hide_price ? 1 : 0,
        edit_pedigree: continueToPedigree ? 1 : 0,
        sync_sire: data.sync_sire ? 1 : 0,
        sync_dam: data.sync_dam ? 1 : 0,
        };
    }).patch(update({ pigeon: props.pigeon.id }).url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: (page) => {
            const report = page.props.syncReport as PedigreeSyncReport | null;
            success(report?.updated.length
                ? `Pigeon updated, also in the pedigrees of ${listLabels(report.updated)}`
                : 'Pigeon updated successfully!');
        },
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
                    <form class="space-y-8 pb-20 md:pb-0" @submit.prevent="submit">
                        <!-- Identification -->
                        <section class="space-y-6">
                            <div class="space-y-2">
                                <h2 class="text-lg font-semibold text-foreground">Identification</h2>
                                <p class="text-sm text-muted-foreground">Core identification numbers and bloodline.</p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="ring_number">Ring Number *</Label>
                                    <div class="relative">
                                        <Input 
                                            id="ring_number" 
                                            v-model="form.ring_number" 
                                            required 
                                            autocomplete="off" 
                                            placeholder="Ring number or type 'PERSO' for personal pigeons"
                                            @blur="checkRingNumber"
                                            :class="{ 'border-destructive': exactDuplicateMatch }"
                                        />
                                        <span v-if="checkingRingNumber" class="absolute right-3 top-1/2 -translate-y-1/2">
                                            <svg class="animate-spin h-4 w-4 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                        </span>
                                    </div>
                                    <InputError :message="form.errors.ring_number" />
                                    
                                    <!-- Exact duplicate error -->
                                    <div v-if="exactDuplicateMatch" class="rounded-md border border-destructive bg-destructive/10 p-3 text-sm">
                                        <p class="font-medium text-destructive">A pigeon with ring number {{ exactDuplicateMatch.ring_number }} already exists.</p>
                                        <div class="mt-2 flex items-center gap-2 text-muted-foreground">
                                            <span v-if="exactDuplicateMatch.name">{{ exactDuplicateMatch.name }}</span>
                                            <span v-if="exactDuplicateMatch.gender">• {{ exactDuplicateMatch.gender === 'male' ? 'Cock' : 'Hen' }}</span>
                                            <span v-if="exactDuplicateMatch.status">• {{ exactDuplicateMatch.status }}</span>
                                        </div>
                                        <Link :href="showRoute({ pigeon: exactDuplicateMatch.id }).url" class="mt-2 inline-flex items-center text-sm font-medium text-primary hover:underline">
                                            View / Edit this pigeon →
                                        </Link>
                                    </div>
                                </div>
                                <div class="grid gap-2">
                                    <Label>Bloodline</Label>
                                    <BloodlineMultiSelect
                                        v-model="selectedBloodlines"
                                        :bloodlines="bloodlines"
                                    />
                                    <InputError :message="form.errors.bloodlines" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="name">Name</Label>
                                    <Input id="name" v-model="form.name" autocomplete="off" placeholder="Optional pigeon name" />
                                    <InputError :message="form.errors.name" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="personal_number">Personal Ring Number</Label>
                                    <Input id="personal_number" v-model="form.personal_number" autocomplete="off" placeholder="Optional, Perso number" />
                                    <InputError :message="form.errors.personal_number" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="status">Status *</Label>
                                    <select id="status" v-model="form.status" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                        <option v-for="option in PIGEON_STATUSES" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <InputError :message="form.errors.status" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="location_id">Location</Label>
                                    <select id="location_id" v-model="form.location_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                        <option :value="null">— No location —</option>
                                        <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                                            {{ loc.name }}{{ loc.type === 'olr' ? ' (OLR)' : '' }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.location_id" />
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
                                <Label for="remarks">Remarks <span class="font-normal text-muted-foreground">(shown on pedigree)</span></Label>
                                <textarea id="remarks" v-model="form.remarks" rows="3" class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 placeholder:text-muted-foreground" placeholder="Short notes, e.g. '1st club 300km 2024', 'Strong in headwinds'" />
                                <p class="text-xs text-muted-foreground">Use for quick at-a-glance info like race highlights or key tags. Remarks are printed on the pedigree.</p>
                                <InputError :message="form.errors.remarks" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="notes">Notes <span class="font-normal text-muted-foreground">(private, not shown on pedigree)</span></Label>
                                <textarea id="notes" v-model="form.notes" rows="4" class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 placeholder:text-muted-foreground" placeholder="Detailed notes, e.g. breeding performance, health history, pairing observations..." />
                                <p class="text-xs text-muted-foreground">Use for longer, detailed history about this pigeon. Notes are never shown on the pedigree.</p>
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
                            <div class="flex flex-col gap-3 rounded-lg border border-gold/40 bg-secondary/50 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex gap-3">
                                    <Network class="mt-0.5 size-5 shrink-0 text-gold" />
                                    <div class="space-y-1 text-sm">
                                        <p class="font-medium text-foreground">Grandparents and beyond</p>
                                        <p class="text-muted-foreground">Add grandparents up to great-great-grandparents in the pedigree editor, or copy a whole side from a related pigeon. Ancestors behind a loft bird are saved on that bird, so its other offspring inherit them too.</p>
                                    </div>
                                </div>
                                <Button v-if="form.isDirty" type="submit" variant="outline" data-next="pedigree" class="shrink-0" :disabled="form.processing">Save &amp; edit pedigree</Button>
                                <Button v-else variant="outline" class="shrink-0" as-child>
                                    <Link :href="editPedigree({ pigeon: pigeon.id }).url">Edit pedigree</Link>
                                </Button>
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
                                            class="absolute z-50 w-full mt-1 bg-popover border rounded-lg shadow-lg max-h-60 overflow-auto"
                                        >
                                            <button
                                                type="button"
                                                @click="() => { sireId = ''; sireSearch = ''; showSireDropdown = false; }"
                                                class="w-full px-4 py-2 text-left hover:bg-accent border-b focus:outline-none focus:bg-accent text-sm text-muted-foreground"
                                            >
                                                Unlinked / External
                                            </button>
                                            <button
                                                v-for="sire in filteredSires"
                                                :key="sire.id"
                                                type="button"
                                                @click="selectSire(sire)"
                                                class="w-full px-4 py-3 text-left hover:bg-accent border-b last:border-b-0 focus:outline-none focus:bg-accent"
                                            >
                                                <PigeonSearchResult :pigeon="sire" />
                                            </button>
                                        </div>
                                        <div v-if="showSireDropdown && filteredSires.length === 0" class="absolute z-50 w-full mt-1 bg-popover border rounded-lg shadow-lg p-4 text-center text-muted-foreground">
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
                                        <Label v-if="sireFieldsReadonly" for="sire_notes">Sire notes <span class="font-normal text-muted-foreground">(private)</span></Label>
                                        <Label v-else for="sire_notes">Sire remarks <span class="font-normal text-muted-foreground">(shown on pedigree)</span></Label>
                                        <textarea id="sire_notes" v-model="form.sire_notes" rows="3" class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 placeholder:text-muted-foreground" />
                                        <p class="text-xs text-muted-foreground">{{ sireFieldsReadonly ? "The pedigree shows the linked sire's own remarks." : 'Race results or key traits of this external sire.' }}</p>
                                        <InputError :message="form.errors.sire_notes" />
                                    </div>
                                    <div v-if="!sireFieldsReadonly && outsideLinks.sire.length" class="grid gap-2 rounded-lg border border-gold/40 bg-secondary/50 p-3 text-sm">
                                        <p class="flex gap-2"><Link2 class="mt-0.5 size-4 shrink-0 text-gold" />This sire is also recorded in the pedigrees of {{ listLabels(outsideLinks.sire) }}, matched by ring number.</p>
                                        <label class="flex items-center gap-2 pl-6"><Checkbox v-model="form.sync_sire" />Update those records with changes made here</label>
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
                                            class="absolute z-50 w-full mt-1 bg-popover border rounded-lg shadow-lg max-h-60 overflow-auto"
                                        >
                                            <button
                                                type="button"
                                                @click="() => { damId = ''; damSearch = ''; showDamDropdown = false; }"
                                                class="w-full px-4 py-2 text-left hover:bg-accent border-b focus:outline-none focus:bg-accent text-sm text-muted-foreground"
                                            >
                                                Unlinked / External
                                            </button>
                                            <button
                                                v-for="dam in filteredDams"
                                                :key="dam.id"
                                                type="button"
                                                @click="selectDam(dam)"
                                                class="w-full px-4 py-3 text-left hover:bg-accent border-b last:border-b-0 focus:outline-none focus:bg-accent"
                                            >
                                                <PigeonSearchResult :pigeon="dam" />
                                            </button>
                                        </div>
                                        <div v-if="showDamDropdown && filteredDams.length === 0" class="absolute z-50 w-full mt-1 bg-popover border rounded-lg shadow-lg p-4 text-center text-muted-foreground">
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
                                        <Label v-if="damFieldsReadonly" for="dam_notes">Dam notes <span class="font-normal text-muted-foreground">(private)</span></Label>
                                        <Label v-else for="dam_notes">Dam remarks <span class="font-normal text-muted-foreground">(shown on pedigree)</span></Label>
                                        <textarea id="dam_notes" v-model="form.dam_notes" rows="3" class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 placeholder:text-muted-foreground" />
                                        <p class="text-xs text-muted-foreground">{{ damFieldsReadonly ? "The pedigree shows the linked dam's own remarks." : 'Race results or key traits of this external dam.' }}</p>
                                        <InputError :message="form.errors.dam_notes" />
                                    </div>
                                    <div v-if="!damFieldsReadonly && outsideLinks.dam.length" class="grid gap-2 rounded-lg border border-gold/40 bg-secondary/50 p-3 text-sm">
                                        <p class="flex gap-2"><Link2 class="mt-0.5 size-4 shrink-0 text-gold" />This dam is also recorded in the pedigrees of {{ listLabels(outsideLinks.dam) }}, matched by ring number.</p>
                                        <label class="flex items-center gap-2 pl-6"><Checkbox v-model="form.sync_dam" />Update those records with changes made here</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Who bred the bird: the user when both parents are their birds, otherwise whoever they enter -->
                            <div v-if="form.sire_id && form.dam_id" class="flex items-start gap-3 rounded-lg border border-border/70 p-4 text-sm">
                                <BadgeCheck class="mt-0.5 size-5 shrink-0 text-gold" />
                                <div>
                                    <p class="font-medium text-foreground">Bred in your loft</p>
                                    <p class="text-muted-foreground">Both parents are your birds, so the pedigree names you as its breeder.</p>
                                </div>
                            </div>
                            <div v-else class="grid gap-2 rounded-lg border border-border/70 p-4">
                                <Label for="bred_by">Bred by</Label>
                                <Input id="bred_by" v-model="form.bred_by" placeholder="Loft or breeder name" autocomplete="off" />
                                <p class="text-xs text-muted-foreground">Not both parents are your birds, so the pedigree names you as its owner and shows who bred it.</p>
                                <InputError :message="form.errors.bred_by" />
                            </div>
                        </section>

                        <Separator />

                        <!-- Photos -->
                        <section class="space-y-6">
                            <div class="space-y-2">
                                <h2 class="text-lg font-semibold text-foreground">Photos</h2>
                                <p class="text-sm text-muted-foreground">Add as many as you like. They are saved to your own Google Drive.</p>
                            </div>
                            <DriveConnectNotice v-if="!driveConnected" unsaved-changes />
                            <div v-else class="grid gap-6">
                                <PhotoListInput
                                    id="photos"
                                    v-model="form.photo_items"
                                    label="Pigeon photos"
                                    description="Photos of the bird. The first is its main photo, shown on cards and on the pedigree. Cropping is optional."
                                    main
                                    :error="fieldError('photos') ?? fieldError('photo_order')"
                                />
                                <PhotoListInput
                                    id="pedigree_images"
                                    v-model="form.certificate_items"
                                    label="Certificates & relatives' photos"
                                    description="Certificates and documents stay private. For a photo of a relative, choose who it shows: it then appears on the pigeon's page and its pedigree."
                                    relations
                                    :error="fieldError('pedigree_images') ?? fieldError('remove_pedigree')"
                                />
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

                        <FormActionBar
                            :cancel-href="show({ pigeon: pigeon.id }).url"
                            submit-label="Save changes"
                            :processing="form.processing"
                            :disabled="!!exactDuplicateMatch"
                        />
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Similar matches warning dialog -->
        <Dialog v-model:open="showDuplicateWarning">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-amber-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        Possible duplicate found
                    </DialogTitle>
                    <DialogDescription>
                        You already have pigeon(s) with similar ring numbers. Are you sure you want to update to this ring number?
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3 py-4">
                    <div v-for="match in similarMatches" :key="match.pigeon.id" class="flex items-center gap-3 rounded-lg border p-3">
                        <div v-if="match.pigeon.photo" class="h-12 w-12 flex-shrink-0 overflow-hidden rounded-full">
                            <img :src="match.pigeon.photo" :alt="match.pigeon.ring_number" class="h-full w-full object-cover" />
                        </div>
                        <div v-else class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-muted">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate">{{ match.pigeon.ring_number }}</p>
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                <span v-if="match.pigeon.name">{{ match.pigeon.name }}</span>
                                <span v-if="match.pigeon.gender">• {{ match.pigeon.gender === 'male' ? 'Cock' : 'Hen' }}</span>
                                <Badge v-if="match.pigeon.status" variant="secondary" class="text-xs">{{ match.pigeon.status }}</Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ match.similarity }}% similar</p>
                        </div>
                        <Link :href="showRoute({ pigeon: match.pigeon.id }).url" class="text-sm font-medium text-primary hover:underline">
                            View
                        </Link>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="showDuplicateWarning = false">
                        Cancel
                    </Button>
                    <Button @click="dismissDuplicateWarning">
                        Continue anyway
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
