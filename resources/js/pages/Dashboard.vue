<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Bird, TrendingUp, Calendar } from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card class="bg-gradient-to-br from-primary/10 via-primary/5 to-background border-primary/20">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Pigeons</CardTitle>
                        <Bird class="h-4 w-4 text-primary" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-primary">{{ stats.total_pigeons }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Active birds in your collection
                        </p>
                    </CardContent>
                </Card>

                <Card class="bg-gradient-to-br from-blue-500/10 via-blue-500/5 to-background border-blue-500/20">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">This Month</CardTitle>
                        <TrendingUp class="h-4 w-4 text-blue-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-blue-600">0</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            New additions this month
                        </p>
                    </CardContent>
                </Card>

                <Card class="bg-gradient-to-br from-sky-500/10 via-sky-500/5 to-background border-sky-500/20">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Recent Activity</CardTitle>
                        <Calendar class="h-4 w-4 text-sky-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-sky-600">{{ stats.recent_pigeons.length }}</div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Latest records updated
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Pigeons -->
            <Card class="flex-1">
                <CardHeader>
                    <CardTitle>Recent Pigeons</CardTitle>
                    <CardDescription>Your most recently added pigeons</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="stats.recent_pigeons.length > 0" class="space-y-4">
                        <div
                            v-for="pigeon in stats.recent_pigeons"
                            :key="pigeon.id"
                            class="flex items-center justify-between p-4 rounded-lg border border-border bg-card hover:bg-accent/50 transition-colors"
                        >
                            <div class="flex items-center gap-4">
                                <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                                    <Bird class="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p class="font-medium">
                                        {{ pigeon.ring_number || pigeon.personal_number || `Pigeon #${pigeon.id}` }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ pigeon.color || 'No color specified' }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ formatDate(pigeon.created_at) }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <Bird class="h-16 w-16 text-muted-foreground/50 mb-4" />
                        <h3 class="text-lg font-semibold mb-2">No pigeons yet</h3>
                        <p class="text-sm text-muted-foreground mb-4">
                            Start building your collection by adding your first pigeon
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
