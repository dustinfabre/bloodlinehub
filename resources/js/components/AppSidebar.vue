<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppLogoWordmark from '@/components/AppLogoWordmark.vue';
import NavUser from '@/components/NavUser.vue';
import PremiumBadge from '@/components/PremiumBadge.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { mainNavItems } from '@/lib/navigation';
import { urlIsActive } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { AppPageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage<AppPageProps>();
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-r-0">
        <SidebarHeader
            class="border-b border-sidebar-border p-4 group-data-[collapsible=icon]:px-2"
        >
            <Link :href="dashboard()" class="flex items-center justify-center">
                <AppLogoWordmark
                    class="h-11 max-w-full group-data-[collapsible=icon]:hidden"
                />
                <AppLogoIcon
                    class="hidden size-8 group-data-[collapsible=icon]:block"
                />
            </Link>
        </SidebarHeader>

        <SidebarContent class="px-3 py-4">
            <!-- Main Navigation -->
            <div class="mb-6">
                <SidebarMenu class="space-y-1">
                    <SidebarMenuItem
                        v-for="item in mainNavItems"
                        :key="item.title"
                    >
                        <Link
                            :href="item.href"
                            :aria-current="
                                urlIsActive(item.href, page.url)
                                    ? 'page'
                                    : undefined
                            "
                            :class="[
                                'group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200',
                                urlIsActive(item.href, page.url)
                                    ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-md shadow-black/20'
                                    : 'text-sidebar-foreground/75 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                            ]"
                        >
                            <component
                                :is="item.icon"
                                :class="[
                                    'h-5 w-5 shrink-0 transition-transform group-hover:scale-110',
                                    urlIsActive(item.href, page.url)
                                        ? 'text-sidebar-primary-foreground'
                                        : '',
                                ]"
                            />
                            <span
                                class="flex-1 group-data-[collapsible=icon]:hidden"
                                >{{ item.title }}</span
                            >
                            <PremiumBadge
                                v-if="item.premium && !page.props.auth.premium"
                                icon-only
                                class="group-data-[collapsible=icon]:hidden"
                            />
                        </Link>
                    </SidebarMenuItem>
                </SidebarMenu>
            </div>
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border p-3">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
