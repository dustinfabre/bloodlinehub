import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    // Premium features unlocked: paid Premium, or an admin
    premium: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    // Premium only: shows a crown for accounts without Premium
    premium?: boolean;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    // Switches set in config/features.php
    features: { marketplace: boolean };
    // Birds that just went missing or died while their pedigree is public
    pedigreePrompt?: PedigreePrompt | null;
    // Admins only: what's waiting in the admin dashboard (null for everyone else)
    adminCounts?: AdminCounts | null;
};

export interface AdminCounts {
    pending_payments: number;
    unread_feedback: number;
}

export interface PedigreePrompt {
    // Unique per prompt, so each is shown once
    id: string;
    pigeons: { id: number; label: string; status: string }[];
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    // Can open /admin
    is_admin: boolean;
    // Premium while this is in the future
    premium_until: string | null;
    // The free trial (full Premium) runs until this; null if the account never had one
    trial_ends_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
