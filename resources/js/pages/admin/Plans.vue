<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { useToast } from '@/composables/useToast';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import { durationLabel, formatMoney, perPeriod } from '@/lib/billing';
import { FREE_FEATURES, PREMIUM_FEATURES } from '@/lib/premium';
import { update } from '@/routes/admin/plans';
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Crown } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    premium: {
        // Null until a price is set (Premium isn't on sale before then)
        price_php: string | null;
        price_usd: string | null;
        duration_months: number;
        trial_days: number;
        on_sale: boolean;
        payments_count: number;
    };
    durations: number[];
}>();

const { success } = useToast();

const form = useForm({
    price_php: props.premium.price_php
        ? String(Number(props.premium.price_php))
        : '',
    price_usd: props.premium.price_usd
        ? String(Number(props.premium.price_usd))
        : '',
    duration_months: String(props.premium.duration_months),
    trial_days: String(props.premium.trial_days),
});

const period = computed(() => perPeriod(Number(form.duration_months)));

const submit = () => {
    form.transform((data) => ({
        ...data,
        duration_months: Number(data.duration_months),
        trial_days: Number(data.trial_days || 0),
    })).patch(update().url, {
        preserveScroll: true,
        onSuccess: () => success('Premium updated.'),
    });
};
</script>

<template>
    <Head title="Plans & prices" />

    <AdminLayout
        title="Plans & prices"
        description="Free and Premium. Set Premium's price and free trial; a change applies to new payments only."
    >
        <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
            <!-- Free: nothing to set -->
            <Card class="py-0">
                <CardContent class="space-y-5 p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">Free</h2>
                            <p class="text-sm text-muted-foreground">
                                Every account, always.
                            </p>
                        </div>
                        <p class="text-2xl font-bold tabular-nums">₱0</p>
                    </div>
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
                </CardContent>
            </Card>

            <!-- Premium: prices, period and trial -->
            <Card class="py-0">
                <CardContent class="space-y-6 p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2
                                class="flex items-center gap-2 text-lg font-semibold"
                            >
                                <Crown
                                    class="size-5 text-amber-600 dark:text-amber-300"
                                />
                                Premium
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Everything in Free, plus the Premium features.
                            </p>
                        </div>
                        <Badge
                            :variant="premium.on_sale ? 'secondary' : 'outline'"
                            class="shrink-0"
                        >
                            {{
                                premium.on_sale ? 'On sale' : 'Not on sale yet'
                            }}
                        </Badge>
                    </div>

                    <form class="grid gap-5" @submit.prevent="submit">
                        <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                            <div class="grid gap-2">
                                <Label for="price_php"
                                    >Price in pesos (₱)</Label
                                >
                                <Input
                                    id="price_php"
                                    v-model="form.price_php"
                                    type="number"
                                    inputmode="decimal"
                                    min="1"
                                    step="0.01"
                                    required
                                    placeholder="e.g. 1499"
                                />
                                <InputError :message="form.errors.price_php" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="duration">Per</Label>
                                <Select v-model="form.duration_months">
                                    <SelectTrigger
                                        id="duration"
                                        class="w-full sm:w-36"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="months in durations"
                                            :key="months"
                                            :value="String(months)"
                                            >{{
                                                durationLabel(months)
                                            }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.duration_months"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="price_usd">
                                Price in dollars (US$)
                                <span class="font-normal text-muted-foreground"
                                    >for payers abroad</span
                                >
                            </Label>
                            <Input
                                id="price_usd"
                                v-model="form.price_usd"
                                type="number"
                                inputmode="decimal"
                                min="1"
                                step="0.01"
                                placeholder="e.g. 29.99"
                            />
                            <p class="text-xs text-muted-foreground">
                                Leave blank and payers abroad can't buy Premium.
                            </p>
                            <InputError :message="form.errors.price_usd" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="trial_days">Free trial (days)</Label>
                            <Input
                                id="trial_days"
                                v-model="form.trial_days"
                                type="number"
                                inputmode="numeric"
                                min="0"
                                max="365"
                                class="w-32"
                            />
                            <p class="text-xs text-muted-foreground">
                                Full Premium, free. New accounts start it when
                                they sign up; accounts that never had one can
                                start it from Plan &amp; billing. Once per
                                account. 0 turns the trial off.
                            </p>
                            <InputError :message="form.errors.trial_days" />
                        </div>

                        <div
                            class="flex flex-col gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <p class="text-sm text-muted-foreground">
                                <template v-if="form.price_php">
                                    {{ formatMoney(form.price_php, 'PHP') }} /
                                    {{ period
                                    }}<template v-if="form.price_usd">
                                        ·
                                        {{ formatMoney(form.price_usd, 'USD') }}
                                        / {{ period }}</template
                                    >
                                </template>
                                <template v-else
                                    >Set a peso price to put Premium on
                                    sale.</template
                                >
                            </p>
                            <Button
                                type="submit"
                                class="shrink-0"
                                :disabled="form.processing"
                            >
                                <Spinner v-if="form.processing" />
                                Save
                            </Button>
                        </div>
                    </form>

                    <div class="border-t border-border pt-5">
                        <p class="mb-2 text-sm font-semibold">Premium adds</p>
                        <ul class="space-y-1.5 text-sm text-muted-foreground">
                            <li
                                v-for="item in PREMIUM_FEATURES"
                                :key="item.key"
                                class="flex gap-2"
                            >
                                <Crown
                                    class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300"
                                />
                                {{ item.title }}
                            </li>
                        </ul>
                        <p
                            v-if="premium.payments_count"
                            class="mt-4 text-xs text-muted-foreground"
                        >
                            {{ premium.payments_count }}
                            {{
                                premium.payments_count === 1
                                    ? 'payment'
                                    : 'payments'
                            }}
                            so far. Each keeps the price and period it was paid
                            at.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
