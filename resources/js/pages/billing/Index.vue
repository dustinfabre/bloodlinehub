<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    PAYMENT_STATUS,
    durationLabel,
    formatDate,
    formatMoney,
} from '@/lib/billing';
import { shrinkImage } from '@/lib/images';
import { FREE_FEATURES, PREMIUM_FEATURES, usePremium } from '@/lib/premium';
import { cn } from '@/lib/utils';
import { index as billingIndex } from '@/routes/billing';
import { qr } from '@/routes/billing/accounts';
import { receipt, store } from '@/routes/billing/payments';
import { create as feedback } from '@/routes/feedback';
import type { BreadcrumbItem } from '@/types';
import type {
    PayerAccount,
    PaymentRegion,
    PaymentStatus,
    Plan,
} from '@/types/billing';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    Crown,
    FileText,
    Globe,
    Landmark,
    QrCode,
    Upload,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface PaymentRow {
    id: number;
    plan_name: string;
    currency: string;
    amount: string;
    reference_number: string | null;
    paid_on: string | null;
    paid_to: string;
    status: PaymentStatus;
    review_note: string | null;
    premium_until: string | null;
    created_at: string;
}

const props = defineProps<{
    premium: { active: boolean; until: string | null };
    plans: Plan[];
    accounts: PayerAccount[];
    payments: PaymentRow[];
    maxPending: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Plan & billing', href: billingIndex().url },
];

const { success, error } = useToast();
const { featureUrl } = usePremium();

const hasLocal = computed(() =>
    props.accounts.some((account) => account.region === 'local'),
);
const hasInternational = computed(() =>
    props.accounts.some((account) => account.region === 'international'),
);
const canPay = computed(
    () => props.plans.length > 0 && props.accounts.length > 0,
);
const pendingCount = computed(
    () =>
        props.payments.filter((payment) => payment.status === 'pending').length,
);

const region = ref<PaymentRegion>(hasLocal.value ? 'local' : 'international');
const planId = ref<number | null>(props.plans[0]?.id ?? null);

const plan = computed(
    () => props.plans.find((p) => p.id === planId.value) ?? null,
);
const currency = computed(() => (region.value === 'local' ? 'PHP' : 'USD'));
const regionAccounts = computed(() =>
    props.accounts.filter((account) => account.region === region.value),
);
const priceFor = (p: Plan) =>
    region.value === 'local' ? p.price_php : p.price_usd;
const price = computed(() => (plan.value ? priceFor(plan.value) : null));

const form = useForm<{
    plan_id: number | null;
    payment_account_id: number | null;
    amount: string;
    reference_number: string;
    paid_on: string;
    payer_note: string;
    receipt: File | null;
}>({
    plan_id: planId.value,
    payment_account_id: null,
    amount: '',
    reference_number: '',
    paid_on: new Date().toLocaleDateString('en-CA'),
    payer_note: '',
    receipt: null,
});

// Keep the form in step with the plan and region picked above: the amount is the plan's price in that currency,
// and the account defaults to the only one when there is just one
watch(
    [planId, region],
    () => {
        // A plan not sold in this currency can't stay picked
        if (!plan.value || !priceFor(plan.value)) {
            planId.value = props.plans.find((p) => priceFor(p))?.id ?? null;
        }
        form.plan_id = planId.value;
        form.amount = price.value ? String(Number(price.value)) : '';
        if (
            !regionAccounts.value.some((a) => a.id === form.payment_account_id)
        ) {
            form.payment_account_id =
                regionAccounts.value.length === 1
                    ? regionAccounts.value[0].id
                    : null;
        }
    },
    { immediate: true },
);

const copied = ref<number | null>(null);
const copyNumber = async (account: PayerAccount) => {
    try {
        await navigator.clipboard.writeText(
            account.account_number.replace(/\s+/g, ''),
        );
        copied.value = account.id;
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        error('Could not copy. Select the number and copy it instead.');
    }
};

const qrAccount = ref<PayerAccount | null>(null);
const qrOpen = computed({
    get: () => qrAccount.value !== null,
    set: (open: boolean) => {
        if (!open) qrAccount.value = null;
    },
});

