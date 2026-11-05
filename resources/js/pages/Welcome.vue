<script setup lang="ts">
import FeatureChapters from '@/components/landing/FeatureChapters.vue';
import InstallAppButton from '@/components/landing/InstallAppButton.vue';
import InstallSection from '@/components/landing/InstallSection.vue';
import PedigreeSpecimen from '@/components/landing/PedigreeSpecimen.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
</script>

<template>
    <Head title="Pigeon pedigrees, breeding and race records">
        <meta
            head-key="description"
            name="description"
            content="Keep ring numbers, pedigrees, pairings, clutches and race results for every bird in your loft. Share pedigrees by QR code and see what a bloodline has proven."
        />
    </Head>

    <PublicLayout>
        <section class="border-b border-gold/30 bg-navy text-ivory">
            <div
                class="mx-auto grid max-w-6xl items-center gap-12 px-4 pt-12 pb-16 sm:px-6 sm:pt-16 sm:pb-20 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:gap-14 lg:pt-24 lg:pb-28"
            >
                <div>
                    <h1
                        class="animate-rise font-display text-[2.5rem] leading-[1.06] font-semibold tracking-[-0.015em] text-balance sm:text-5xl lg:text-[3.5rem]"
                    >
                        Every bird in your loft, and every bird behind it.
                    </h1>
                    <p
                        class="mt-6 max-w-[34rem] animate-rise text-base leading-relaxed text-pretty text-ivory/70 sm:text-lg"
                        style="--rise-delay: 80ms"
                    >
                        Ring numbers, pedigrees, pairings and race results, kept
                        as one record. See what a bird and its bloodline have
                        proven before you pair it, race it or sell it.
                    </p>

                    <div
                        class="mt-9 flex animate-rise flex-wrap items-center gap-3"
                        style="--rise-delay: 160ms"
                    >
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : register()
                            "
                            class="group inline-flex min-h-11 items-center gap-2 rounded-md bg-gold px-5 text-[15px] font-semibold text-navy transition-colors duration-150 hover:bg-gold/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
                        >
                            {{
                                $page.props.auth.user
                                    ? 'Go to your dashboard'
                                    : 'Request an invite'
                            }}
                            <ArrowRight
                                class="size-4 transition-transform duration-150 group-hover:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </Link>
                        <InstallAppButton tone="dark" size="md" />
                    </div>

                    <p
                        v-if="!$page.props.auth.user"
                        class="mt-7 animate-rise text-sm text-ivory/55"
                        style="--rise-delay: 240ms"
                    >
                        Already have an account?
                        <Link
                            :href="login()"
                            class="font-medium text-ivory underline decoration-gold/60 underline-offset-4 transition-colors duration-150 hover:decoration-gold"
                        >
                            Log in
                        </Link>
                    </p>
                </div>

                <PedigreeSpecimen />
            </div>
        </section>

        <FeatureChapters />

        <InstallSection />
    </PublicLayout>
</template>
