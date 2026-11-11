<script setup lang="ts">
import { formatMoney, perPeriod } from '@/lib/billing';
import { PREMIUM_FEATURES } from '@/lib/premium';
import { register } from '@/routes';
import { index as billing } from '@/routes/billing';
import type { Pricing } from '@/types/billing';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Check, Crown } from 'lucide-vue-next';
import { computed } from 'vue';

// Free and Premium side by side, with Premium's price as set in /admin/plans (landing page and /pricing)
const props = defineProps<{
    pricing: Pricing;
    // Where payers can send money, e.g. ["BDO", "GCash", "PayPal"]
    paymentMethods?: string[];
}>();

const page = usePage();
const signedIn = computed(() => !!page.props.auth?.user);
const period = computed(() => perPeriod(props.pricing.duration_months));
const trial = computed(() => props.pricing.trial_days);

const freeFeatures = [
    'Unlimited pigeons, with photos saved to your own Google Drive',
    'Unlimited pairings, clutches and fostering',
    'Printable 5-generation pedigrees',
    'Lineage: offspring, siblings and Golden Pairs',
    'Locations, clubs, one-loft races and sales',
];

const methods = computed(() => {
    const list = props.paymentMethods ?? [];
    if (list.length <= 1) return list.join('');
    return `${list.slice(0, -1).join(', ')} or ${list.at(-1)}`;
});
</script>

<template>
    <div class="grid gap-5 lg:grid-cols-2">
        <!-- Free -->
        <article
            class="flex flex-col rounded-lg border border-border bg-card p-6 sm:p-8"
            aria-labelledby="plan-free"
        >
            <h3
                id="plan-free"
                class="font-display text-2xl font-semibold text-navy"
            >
                Free
            </h3>
            <p class="mt-1 text-[15px] text-muted-foreground">
                Everything you need to run your loft.
            </p>
            <p class="mt-6 flex items-baseline gap-2">
                <span
                    class="font-display text-5xl font-semibold text-navy tabular-nums"
                    >₱0</span
                >
                <span class="text-muted-foreground">forever</span>
            </p>
            <ul class="mt-8 flex-1 space-y-3">
                <li
                    v-for="item in freeFeatures"
                    :key="item"
                    class="flex gap-3 text-[15px] leading-relaxed text-navy"
                >
                    <Check
                        class="mt-1 size-4 shrink-0 text-gold"
                        aria-hidden="true"
                    />
                    {{ item }}
                </li>
            </ul>
            <Link
                v-if="!signedIn"
                :href="register()"
                class="mt-8 inline-flex min-h-11 items-center justify-center rounded-md border border-navy px-5 text-[15px] font-semibold text-navy transition-colors duration-150 hover:bg-navy hover:text-ivory focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy"
            >
                Create a free account
            </Link>
        </article>

        <!-- Premium -->
        <article
            class="flex flex-col rounded-lg border border-gold/40 bg-navy p-6 text-ivory sm:p-8"
            aria-labelledby="plan-premium"
        >
            <div class="flex items-start justify-between gap-3">
                <h3
                    id="plan-premium"
                    class="flex items-center gap-2 font-display text-2xl font-semibold"
                >
                    <Crown class="size-5 text-gold" aria-hidden="true" />
                    Premium
                </h3>
                <span
                    v-if="trial > 0"
                    class="rounded-full border border-gold px-3 py-1 text-xs font-semibold tracking-wide text-gold uppercase"
                    >{{ trial }} days free</span
                >
            </div>
            <p class="mt-1 text-[15px] text-ivory/70">
                For breeders who pair, sell and share.
            </p>

            <div class="mt-6">
                <p
                    v-if="pricing.on_sale && pricing.price_php"
                    class="flex flex-wrap items-baseline gap-x-2"
                >
                    <span
                        class="font-display text-5xl font-semibold tabular-nums"
                        >{{ formatMoney(pricing.price_php, 'PHP') }}</span
                    >
                    <span class="text-ivory/70">per {{ period }}</span>
                </p>
                <p v-else class="font-display text-3xl font-semibold">
                    Price coming soon
                </p>
                <p
                    v-if="pricing.on_sale && pricing.price_usd"
                    class="mt-1 text-sm text-ivory/60"
                >
                    Abroad:
                    {{ formatMoney(pricing.price_usd, 'USD') }} per
                    {{ period }}
                </p>
            </div>

            <p class="mt-8 text-sm font-semibold text-ivory/80">
                Everything in Free, plus:
            </p>
            <ul class="mt-3 flex-1 space-y-3">
                <li
                    v-for="item in PREMIUM_FEATURES"
                    :key="item.key"
                    class="flex gap-3 text-[15px] leading-relaxed"
                >
                    <Check
                        class="mt-1 size-4 shrink-0 text-gold"
                        aria-hidden="true"
                    />
                    {{ item.title }}
                </li>
            </ul>
            <Link
                :href="signedIn ? billing() : register()"
                class="group mt-8 inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-gold px-5 text-[15px] font-semibold text-navy transition-colors duration-150 hover:bg-gold/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
            >
                {{
                    signedIn
                        ? 'Get Premium'
                        : trial > 0
                          ? `Start your ${trial}-day free trial`
                          : 'Create an account'
                }}
                <ArrowRight
                    class="size-4 transition-transform duration-150 group-hover:translate-x-0.5"
                    aria-hidden="true"
                />
            </Link>
        </article>
    </div>

    <p class="mt-6 max-w-[70ch] text-sm leading-relaxed text-muted-foreground">
        <template v-if="trial > 0"
            >The free trial starts when you sign up: {{ trial }} days of every
            Premium feature, no payment needed.
        </template>
        Pay for Premium by bank transfer or e-wallet<template v-if="methods">
            ({{ methods }})</template
        >, then upload your receipt in the app. No card and no automatic
        renewal: Premium runs for the time you paid for, and your records stay
        when it ends.
    </p>
</template>
