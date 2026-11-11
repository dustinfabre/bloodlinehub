<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { isPremium } from '@/lib/billing';
import { logout } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as billing } from '@/routes/billing';
import { create as feedback } from '@/routes/feedback';
import { edit } from '@/routes/profile';
import type { AppPageProps, User } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Crown,
    LogOut,
    MessageSquare,
    Settings,
    Shield,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    user: User;
}

const handleLogout = () => {
    router.flushAll();
};

const props = defineProps<Props>();

const page = usePage<AppPageProps>();
const premium = computed(() => isPremium(props.user.premium_until));
const onTrial = computed(
    () => !premium.value && isPremium(props.user.trial_ends_at),
);
// What's waiting for an admin: payments to review and unread feedback
const adminWaiting = computed(
    () =>
        (page.props.adminCounts?.pending_payments ?? 0) +
        (page.props.adminCounts?.unread_feedback ?? 0),
);
// The feedback page records where it was opened from, so a bug report says which page it's about
const feedbackHref = computed(() => feedback({ query: { from: page.url } }));
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="edit()" prefetch as="button">
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="flex w-full items-center text-left"
                :href="billing()"
                as="button"
            >
                <Crown class="mr-2 h-4 w-4" />
                <span class="flex-1">Plan & billing</span>
                <span
                    v-if="premium || onTrial"
                    class="rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300"
                    >{{ premium ? 'Premium' : 'Trial' }}</span
                >
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="flex w-full items-center text-left"
                :href="feedbackHref"
                as="button"
            >
                <MessageSquare class="mr-2 h-4 w-4" />
                Send feedback
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="user.is_admin" :as-child="true">
            <Link
                class="flex w-full items-center text-left"
                :href="adminDashboard()"
                as="button"
            >
                <Shield class="mr-2 h-4 w-4" />
                <span class="flex-1">Admin</span>
                <span
                    v-if="adminWaiting"
                    class="min-w-5 rounded-full bg-primary px-1.5 py-0.5 text-center text-[10px] font-semibold text-primary-foreground tabular-nums"
                    >{{ adminWaiting }}</span
                >
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
