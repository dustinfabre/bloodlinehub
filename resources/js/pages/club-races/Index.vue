<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface ClubRace {
    id: number;
    name: string;
    club_name: string | null;
    release_point: string | null;
    distance: number | null;
    distance_unit: string;
    race_date: string | null;
    release_time: string | null;
    status: string;
    pigeons_count: number;
}

const props = defineProps<{
    clubRaces: ClubRace[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Club Races', href: '/club-races' },
];

const handleDelete = (race: ClubRace) => {
    if (!window.confirm(`Delete "${race.name}"? This action cannot be undone.`)) return;
    router.delete(`/club-races/${race.id}`, {
        preserveScroll: true,
    });
};

const statusBadgeVariant = (status: string) => {
    if (status === 'upcoming') return 'default';
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

const formatDistance = (distance: number | null, unit: string) => {
    if (!distance) return '-';
    return `${distance} ${unit}`;
};
</script>

<template>
    <Head title="Club Races" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">
                        Club Races
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Track your local club race results.
                    </p>
                </div>
                <Button as-child>
                    <Link href="/club-races/create">
                        Add Club Race
                    </Link>
                </Button>
            </div>

            <Card class="flex-1">
                <CardHeader>
                    <CardTitle>Your Club Races</CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="props.clubRaces.length === 0"
                        class="flex flex-col items-center justify-center rounded-lg border border-dashed border-primary/20 bg-primary/5 py-16 text-center"
                    >
                        <h2 class="text-lg font-semibold text-primary">
                            No club races yet
                        </h2>
                        <p class="mt-2 max-w-md text-sm text-muted-foreground">
                            Start by adding your first club race to track results.
                        </p>
                        <Button as-child class="mt-6">
                            <Link href="/club-races/create">
                                Add Club Race
                            </Link>
                        </Button>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b text-left text-sm text-muted-foreground">
                                    <th class="pb-3 font-medium">Race</th>
                                    <th class="pb-3 font-medium">Release Point</th>
                                    <th class="pb-3 font-medium">Distance</th>
                                    <th class="pb-3 font-medium">Date</th>
                                    <th class="pb-3 font-medium">Entries</th>
                                    <th class="pb-3 font-medium">Status</th>
                                    <th class="pb-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="race in props.clubRaces"
                                    :key="race.id"
                                    class="border-b last:border-b-0"
                                >
                                    <td class="py-4">
                                        <Link
                                            :href="`/club-races/${race.id}`"
                                            class="font-medium text-foreground hover:text-primary hover:underline"
                                        >
                                            {{ race.name }}
                                        </Link>
                                        <p v-if="race.club_name" class="text-sm text-muted-foreground">
                                            {{ race.club_name }}
                                        </p>
                                    </td>
                                    <td class="py-4 text-sm">
                                        {{ race.release_point || '-' }}
                                    </td>
                                    <td class="py-4 text-sm">
                                        {{ formatDistance(race.distance, race.distance_unit) }}
                                    </td>
                                    <td class="py-4 text-sm">
                                        {{ formatDate(race.race_date) }}
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
                                                <Link :href="`/club-races/${race.id}`">
                                                    View
                                                </Link>
                                            </Button>
                                            <Button variant="outline" size="sm" as-child>
                                                <Link :href="`/club-races/${race.id}/edit`">
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
