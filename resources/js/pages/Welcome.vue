<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppLogoWordmark from '@/components/AppLogoWordmark.vue';
import { Bird, Layers, Shield, Users } from 'lucide-vue-next';
</script>

<template>
    <Head title="BloodlineHub" />

    <div class="min-h-screen bg-background text-foreground">
        <nav class="bg-navy">
            <div class="mx-auto flex h-20 w-full max-w-6xl items-center justify-between px-6">
                <AppLogoWordmark class="h-11 sm:h-12" />
                <div class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-full bg-gold px-5 py-2 text-sm font-semibold text-navy shadow hover:bg-gold/90"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-full bg-gold px-5 py-2 text-sm font-semibold text-navy shadow hover:bg-gold/90"
                        >
                            Log in
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-16 px-6 py-20">
            <section class="text-center">
                <AppLogoIcon class="mx-auto mb-10 size-36 md:size-44" alt="" />
                <h1 class="text-4xl font-bold tracking-tight md:text-6xl">
                    Your Pigeon Pedigree Command Center
                </h1>
                <p class="mx-auto mt-6 max-w-3xl text-lg text-muted-foreground">
                    Track bloodlines, manage loft records, and showcase your champions. BloodlineHub keeps every ring number, pedigree note, and sale-ready detail organised for your loft.
                </p>
                <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="login()"
                        class="rounded-full bg-primary px-8 py-3 text-base font-semibold text-primary-foreground shadow-lg shadow-primary/20 transition hover:bg-primary/90"
                    >
                        Launch App
                    </Link>
                    <Link
                        v-else
                        :href="dashboard()"
                        class="rounded-full bg-primary px-8 py-3 text-base font-semibold text-primary-foreground shadow-lg shadow-primary/20 transition hover:bg-primary/90"
                    >
                        Go to Dashboard
                    </Link>
                </div>
            </section>

            <section class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gold/20 text-navy">
                        <Layers class="h-6 w-6" />
                    </div>
                    <h2 class="text-lg font-semibold">Detailed Records</h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Capture colour, ring and personal numbers, remarks, notes, and gallery shots for every bird in your loft.
                    </p>
                </article>

                <article class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gold/20 text-navy">
                        <Shield class="h-6 w-6" />
                    </div>
                    <h2 class="text-lg font-semibold">Pedigree Builder</h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Design printable pedigrees that highlight lineage and key achievements for prospective buyers.
                    </p>
                </article>

                <article class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gold/20 text-navy">
                        <Users class="h-6 w-6" />
                    </div>
                    <h2 class="text-lg font-semibold">Public Profiles</h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Showcase top pigeons with curated public pages and shareable links for clubs and auctions.
                    </p>
                </article>

                <article class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gold/20 text-navy">
                        <Bird class="h-6 w-6" />
                    </div>
                    <h2 class="text-lg font-semibold">Sales & Auctions</h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Prepare listings now and roll into auctions later with built-in pricing, status, and interest tracking.
                    </p>
                </article>
            </section>

            <section class="rounded-3xl border-t-4 border-gold bg-navy p-10 text-center shadow-sm">
                <h2 class="text-3xl font-semibold text-gold">Built for loft management teams</h2>
                <p class="mx-auto mt-4 max-w-2xl text-base text-ivory/80">
                    Keep every breeder and racer organised, deliver pedigrees on demand, and transition seamlessly from record keeping to sales operations when Phase 2 unlocks.
                </p>
            </section>
        </main>

        <footer class="border-t border-border py-8 text-sm text-muted-foreground">
            <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-4 px-6 md:flex-row">
                <div class="flex items-center gap-2 text-foreground">
                    <AppLogoIcon class="size-8" alt="" />
                    <span class="font-medium">BloodlineHub</span>
                </div>
                <span>© {{ new Date().getFullYear() }} BloodlineHub. All rights reserved.</span>
            </div>
        </footer>
    </div>
</template>
