<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const currentPage = computed(() => props.breadcrumbs.at(-1));
const parentPage = computed(() => props.breadcrumbs.at(-2));
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background/95 px-3 backdrop-blur md:static md:h-16 md:px-4 md:transition-[width,height] md:ease-linear md:group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <div class="hidden items-center gap-2 md:flex">
            <SidebarTrigger class="-ml-1 min-h-11 min-w-11" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex min-w-0 flex-1 items-center gap-2 md:hidden">
            <Link
                v-if="parentPage"
                :href="parentPage.href"
                class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-md text-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :aria-label="`Back to ${parentPage.title}`"
            >
                <ArrowLeft class="size-5" aria-hidden="true" />
            </Link>
            <div
                v-else
                class="flex size-11 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground"
            >
                <span class="text-sm font-bold" aria-hidden="true">BH</span>
            </div>
            <h1 class="truncate text-base font-semibold">
                {{ currentPage?.title ?? 'BloodlineHub' }}
            </h1>
        </div>
    </header>
</template>
