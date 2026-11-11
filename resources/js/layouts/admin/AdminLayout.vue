<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { index as bankAccounts } from '@/routes/admin/bank-accounts';
import { index as inbox } from '@/routes/admin/inbox';
import { index as payments } from '@/routes/admin/payments';
import { index as plans } from '@/routes/admin/plans';
import { index as users } from '@/routes/admin/users';
import type { AppPageProps, BreadcrumbItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    Inbox,
    LayoutDashboard,
    Receipt,
    Tag,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

// Shell for /admin: the app layout with the admin menu beside (or above, on phones) the page
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    { description: undefined, breadcrumbs: undefined },
);

const page = usePage<AppPageProps>();

const items = computed(() => [
    {
        title: 'Overview',
        href: toUrl(dashboard()),
        icon: LayoutDashboard,
        exact: true,
    },
    {
        title: 'Payments',
        href: toUrl(payments()),
        icon: Receipt,
        count: page.props.adminCounts?.pending_payments ?? 0,
    },
    {
        title: 'Inbox',
        href: toUrl(inbox()),
        icon: Inbox,
        count: page.props.adminCounts?.unread_feedback ?? 0,
    },
    { title: 'Users', href: toUrl(users()), icon: Users },
    { title: 'Bank accounts', href: toUrl(bankAccounts()), icon: Banknote },
    { title: 'Plans & prices', href: toUrl(plans()), icon: Tag },
]);

const path = computed(() => page.url.split('?')[0]);
const isActive = (href: string, exact = false) =>
    exact
        ? path.value === href
        : path.value === href || path.value.startsWith(`${href}/`);

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
    <AppLayout :breadcrumbs="crumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6 lg:flex-row lg:gap-10">
            <aside class="lg:w-52 lg:shrink-0">
                <p
                    class="mb-2 hidden px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase lg:block"
                >
                    Admin
                </p>
                <nav
                    class="-mx-4 flex gap-1 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0"
                    aria-label="Admin"
                >
                    <Link
                        v-for="item in items"
                        :key="item.href"
                        :href="item.href"
                        :aria-current="
                            isActive(item.href, item.exact) ? 'page' : undefined
                        "
                        :class="
                            cn(
                                'flex shrink-0 items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                                isActive(item.href, item.exact)
                                    ? 'bg-muted text-foreground'
                                    : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                            )
                        "
                    >
                        <component :is="item.icon" class="size-4 shrink-0" />
                        <span class="flex-1">{{ item.title }}</span>
                        <Badge
                            v-if="item.count"
                            class="h-5 min-w-5 justify-center rounded-full px-1.5 text-[11px] tabular-nums"
                            >{{ item.count }}</Badge
                        >
                    </Link>
                </nav>
            </aside>

            <main class="min-w-0 flex-1">
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
        </div>
    </AppLayout>
</template>
