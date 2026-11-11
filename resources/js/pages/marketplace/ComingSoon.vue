<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { login, register } from '@/routes';
import { index as salesIndex } from '@/routes/sales';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
</script>

<template>
    <Head title="Marketplace coming soon" />

    <PublicLayout>
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24 lg:py-28">
            <div class="max-w-xl">
                <h1
                    class="animate-rise font-display text-4xl leading-[1.08] font-semibold tracking-[-0.01em] text-balance text-navy sm:text-5xl"
                >
                    The marketplace is coming soon.
                </h1>
                <p
                    class="mt-5 animate-rise text-base leading-relaxed text-pretty text-muted-foreground sm:text-lg"
                    style="--rise-delay: 80ms"
                >
                    A public place to find birds for sale. Listings will show
                    the bird's photo, sire and dam, color and hatch year, with
                    the seller's contact details. You won't need an account to
                    browse.
                </p>

                <div
                    class="mt-10 flex animate-rise flex-wrap items-center gap-x-6 gap-y-4"
                    style="--rise-delay: 160ms"
                >
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="salesIndex()"
                            class="group inline-flex min-h-11 items-center gap-2 rounded-md bg-navy px-5 text-[15px] font-semibold text-ivory transition-colors duration-150 hover:bg-navy/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy"
                        >
                            Prepare a listing in Sales
                            <ArrowRight
                                class="size-4 transition-transform duration-150 group-hover:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </Link>
                        <p class="text-sm text-muted-foreground">
                            Active listings will show here when it opens.
                        </p>
                    </template>
                    <template v-else>
                        <Link
                            :href="register()"
                            class="group inline-flex min-h-11 items-center gap-2 rounded-md bg-navy px-5 text-[15px] font-semibold text-ivory transition-colors duration-150 hover:bg-navy/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy"
                        >
                            Create a free account
                            <ArrowRight
                                class="size-4 transition-transform duration-150 group-hover:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </Link>
                        <p class="text-sm text-muted-foreground">
                            Have an account?
                            <Link
                                :href="login()"
                                class="font-medium text-navy underline decoration-gold underline-offset-4 transition-colors duration-150 hover:decoration-navy"
                            >
                                Log in
                            </Link>
                        </p>
                    </template>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
