<script setup lang="ts">
import InstallAppButton from '@/components/landing/InstallAppButton.vue';
import { usePwaInstall } from '@/composables/usePwaInstall';
import { vReveal } from '@/lib/reveal';

const { isInstalled } = usePwaInstall();
</script>

<template>
    <section
        v-reveal
        aria-labelledby="install-heading"
        class="mx-auto max-w-6xl px-4 pb-20 sm:px-6 sm:pb-28"
    >
        <div
            class="flex flex-col gap-6 rounded-lg border border-border bg-card px-5 py-8 sm:px-10 sm:py-10 md:flex-row md:items-center md:justify-between md:gap-12"
        >
            <div class="max-w-[60ch]">
                <h2
                    id="install-heading"
                    class="font-display text-2xl font-semibold text-navy"
                >
                    Keep it on your home screen
                </h2>
                <p
                    class="mt-3 text-[15px] leading-relaxed text-muted-foreground"
                >
                    Install BloodlineHub on your phone or computer and it opens
                    like an app, full screen and straight to your dashboard.
                    When a new version is out, it tells you and updates in one
                    tap.
                </p>
            </div>

            <div class="shrink-0">
                <p v-if="isInstalled" class="text-sm text-muted-foreground">
                    Installed on this device.
                </p>
                <InstallAppButton v-else tone="light" size="md" />
            </div>
        </div>
    </section>
</template>
