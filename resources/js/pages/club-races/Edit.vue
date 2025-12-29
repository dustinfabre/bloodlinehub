<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
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
    description: string | null;
    weather_conditions: string | null;
    wind_direction: string | null;
    status: string;
}

const props = defineProps<{
    clubRace: ClubRace;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Club Races', href: '/club-races' },
    { title: props.clubRace.name, href: `/club-races/${props.clubRace.id}` },
    { title: 'Edit', href: `/club-races/${props.clubRace.id}/edit` },
];

const form = useForm({
    name: props.clubRace.name,
    club_name: props.clubRace.club_name || '',
    release_point: props.clubRace.release_point || '',
    distance: props.clubRace.distance || '',
    distance_unit: props.clubRace.distance_unit || 'km',
    race_date: props.clubRace.race_date || '',
    release_time: props.clubRace.release_time || '',
    description: props.clubRace.description || '',
    weather_conditions: props.clubRace.weather_conditions || '',
    wind_direction: props.clubRace.wind_direction || '',
    status: props.clubRace.status,
});

const submit = () => {
    form.put(`/club-races/${props.clubRace.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Edit ${clubRace.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    Edit Club Race
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Update the details of this club race.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Race Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="name">Race Name *</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="e.g., Weekly Training Race"
                                    required
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="club_name">Club Name</Label>
                                <Input
                                    id="club_name"
                                    v-model="form.club_name"
                                    type="text"
                                    placeholder="e.g., Metro Pigeon Club"
                                />
                                <InputError :message="form.errors.club_name" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="release_point">Release Point</Label>
                                <Input
                                    id="release_point"
                                    v-model="form.release_point"
                                    type="text"
                                    placeholder="e.g., North Station"
                                />
                                <InputError :message="form.errors.release_point" />
                            </div>

                            <div class="space-y-2">
                                <Label for="distance">Distance</Label>
                                <Input
                                    id="distance"
                                    v-model="form.distance"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="e.g., 150"
                                />
                                <InputError :message="form.errors.distance" />
                            </div>

                            <div class="space-y-2">
                                <Label for="distance_unit">Unit</Label>
                                <select
                                    id="distance_unit"
                                    v-model="form.distance_unit"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="km">Kilometers</option>
                                    <option value="miles">Miles</option>
                                </select>
                                <InputError :message="form.errors.distance_unit" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="race_date">Race Date</Label>
                                <Input
                                    id="race_date"
                                    v-model="form.race_date"
                                    type="date"
                                />
                                <InputError :message="form.errors.race_date" />
                            </div>

                            <div class="space-y-2">
                                <Label for="release_time">Release Time</Label>
                                <Input
                                    id="release_time"
                                    v-model="form.release_time"
                                    type="time"
                                />
                                <InputError :message="form.errors.release_time" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="weather_conditions">Weather Conditions</Label>
                                <Input
                                    id="weather_conditions"
                                    v-model="form.weather_conditions"
                                    type="text"
                                    placeholder="e.g., Clear, sunny"
                                />
                                <InputError :message="form.errors.weather_conditions" />
                            </div>

                            <div class="space-y-2">
                                <Label for="wind_direction">Wind Direction</Label>
                                <Input
                                    id="wind_direction"
                                    v-model="form.wind_direction"
                                    type="text"
                                    placeholder="e.g., NW 15 km/h"
                                />
                                <InputError :message="form.errors.wind_direction" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Additional details about the race..."
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="space-y-2">
                            <Label for="status">Status</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="upcoming">Upcoming</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-4">
                    <Button type="button" variant="outline" as-child>
                        <a :href="`/club-races/${clubRace.id}`">Cancel</a>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
