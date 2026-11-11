<script setup lang="ts">
import AdminSidebar from '@/components/admin/AdminSidebar.vue';
import AppShell from '@/components/AppShell.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PwaStatus from '@/components/PwaStatus.vue';
import { SidebarInset, SidebarTrigger } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import type { AppPageProps, BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Shield } from 'lucide-vue-next';
import { computed } from 'vue';

// Shell for /admin, separate from the breeders' app: its own sidebar (admin pages only), no loft navigation, no
// mobile bottom bar, and none of the app's loft prompts or beta notices
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    { description: undefined, breadcrumbs: undefined },
);

const page = usePage<AppPageProps>();
const path = computed(() => page.url.split('?')[0]);

const crumbs = computed<BreadcrumbItem[]>(
    () =>
        props.breadcrumbs ?? [
            { title: 'Admin', href: toUrl(dashboard()) },
            ...(props.title === 'Overview'
                ? []
                : [{ title: props.title, href: path.value }]),
        ],
);
</script>

<template>
    <AppShell variant="sidebar">
        <AdminSidebar />
        <SidebarInset class="overflow-x-clip">
            <header
                class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background/95 px-3 backdrop-blur md:static md:h-16 md:px-4"
            >
                <!-- Always shown: on phones it opens the admin menu, which has no bottom bar -->
                <SidebarTrigger class="-ml-1 min-h-11 min-w-11" />
                <div class="hidden md:block">
                    <Breadcrumbs :breadcrumbs="crumbs" />
                </div>
                <p
                    class="flex min-w-0 items-center gap-2 text-base font-semibold md:hidden"
                >
                    <Shield
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="truncate">Admin · {{ title }}</span>
                </p>
            </header>

            <main class="mx-auto w-full max-w-6xl p-4 sm:p-6">
                <header class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="mt-1 text-muted-foreground">
                        {{ description }}
                    </p>
                </header>
                <slot />
            </main>
        </SidebarInset>
        <PwaStatus />
        <Toaster
            position="top-right"
            :richColors="true"
            :closeButton="true"
            :duration="3000"
        />
    </AppShell>
</template>
