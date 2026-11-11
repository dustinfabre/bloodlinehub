<script setup lang="ts">
import AppLogoWordmark from '@/components/AppLogoWordmark.vue';
import InstallAppButton from '@/components/landing/InstallAppButton.vue';
import {
    dashboard,
    home,
    login,
    pricing,
    privacy,
    register,
    terms,
} from '@/routes';
import { index as marketplace } from '@/routes/marketplace';
import { Head, Link } from '@inertiajs/vue3';

// Shell for the public pages (landing, coming soon): navy header and footer around the page
const year = new Date().getFullYear();
</script>

<template>
    <Head>
        <!-- Serif for headings, only used by the public pages -->
        <link
            head-key="font-display"
            rel="stylesheet"
            href="https://fonts.bunny.net/css?family=source-serif-4:600,700&display=swap"
        />
    </Head>

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="sticky top-0 z-40 border-b border-white/10 bg-navy">
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:h-20 sm:px-6"
            >
                <Link
                    :href="home()"
                    class="shrink-0 rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-gold"
                >
                    <AppLogoWordmark class="h-8 sm:h-11" />
                </Link>

                <nav class="flex items-center gap-2 sm:gap-3" aria-label="Main">
                    <Link
                        :href="pricing()"
                        class="hidden min-h-10 items-center px-3 text-sm font-medium text-ivory/70 transition-colors duration-150 hover:text-ivory md:inline-flex"
                    >
                        Pricing
                    </Link>
                    <Link
                        :href="marketplace()"
                        class="hidden min-h-10 items-center px-3 text-sm font-medium text-ivory/70 transition-colors duration-150 hover:text-ivory md:inline-flex"
                    >
                        Marketplace
                    </Link>
                    <InstallAppButton tone="dark" size="sm" />
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="login()"
                        class="hidden min-h-10 items-center px-3 text-sm font-medium text-ivory/70 transition-colors duration-150 hover:text-ivory sm:inline-flex"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="$page.props.auth.user ? dashboard() : register()"
                        class="inline-flex min-h-11 items-center rounded-md bg-gold px-4 text-sm font-semibold text-navy transition-colors duration-150 hover:bg-gold/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold md:min-h-10"
                    >
                        {{ $page.props.auth.user ? 'Dashboard' : 'Sign up' }}
                    </Link>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer
            class="bg-navy pb-[env(safe-area-inset-bottom)] text-sm text-ivory/60"
        >
            <div
                class="mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-10 sm:px-6 md:flex-row md:items-center md:justify-between"
            >
                <AppLogoWordmark class="h-8" />
                <nav class="flex flex-wrap gap-x-6" aria-label="Footer">
                    <Link
                        :href="pricing()"
                        class="inline-flex min-h-11 items-center transition-colors duration-150 hover:text-ivory md:min-h-0"
                    >
                        Pricing
                    </Link>
                    <Link
                        :href="marketplace()"
                        class="inline-flex min-h-11 items-center transition-colors duration-150 hover:text-ivory md:min-h-0"
                    >
                        Marketplace
                    </Link>
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="register()"
                        class="inline-flex min-h-11 items-center transition-colors duration-150 hover:text-ivory md:min-h-0"
                    >
                        Sign up
                    </Link>
                    <Link
                        :href="$page.props.auth.user ? dashboard() : login()"
                        class="inline-flex min-h-11 items-center transition-colors duration-150 hover:text-ivory md:min-h-0"
                    >
                        {{ $page.props.auth.user ? 'Dashboard' : 'Log in' }}
                    </Link>
                    <Link
                        :href="privacy()"
                        class="inline-flex min-h-11 items-center transition-colors duration-150 hover:text-ivory md:min-h-0"
                    >
                        Privacy
                    </Link>
                    <Link
                        :href="terms()"
                        class="inline-flex min-h-11 items-center transition-colors duration-150 hover:text-ivory md:min-h-0"
                    >
                        Terms
                    </Link>
                </nav>
                <p>&copy; {{ year }} BloodlineHub</p>
            </div>
        </footer>
    </div>
</template>
