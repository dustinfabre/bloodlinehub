<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface Club {
    id: number;
    name: string;
}

interface ClubSeason {
    id: number;
    name: string;
}

interface ClubSeasonRace {
    id: number;
    release_point: string;
    distance: number;
    distance_unit: string;
    race_date: string;
    release_time: string | null;
    weather_conditions: string | null;
    wind_direction: string | null;
}

const props = defineProps<{
    club: Club;
    season: ClubSeason;
    race: ClubSeasonRace;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: props.season.name, href: `/clubs/${props.club.id}/seasons/${props.season.id}` },
    { title: `${props.race.release_point} ${props.race.distance}${props.race.distance_unit}`, href: `/clubs/${props.club.id}/seasons/${props.season.id}/races/${props.race.id}` },
    { title: 'Edit', href: `/clubs/${props.club.id}/seasons/${props.season.id}/races/${props.race.id}/edit` },
];

const form = useForm({
    release_point: props.race.release_point,
    distance: props.race.distance,
    distance_unit: props.race.distance_unit,
    race_date: props.race.race_date,
    release_time: props.race.release_time || '',
    weather_conditions: props.race.weather_conditions || '',
    wind_direction: props.race.wind_direction || '',
});

const handleSubmit = () => {
    form.put(`/clubs/${props.club.id}/seasons/${props.season.id}/races/${props.race.id}`);
};
</script>

<template>
    <Head :title="`Edit Race - ${season.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Edit Race</h1>
                <p class="text-muted-foreground">Update race details for {{ season.name }}</p>
            </div>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Race Details</CardTitle>
                    <CardDescription>Modify the information for this race.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="handleSubmit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="release_point">Release Point *</Label>
                            <Input
                                id="release_point"
                                v-model="form.release_point"
                                placeholder="e.g., Lucena"
                                required
                            />
                            <p v-if="form.errors.release_point" class="text-sm text-destructive">{{ form.errors.release_point }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="distance">Distance *</Label>
                                <Input
                                    id="distance"
                                    v-model="form.distance"
                                    type="number"
                                    step="0.01"
                                    placeholder="e.g., 100"
                                    required
                                />
                                <p v-if="form.errors.distance" class="text-sm text-destructive">{{ form.errors.distance }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="distance_unit">Unit</Label>
                                <Select v-model="form.distance_unit">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select unit" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="km">Kilometers (km)</SelectItem>
                                        <SelectItem value="mi">Miles (mi)</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.distance_unit" class="text-sm text-destructive">{{ form.errors.distance_unit }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="race_date">Race Date *</Label>
                                <Input
                                    id="race_date"
                                    v-model="form.race_date"
                                    type="date"
                                    required
                                />
                                <p v-if="form.errors.race_date" class="text-sm text-destructive">{{ form.errors.race_date }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="release_time">Release Time</Label>
                                <Input
                                    id="release_time"
                                    v-model="form.release_time"
                                    type="time"
                                />
                                <p v-if="form.errors.release_time" class="text-sm text-destructive">{{ form.errors.release_time }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="weather_conditions">Weather Conditions</Label>
                                <Input
                                    id="weather_conditions"
                                    v-model="form.weather_conditions"
                                    placeholder="e.g., Clear, Sunny"
                                />
                                <p v-if="form.errors.weather_conditions" class="text-sm text-destructive">{{ form.errors.weather_conditions }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="wind_direction">Wind Direction</Label>
                                <Input
                                    id="wind_direction"
                                    v-model="form.wind_direction"
                                    placeholder="e.g., NE, SW"
                                />
                                <p v-if="form.errors.wind_direction" class="text-sm text-destructive">{{ form.errors.wind_direction }}</p>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <Button type="submit" :disabled="form.processing">
                                Update Race
                            </Button>
                            <Button type="button" variant="outline" as-child>
                                <Link :href="`/clubs/${club.id}/seasons/${season.id}/races/${race.id}`">Cancel</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
