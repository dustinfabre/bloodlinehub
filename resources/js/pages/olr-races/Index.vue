<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface OlrRace {
    id: number;
    name: string;
    organizer: string | null;
    location: string | null;
    country: string | null;
    year: number | null;
    start_date: string | null;
    end_date: string | null;
    status: string;
    pigeons_count: number;
}

const props = defineProps<{
    olrRaces: OlrRace[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'OLR Races', href: '/olr-races' },
];

const handleDelete = (race: OlrRace) => {
    if (!window.confirm(`Delete "${race.name}"? This action cannot be undone.`)) return;
    router.delete(`/olr-races/${race.id}`, {
        preserveScroll: true,
    });
};

const statusBadgeVariant = (status: string) => {
    if (status === 'active') return 'default';
    if (status === 'completed') return 'secondary';
    return 'destructive';
};

const formatDate = (value: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};
</script>

<template>
    <Head title="OLR Races" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">
                        OLR Races
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Manage your One Loft Race entries.
                    </p>
                </div>
                <Button as-child>
                    <Link href="/olr-races/create">
                        Add OLR Race
                    </Link>
                </Button>
            </div>

            <Card class="flex-1">
                <CardHeader>
                    <CardTitle>Your OLR Races</CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="props.olrRaces.length === 0"
                        class="flex flex-col items-center justify-center rounded-lg border border-dashed border-primary/20 bg-primary/5 py-16 text-center"
                    >
                        <h2 class="text-lg font-semibold text-primary">
                            No OLR races yet
                        </h2>
                        <p class="mt-2 max-w-md text-sm text-muted-foreground">
                            Start by adding your first One Loft Race to track your entries.
                        </p>
                        <Button as-child class="mt-6">
                            <Link href="/olr-races/create">
                                Add OLR Race
                            </Link>
                        </Button>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b text-left text-sm text-muted-foreground">
                                    <th class="pb-3 font-medium">Name</th>
                                    <th class="pb-3 font-medium">Location</th>
                                    <th class="pb-3 font-medium">Year</th>
                                    <th class="pb-3 font-medium">Dates</th>
                                    <th class="pb-3 font-medium">Entries</th>
                                    <th class="pb-3 font-medium">Status</th>
                                    <th class="pb-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="race in props.olrRaces"
                                    :key="race.id"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="py-4">
                                        <Link
                                            :href="`/olr-races/${race.id}`"
                                            class="font-medium text-foreground hover:text-primary hover:underline"
                                        >
                                            {{ race.name }}
                                        </Link>
                                        <p v-if="race.organizer" class="text-sm text-muted-foreground">
                                            {{ race.organizer }}
                                        </p>
                                    </td>
                                    <td class="py-4 text-sm">
                                        <span v-if="race.location || race.country">
                                            {{ [race.location, race.country].filter(Boolean).join(', ') }}
                                        </span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </td>
                                    <td class="py-4 text-sm">
                                        {{ race.year || '-' }}
                                    </td>
                                    <td class="py-4 text-sm">
                                        <span v-if="race.start_date">
                                            {{ formatDate(race.start_date) }}
                                            <span v-if="race.end_date"> - {{ formatDate(race.end_date) }}</span>
                                        </span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </td>
                                    <td class="py-4 text-sm">
                                        {{ race.pigeons_count }} pigeon{{ race.pigeons_count !== 1 ? 's' : '' }}
                                    </td>
                                    <td class="py-4">
                                        <Badge :variant="statusBadgeVariant(race.status)">
                                            {{ race.status }}
                                        </Badge>
                                    </td>
                                    <td class="py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Button variant="outline" size="sm" as-child>
                                                <Link :href="`/olr-races/${race.id}`">
                                                    View
                                                </Link>
                                            </Button>
                                            <Button variant="outline" size="sm" as-child>
                                                <Link :href="`/olr-races/${race.id}/edit`">
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                @click="handleDelete(race)"
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
