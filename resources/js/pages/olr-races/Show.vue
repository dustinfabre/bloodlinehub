<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Plus, Calendar, Users, Flag, Globe, MapPin, Pencil, Trash2 } from 'lucide-vue-next';

interface OlrSeason {
    id: number;
    name: string;
    year: number;
    start_date: string | null;
    end_date: string | null;
    status: string;
    entries_count: number;
    races_count: number;
}

interface OlrRace {
    id: number;
    name: string;
    organizer: string | null;
    location: string | null;
    country: string | null;
    website: string | null;
    description: string | null;
    status: string;
    seasons: OlrSeason[];
}

const props = defineProps<{
    olrRace: OlrRace;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'OLR Races', href: '/olr-races' },
    { title: props.olrRace.name, href: `/olr-races/${props.olrRace.id}` },
];

const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const statusBadgeVariant = (status: string) => {
    if (status === 'active') return 'default';
    if (status === 'completed') return 'secondary';
    return 'destructive';
};

const handleDeleteSeason = (season: OlrSeason) => {
    if (!confirm(`Delete season "${season.name}"? This will delete all entries and race data.`)) return;
    router.delete(`/olr-races/${props.olrRace.id}/seasons/${season.id}`);
};

const handleDelete = () => {
    if (!confirm(`Delete "${props.olrRace.name}"? This will delete all seasons and race data.`)) return;
    router.delete(`/olr-races/${props.olrRace.id}`);
};
</script>

<template>
    <Head :title="olrRace.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-semibold text-foreground">{{ olrRace.name }}</h1>
                        <Badge :variant="olrRace.status === 'active' ? 'default' : 'secondary'">
                            {{ olrRace.status }}
                        </Badge>
                    </div>
                    <p v-if="olrRace.organizer" class="mt-1 text-sm text-muted-foreground">
                        {{ olrRace.organizer }}
                    </p>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <Button variant="outline" as-child class="flex-1 sm:flex-initial">
                        <Link :href="`/olr-races/${olrRace.id}/edit`">
                            <Pencil class="mr-2 h-4 w-4" />
                            Edit
                        </Link>
                    </Button>
                    <Button variant="destructive" @click="handleDelete" class="flex-1 sm:flex-initial">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </div>
            </div>

            <!-- OLR Race Details -->
            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-if="olrRace.location || olrRace.country">
                            <dt class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <MapPin class="h-4 w-4" />
                                Location
                            </dt>
                            <dd class="mt-1 text-sm text-foreground">
                                {{ [olrRace.location, olrRace.country].filter(Boolean).join(', ') }}
                            </dd>
                        </div>
                        <div v-if="olrRace.website">
                            <dt class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Globe class="h-4 w-4" />
                                Website
                            </dt>
                            <dd class="mt-1 text-sm">
                                <a :href="olrRace.website" target="_blank" class="text-primary hover:underline">
                                    {{ olrRace.website }}
                                </a>
                            </dd>
                        </div>
                    </dl>
                    <div v-if="olrRace.description" class="mt-4 border-t pt-4">
                        <dt class="text-sm font-medium text-muted-foreground">Description</dt>
                        <dd class="mt-1 text-sm text-foreground whitespace-pre-wrap">{{ olrRace.description }}</dd>
                    </div>
                </CardContent>
            </Card>

            <!-- Seasons -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Seasons</CardTitle>
                        <CardDescription>Manage racing seasons for this OLR</CardDescription>
                    </div>
                    <Button as-child>
                        <Link :href="`/olr-races/${olrRace.id}/seasons/create`">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Season
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <div v-if="olrRace.seasons.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center">
                        <Calendar class="h-10 w-10 text-muted-foreground/60" />
                        <h3 class="mt-4 font-semibold">No seasons yet</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Add a season to start tracking entries and races.</p>
                        <Button class="mt-4" as-child>
                            <Link :href="`/olr-races/${olrRace.id}/seasons/create`">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Season
                            </Link>
                        </Button>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="season in olrRace.seasons"
                            :key="season.id"
                            class="flex items-center justify-between rounded-lg border p-4 transition-colors hover:bg-muted/50"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="`/olr-races/${olrRace.id}/seasons/${season.id}`"
                                        class="font-medium hover:text-primary hover:underline"
                                    >
                                        {{ season.name }}
                                    </Link>
                                    <Badge :variant="statusBadgeVariant(season.status)" class="text-xs">
                                        {{ season.status }}
                                    </Badge>
                                </div>
                                <div class="mt-1 flex items-center gap-4 text-sm text-muted-foreground">
                                    <span class="flex items-center gap-1">
                                        <Calendar class="h-3 w-3" />
                                        {{ season.year }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <Users class="h-3 w-3" />
                                        {{ season.entries_count }} {{ season.entries_count === 1 ? 'entry' : 'entries' }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <Flag class="h-3 w-3" />
                                        {{ season.races_count }} {{ season.races_count === 1 ? 'race' : 'races' }}
                                    </span>
                                </div>
                                <div v-if="season.start_date || season.end_date" class="mt-1 text-xs text-muted-foreground">
                                    {{ formatDate(season.start_date) }} - {{ formatDate(season.end_date) }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="`/olr-races/${olrRace.id}/seasons/${season.id}`">
                                        View
                                    </Link>
                                </Button>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="`/olr-races/${olrRace.id}/seasons/${season.id}/edit`">
                                        Edit
                                    </Link>
                                </Button>
                                <Button variant="destructive" size="sm" @click="handleDeleteSeason(season)">
                                    Delete
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
