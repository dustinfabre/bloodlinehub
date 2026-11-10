<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { type LegalSection } from '@/lib/legal';
import { privacy, terms } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';

// Shell for the Privacy Policy and Terms: title, date, a contents list beside the text, and links between the two
defineProps<{
    title: string;
    description: string;
    updated: string;
    sections: LegalSection[];
}>();
</script>

<template>
    <Head :title="title">
        <meta head-key="description" name="description" :content="description" />
    </Head>

    <PublicLayout>
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16 lg:py-20">
            <header class="max-w-3xl border-b border-border pb-8">
                <h1
                    class="font-display text-4xl leading-[1.08] font-semibold tracking-[-0.01em] text-balance text-foreground sm:text-5xl"
                >
                    {{ title }}
                </h1>
                <p class="mt-4 text-sm text-muted-foreground">
                    Last updated {{ updated }}
                </p>
                <div
                    class="mt-6 text-base leading-relaxed text-pretty text-foreground/80 sm:text-lg [&_a]:font-medium [&_a]:text-foreground [&_a]:underline [&_a]:decoration-gold [&_a]:underline-offset-4 [&_a:hover]:decoration-current [&_p+p]:mt-4"
                >
                    <slot name="intro" />
                </div>
            </header>

            <div
                class="mt-10 grid gap-10 lg:grid-cols-[14rem_minmax(0,1fr)] lg:gap-16"
            >
                <nav
                    aria-label="On this page"
                    class="rounded-lg border border-border bg-card p-5 lg:sticky lg:top-28 lg:self-start lg:border-0 lg:bg-transparent lg:p-0"
                >
                    <p
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        On this page
                    </p>
                    <ol class="mt-3 space-y-1 text-sm">
                        <li v-for="section in sections" :key="section.id">
                            <a
                                :href="`#${section.id}`"
                                class="inline-flex min-h-9 items-center text-foreground/75 transition-colors duration-150 hover:text-foreground lg:min-h-0 lg:py-1"
                            >
                                {{ section.title }}
                            </a>
                        </li>
                    </ol>
                </nav>

                <article
                    class="max-w-[68ch] space-y-12 text-[15px] leading-relaxed text-foreground/80 sm:text-base [&_a]:font-medium [&_a]:text-foreground [&_a]:underline [&_a]:decoration-gold [&_a]:underline-offset-4 [&_a]:wrap-break-word [&_a:hover]:decoration-current [&_h2]:scroll-mt-28 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:leading-tight [&_h2]:font-semibold [&_h2]:text-foreground [&_h3]:mt-6 [&_h3]:font-semibold [&_h3]:text-foreground [&_li]:pl-1 [&_ol]:mt-3 [&_ol]:list-decimal [&_ol]:space-y-2 [&_ol]:pl-5 [&_p]:mt-3 [&_strong]:font-semibold [&_strong]:text-foreground [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:pl-5 [&_ul]:marker:text-gold"
                >
                    <slot />
                </article>
            </div>

            <footer
                class="mt-16 flex flex-wrap gap-x-6 gap-y-2 border-t border-border pt-6 text-sm text-muted-foreground"
            >
                <Link
                    :href="privacy()"
                    class="inline-flex min-h-11 items-center underline-offset-4 hover:text-foreground hover:underline md:min-h-0"
                >
                    Privacy Policy
                </Link>
                <Link
                    :href="terms()"
                    class="inline-flex min-h-11 items-center underline-offset-4 hover:text-foreground hover:underline md:min-h-0"
                >
                    Terms and Conditions
                </Link>
            </footer>
        </div>
    </PublicLayout>
</template>
