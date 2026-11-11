<script setup lang="ts">
import PricingPlans from '@/components/landing/PricingPlans.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { durationLabel } from '@/lib/billing';
import type { Pricing } from '@/types/billing';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    pricing: Pricing;
    paymentMethods: string[];
}>();

const trial = computed(() => props.pricing.trial_days);

const questions = computed(() => [
    ...(trial.value > 0
        ? [
              {
                  q: 'How does the free trial work?',
                  a: `Every new account gets ${trial.value} days of full Premium, starting when you sign up. You don't pay or enter anything. When it ends you keep all your records and stay on Free, unless you choose to pay.`,
              },
          ]
        : []),
    {
        q: 'How do I pay?',
        a: 'In the app, open Plan & billing. Send the price to one of the accounts shown there by bank transfer or e-wallet, then upload a screenshot or photo of your receipt.',
    },
    {
        q: 'When does Premium start?',
        a: 'As soon as we confirm your payment arrived. If you pay during your trial or before your Premium runs out, the new time is added after it, so you lose nothing by paying early.',
    },
    {
        q: 'Does it renew by itself?',
        a: `No. One payment gives you ${durationLabel(props.pricing.duration_months)} of Premium. Nothing is charged unless you pay again.`,
    },
    {
        q: 'What happens when Premium ends?',
        a: 'Your birds, pedigrees and records stay, and everything in Free keeps working. Premium features lock until you renew, and pedigrees shared online stop opening for other people.',
    },
]);
</script>

<template>
    <Head title="Pricing">
        <meta
            head-key="description"
            name="description"
            content="BloodlineHub is free for unlimited pigeons and pairings. Premium adds Pairing Analysis, Loft Check, QR code pedigrees and more."
        />
    </Head>

    <PublicLayout>
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
            <div class="max-w-2xl">
                <h1
                    class="animate-rise font-display text-4xl leading-[1.08] font-semibold tracking-[-0.01em] text-balance text-navy sm:text-5xl"
                >
                    Free for your whole loft. Premium when you breed to win.
                </h1>
                <p
                    class="mt-5 animate-rise text-base leading-relaxed text-pretty text-muted-foreground sm:text-lg"
                    style="--rise-delay: 80ms"
                >
                    Keep every bird and every pairing for free, with no limits.
                    Premium adds the tools for planning crosses, counting your
                    team and selling with pedigrees buyers can check.
                </p>
            </div>

            <div class="mt-12 animate-rise" style="--rise-delay: 160ms">
                <PricingPlans
                    :pricing="pricing"
                    :payment-methods="paymentMethods"
                />
            </div>
        </section>

        <section
            class="mx-auto max-w-6xl px-4 pb-20 sm:px-6 sm:pb-28"
            aria-labelledby="pricing-questions"
        >
            <h2
                id="pricing-questions"
                class="font-display text-2xl font-semibold text-navy sm:text-3xl"
            >
                Questions
            </h2>
            <dl class="mt-8 grid gap-x-10 gap-y-8 md:grid-cols-2">
                <div v-for="item in questions" :key="item.q">
                    <dt class="font-medium text-navy">{{ item.q }}</dt>
                    <dd
                        class="mt-2 text-[15px] leading-relaxed text-muted-foreground"
                    >
                        {{ item.a }}
                    </dd>
                </div>
            </dl>
        </section>
    </PublicLayout>
</template>
