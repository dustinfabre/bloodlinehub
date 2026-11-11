<script setup lang="ts">
import ImageCropper from '@/components/ImageCropper.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import { ACCOUNT_TYPES } from '@/lib/billing';
import { destroy, store, update } from '@/routes/admin/bank-accounts';
import { qr } from '@/routes/billing/accounts';
import type {
    PayerAccount,
    PaymentMethod,
    PaymentRegion,
} from '@/types/billing';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    Crop,
    Globe,
    Landmark,
    Pencil,
    Plus,
    QrCode,
    Trash2,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

type AdminAccount = PayerAccount & {
    is_active: boolean;
    sort_order: number;
    payments_count: number;
};

const props = defineProps<{
    accounts: AdminAccount[];
}>();

const { success, error } = useToast();

const sections: {
    region: PaymentRegion;
    title: string;
    description: string;
    icon: typeof Landmark;
}[] = [
    {
        region: 'local',
        title: 'Local (PHP)',
        description:
            'Banks and e-wallets in the Philippines. Payers here pay the peso price.',
        icon: Landmark,
    },
    {
        region: 'international',
        title: 'International (USD)',
        description:
            'Accounts that can receive money from abroad. Payers here pay the dollar price.',
        icon: Globe,
    },
];

const accountsIn = (region: PaymentRegion) =>
    props.accounts.filter((account) => account.region === region);

// Changed after each save, so a replaced QR code isn't shown from the browser's cache
const qrVersion = ref(Date.now());
const qrUrl = (account: AdminAccount) =>
    qr(account.id, { query: { v: qrVersion.value } }).url;

// Create / edit
const editing = ref<AdminAccount | null>(null);
const showForm = ref(false);

const form = useForm({
    region: 'local' as PaymentRegion,
    method: 'bank' as PaymentMethod,
    bank_name: '',
    account_name: '',
    account_number: '',
    account_type: 'checking' as 'checking' | 'savings',
    routing_number: '',
    swift_code: '',
    bank_address: '',
    instructions: '',
    is_active: true,
    sort_order: 0 as number | string,
    qr: null as File | null,
    remove_qr: false,
});

const isInternational = computed(() => form.region === 'international');
// PayPal is international only: picking it switches the region
const isPaypal = computed(
    () => isInternational.value && form.method === 'paypal',
);

const qrPreview = ref<string | null>(null);
const qrInput = ref<HTMLInputElement | null>(null);
// The image as picked, so it can be cropped again from scratch; form.qr holds the (cropped) file to upload
const qrOriginal = ref<File | null>(null);
const qrOriginalUrl = ref<string | null>(null);
const qrCropOpen = ref(false);

const clearPreview = () => {
    if (qrPreview.value) URL.revokeObjectURL(qrPreview.value);
    qrPreview.value = null;
};

const clearOriginal = () => {
    if (qrOriginalUrl.value) URL.revokeObjectURL(qrOriginalUrl.value);
    qrOriginalUrl.value = null;
    qrOriginal.value = null;
};

onBeforeUnmount(() => {
    clearPreview();
    clearOriginal();
});

const useQrFile = (file: File) => {
    clearPreview();
    form.qr = file;
    qrPreview.value = URL.createObjectURL(file);
};

// A picked image opens in the cropper straight away (square, as QR codes are); "Use as is" keeps it uncropped
const onQrChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    clearOriginal();
    if (!file) {
        clearPreview();
        form.qr = null;
        return;
    }
    qrOriginal.value = file;
    qrOriginalUrl.value = URL.createObjectURL(file);
    useQrFile(file);
    form.remove_qr = false;
    qrCropOpen.value = true;
};

const clearQrChoice = () => {
    clearPreview();
    clearOriginal();
    form.qr = null;
    if (qrInput.value) qrInput.value.value = '';
};

const openCreate = (region: PaymentRegion, method: PaymentMethod = 'bank') => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    clearQrChoice();
    form.region = region;
    form.method = method;
    showForm.value = true;
};

const openEdit = (account: AdminAccount) => {
    editing.value = account;
    form.clearErrors();
    clearQrChoice();
    form.region = account.region;
    form.method = account.method;
    form.bank_name = account.bank_name;
    form.account_name = account.account_name;
    form.account_number = account.account_number;
    form.account_type = account.account_type ?? 'checking';
    form.routing_number = account.routing_number ?? '';
    form.swift_code = account.swift_code ?? '';
    form.bank_address = account.bank_address ?? '';
    form.instructions = account.instructions ?? '';
    form.is_active = account.is_active;
    form.sort_order = account.sort_order;
    form.remove_qr = false;
    showForm.value = true;
};

