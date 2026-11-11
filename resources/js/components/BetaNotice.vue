<script setup lang="ts">
import { create as feedback } from '@/routes/feedback';
import { Link, usePage } from '@inertiajs/vue3';
import { FlaskConical } from 'lucide-vue-next';
import { computed } from 'vue';

// "Beta" banner and disclaimer at the top of every page of the sections that are still being polished
const SECTIONS = [
    {
        prefix: '/olr-races',
        name: 'OLR',
        disclaimer:
            'Standings, positions and speeds here are worked out from what you enter. Always go by the official results the race publishes.',
    },
    {
        prefix: '/clubs',
        name: 'Clubs',
        disclaimer:
            "Standings and results here are worked out from what you enter. Always go by your club's official results.",
    },
    {
        prefix: '/sales',
        name: 'Sales & Auctions',
        disclaimer:
            "BloodlineHub only keeps your listings and sales records. It isn't part of any sale and doesn't handle payments between buyers and sellers, so check the bird, its papers and the other person before any money changes hands.",
    },
];

const page = usePage();

const section = computed(() => {
    const path = page.url.split('?')[0];
    return SECTIONS.find(
        (s) => path === s.prefix || path.startsWith(`${s.prefix}/`),
    );
});
</script>

<template>
    <aside
        v-if="section"
        class="mx-4 mt-4 flex gap-3 rounded-lg border border-sky-500/30 bg-sky-500/5 px-4 py-3 text-sm sm:mx-6"
        aria-label="Beta notice"
    >
        <FlaskConical
            class="mt-0.5 size-4 shrink-0 text-sky-700 dark:text-sky-300"
            aria-hidden="true"
        />
        <div class="min-w-0 space-y-1">
            <p>
                <span
                    class="mr-1.5 rounded bg-sky-500/15 px-1.5 py-0.5 text-[11px] font-semibold tracking-wide text-sky-800 uppercase dark:text-sky-200"
                    >Beta</span
                >
                <span class="font-medium"
                    >{{ section.name }} is still being polished.</span
                >
                Some things may not work as expected or may change.
            </p>
            <p class="text-muted-foreground">
                {{ section.disclaimer }} Found a problem?
                <Link
                    :href="feedback({ query: { from: page.url } })"
                    class="font-medium text-foreground underline underline-offset-4"
                    >Tell us</Link
                >.
            </p>
        </div>
    </aside>
</template>
