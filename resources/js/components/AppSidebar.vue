<script setup lang="ts">
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as clubs } from '@/routes/clubs';
import { index as olrRaces } from '@/routes/olr-races';
import { index as pigeons } from '@/routes/pigeons';
import { index as sales } from '@/routes/sales';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bird, LayoutGrid, DollarSign, Trophy, Flag } from 'lucide-vue-next';

const page = usePage();

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Pigeons',
        href: pigeons(),
        icon: Bird,
    },
    {
        title: 'OLR Races',
        href: olrRaces(),
        icon: Trophy,
    },
    {
        title: 'Clubs',
        href: clubs(),
        icon: Flag,
    },
    {
        title: 'Sales & Auctions',
        href: sales(),
        icon: DollarSign,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-r-0">
        <SidebarHeader class="border-b border-border/40 bg-gradient-to-b from-primary/5 to-transparent p-4">
            <div class="flex items-center gap-3 px-2">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary/80 shadow-lg shadow-primary/20">
                    <Bird class="h-6 w-6 text-primary-foreground" />
                </div>
                <div class="flex flex-col group-data-[collapsible=icon]:hidden">
                    <span class="text-base font-bold tracking-tight text-foreground">BloodlineHub</span>
                    <span class="text-xs text-muted-foreground">Pigeon Management</span>
                </div>
            </div>
        </SidebarHeader>

        <SidebarContent class="px-3 py-4">
            <!-- Main Navigation -->
            <div class="mb-6">
                <SidebarMenu class="space-y-1">
                    <SidebarMenuItem v-for="item in mainNavItems" :key="item.title">
                        <Link
                            :href="item.href"
                            :class="[
                                'group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200',
                                urlIsActive(item.href, page.url)
                                    ? 'bg-gradient-to-r from-primary to-primary/90 text-primary-foreground shadow-md shadow-primary/20'
                                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                            ]"
                        >
                            <component
                                :is="item.icon"
                                :class="[
                                    'h-5 w-5 shrink-0 transition-transform group-hover:scale-110',
                                    urlIsActive(item.href, page.url) ? 'text-primary-foreground' : '',
                                ]"
                            />
                            <span class="group-data-[collapsible=icon]:hidden">{{ item.title }}</span>
                        </Link>
                    </SidebarMenuItem>
                </SidebarMenu>
            </div>
        </SidebarContent>

        <SidebarFooter class="border-t border-border/40 bg-gradient-to-t from-primary/5 to-transparent p-3">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
