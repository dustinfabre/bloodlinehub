<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Pager from '@/components/Pager.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import {
    durationLabel,
    formatDate,
    formatDateTime,
    formatMoney,
    PAYMENT_STATUS,
} from '@/lib/billing';
import { cn } from '@/lib/utils';
import { approve, index, reject } from '@/routes/admin/payments';
import { receipt } from '@/routes/billing/payments';
import type { Paginated, PaymentStatus } from '@/types/billing';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    Check,
    Download,
    FileText,
    Image as ImageIcon,
    Receipt,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface AdminPayment {
    id: number;
    user_id: number | null;
    payer_name: string;
    payer_email: string;
    plan_name: string;
    duration_months: number;
    paid_to: string;
    currency: string;
    amount: string;
    reference_number: string | null;
    paid_on: string | null;
    payer_note: string | null;
    receipt_mime: string;
    status: PaymentStatus;
    review_note: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    premium_until: string | null;
    created_at: string;
}

type StatusFilter = PaymentStatus | 'all';

const props = defineProps<{
    payments: Paginated<AdminPayment>;
    filters: { status: StatusFilter; search: string };
    // Payments per status (an empty list when there are none at all)
    counts: Partial<Record<PaymentStatus, number>> | [];
}>();

const { success, error } = useToast();

const count = (status: StatusFilter): number => {
    const counts = props.counts as Partial<Record<PaymentStatus, number>>;
    if (status === 'all') {
        return Object.values(counts).reduce(
            (sum, n) => sum + Number(n ?? 0),
            0,
        );
    }
    return Number(counts[status] ?? 0);
};

const tabs: { value: StatusFilter; label: string }[] = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'all', label: 'All' },
];

const query = (status: StatusFilter, search: string) => ({
    status,
    ...(search.trim() ? { search: search.trim() } : {}),
});

// Search
const search = ref(props.filters.search);

