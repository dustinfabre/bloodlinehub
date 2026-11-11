<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppLogoWordmark from '@/components/AppLogoWordmark.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { cn, toUrl } from '@/lib/utils';
import { dashboard as appDashboard } from '@/routes';
import { dashboard } from '@/routes/admin';
import { index as bankAccounts } from '@/routes/admin/bank-accounts';
import { index as inbox } from '@/routes/admin/inbox';
import { index as payments } from '@/routes/admin/payments';
import { index as plans } from '@/routes/admin/plans';
import { index as users } from '@/routes/admin/users';
import type { AppPageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Banknote,
    Inbox,
    LayoutDashboard,
    Receipt,
    Shield,
    Tag,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

// The admin console's own sidebar: only admin pages, so it never looks like part of a breeder's loft
const page = usePage<AppPageProps>();
const { isMobile, setOpenMobile } = useSidebar();

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

// On phones the sidebar is a sheet: close it once a page is picked
const picked = () => {
    if (isMobile.value) setOpenMobile(false);
};
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-r-0">
        <SidebarHeader
            class="gap-3 border-b border-sidebar-border p-4 group-data-[collapsible=icon]:px-2"
        >
            <Link
                :href="dashboard()"
                class="flex items-center justify-center"
                @click="picked"
            >
                <AppLogoWordmark
                    class="h-11 max-w-full group-data-[collapsible=icon]:hidden"
                />
                <AppLogoIcon
                    class="hidden size-8 group-data-[collapsible=icon]:block"
                />
            </Link>
            <div
                class="flex items-center justify-center gap-1.5 rounded-md bg-sidebar-accent px-2 py-1 text-xs font-semibold tracking-wider text-sidebar-accent-foreground uppercase group-data-[collapsible=icon]:px-0"
            >
                <Shield class="size-3.5 shrink-0" aria-hidden="true" />
                <span class="group-data-[collapsible=icon]:sr-only"
                    >Admin console</span
                >
            </div>
        </SidebarHeader>

        <SidebarContent class="px-3 py-4">
            <nav aria-label="Admin">
                <SidebarMenu class="space-y-1">
                    <SidebarMenuItem v-for="item in items" :key="item.href">
                        <Link
                            :href="item.href"
                            :title="item.title"
                            :aria-current="
                                isActive(item.href, item.exact)
                                    ? 'page'
                                    : undefined
                            "
                            :class="
                                cn(
                                    'group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200',
                                    isActive(item.href, item.exact)
                                        ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-md shadow-black/20'
                                        : 'text-sidebar-foreground/75 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                )
                            "
                            @click="picked"
                        >
                            <span class="relative shrink-0">
                                <component
                                    :is="item.icon"
                                    class="h-5 w-5 transition-transform group-hover:scale-110"
                                />
                                <!-- A dot on the icon when the sidebar is collapsed to icons -->
                                <span
                                    v-if="item.count"
                                    class="absolute -top-1 -right-1 hidden size-2 rounded-full bg-amber-400 group-data-[collapsible=icon]:block"
                                    aria-hidden="true"
                                />
                            </span>
                            <span
                                class="flex-1 group-data-[collapsible=icon]:hidden"
                                >{{ item.title }}</span
                            >
                            <span
                                v-if="item.count"
                                class="min-w-5 rounded-full bg-amber-400 px-1.5 py-0.5 text-center text-[11px] leading-none font-semibold text-navy tabular-nums group-data-[collapsible=icon]:hidden"
                                >{{ item.count }}</span
                            >
                        </Link>
                    </SidebarMenuItem>
                </SidebarMenu>
            </nav>
        </SidebarContent>

        <SidebarFooter class="gap-2 border-t border-sidebar-border p-3">
            <Link
                :href="appDashboard()"
                title="Back to the app"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-sidebar-foreground/75 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
            >
                <ArrowLeft class="h-5 w-5 shrink-0" />
                <span class="group-data-[collapsible=icon]:hidden"
                    >Back to the app</span
                >
            </Link>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
