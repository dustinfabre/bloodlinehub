<script setup lang="ts">
import { daysLeft, isPremium } from '@/lib/billing';
import { index as billing } from '@/routes/billing';
import type { AppPageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Crown } from 'lucide-vue-next';
import { computed } from 'vue';

// A reminder on every app page when the free trial has 3 days or less left and nothing has been paid
const page = usePage<AppPageProps>();

const left = computed(() => {
    const user = page.props.auth?.user;
    if (
        !user ||
        isPremium(user.premium_until) ||
        !isPremium(user.trial_ends_at)
    )
        return null;
    const days = daysLeft(user.trial_ends_at);
    return days <= 3 ? days : null;
});

const onBilling = computed(() => page.url.startsWith(billing().url));
</script>

<template>
    <aside
        v-if="left !== null && !onBilling"
        class="mx-4 mt-4 flex flex-col gap-2 rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-sm sm:mx-6 sm:flex-row sm:items-center sm:justify-between"
        aria-label="Free trial"
    >
        <p class="flex items-start gap-2">
            <Crown
                class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300"
                aria-hidden="true"
            />
            <span>
                <span class="font-medium"
                    >Your free trial ends
                    {{ left <= 1 ? 'within a day' : `in ${left} days` }}.</span
                >
                Premium features lock when it does; your records stay.
            </span>
        </p>
        <Link
            :href="billing()"
            class="shrink-0 font-medium text-foreground underline underline-offset-4"
            >Keep Premium</Link
        >
    </aside>
</template>
