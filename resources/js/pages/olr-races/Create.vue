<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'OLR Races', href: '/olr-races' },
    { title: 'Add OLR Race', href: '/olr-races/create' },
];

const form = useForm({
    name: '',
    organizer: '',
    location: '',
    country: '',
    year: new Date().getFullYear(),
    start_date: '',
    end_date: '',
    description: '',
    website_url: '',
    status: 'active',
});

const submit = () => {
    form.post('/olr-races', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Add OLR Race" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    Add OLR Race
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Create a new One Loft Race to track your entries.
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
                                    placeholder="e.g., South African Million Dollar"
                                    required
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="organizer">Organizer</Label>
                                <Input
                                    id="organizer"
                                    v-model="form.organizer"
                                    type="text"
                                    placeholder="e.g., SAMDPR"
                                />
                                <InputError :message="form.errors.organizer" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="location">Location</Label>
                                <Input
                                    id="location"
                                    v-model="form.location"
                                    type="text"
                                    placeholder="e.g., Sun City"
                                />
                                <InputError :message="form.errors.location" />
                            </div>

                            <div class="space-y-2">
                                <Label for="country">Country</Label>
                                <Input
                                    id="country"
                                    v-model="form.country"
                                    type="text"
                                    placeholder="e.g., South Africa"
                                />
                                <InputError :message="form.errors.country" />
                            </div>

                            <div class="space-y-2">
                                <Label for="year">Year</Label>
                                <Input
                                    id="year"
                                    v-model="form.year"
                                    type="number"
                                    min="1900"
                                    max="2100"
                                />
                                <InputError :message="form.errors.year" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="start_date">Start Date</Label>
                                <Input
                                    id="start_date"
                                    v-model="form.start_date"
                                    type="date"
                                />
                                <InputError :message="form.errors.start_date" />
                            </div>

                            <div class="space-y-2">
                                <Label for="end_date">End Date</Label>
                                <Input
                                    id="end_date"
                                    v-model="form.end_date"
                                    type="date"
                                />
                                <InputError :message="form.errors.end_date" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="website_url">Website URL</Label>
                            <Input
                                id="website_url"
                                v-model="form.website_url"
                                type="url"
                                placeholder="https://..."
                            />
                            <InputError :message="form.errors.website_url" />
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
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-4">
                    <Button type="button" variant="outline" as-child>
                        <a href="/olr-races">Cancel</a>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Race' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
