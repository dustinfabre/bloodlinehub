<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface OlrRace {
    id: number;
    name: string;
}

interface OlrSeason {
    id: number;
    name: string;
}

const props = defineProps<{
    olrRace: OlrRace;
    season: OlrSeason;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'OLR Races', href: '/olr-races' },
    { title: props.olrRace.name, href: `/olr-races/${props.olrRace.id}` },
    { title: props.season.name, href: `/olr-races/${props.olrRace.id}/seasons/${props.season.id}` },
    { title: 'Create Race', href: `/olr-races/${props.olrRace.id}/seasons/${props.season.id}/races/create` },
];

const form = useForm({
    name: '',
    release_point: '',
    distance: '',
    distance_unit: 'km',
    race_date: '',
    release_time: '',
    weather_conditions: '',
    wind_direction: '',
    notes: '',
});

const submit = () => {
    form.post(`/olr-races/${props.olrRace.id}/seasons/${props.season.id}/races`);
};
</script>

<template>
    <Head title="Create Race" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Create Race</h1>
                <p class="text-sm text-muted-foreground">Add a new race to {{ season.name }}</p>
            </div>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Race Details</CardTitle>
                    <CardDescription>Enter the details for this race</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="name">Race Name *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                required
                                placeholder="e.g., Training Race 1"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="release_point">Release Point</Label>
                            <Input
                                id="release_point"
                                v-model="form.release_point"
                                placeholder="e.g., Lucena City"
                            />
                            <InputError :message="form.errors.release_point" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="distance">Distance</Label>
                                <Input
                                    id="distance"
                                    v-model="form.distance"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="e.g., 100"
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
                                    <option value="km">Kilometers (km)</option>
                                    <option value="mi">Miles (mi)</option>
                                </select>
                                <InputError :message="form.errors.distance_unit" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
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

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="weather_conditions">Weather Conditions</Label>
                                <Input
                                    id="weather_conditions"
                                    v-model="form.weather_conditions"
                                    placeholder="e.g., Sunny, clear skies"
                                />
                                <InputError :message="form.errors.weather_conditions" />
                            </div>

                            <div class="space-y-2">
                                <Label for="wind_direction">Wind Direction</Label>
                                <Input
                                    id="wind_direction"
                                    v-model="form.wind_direction"
                                    placeholder="e.g., NE 10km/h"
                                />
                                <InputError :message="form.errors.wind_direction" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="notes">Notes</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Additional notes about this race..."
                            />
                            <InputError :message="form.errors.notes" />
                        </div>

                        <div class="flex gap-3">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Creating...' : 'Create Race' }}
                            </Button>
                            <Button type="button" variant="outline" as-child>
                                <Link :href="`/olr-races/${olrRace.id}/seasons/${season.id}`">Cancel</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
