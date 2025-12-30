<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Plus, Globe, MapPin, Users, Pencil, Trash2, Eye } from 'lucide-vue-next';

interface Club {
    id: number;
    name: string;
    location: string | null;
    country: string | null;
    website: string | null;
    description: string | null;
    status: string;
    seasons_count: number;
}

defineProps<{
    clubs: Club[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
];

const handleDelete = (club: Club) => {
    if (!confirm(`Delete "${club.name}"? This will delete all seasons and race data.`)) return;
    router.delete(`/clubs/${club.id}`);
};
</script>

<template>
    <Head title="Clubs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">Clubs</h1>
                    <p class="text-sm text-muted-foreground">Manage your racing clubs</p>
                </div>
                <Button as-child>
                    <Link href="/clubs/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Club
                    </Link>
                </Button>
            </div>

            <div v-if="clubs.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed border-primary/20 bg-primary/5 py-16 text-center">
                <Users class="h-12 w-12 text-primary/60" />
                <h3 class="mt-4 text-lg font-semibold text-primary">No Clubs yet</h3>
                <p class="mt-2 max-w-md text-sm text-muted-foreground">
                    Get started by adding your first racing club to track seasons and race results.
                </p>
                <Button class="mt-4" as-child>
                    <Link href="/clubs/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Club
                    </Link>
                </Button>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="club in clubs" :key="club.id" class="group relative overflow-hidden transition-shadow hover:shadow-md">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <CardTitle class="truncate">
                                    <Link :href="`/clubs/${club.id}`" class="hover:text-primary hover:underline">
                                        {{ club.name }}
                                    </Link>
                                </CardTitle>
                            </div>
                            <Badge :variant="club.status === 'active' ? 'default' : 'secondary'">
                                {{ club.status }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2 text-sm text-muted-foreground">
                            <div v-if="club.location || club.country" class="flex items-center gap-2">
                                <MapPin class="h-4 w-4" />
                                <span>{{ [club.location, club.country].filter(Boolean).join(', ') }}</span>
                            </div>
                            <div v-if="club.website" class="flex items-center gap-2">
                                <Globe class="h-4 w-4" />
                                <a :href="club.website" target="_blank" class="text-primary hover:underline truncate">
                                    {{ club.website }}
                                </a>
                            </div>
                            <div class="flex items-center gap-2 pt-2 border-t">
                                <span class="font-medium text-foreground">{{ club.seasons_count }}</span>
                                <span>{{ club.seasons_count === 1 ? 'Season' : 'Seasons' }}</span>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-2 sm:opacity-0 sm:transition-opacity sm:group-hover:opacity-100">
                            <Button variant="default" size="sm" as-child>
                                <Link :href="`/clubs/${club.id}`">
                                    <Eye class="mr-1 h-3 w-3" />
                                    View
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="`/clubs/${club.id}/edit`">
                                    <Pencil class="mr-1 h-3 w-3" />
                                    Edit
                                </Link>
                            </Button>
                            <Button variant="destructive" size="sm" @click="handleDelete(club)">
                                <Trash2 class="mr-1 h-3 w-3" />
                                Delete
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