const receiptPreview = ref<string | null>(null);
const preparing = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const pickReceipt = async (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    preparing.value = true;
    try {
        form.receipt = await shrinkImage(file);
    } finally {
        preparing.value = false;
    }

    if (receiptPreview.value) URL.revokeObjectURL(receiptPreview.value);
    receiptPreview.value = form.receipt.type.startsWith('image/')
        ? URL.createObjectURL(form.receipt)
        : null;
    form.clearErrors('receipt');
};

const clearReceipt = () => {
    form.receipt = null;
    if (receiptPreview.value) URL.revokeObjectURL(receiptPreview.value);
    receiptPreview.value = null;
    if (fileInput.value) fileInput.value.value = '';
};

const submit = () => {
    form.post(store().url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('reference_number', 'payer_note');
            clearReceipt();
            success(
                "Receipt sent. We'll check your payment and switch on Premium.",
            );
        },
    });
};
</script>

<template>
    <Head title="Plan & billing" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-8 p-4 sm:p-6">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">
                    Plan & billing
                </h1>
                <p class="mt-1 text-muted-foreground">
                    Pay for Premium by bank transfer or e-wallet, then upload
                    your receipt here.
                </p>
            </div>

            <!-- Current plan -->
            <Card>
                <CardContent
                    class="flex flex-col gap-4 sm:flex-row sm:items-center"
                >
                    <div
                        :class="
                            cn(
                                'flex size-12 shrink-0 items-center justify-center rounded-full',
                                premium.active
                                    ? 'bg-amber-500/15 text-amber-600 dark:text-amber-300'
                                    : 'bg-muted text-muted-foreground',
                            )
                        "
                    >
                        <Crown class="size-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-lg font-semibold">
                            {{ premium.active ? 'Premium' : 'Free plan' }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="premium.active">
                                Active until {{ formatDate(premium.until) }}.
                                Paying again adds the new period after this
                                date.
                            </template>
                            <template v-else-if="premium.until">
                                Your Premium ended on
                                {{ formatDate(premium.until) }}.
                            </template>
                            <template v-else>
                                You're on the free plan.
                            </template>
                        </p>
                    </div>
                    <Badge
                        v-if="pendingCount"
                        variant="outline"
                        :class="PAYMENT_STATUS.pending.class"
                    >
                        {{ pendingCount }} waiting for review
                    </Badge>
                </CardContent>
            </Card>

            <!-- What Premium adds over the free plan -->
            <section
                class="grid gap-4 rounded-xl border border-border bg-card p-5 sm:grid-cols-2 sm:p-6"
                aria-labelledby="plans-compared"
            >
                <h2 id="plans-compared" class="sr-only">
                    Free and Premium compared
                </h2>
                <div>
                    <p class="mb-2 text-sm font-semibold">Free, always</p>
                    <ul class="space-y-1.5 text-sm text-muted-foreground">
                        <li
                            v-for="item in FREE_FEATURES"
                            :key="item"
                            class="flex gap-2"
                        >
                            <Check class="mt-0.5 size-4 shrink-0" />
                            {{ item }}
                        </li>
                    </ul>
                </div>
                <div>
                    <p class="mb-2 text-sm font-semibold">Premium adds</p>
                    <ul class="space-y-1.5 text-sm">
                        <li
                            v-for="item in PREMIUM_FEATURES"
                            :key="item.key"
                            class="flex gap-2"
                        >
                            <Crown
                                class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300"
                            />
                            <Link
                                :href="featureUrl(item.key)"
                                class="underline-offset-4 hover:underline"
                                >{{ item.title }}</Link
                            >
                        </li>
                    </ul>
                </div>
            </section>

            <Card v-if="!canPay">
                <CardContent class="py-10 text-center">
                    <p class="font-medium">Premium isn't on sale yet.</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Check back soon, or
                        <Link
                            :href="feedback()"
                            class="font-medium text-foreground underline underline-offset-4"
                            >send us a message</Link
                        >.
                    </p>
                </CardContent>
            </Card>

            <template v-else>
                <!-- 1. Plan -->
                <section class="space-y-3" aria-labelledby="step-plan">
                    <h2 id="step-plan" class="text-lg font-semibold">
                        1. Choose a plan
                    </h2>

                    <div
                        v-if="hasLocal && hasInternational"
                        class="inline-flex rounded-lg border border-border bg-muted/50 p-1"
                        role="radiogroup"
                        aria-label="Where are you paying from?"
                    >
                        <button
                            v-for="option in [
                                {
                                    value: 'local',
                                    label: 'Philippines (₱)',
                                    icon: Landmark,
                                },
                                {
                                    value: 'international',
                                    label: 'Abroad (US$)',
                                    icon: Globe,
                                },
                            ] as const"
                            :key="option.value"
                            type="button"
                            role="radio"
                            :aria-checked="region === option.value"
                            :class="
                                cn(
                                    'inline-flex min-h-9 items-center gap-2 rounded-md px-3 text-sm font-medium transition-colors',
                                    region === option.value
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )
                            "
                            @click="region = option.value"
                        >
                            <component :is="option.icon" class="size-4" />
                            {{ option.label }}
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="p in plans"
                            :key="p.id"
                            :class="
                                cn(
                                    'relative flex cursor-pointer flex-col gap-1 rounded-xl border bg-card p-4 transition-colors has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50',
                                    planId === p.id
                                        ? 'border-primary ring-1 ring-primary'
                                        : 'border-border hover:border-foreground/30',
                                    !priceFor(p) &&
                                        'cursor-not-allowed opacity-50',
                                )
                            "
                        >
                            <input
                                v-model="planId"
                                type="radio"
                                name="plan"
                                class="sr-only"
                                :value="p.id"
                                :disabled="!priceFor(p)"
                            />
                            <span
                                class="flex items-start justify-between gap-2"
                            >
                                <span class="font-semibold">{{ p.name }}</span>
                                <Check
                                    v-if="planId === p.id"
                                    class="size-5 shrink-0 text-primary"
                                />
                            </span>
                            <span class="text-2xl font-bold tabular-nums">
                                {{
                                    priceFor(p)
                                        ? formatMoney(priceFor(p), currency)
                                        : 'Not available abroad'
                                }}
                            </span>
                            <span class="text-sm text-muted-foreground">
                                {{ durationLabel(p.duration_months) }} of
                                Premium<template v-if="p.description">
                                    · {{ p.description }}</template
                                >
                            </span>
                        </label>
                    </div>
                </section>

                <!-- 2. Send the money -->
                <section class="space-y-3" aria-labelledby="step-send">
                    <h2 id="step-send" class="text-lg font-semibold">
                        2. Send
                        <span v-if="price" class="tabular-nums">{{
                            formatMoney(price, currency)
                        }}</span>
                        to one of these accounts
                    </h2>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <Card
                            v-for="account in regionAccounts"
                            :key="account.id"
                            class="gap-3 py-4"
                        >
                            <CardHeader class="px-4">
                                <CardTitle
                                    class="flex items-center gap-2 text-base"
                                >
                                    <Landmark
                                        class="size-4 text-muted-foreground"
                                    />
                                    {{ account.bank_name }}
                                </CardTitle>
                                <CardDescription>{{
                                    account.account_name
                                }}</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-3 px-4">
                                <div
                                    class="flex items-center justify-between gap-2 rounded-lg bg-muted px-3 py-2"
                                >
                                    <span
                                        class="min-w-0 font-mono text-[15px] font-semibold tracking-wide break-all"
                                        >{{ account.account_number }}</span
                                    >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="shrink-0"
                                        :aria-label="`Copy ${account.bank_name} account number`"
                                        @click="copyNumber(account)"
                                    >
                                        <Check
                                            v-if="copied === account.id"
                                            class="size-4"
                                        />
                                        <Copy v-else class="size-4" />
                                        {{
                                            copied === account.id
                                                ? 'Copied'
                                                : 'Copy'
                                        }}
                                    </Button>
                                </div>
                                <dl
                                    v-if="
                                        account.swift_code ||
                                        account.bank_address
                                    "
                                    class="grid gap-1 text-sm"
                                >
                                    <div
                                        v-if="account.swift_code"
                                        class="flex gap-2"
                                    >
                                        <dt class="text-muted-foreground">
                                            SWIFT/BIC
                                        </dt>
                                        <dd class="font-mono font-medium">
                                            {{ account.swift_code }}
                                        </dd>
                                    </div>
                                    <div
                                        v-if="account.bank_address"
                                        class="flex gap-2"
                                    >
                                        <dt
                                            class="shrink-0 text-muted-foreground"
                                        >
                                            Bank address
                                        </dt>
                                        <dd>{{ account.bank_address }}</dd>
                                    </div>
                                </dl>
                                <p
                                    v-if="account.instructions"
                                    class="text-sm whitespace-pre-line text-muted-foreground"
                                >
                                    {{ account.instructions }}
                                </p>
                                <Button
                                    v-if="account.has_qr"
                                    variant="outline"
                                    size="sm"
                                    @click="qrAccount = account"
                                >
                                    <QrCode class="size-4" />
                                    Show QR code
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                    <p
                        v-if="!regionAccounts.length"
                        class="text-sm text-muted-foreground"
                    >
                        No accounts for this region yet.
                    </p>
                </section>

                <!-- 3. Receipt -->
                <section class="space-y-3" aria-labelledby="step-receipt">
                    <h2 id="step-receipt" class="text-lg font-semibold">
                        3. Upload your receipt
                    </h2>

                    <Card>
                        <CardContent>
                            <form class="grid gap-5" @submit.prevent="submit">
                                <InputError :message="form.errors.plan_id" />

                                <div class="grid gap-2">
                                    <Label>Account you paid to</Label>
                                    <div class="flex flex-wrap gap-2">
                                        <label
                                            v-for="account in regionAccounts"
                                            :key="account.id"
                                            :class="
                                                cn(
                                                    'inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm font-medium has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50',
                                                    form.payment_account_id ===
                                                        account.id
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-border hover:border-foreground/30',
                                                )
                                            "
                                        >
                                            <input
                                                v-model="
                                                    form.payment_account_id
                                                "
                                                type="radio"
                                                name="payment_account_id"
                                                class="sr-only"
                                                :value="account.id"
                                            />
                                            {{ account.bank_name }}
                                        </label>
                                    </div>
                                    <InputError
                                        :message="
                                            form.errors.payment_account_id
                                        "
                                    />
                                </div>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="amount"
                                            >Amount sent ({{ currency }})</Label
                                        >
                                        <Input
                                            id="amount"
                                            v-model="form.amount"
                                            type="number"
                                            inputmode="decimal"
                                            min="1"
                                            step="0.01"
                                            required
                                        />
                                        <InputError
                                            :message="form.errors.amount"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="paid_on"
                                            >Date you paid</Label
                                        >
                                        <Input
                                            id="paid_on"
                                            v-model="form.paid_on"
                                            type="date"
                                            :max="
                                                new Date().toLocaleDateString(
                                                    'en-CA',
                                                )
                                            "
                                            required
                                        />
                                        <InputError
                                            :message="form.errors.paid_on"
                                        />
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="reference_number">
                                        Reference number
                                        <span
                                            class="font-normal text-muted-foreground"
                                            >(if your receipt has one)</span
                                        >
                                    </Label>
                                    <Input
                                        id="reference_number"
                                        v-model="form.reference_number"
                                        autocomplete="off"
                                        placeholder="e.g. 1234 567 890123"
                                    />
                                    <InputError
                                        :message="form.errors.reference_number"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="receipt">Receipt</Label>
                                    <p class="text-sm text-muted-foreground">
                                        A screenshot or photo of the
                                        confirmation, or the PDF from your
                                        online banking.
                                    </p>
                                    <div
                                        v-if="form.receipt"
                                        class="flex items-center gap-3 rounded-lg border border-border p-3"
                                    >
                                        <img
                                            v-if="receiptPreview"
                                            :src="receiptPreview"
                                            alt="Receipt preview"
                                            class="size-16 rounded object-cover"
                                        />
                                        <FileText
                                            v-else
                                            class="size-8 text-muted-foreground"
                                        />
                                        <span
                                            class="min-w-0 flex-1 truncate text-sm"
                                            >{{ form.receipt.name }}</span
                                        >
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="clearReceipt"
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                    <label
                                        v-else
                                        for="receipt"
                                        class="flex min-h-24 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-border text-sm text-muted-foreground transition-colors hover:border-foreground/30 hover:text-foreground has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                                    >
                                        <Spinner
                                            v-if="preparing"
                                            class="size-5"
                                        />
                                        <Upload v-else class="size-5" />
                                        {{
                                            preparing
                                                ? 'Preparing…'
                                                : 'Choose a file'
                                        }}
                                    </label>
                                    <input
                                        id="receipt"
                                        ref="fileInput"
                                        type="file"
                                        accept="image/*,application/pdf"
                                        class="sr-only"
                                        @change="pickReceipt"
                                    />
                                    <InputError
                                        :message="form.errors.receipt"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="payer_note">
                                        Note
                                        <span
                                            class="font-normal text-muted-foreground"
                                            >(optional)</span
                                        >
                                    </Label>
                                    <Textarea
                                        id="payer_note"
                                        v-model="form.payer_note"
                                        rows="2"
                                        placeholder="e.g. Sent from my wife's GCash, Maria Santos"
                                    />
                                    <InputError
                                        :message="form.errors.payer_note"
                                    />
                                </div>

                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <p class="text-sm text-muted-foreground">
                                        We check payments by hand, usually
                                        within a day. Premium starts when we
                                        approve it.
                                    </p>
                                    <Button
                                        type="submit"
                                        class="shrink-0"
                                        :disabled="
                                            form.processing ||
                                            preparing ||
                                            !form.receipt ||
                                            pendingCount >= maxPending
                                        "
                                    >
                                        <Spinner v-if="form.processing" />
                                        Send receipt
                                    </Button>
                                </div>
                                <p
                                    v-if="pendingCount >= maxPending"
                                    class="text-sm text-muted-foreground"
                                >
                                    You have {{ pendingCount }} payments waiting
                                    for review. You can send another once we've
                                    checked them.
                                </p>
                            </form>
                        </CardContent>
                    </Card>
                </section>
            </template>

            <!-- History -->
            <section
                v-if="payments.length"
                class="space-y-3"
                aria-labelledby="history"
            >
                <h2 id="history" class="text-lg font-semibold">
                    Your payments
                </h2>
                <ul
                    class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
                >
                    <li
                        v-for="payment in payments"
                        :key="payment.id"
                        class="flex flex-col gap-2 p-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="min-w-0 space-y-1">
                            <p class="font-medium">
                                {{ payment.plan_name }} ·
                                <span class="tabular-nums">{{
                                    formatMoney(
                                        payment.amount,
                                        payment.currency,
                                    )
                                }}</span>
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Paid {{ formatDate(payment.paid_on) }} to
                                {{ payment.paid_to
                                }}<template v-if="payment.reference_number">
                                    · Ref.
                                    {{ payment.reference_number }}</template
                                >
                            </p>
                            <p
                                v-if="
                                    payment.status === 'approved' &&
                                    payment.premium_until
                                "
                                class="text-sm text-muted-foreground"
                            >
                                Premium until
                                {{ formatDate(payment.premium_until) }}
                            </p>
                            <p
                                v-if="
                                    payment.status === 'rejected' &&
                                    payment.review_note
                                "
                                class="text-sm text-destructive"
                            >
                                {{ payment.review_note }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <a
                                :href="receipt(payment.id).url"
                                target="_blank"
                                rel="noopener"
                                class="text-sm font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                >Receipt</a
                            >
                            <Badge
                                variant="outline"
                                :class="PAYMENT_STATUS[payment.status].class"
                            >
                                {{ PAYMENT_STATUS[payment.status].label }}
                            </Badge>
                        </div>
                    </li>
                </ul>
                <p class="text-sm text-muted-foreground">
                    A problem with a payment?
                    <Link
                        :href="feedback()"
                        class="font-medium text-foreground underline underline-offset-4"
                        >Send us a message</Link
                    >.
                </p>
            </section>
        </div>

        <Dialog v-model:open="qrOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle
                        >{{ qrAccount?.bank_name }} QR code</DialogTitle
                    >
                    <DialogDescription>
                        Scan it with your banking or e-wallet app.
                    </DialogDescription>
                </DialogHeader>
                <img
                    v-if="qrAccount"
                    :src="
                        qr(qrAccount.id, {
                            query: { v: qrAccount.qr_version ?? 0 },
                        }).url
                    "
                    :alt="`${qrAccount.bank_name} QR code`"
                    class="mx-auto w-full max-w-72 rounded-lg bg-white"
                />
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
