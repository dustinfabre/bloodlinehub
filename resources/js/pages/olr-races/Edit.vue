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
    organizer: string | null;
    location: string | null;
    country: string | null;
    website: string | null;
    description: string | null;
    status: string;
}

const props = defineProps<{
    olrRace: OlrRace;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'OLR Races', href: '/olr-races' },
    { title: props.olrRace.name, href: `/olr-races/${props.olrRace.id}` },
    { title: 'Edit', href: `/olr-races/${props.olrRace.id}/edit` },
];

const form = useForm({
    name: props.olrRace.name,
    organizer: props.olrRace.organizer || '',
    location: props.olrRace.location || '',
    country: props.olrRace.country || '',
    website: props.olrRace.website || '',
    description: props.olrRace.description || '',
    status: props.olrRace.status,
});

const submit = () => {
    form.patch(`/olr-races/${props.olrRace.id}`);
};
</script>

<template>
    <Head :title="`Edit ${olrRace.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Edit OLR Race</h1>
                <p class="text-sm text-muted-foreground">Update {{ olrRace.name }}</p>
            </div>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>OLR Race Details</CardTitle>
                    <CardDescription>Update the details of the OLR race organization</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="name">Name *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                required
                                placeholder="e.g., South African Million Dollar Race"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="organizer">Organizer</Label>
                            <Input
                                id="organizer"
                                v-model="form.organizer"
                                placeholder="e.g., SAMDR Organization"
                            />
                            <InputError :message="form.errors.organizer" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="location">Location</Label>
                                <Input
                                    id="location"
                                    v-model="form.location"
                                    placeholder="e.g., Johannesburg"
                                />
                                <InputError :message="form.errors.location" />
                            </div>

                            <div class="space-y-2">
                                <Label for="country">Country</Label>
                                <Input
                                    id="country"
                                    v-model="form.country"
                                    placeholder="e.g., South Africa"
                                />
                                <InputError :message="form.errors.country" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="website">Website</Label>
                            <Input
                                id="website"
                                v-model="form.website"
                                type="url"
                                placeholder="https://example.com"
                            />
                            <InputError :message="form.errors.website" />
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                placeholder="Brief description of the OLR race..."
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="space-y-2">
                            <Label for="status">Status *</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                required
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>

                        <div class="flex gap-3">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                            </Button>
                            <Button type="button" variant="outline" as-child>
                                <Link :href="`/olr-races/${olrRace.id}`">Cancel</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
