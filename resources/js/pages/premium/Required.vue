<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { FREE_FEATURES, PREMIUM_FEATURES, usePremium } from '@/lib/premium';
import { index as billing } from '@/routes/billing';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Check, Crown } from 'lucide-vue-next';

// Shown instead of a Premium page (or after a locked button) to accounts without Premium
const props = defineProps<{
    feature: { key: string; title: string; description: string };
}>();

const { premium } = usePremium();

const breadcrumbs: BreadcrumbItem[] = [
    { title: props.feature.title, href: '' },
];

const goBack = () => window.history.back();
</script>

<template>
    <Head :title="feature.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 sm:p-6">
            <Card class="overflow-hidden">
                <CardContent class="space-y-6 p-6 sm:p-8">
                    <div class="space-y-3">
                        <span
                            class="inline-flex size-12 items-center justify-center rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-300"
                        >
                            <Crown class="size-6" />
                        </span>
                        <p
                            class="text-sm font-semibold tracking-wide text-amber-700 uppercase dark:text-amber-300"
                        >
                            Premium feature
                        </p>
                        <h1
                            class="text-2xl font-bold tracking-tight sm:text-3xl"
                        >
                            {{ feature.title }}
                        </h1>
                        <p class="text-muted-foreground">
                            {{ feature.description }}
                        </p>
                    </div>

                    <p
                        v-if="premium"
                        class="rounded-lg bg-muted p-4 text-sm text-muted-foreground"
                    >
                        You have Premium, so this is already unlocked.
                    </p>

                    <div v-else class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <h2 class="mb-2 text-sm font-semibold">
                                Premium adds
                            </h2>
                            <ul class="space-y-1.5 text-sm">
                                <li
                                    v-for="item in PREMIUM_FEATURES"
                                    :key="item.key"
                                    class="flex gap-2"
                                >
                                    <Crown
                                        class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300"
                                    />
                                    <span
                                        :class="
                                            item.key === feature.key
                                                ? 'font-semibold'
                                                : ''
                                        "
                                        >{{ item.title }}</span
                                    >
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h2 class="mb-2 text-sm font-semibold">
                                Always free
                            </h2>
                            <ul
                                class="space-y-1.5 text-sm text-muted-foreground"
                            >
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
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Button v-if="!premium" as-child>
                            <Link :href="billing()">
                                <Crown class="size-4" />
                                Get Premium
                            </Link>
                        </Button>
                        <Button variant="outline" @click="goBack"
                            >Go back</Button
                        >
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
