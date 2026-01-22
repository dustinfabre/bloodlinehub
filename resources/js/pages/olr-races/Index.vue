<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Plus, Globe, MapPin, Trophy, Pencil, Trash2, Eye } from 'lucide-vue-next';

interface OlrRace {
    id: number;
    name: string;
    organizer: string | null;
    location: string | null;
    country: string | null;
    website: string | null;
    description: string | null;
    status: string;
    seasons_count: number;
}

defineProps<{
    olrRaces: OlrRace[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'OLR Races', href: '/olr-races' },
];

const handleDelete = (race: OlrRace) => {
    if (!confirm(`Delete "${race.name}"? This will delete all seasons and race data.`)) return;
    router.delete(`/olr-races/${race.id}`);
};
</script>

<template>
    <Head title="OLR Races" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">OLR Races</h1>
                    <p class="text-sm text-muted-foreground">Manage your One Loft Racing organizations</p>
                </div>
                <Button as-child>
                    <Link href="/olr-races/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Add OLR Race
                    </Link>
                </Button>
            </div>

            <div v-if="olrRaces.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed border-primary/20 bg-primary/5 py-16 text-center">
                <Trophy class="h-12 w-12 text-primary/60" />
                <h3 class="mt-4 text-lg font-semibold text-primary">No OLR Races yet</h3>
                <p class="mt-2 max-w-md text-sm text-muted-foreground">
                    Get started by adding your first One Loft Racing organization to track seasons and race results.
                </p>
                <Button class="mt-4" as-child>
                    <Link href="/olr-races/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Add OLR Race
                    </Link>
                </Button>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="race in olrRaces" :key="race.id" class="overflow-hidden transition-shadow hover:shadow-md">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <CardTitle class="truncate">
                                    <Link :href="`/olr-races/${race.id}`" class="hover:text-primary hover:underline">
                                        {{ race.name }}
                                    </Link>
                                </CardTitle>
                                <CardDescription v-if="race.organizer" class="mt-1">
                                    {{ race.organizer }}
                                </CardDescription>
                            </div>
                            <Badge :variant="race.status === 'active' ? 'default' : 'secondary'">
                                {{ race.status }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2 text-sm text-muted-foreground">
                            <div v-if="race.location || race.country" class="flex items-center gap-2">
                                <MapPin class="h-4 w-4" />
                                <span>{{ [race.location, race.country].filter(Boolean).join(', ') }}</span>
                            </div>
                            <div v-if="race.website" class="flex items-center gap-2">
                                <Globe class="h-4 w-4" />
                                <a :href="race.website" target="_blank" class="text-primary hover:underline truncate">
                                    {{ race.website }}
                                </a>
                            </div>
                            <div class="flex items-center gap-2 pt-2 border-t">
                                <span class="font-medium text-foreground">{{ race.seasons_count }}</span>
                                <span>{{ race.seasons_count === 1 ? 'Season' : 'Seasons' }}</span>
                            </div>
                        </div>
                    </CardContent>
                    <CardFooter class="flex gap-2">
                        <Button variant="outline" size="sm" as-child class="flex-1">
                            <Link :href="`/olr-races/${race.id}`">View</Link>
                        </Button>
                        <Button variant="outline" size="sm" as-child class="flex-1">
                            <Link :href="`/olr-races/${race.id}/edit`">Edit</Link>
                        </Button>
                        <Button variant="destructive" size="sm" @click="handleDelete(race)">
                            Delete
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
