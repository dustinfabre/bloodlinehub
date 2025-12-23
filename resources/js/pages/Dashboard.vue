<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as pigeons } from '@/routes/pigeons';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Bird, TrendingUp, Users, ArrowRight, Plus } from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

interface Pigeon {
    id: number;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    created_at: string;
}

interface Props {
    stats: {
        total_pigeons: number;
        recent_pigeons: Pigeon[];
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-6">
            <!-- Welcome Header -->
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight text-foreground">
                    Welcome back
                </h1>
                <p class="text-muted-foreground">
                    Here's what's happening with your loft today.
                </p>
            </div>

            <!-- Stats Grid -->
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Card class="relative overflow-hidden border-l-4 border-l-primary bg-gradient-to-br from-primary/5 via-background to-background">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm font-medium text-muted-foreground">
                                Total Pigeons
                            </CardTitle>
                            <div class="rounded-lg bg-primary/10 p-2">
                                <Bird class="h-5 w-5 text-primary" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-4xl font-bold text-foreground">
                            {{ stats.total_pigeons }}
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Active birds in your collection
                        </p>
                    </CardContent>
                </Card>

                <Card class="relative overflow-hidden border-l-4 border-l-blue-500 bg-gradient-to-br from-blue-500/5 via-background to-background">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm font-medium text-muted-foreground">
                                This Month
                            </CardTitle>
                            <div class="rounded-lg bg-blue-500/10 p-2">
                                <TrendingUp class="h-5 w-5 text-blue-600" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-4xl font-bold text-foreground">
                            {{ stats.recent_pigeons.length }}
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            New additions this month
                        </p>
                    </CardContent>
                </Card>

                <Card class="relative overflow-hidden border-l-4 border-l-violet-500 bg-gradient-to-br from-violet-500/5 via-background to-background">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm font-medium text-muted-foreground">
                                Breeders
                            </CardTitle>
                            <div class="rounded-lg bg-violet-500/10 p-2">
                                <Users class="h-5 w-5 text-violet-600" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-4xl font-bold text-foreground">
                            0
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Active breeding pairs
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions -->
            <Card class="border-dashed">
                <CardHeader>
                    <CardTitle>Quick actions</CardTitle>
                    <CardDescription>
                        Common tasks to manage your loft
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Button as-child variant="outline" class="h-auto flex-col items-start gap-2 p-4">
                            <Link :href="pigeons().url">
                                <div class="flex w-full items-center justify-between">
                                    <div class="rounded-lg bg-primary/10 p-2">
                                        <Plus class="h-4 w-4 text-primary" />
                                    </div>
                                    <ArrowRight class="h-4 w-4 text-muted-foreground" />
                                </div>
                                <div class="space-y-1 text-left">
                                    <div class="font-semibold text-foreground">Add pigeon</div>
                                    <div class="text-xs text-muted-foreground">
                                        Register new bird
                                    </div>
                                </div>
                            </Link>
                        </Button>

                        <Button as-child variant="outline" class="h-auto flex-col items-start gap-2 p-4">
                            <Link :href="pigeons().url">
                                <div class="flex w-full items-center justify-between">
                                    <div class="rounded-lg bg-blue-500/10 p-2">
                                        <Bird class="h-4 w-4 text-blue-600" />
                                    </div>
                                    <ArrowRight class="h-4 w-4 text-muted-foreground" />
                                </div>
                                <div class="space-y-1 text-left">
                                    <div class="font-semibold text-foreground">View all pigeons</div>
                                    <div class="text-xs text-muted-foreground">
                                        Browse your loft
                                    </div>
                                </div>
                            </Link>
                        </Button>

                        <div class="flex h-auto flex-col items-start gap-2 rounded-lg border border-dashed border-border/50 bg-muted/20 p-4 opacity-60">
                            <div class="flex w-full items-center justify-between">
                                <div class="rounded-lg bg-muted p-2">
                                    <TrendingUp class="h-4 w-4 text-muted-foreground" />
                                </div>
                            </div>
                            <div class="space-y-1 text-left">
                                <div class="text-sm font-semibold text-foreground">Pedigree generator</div>
                                <div class="text-xs text-muted-foreground">
                                    Coming soon
                                </div>
                            </div>
                        </div>

                        <div class="flex h-auto flex-col items-start gap-2 rounded-lg border border-dashed border-border/50 bg-muted/20 p-4 opacity-60">
                            <div class="flex w-full items-center justify-between">
                                <div class="rounded-lg bg-muted p-2">
                                    <Users class="h-4 w-4 text-muted-foreground" />
                                </div>
                            </div>
                            <div class="space-y-1 text-left">
                                <div class="text-sm font-semibold text-foreground">Sales & auctions</div>
                                <div class="text-xs text-muted-foreground">
                                    Coming soon
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Pigeons -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Recent pigeons</CardTitle>
                        <CardDescription class="mt-1">
                            Your most recently added birds
                        </CardDescription>
                    </div>
                    <Button as-child variant="ghost" size="sm">
                        <Link :href="pigeons().url" class="gap-1">
                            View all
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <div v-if="stats.recent_pigeons.length > 0" class="space-y-3">
                        <div
                            v-for="pigeon in stats.recent_pigeons"
                            :key="pigeon.id"
                            class="group flex items-center justify-between rounded-lg border border-border/60 bg-card p-4 transition-all hover:border-primary/30 hover:bg-accent/30"
                        >
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                                    <Bird class="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <p class="font-semibold text-foreground">
                                        {{ pigeon.ring_number || pigeon.personal_number || `Pigeon #${pigeon.id}` }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ pigeon.color || 'No color specified' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-sm text-muted-foreground">
                                        {{ formatDate(pigeon.created_at) }}
                                    </p>
                                </div>
                                <ArrowRight class="h-4 w-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center rounded-lg border border-dashed border-primary/20 bg-primary/5 py-12 text-center">
                        <div class="rounded-full bg-primary/10 p-4">
                            <Bird class="h-10 w-10 text-primary" />
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-foreground">No pigeons yet</h3>
                        <p class="mt-2 max-w-sm text-sm text-muted-foreground">
                            Start building your collection by adding your first pigeon to the system
                        </p>
                        <Button as-child class="mt-6">
                            <Link :href="pigeons().url">
                                <Plus class="mr-2 h-4 w-4" />
                                Add your first pigeon
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
