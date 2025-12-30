<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Plus, Calendar, Users, Flag, Globe, MapPin, Pencil, Trash2 } from 'lucide-vue-next';

interface ClubSeason {
    id: number;
    name: string;
    year: number;
    start_date: string | null;
    end_date: string | null;
    status: string;
    entries_count: number;
    races_count: number;
}

interface Club {
    id: number;
    name: string;
    location: string | null;
    country: string | null;
    website: string | null;
    description: string | null;
    status: string;
    seasons: ClubSeason[];
}

const props = defineProps<{
    club: Club;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
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

const handleDeleteSeason = (season: ClubSeason) => {
    if (!confirm(`Delete season "${season.name}"? This will delete all entries and race data.`)) return;
    router.delete(`/clubs/${props.club.id}/seasons/${season.id}`);
};

const handleDelete = () => {
    if (!confirm(`Delete "${props.club.name}"? This will delete all seasons and race data.`)) return;
    router.delete(`/clubs/${props.club.id}`);
};
</script>

<template>
    <Head :title="club.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-semibold text-foreground">{{ club.name }}</h1>
                        <Badge :variant="club.status === 'active' ? 'default' : 'secondary'">
                            {{ club.status }}
                        </Badge>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="`/clubs/${club.id}/edit`">
                            <Pencil class="mr-2 h-4 w-4" />
                            Edit
                        </Link>
                    </Button>
                    <Button variant="destructive" @click="handleDelete">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </div>
            </div>

            <!-- Club Details -->
            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-if="club.location || club.country">
                            <dt class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <MapPin class="h-4 w-4" />
                                Location
                            </dt>
                            <dd class="mt-1 text-sm text-foreground">
                                {{ [club.location, club.country].filter(Boolean).join(', ') }}
                            </dd>
                        </div>
                        <div v-if="club.website">
                            <dt class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                <Globe class="h-4 w-4" />
                                Website
                            </dt>
                            <dd class="mt-1 text-sm">
                                <a :href="club.website" target="_blank" class="text-primary hover:underline">
                                    {{ club.website }}
                                </a>
                            </dd>
                        </div>
                    </dl>
                    <div v-if="club.description" class="mt-4 border-t pt-4">
                        <dt class="text-sm font-medium text-muted-foreground">Description</dt>
                        <dd class="mt-1 text-sm text-foreground whitespace-pre-wrap">{{ club.description }}</dd>
                    </div>
                </CardContent>
            </Card>

            <!-- Seasons -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Seasons</CardTitle>
                        <CardDescription>Manage racing seasons for this club</CardDescription>
                    </div>
                    <Button as-child>
                        <Link :href="`/clubs/${club.id}/seasons/create`">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Season
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <div v-if="club.seasons.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center">
                        <Calendar class="h-10 w-10 text-muted-foreground/60" />
                        <h3 class="mt-4 font-semibold">No seasons yet</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Add a season to start tracking entries and races.</p>
                        <Button class="mt-4" as-child>
                            <Link :href="`/clubs/${club.id}/seasons/create`">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Season
                            </Link>
                        </Button>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="season in club.seasons"
                            :key="season.id"
                            class="flex items-center justify-between rounded-lg border p-4 transition-colors hover:bg-muted/50"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="`/clubs/${club.id}/seasons/${season.id}`"
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
                                    <Link :href="`/clubs/${club.id}/seasons/${season.id}`">
                                        View
                                    </Link>
                                </Button>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="`/clubs/${club.id}/seasons/${season.id}/edit`">
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
