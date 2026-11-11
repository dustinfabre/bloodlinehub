<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Pager from '@/components/Pager.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useToast } from '@/composables/useToast';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import { formatDate } from '@/lib/billing';
import { cn } from '@/lib/utils';
import { index, premium } from '@/routes/admin/users';
import type { Paginated } from '@/types/billing';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Crown, Search, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface AdminUser {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    premium_until: string | null;
    premium: boolean;
    pigeons_count: number;
    created_at: string;
}

type PlanFilter = 'premium' | 'free' | null;

const props = defineProps<{
    users: Paginated<AdminUser>;
    filters: { search: string; plan: PlanFilter };
}>();

const { success } = useToast();

const query = (search: string, plan: PlanFilter) => ({
    ...(search.trim() ? { search: search.trim() } : {}),
    ...(plan ? { plan } : {}),
});

const plans: { value: PlanFilter; label: string }[] = [
    { value: null, label: 'All' },
    { value: 'premium', label: 'Premium' },
    { value: 'free', label: 'Free' },
];

// Search
const search = ref(props.filters.search);

const applySearch = useDebounceFn(() => {
    if (search.value.trim() === props.filters.search) return;
    router.get(index().url, query(search.value, props.filters.plan), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 350);

watch(search, applySearch);

// "2026-11-12" in the viewer's own time zone
const localDate = (date: Date) => {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const tomorrow = computed(() => {
    const date = new Date();
    date.setDate(date.getDate() + 1);
    return localDate(date);
});

const planLabel = (user: AdminUser) => {
    if (user.premium) return `Premium until ${formatDate(user.premium_until)}`;
    if (user.premium_until)
        return `Free · expired ${formatDate(user.premium_until)}`;
    return 'Free';
};

// Set Premium by hand
const editing = ref<AdminUser | null>(null);
const form = useForm({ premium_until: '' });
const showPremium = computed({
    get: () => editing.value !== null,
    set: (open: boolean) => {
        if (!open) editing.value = null;
    },
});

const openPremium = (user: AdminUser) => {
    form.clearErrors();
    form.premium_until =
        user.premium && user.premium_until
            ? localDate(new Date(user.premium_until))
            : '';
    editing.value = user;
};

const savePremium = (turnOff = false) => {
    const user = editing.value;
    if (!user) return;
    if (turnOff) form.premium_until = '';

    form.patch(premium(user.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
            success(
                turnOff || !form.premium_until
                    ? `Premium turned off for ${user.name}.`
                    : `${user.name} has Premium until ${formatDate(`${form.premium_until}T00:00:00`)}.`,
            );
        },
    });
};
</script>

<template>
    <Head title="Users · Admin" />

    <AdminLayout
        title="Users"
        description="Every account, its birds and its plan. Set Premium by hand to fix a mistake or give it free."
    >
        <div class="space-y-5">
            <div
                class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <nav class="flex flex-wrap gap-1.5" aria-label="Plan">
                    <Link
                        v-for="plan in plans"
                        :key="plan.label"
                        :href="
                            index({ query: query(filters.search, plan.value) })
                                .url
                        "
                        preserve-scroll
                        :aria-current="
                            filters.plan === plan.value ? 'page' : undefined
                        "
                        :class="
                            cn(
                                'inline-flex items-center rounded-full border px-3 py-1.5 text-sm font-medium transition-colors',
                                filters.plan === plan.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'border-border text-muted-foreground hover:bg-muted hover:text-foreground',
                            )
                        "
                    >
                        {{ plan.label }}
                    </Link>
                </nav>

                <div class="relative md:w-72">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        placeholder="Name or email"
                        aria-label="Search users"
                        class="pl-9"
                    />
                </div>
            </div>

            <div
                v-if="users.data.length === 0"
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border py-16 text-center"
            >
                <Users class="mb-3 size-10 text-muted-foreground/40" />
                <p class="text-muted-foreground">
                    {{
                        filters.search
                            ? 'No accounts match your search.'
                            : 'No accounts here.'
                    }}
                </p>
            </div>

            <template v-else>
                <!-- Phones: one card per account -->
                <ul class="space-y-3 md:hidden">
                    <li
                        v-for="user in users.data"
                        :key="user.id"
                        class="rounded-xl border border-border bg-card p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="flex flex-wrap items-center gap-2 font-medium"
                                >
                                    <span class="truncate">{{
                                        user.name
                                    }}</span>
                                    <Badge
                                        v-if="user.is_admin"
                                        variant="outline"
                                        >Admin</Badge
                                    >
                                </p>
                                <p
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    {{ user.email }}
                                </p>
                            </div>
                            <Badge
                                v-if="user.premium"
                                variant="secondary"
                                class="shrink-0"
                                >Premium</Badge
                            >
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Joined {{ formatDate(user.created_at) }} ·
                            {{ user.pigeons_count }}
                            {{ user.pigeons_count === 1 ? 'bird' : 'birds' }}
                        </p>
                        <div
                            class="mt-3 flex items-center justify-between gap-3 border-t border-border pt-3"
                        >
                            <span class="min-w-0 text-sm">{{
                                planLabel(user)
                            }}</span>
                            <Button
                                variant="outline"
                                size="sm"
                                class="shrink-0"
                                @click="openPremium(user)"
                            >
                                <Crown class="size-4" /> Set Premium
                            </Button>
                        </div>
                    </li>
                </ul>

                <!-- Wider screens: a table, scrolling inside its own box if it must -->
                <div
                    class="hidden overflow-hidden rounded-xl border border-border bg-card md:block"
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Account</TableHead>
                                <TableHead>Joined</TableHead>
                                <TableHead class="text-right">Birds</TableHead>
                                <TableHead>Plan</TableHead>
                                <TableHead class="w-0"
                                    ><span class="sr-only"
                                        >Actions</span
                                    ></TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="user in users.data" :key="user.id">
                                <TableCell class="max-w-72">
                                    <p
                                        class="flex items-center gap-2 font-medium"
                                    >
                                        <span class="truncate">{{
                                            user.name
                                        }}</span>
                                        <Badge
                                            v-if="user.is_admin"
                                            variant="outline"
                                            >Admin</Badge
                                        >
                                    </p>
                                    <p class="truncate text-muted-foreground">
                                        {{ user.email }}
                                    </p>
                                </TableCell>
                                <TableCell class="whitespace-nowrap">{{
                                    formatDate(user.created_at)
                                }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{
                                    user.pigeons_count
                                }}</TableCell>
                                <TableCell class="whitespace-nowrap">
                                    <span
                                        :class="
                                            user.premium
                                                ? 'font-medium'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ planLabel(user) }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openPremium(user)"
                                    >
                                        <Crown class="size-4" /> Set Premium
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </template>

            <Pager :page="users" noun="accounts" />
        </div>

        <Dialog v-model:open="showPremium">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Set Premium</DialogTitle>
                    <DialogDescription v-if="editing">
                        {{ editing.name }} · {{ planLabel(editing) }}. Premium
                        lasts to the end of the day you pick. This doesn't
                        record a payment.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="savePremium()">
                    <div class="grid gap-2">
                        <Label for="premium-until">Premium until</Label>
                        <Input
                            id="premium-until"
                            v-model="form.premium_until"
                            type="date"
                            :min="tomorrow"
                            required
                        />
                        <InputError :message="form.errors.premium_until" />
                    </div>
                    <DialogFooter class="gap-2 sm:justify-between">
                        <Button
                            v-if="editing?.premium"
                            type="button"
                            variant="ghost"
                            class="text-destructive hover:text-destructive"
                            :disabled="form.processing"
                            @click="savePremium(true)"
                        >
                            Turn off Premium
                        </Button>
                        <span v-else />
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="editing = null"
                                >Cancel</Button
                            >
                            <Button
                                type="submit"
                                :disabled="
                                    form.processing || !form.premium_until
                                "
                            >
                                <Spinner v-if="form.processing" />
                                Save
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
