<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';
import { Bird, Layers, Shield, Users } from 'lucide-vue-next';
</script>

<template>
    <Head title="BloodlineHub" />

    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-sky-100 text-slate-900 dark:from-slate-950 dark:via-slate-950 dark:to-slate-900 dark:text-slate-50">
        <nav class="border-b border-blue-100/60 bg-white/80 backdrop-blur dark:border-blue-900/40 dark:bg-slate-900/80">
            <div class="mx-auto flex h-20 w-full max-w-6xl items-center justify-between px-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/20">
                        <Bird class="h-6 w-6 text-primary" />
                    </span>
                    <span class="text-2xl font-semibold text-primary dark:text-sky-300">BloodlineHub</span>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow hover:bg-primary/90"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white shadow hover:bg-primary/90"
                        >
                            Log in
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-16 px-6 py-20">
            <section class="text-center">
                <div class="mx-auto mb-10 flex h-20 w-20 items-center justify-center rounded-full bg-primary/15">
                    <Bird class="h-10 w-10 text-primary" />
                </div>
                <h1 class="text-4xl font-bold tracking-tight text-slate-900 dark:text-slate-100 md:text-6xl">
                    Your Pigeon Pedigree Command Center
                </h1>
                <p class="mx-auto mt-6 max-w-3xl text-lg text-slate-600 dark:text-slate-300">
                    Track bloodlines, manage loft records, and showcase your champions. BloodlineHub keeps every ring number, pedigree note, and sale-ready detail organised for your loft.
                </p>
                <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="login()"
                        class="rounded-full bg-primary px-8 py-3 text-base font-semibold text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90"
                    >
                        Launch App
                    </Link>
                    <Link
                        v-else
                        :href="dashboard()"
                        class="rounded-full bg-primary px-8 py-3 text-base font-semibold text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90"
                    >
                        Go to Dashboard
                    </Link>
                </div>
            </section>

            <section class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-2xl border border-blue-100/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-blue-900/40 dark:bg-slate-900/80">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/15 text-primary">
                        <Layers class="h-6 w-6" />
                    </div>
                    <h2 class="text-lg font-semibold">Detailed Records</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        Capture colour, ring and personal numbers, remarks, notes, and gallery shots for every bird in your loft.
                    </p>
                </article>

                <article class="rounded-2xl border border-blue-100/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-blue-900/40 dark:bg-slate-900/80">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/15 text-primary">
                        <Shield class="h-6 w-6" />
                    </div>
                    <h2 class="text-lg font-semibold">Pedigree Builder</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        Design printable pedigrees that highlight lineage and key achievements for prospective buyers.
                    </p>
                </article>

                <article class="rounded-2xl border border-blue-100/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-blue-900/40 dark:bg-slate-900/80">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/15 text-primary">
                        <Users class="h-6 w-6" />
                    </div>
                    <h2 class="text-lg font-semibold">Public Profiles</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        Showcase top pigeons with curated public pages and shareable links for clubs and auctions.
                    </p>
                </article>

                <article class="rounded-2xl border border-blue-100/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-blue-900/40 dark:bg-slate-900/80">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/15 text-primary">
                        <Bird class="h-6 w-6" />
                    </div>
                    <h2 class="text-lg font-semibold">Sales & Auctions</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        Prepare listings now and roll into auctions later with built-in pricing, status, and interest tracking.
                    </p>
                </article>
            </section>

            <section class="rounded-3xl border border-primary/20 bg-primary/10 p-10 text-center shadow-sm backdrop-blur dark:border-sky-500/30 dark:bg-sky-500/10">
                <h2 class="text-3xl font-semibold text-primary dark:text-sky-200">Built for loft management teams</h2>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-600 dark:text-slate-300">
                    Keep every breeder and racer organised, deliver pedigrees on demand, and transition seamlessly from record keeping to sales operations when Phase 2 unlocks.
                </p>
            </section>
        </main>

        <footer class="border-t border-blue-100/60 bg-white/70 py-8 text-sm text-slate-500 backdrop-blur dark:border-blue-900/40 dark:bg-slate-900/80 dark:text-slate-400">
            <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-4 px-6 md:flex-row">
                <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                    <Bird class="h-5 w-5 text-primary" />
                    <span class="font-medium">BloodlineHub</span>
                </div>
                <span>© {{ new Date().getFullYear() }} BloodlineHub. All rights reserved.</span>
            </div>
        </footer>
    </div>
</template>