const applySearch = useDebounceFn(() => {
    if (search.value.trim() === props.filters.search) return;
    router.get(index().url, query(props.filters.status, search.value), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 350);

watch(search, applySearch);

// A date-only value ("2026-11-11") read as a local date, not UTC midnight
const formatDay = (value: string | null) =>
    value ? formatDate(`${value}T00:00:00`) : '';

const isPdf = (payment: AdminPayment) =>
    payment.receipt_mime === 'application/pdf';

// Receipt viewer
const viewing = ref<AdminPayment | null>(null);
const showReceipt = computed({
    get: () => viewing.value !== null,
    set: (open: boolean) => {
        if (!open) viewing.value = null;
    },
});

// Approve
const approving = ref<AdminPayment | null>(null);
const approveProcessing = ref(false);
const showApprove = computed({
    get: () => approving.value !== null,
    set: (open: boolean) => {
        if (!open) approving.value = null;
    },
});

const confirmApprove = () => {
    const payment = approving.value;
    if (!payment) return;

    router.post(
        approve(payment.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (approveProcessing.value = true),
            onFinish: () => (approveProcessing.value = false),
            onSuccess: () => {
                approving.value = null;
                success(`Approved. ${payment.payer_name} now has Premium.`);
            },
            onError: (errors) => {
                approving.value = null;
                error(errors.payment ?? 'Could not approve this payment.');
            },
        },
    );
};

// Reject
const rejecting = ref<AdminPayment | null>(null);
const rejectForm = useForm({ review_note: '' });
const showReject = computed({
    get: () => rejecting.value !== null,
    set: (open: boolean) => {
        if (!open) rejecting.value = null;
    },
});

const openReject = (payment: AdminPayment) => {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejecting.value = payment;
};

const confirmReject = () => {
    const payment = rejecting.value;
    if (!payment) return;

    rejectForm.post(reject(payment.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            rejecting.value = null;
            rejectForm.reset();
            success('Rejected. The payer will see your reason.');
        },
        onError: (errors) => {
            if (errors.payment) {
                rejecting.value = null;
                error(errors.payment);
            }
        },
    });
};

const emptyMessage = computed(() => {
    if (props.filters.search) return 'No payments match your search.';
    return {
        pending: 'No payments waiting for review.',
        approved: 'No approved payments yet.',
        rejected: 'No rejected payments.',
        all: 'No payments yet.',
    }[props.filters.status];
});
</script>

<template>
    <Head title="Payments · Admin" />

    <AdminLayout
        title="Payments"
        description="Bank transfers with their receipts. Check the money arrived in the account before you approve."
    >
        <div class="space-y-5">
            <div
                class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <nav class="flex flex-wrap gap-1.5" aria-label="Status">
                    <Link
                        v-for="tab in tabs"
                        :key="tab.value"
                        :href="
                            index({ query: query(tab.value, filters.search) })
                                .url
                        "
                        preserve-scroll
                        :aria-current="
                            filters.status === tab.value ? 'page' : undefined
                        "
                        :class="
                            cn(
                                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition-colors',
                                filters.status === tab.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'border-border text-muted-foreground hover:bg-muted hover:text-foreground',
                            )
                        "
                    >
                        {{ tab.label }}
                        <span class="tabular-nums opacity-70">{{
                            count(tab.value)
                        }}</span>
                    </Link>
                </nav>

                <div class="relative md:w-72">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        placeholder="Name, email or reference no."
                        aria-label="Search payments"
                        class="pl-9"
                    />
                </div>
            </div>

            <div
                v-if="payments.data.length === 0"
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border py-16 text-center"
            >
                <Receipt class="mb-3 size-10 text-muted-foreground/40" />
                <p class="text-muted-foreground">{{ emptyMessage }}</p>
            </div>

            <div v-else class="space-y-3">
                <Card
                    v-for="payment in payments.data"
                    :key="payment.id"
                    class="py-0"
                >
                    <CardContent class="p-4 sm:p-5">
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold">
                                        {{ payment.payer_name }}
                                    </p>
                                    <Badge
                                        variant="outline"
                                        :class="
                                            PAYMENT_STATUS[payment.status].class
                                        "
                                    >
                                        {{
                                            PAYMENT_STATUS[payment.status].label
                                        }}
                                    </Badge>
                                    <Badge
                                        v-if="payment.user_id === null"
                                        variant="outline"
                                        class="text-muted-foreground"
                                    >
                                        Account deleted
                                    </Badge>
                                </div>
                                <a
                                    :href="`mailto:${payment.payer_email}`"
                                    class="block truncate text-sm text-muted-foreground hover:text-foreground hover:underline"
                                    >{{ payment.payer_email }}</a
                                >
                            </div>
                            <div class="shrink-0 sm:text-right">
                                <p class="text-xl font-bold tabular-nums">
                                    {{
                                        formatMoney(
                                            payment.amount,
                                            payment.currency,
                                        )
                                    }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ payment.plan_name }} ·
                                    {{ durationLabel(payment.duration_months) }}
                                </p>
                            </div>
                        </div>

                        <dl
                            class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div class="min-w-0">
                                <dt class="text-muted-foreground">Paid to</dt>
                                <dd class="wrap-break-word">
                                    {{ payment.paid_to }}
                                </dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-muted-foreground">
                                    Reference no.
                                </dt>
                                <dd class="font-mono wrap-break-word">
                                    {{ payment.reference_number || '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Paid on</dt>
                                <dd>{{ formatDay(payment.paid_on) || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Sent</dt>
                                <dd>
                                    {{ formatDateTime(payment.created_at) }}
                                </dd>
                            </div>
                        </dl>

                        <p
                            v-if="payment.payer_note"
                            class="mt-3 rounded-md bg-muted/50 px-3 py-2 text-sm wrap-break-word whitespace-pre-wrap"
                        >
                            <span class="font-medium">Payer's note:</span>
                            {{ payment.payer_note }}
                        </p>

                        <p
                            v-if="payment.status !== 'pending'"
                            class="mt-3 text-sm text-muted-foreground"
                        >
                            {{
                                payment.status === 'approved'
                                    ? 'Approved'
                                    : 'Rejected'
                            }}
                            <template v-if="payment.reviewer"
                                >by {{ payment.reviewer }}</template
                            >
                            <template v-if="payment.reviewed_at">
                                ·
                                {{
                                    formatDateTime(payment.reviewed_at)
                                }}</template
                            >
                            <template
                                v-if="
                                    payment.status === 'approved' &&
                                    payment.premium_until
                                "
                            >
                                · Premium until
                                {{ formatDate(payment.premium_until) }}
                            </template>
                        </p>
                        <p
                            v-if="
                                payment.status === 'rejected' &&
                                payment.review_note
                            "
                            class="mt-1 text-sm wrap-break-word whitespace-pre-wrap text-destructive"
                        >
                            Reason: {{ payment.review_note }}
                        </p>

                        <div
                            class="mt-4 flex flex-wrap gap-2 border-t border-border pt-4"
                        >
                            <Button
                                v-if="isPdf(payment)"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <a :href="receipt(payment.id).url" download>
                                    <FileText class="size-4" /> Download receipt
                                    (PDF)
                                </a>
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                size="sm"
                                @click="viewing = payment"
                            >
                                <ImageIcon class="size-4" /> View receipt
                            </Button>

                            <template v-if="payment.status === 'pending'">
                                <Button
                                    size="sm"
                                    class="sm:ml-auto"
                                    @click="approving = payment"
                                >
                                    <Check class="size-4" /> Approve
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openReject(payment)"
                                >
                                    <X class="size-4" /> Reject
                                </Button>
                            </template>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Pager :page="payments" noun="payments" />
        </div>

        <!-- Receipt -->
        <Dialog v-model:open="showReceipt">
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Receipt</DialogTitle>
                    <DialogDescription v-if="viewing">
                        {{ viewing.payer_name }} ·
                        {{ formatMoney(viewing.amount, viewing.currency) }}
                        <template v-if="viewing.reference_number">
                            · Ref. {{ viewing.reference_number }}</template
                        >
                    </DialogDescription>
                </DialogHeader>
                <div
                    v-if="viewing"
                    class="overflow-hidden rounded-md border border-border bg-muted/40"
                >
                    <img
                        :src="receipt(viewing.id).url"
                        :alt="`Receipt from ${viewing.payer_name}`"
                        class="mx-auto max-h-[70vh] w-auto max-w-full object-contain"
                    />
                </div>
                <DialogFooter v-if="viewing">
                    <Button variant="outline" as-child>
                        <a
                            :href="receipt(viewing.id).url"
                            target="_blank"
                            rel="noopener"
                        >
                            <Download class="size-4" /> Open full size
                        </a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Approve -->
        <Dialog v-model:open="showApprove">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Approve this payment?</DialogTitle>
                    <DialogDescription v-if="approving">
                        Premium for
                        {{ durationLabel(approving.duration_months) }} will be
                        added to {{ approving.payer_name }}, starting today or
                        when their current Premium ends.
                    </DialogDescription>
                </DialogHeader>
                <p
                    v-if="approving"
                    class="rounded-md bg-muted/50 px-3 py-2 text-sm"
                >
                    Only approve once
                    <strong>{{
                        formatMoney(approving.amount, approving.currency)
                    }}</strong>
                    has arrived in {{ approving.paid_to }}.
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="approving = null"
                        >Cancel</Button
                    >
                    <Button
                        :disabled="approveProcessing"
                        @click="confirmApprove"
                    >
                        <Spinner v-if="approveProcessing" />
                        Approve
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Reject -->
        <Dialog v-model:open="showReject">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reject this payment</DialogTitle>
                    <DialogDescription v-if="rejecting">
                        {{ rejecting.payer_name }} will see your reason on their
                        billing page and by email.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="confirmReject">
                    <div class="grid gap-2">
                        <Label for="review-note">Reason</Label>
                        <Textarea
                            id="review-note"
                            v-model="rejectForm.review_note"
                            rows="3"
                            required
                            placeholder="e.g. No transfer with this reference arrived"
                        />
                        <InputError :message="rejectForm.errors.review_note" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="rejecting = null"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="rejectForm.processing"
                        >
                            <Spinner v-if="rejectForm.processing" />
                            Reject payment
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
