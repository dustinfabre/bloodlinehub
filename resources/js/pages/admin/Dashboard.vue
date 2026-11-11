<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useToast } from '@/composables/useToast';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import { formatDate, formatMoney, isPremium } from '@/lib/billing';
import { index as inbox } from '@/routes/admin/inbox';
import { index as payments } from '@/routes/admin/payments';
import { index as users } from '@/routes/admin/users';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Copy,
    Crown,
    Inbox,
    Mail,
    Receipt,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface PendingPayment {
    id: number;
    payer_name: string;
    plan_name: string;
    currency: string;
    amount: string;
    created_at: string;
}

interface RecentUser {
    id: number;
    name: string;
    email: string;
    premium_until: string | null;
    created_at: string;
}

const props = defineProps<{
    stats: {
        users: number;
        users_7d: number;
        users_30d: number;
        premium: number;
        pending_payments: number;
        unread_feedback: number;
        // Approved in the last 30 days, per currency (an empty list when there were none)
        revenue_30d: Record<string, string> | [];
    };
    pendingPayments: PendingPayment[];
    recentUsers: RecentUser[];
    waitlist: string[];
}>();

const { success, error } = useToast();

const revenue = computed(() =>
    Object.entries(props.stats.revenue_30d ?? {}).map(([currency, total]) =>
        formatMoney(total, currency),
    ),
);

const cards = computed(() => [
    {
        title: 'Users',
        value: props.stats.users,
        note: `${props.stats.users_7d} this week · ${props.stats.users_30d} in 30 days`,
        icon: Users,
        href: users().url,
    },
    {
        title: 'Premium',
        value: props.stats.premium,
        note: revenue.value.length
            ? `${revenue.value.join(' + ')} approved in 30 days`
            : 'No payments approved in 30 days',
        icon: Crown,
        href: users({ query: { plan: 'premium' } }).url,
    },
    {
        title: 'Payments to review',
        value: props.stats.pending_payments,
        note: props.stats.pending_payments
            ? 'Check the transfer arrived, then approve'
            : 'Nothing waiting',
        icon: Receipt,
        href: payments().url,
        highlight: props.stats.pending_payments > 0,
    },
    {
        title: 'Unread feedback',
        value: props.stats.unread_feedback,
        note: props.stats.unread_feedback ? 'In the inbox' : 'All read',
        icon: Inbox,
        href: inbox({ query: { folder: 'unread' } }).url,
        highlight: props.stats.unread_feedback > 0,
    },
]);

const copyWaitlist = async () => {
    try {
        await navigator.clipboard.writeText(props.waitlist.join(', '));
        success(`Copied ${props.waitlist.length} emails.`);
    } catch {
        error('Could not copy. Select the emails below and copy them instead.');
    }
};
</script>

<template>
    <Head title="Admin" />

    <AdminLayout
        title="Overview"
        description="Sign-ups, Premium and what is waiting for you."
    >
        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="card in cards"
                    :key="card.title"
                    :href="card.href"
                    class="group rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <Card
                        :class="[
                            'h-full gap-2 py-5 transition-colors group-hover:bg-muted/40',
                            card.highlight ? 'border-amber-500/50' : '',
                        ]"
                    >
                        <CardHeader
                            class="flex flex-row items-center justify-between gap-2 px-5"
                        >
                            <CardTitle
                                class="text-sm font-medium text-muted-foreground"
                            >
                                {{ card.title }}
                            </CardTitle>
                            <component
                                :is="card.icon"
                                class="size-4 text-muted-foreground"
                            />
                        </CardHeader>
                        <CardContent class="px-5">
                            <p class="text-3xl font-bold tabular-nums">
                                {{ card.value }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ card.note }}
                            </p>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader
                        class="flex flex-row items-start justify-between gap-2"
                    >
                        <div>
                            <CardTitle>Payments to review</CardTitle>
                            <CardDescription>Oldest first</CardDescription>
                        </div>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="payments().url">
                                All <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="pendingPayments.length === 0"
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No payments waiting.
                        </p>
                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="payment in pendingPayments"
                                :key="payment.id"
                            >
                                <Link
                                    :href="payments().url"
                                    class="flex items-center justify-between gap-3 py-3 hover:text-foreground"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">
                                            {{ payment.payer_name }}
                                        </p>
                                        <p
                                            class="truncate text-sm text-muted-foreground"
                                        >
                                            {{ payment.plan_name }} · sent
                                            {{ formatDate(payment.created_at) }}
                                        </p>
                                    </div>
                                    <span
                                        class="shrink-0 font-semibold tabular-nums"
                                    >
                                        {{
                                            formatMoney(
                                                payment.amount,
                                                payment.currency,
                                            )
                                        }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-start justify-between gap-2"
                    >
                        <div>
                            <CardTitle>Recent sign-ups</CardTitle>
                            <CardDescription>Newest first</CardDescription>
                        </div>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="users().url">
                                All <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="recentUsers.length === 0"
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No accounts yet.
                        </p>
                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="user in recentUsers"
                                :key="user.id"
                                class="flex items-center justify-between gap-3 py-3"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <UserPlus
                                        class="size-4 shrink-0 text-muted-foreground"
                                    />
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">
                                            {{ user.name }}
                                        </p>
                                        <p
                                            class="truncate text-sm text-muted-foreground"
                                        >
                                            {{ user.email }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="flex shrink-0 flex-col items-end gap-1"
                                >
                                    <Badge
                                        v-if="isPremium(user.premium_until)"
                                        variant="secondary"
                                    >
                                        Premium
                                    </Badge>
                                    <span class="text-xs text-muted-foreground">
                                        {{ formatDate(user.created_at) }}
                                    </span>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <Mail class="size-4" /> Waitlist
                            <Badge variant="secondary" class="tabular-nums">{{
                                waitlist.length
                            }}</Badge>
                        </CardTitle>
                        <CardDescription class="mt-1">
                            People who asked for an invite before sign-up
                            opened. Let them know they can now create an
                            account.
                        </CardDescription>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        class="shrink-0"
                        :disabled="waitlist.length === 0"
                        @click="copyWaitlist"
                    >
                        <Copy class="size-4" /> Copy all emails
                    </Button>
                </CardHeader>
                <CardContent v-if="waitlist.length">
                    <p
                        class="max-h-40 overflow-y-auto rounded-md border border-border bg-muted/40 p-3 text-sm wrap-break-word text-muted-foreground select-all"
                    >
                        {{ waitlist.join(', ') }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