const submit = () => {
    const account = editing.value;
    const url = account ? update(account.id).url : store().url;

    form.transform((data) => ({
        ...data,
        method: data.region === 'international' ? data.method : 'bank',
        // Account type, routing number, SWIFT and bank address are for international transfers only
        account_type: data.region === 'international' ? data.account_type : '',
        routing_number:
            data.region === 'international' ? data.routing_number : '',
        swift_code: data.region === 'international' ? data.swift_code : '',
        bank_address: data.region === 'international' ? data.bank_address : '',
    })).post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            clearQrChoice();
            qrVersion.value = Date.now();
            success(account ? 'Account updated.' : 'Account added.');
        },
    });
};

// Delete
const deleting = ref<AdminAccount | null>(null);
const showDelete = computed({
    get: () => deleting.value !== null,
    set: (open: boolean) => {
        if (!open) deleting.value = null;
    },
});

const confirmDelete = () => {
    const account = deleting.value;
    if (!account) return;
    router.delete(destroy(account.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
            success('Account deleted.');
        },
        onError: () => error('Could not delete this account.'),
    });
};
</script>

<template>
    <Head title="Bank accounts · Admin" />

    <AdminLayout
        title="Bank accounts"
        description="Shown on the billing page. Payers transfer to one of these, then upload their receipt for you to check."
    >
        <div class="space-y-10">
            <section
                v-for="section in sections"
                :key="section.region"
                class="space-y-4"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <h2
                            class="flex items-center gap-2 text-lg font-semibold"
                        >
                            <component
                                :is="section.icon"
                                class="size-5 text-muted-foreground"
                            />
                            {{ section.title }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ section.description }}
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openCreate(section.region)"
                        >
                            <Plus class="size-4" />
                            {{
                                section.region === 'international'
                                    ? 'Add bank account'
                                    : 'Add account'
                            }}
                        </Button>
                        <Button
                            v-if="section.region === 'international'"
                            variant="outline"
                            size="sm"
                            @click="openCreate(section.region, 'paypal')"
                        >
                            <Plus class="size-4" /> Add PayPal
                        </Button>
                    </div>
                </div>

                <div
                    v-if="accountsIn(section.region).length === 0"
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border py-10 text-center"
                >
                    <Banknote class="mb-2 size-8 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">
                        No
                        {{
                            section.region === 'local'
                                ? 'local'
                                : 'international'
                        }}
                        accounts yet.
                        {{
                            section.region === 'local'
                                ? 'Payers in the Philippines will have nowhere to send money.'
                                : 'Payers abroad will have nowhere to send money.'
                        }}
                    </p>
                </div>

                <div v-else class="grid gap-3 xl:grid-cols-2">
                    <Card
                        v-for="account in accountsIn(section.region)"
                        :key="account.id"
                        :class="['py-0', account.is_active ? '' : 'opacity-70']"
                    >
                        <CardContent class="flex gap-4 p-4 sm:p-5">
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold">
                                        {{ account.bank_name }}
                                    </p>
                                    <Badge
                                        :variant="
                                            account.is_active
                                                ? 'secondary'
                                                : 'outline'
                                        "
                                    >
                                        {{
                                            account.is_active
                                                ? 'Shown'
                                                : 'Hidden'
                                        }}
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        class="text-muted-foreground"
                                        >{{ account.currency }}</Badge
                                    >
                                </div>
                                <dl class="space-y-1 text-sm">
                                    <div class="flex flex-wrap gap-x-2">
                                        <dt class="text-muted-foreground">
                                            Name
                                        </dt>
                                        <dd class="min-w-0 wrap-break-word">
                                            {{ account.account_name }}
                                        </dd>
                                    </div>
                                    <div class="flex flex-wrap gap-x-2">
                                        <dt class="text-muted-foreground">
                                            {{
                                                account.method === 'paypal'
                                                    ? 'PayPal email'
                                                    : 'Number'
                                            }}
                                        </dt>
                                        <dd class="min-w-0 font-mono break-all">
                                            {{ account.account_number }}
                                        </dd>
                                    </div>
                                    <div
                                        v-if="account.account_type"
                                        class="flex flex-wrap gap-x-2"
                                    >
                                        <dt class="text-muted-foreground">
                                            Type
                                        </dt>
                                        <dd>
                                            {{
                                                ACCOUNT_TYPES[
                                                    account.account_type
                                                ]
                                            }}
                                        </dd>
                                    </div>
                                    <div
                                        v-if="account.routing_number"
                                        class="flex flex-wrap gap-x-2"
                                    >
                                        <dt class="text-muted-foreground">
                                            Routing
                                        </dt>
                                        <dd class="font-mono break-all">
                                            {{ account.routing_number }}
                                        </dd>
                                    </div>
                                    <div
                                        v-if="account.swift_code"
                                        class="flex flex-wrap gap-x-2"
                                    >
                                        <dt class="text-muted-foreground">
                                            SWIFT
                                        </dt>
                                        <dd class="font-mono break-all">
                                            {{ account.swift_code }}
                                        </dd>
                                    </div>
                                    <div
                                        v-if="account.bank_address"
                                        class="flex flex-wrap gap-x-2"
                                    >
                                        <dt class="text-muted-foreground">
                                            Address
                                        </dt>
                                        <dd class="min-w-0 wrap-break-word">
                                            {{ account.bank_address }}
                                        </dd>
                                    </div>
                                </dl>
                                <p
                                    v-if="account.instructions"
                                    class="text-sm wrap-break-word whitespace-pre-wrap text-muted-foreground"
                                >
                                    {{ account.instructions }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ account.payments_count }}
                                    {{
                                        account.payments_count === 1
                                            ? 'payment'
                                            : 'payments'
                                    }}
                                    · order {{ account.sort_order }}
                                </p>
                                <div class="flex flex-wrap gap-2 pt-1">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEdit(account)"
                                    >
                                        <Pencil class="size-4" /> Edit
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        @click="deleting = account"
                                    >
                                        <Trash2 class="size-4" /> Delete
                                    </Button>
                                </div>
                            </div>
                            <a
                                v-if="account.has_qr"
                                :href="qrUrl(account)"
                                target="_blank"
                                rel="noopener"
                                class="size-20 shrink-0 overflow-hidden rounded-md border border-border bg-white sm:size-24"
                                :aria-label="`${account.bank_name} QR code`"
                            >
                                <img
                                    :src="qrUrl(account)"
                                    alt=""
                                    class="size-full object-contain"
                                />
                            </a>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </div>

        <!-- Opens over the form when a QR image is picked -->
        <ImageCropper
            v-model:open="qrCropOpen"
            :image-src="qrOriginalUrl"
            :file-name="qrOriginal?.name ?? 'qr.jpg'"
            title="Crop the QR code"
            :initial-aspect="1"
            cancel-label="Use as is"
            @crop="useQrFile"
        />

        <!-- Create / edit -->
        <Dialog v-model:open="showForm">
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        editing
                            ? 'Edit account'
                            : isPaypal
                              ? 'Add PayPal'
                              : 'Add account'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            isInternational
                                ? 'Payers abroad send dollars here.'
                                : 'Payers in the Philippines send pesos here.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="account-region">Region</Label>
                        <Select v-model="form.region">
                            <SelectTrigger id="account-region" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="local"
                                    >Local · pesos (PHP)</SelectItem
                                >
                                <SelectItem value="international"
                                    >International · dollars (USD)</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.region" />
                    </div>

                    <div v-if="isInternational" class="grid gap-2">
                        <Label for="account-method">Paid with</Label>
                        <Select v-model="form.method">
                            <SelectTrigger id="account-method" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="bank"
                                    >Bank transfer</SelectItem
                                >
                                <SelectItem value="paypal">PayPal</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.method" />
                    </div>

                    <!-- PayPal: whose account, and its email -->
                    <template v-if="isPaypal">
                        <div class="grid gap-2">
                            <Label for="account-name">Name</Label>
                            <Input
                                id="account-name"
                                v-model="form.account_name"
                                required
                                placeholder="Name on the PayPal account"
                            />
                            <InputError :message="form.errors.account_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="account-number">PayPal email</Label>
                            <Input
                                id="account-number"
                                v-model="form.account_number"
                                type="email"
                                inputmode="email"
                                required
                                placeholder="payments@bloodlinehub.com"
                            />
                            <InputError :message="form.errors.account_number" />
                        </div>
                    </template>

                    <!-- Local: bank or wallet, then whose account it is -->
                    <template v-else-if="!isInternational">
                        <div class="grid gap-2">
                            <Label for="account-bank">Bank or wallet</Label>
                            <Input
                                id="account-bank"
                                v-model="form.bank_name"
                                required
                                placeholder="BDO, BPI, GCash, Maya…"
                            />
                            <InputError :message="form.errors.bank_name" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="account-name">Account name</Label>
                                <Input
                                    id="account-name"
                                    v-model="form.account_name"
                                    required
                                />
                                <InputError
                                    :message="form.errors.account_name"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="account-number"
                                    >Account number</Label
                                >
                                <Input
                                    id="account-number"
                                    v-model="form.account_number"
                                    required
                                    placeholder="Account or mobile no."
                                />
                                <InputError
                                    :message="form.errors.account_number"
                                />
                            </div>
                        </div>
                    </template>

                    <!-- International: in the order the receiving details are given (e.g. by Wise or Payoneer) -->
                    <template v-else>
                        <div class="grid gap-2">
                            <Label for="account-name">Name</Label>
                            <Input
                                id="account-name"
                                v-model="form.account_name"
                                required
                                placeholder="Name on the account"
                            />
                            <InputError :message="form.errors.account_name" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="account-number"
                                    >Account number</Label
                                >
                                <Input
                                    id="account-number"
                                    v-model="form.account_number"
                                    required
                                />
                                <InputError
                                    :message="form.errors.account_number"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="account-type">Account type</Label>
                                <Select v-model="form.account_type">
                                    <SelectTrigger
                                        id="account-type"
                                        class="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="(
                                                label, value
                                            ) in ACCOUNT_TYPES"
                                            :key="value"
                                            :value="value"
                                            >{{ label }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.account_type"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="account-routing">Routing number</Label>
                            <Input
                                id="account-routing"
                                v-model="form.routing_number"
                                inputmode="numeric"
                                placeholder="e.g. 026073150"
                            />
                            <InputError :message="form.errors.routing_number" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="account-bank">Bank name</Label>
                            <Input
                                id="account-bank"
                                v-model="form.bank_name"
                                required
                                placeholder="e.g. Community Federal Savings Bank"
                            />
                            <InputError :message="form.errors.bank_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="account-address">Bank address</Label>
                            <Textarea
                                id="account-address"
                                v-model="form.bank_address"
                                rows="2"
                            />
                            <InputError :message="form.errors.bank_address" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="account-swift"
                                >SWIFT / BIC
                                <span class="font-normal text-muted-foreground"
                                    >(optional, for wires from outside the
                                    US)</span
                                ></Label
                            >
                            <Input
                                id="account-swift"
                                v-model="form.swift_code"
                            />
                            <InputError :message="form.errors.swift_code" />
                        </div>
                    </template>

                    <div class="grid gap-2">
                        <Label for="account-instructions"
                            >Instructions for payers (optional)</Label
                        >
                        <Textarea
                            id="account-instructions"
                            v-model="form.instructions"
                            rows="3"
                            placeholder="e.g. Put your email in the transfer note."
                        />
                        <InputError :message="form.errors.instructions" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="account-qr">QR code (optional)</Label>
                        <p class="text-xs text-muted-foreground">
                            An InstaPay / QR Ph or wallet QR that payers can
                            scan.
                        </p>
                        <div class="flex items-center gap-3">
                            <div
                                v-if="
                                    qrPreview ||
                                    (editing?.has_qr && !form.remove_qr)
                                "
                                class="size-16 shrink-0 overflow-hidden rounded-md border border-border bg-white"
                            >
                                <img
                                    :src="qrPreview ?? qrUrl(editing!)"
                                    alt="QR code preview"
                                    class="size-full object-contain"
                                />
                            </div>
                            <QrCode
                                v-else
                                class="size-8 shrink-0 text-muted-foreground/50"
                            />
                            <input
                                id="account-qr"
                                ref="qrInput"
                                type="file"
                                accept="image/*"
                                class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs file:mr-3 file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none dark:bg-input/30"
                                @change="onQrChange"
                            />
                        </div>
                        <div v-if="form.qr" class="flex flex-wrap gap-2">
                            <Button
                                v-if="qrOriginal"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="qrCropOpen = true"
                            >
                                <Crop class="size-4" />
                                Crop
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="clearQrChoice"
                            >
                                Don't use this file
                            </Button>
                        </div>
                        <Label
                            v-if="editing?.has_qr && !form.qr"
                            for="account-remove-qr"
                            class="flex items-center gap-2 font-normal"
                        >
                            <Checkbox
                                id="account-remove-qr"
                                v-model="form.remove_qr"
                            />
                            Remove the current QR code
                        </Label>
                        <InputError :message="form.errors.qr" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 sm:items-end">
                        <Label
                            for="account-active"
                            class="flex items-start gap-2 font-normal"
                        >
                            <Checkbox
                                id="account-active"
                                v-model="form.is_active"
                                class="mt-0.5"
                            />
                            <span>
                                Show on the billing page
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >Untick to hide it without deleting.</span
                                >
                            </span>
                        </Label>
                        <div class="grid gap-2">
                            <Label for="account-order">Order</Label>
                            <Input
                                id="account-order"
                                v-model="form.sort_order"
                                type="number"
                                min="0"
                                max="999"
                            />
                            <InputError :message="form.errors.sort_order" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showForm = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="form.processing">
                            <Spinner v-if="form.processing" />
                            {{ editing ? 'Save' : 'Add account' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete -->
        <Dialog v-model:open="showDelete">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete {{ deleting?.bank_name }}?</DialogTitle>
                    <DialogDescription>
                        It disappears from the billing page. Past payments keep
                        the account's details as they were when paid. To stop it
                        showing for a while, edit it and untick "Show on the
                        billing page" instead.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleting = null"
                        >Cancel</Button
                    >
                    <Button variant="destructive" @click="confirmDelete">
                        <Trash2 class="size-4" /> Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
