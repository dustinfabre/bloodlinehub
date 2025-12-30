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

const props = defineProps<{
    olrRace: OlrRace;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'OLR Races', href: '/olr-races' },
    { title: props.olrRace.name, href: `/olr-races/${props.olrRace.id}` },
    { title: 'Create Season', href: `/olr-races/${props.olrRace.id}/seasons/create` },
];

const currentYear = new Date().getFullYear();

const form = useForm({
    name: `Season ${currentYear}`,
    year: currentYear,
    start_date: '',
    end_date: '',
    status: 'active',
});

const submit = () => {
    form.post(`/olr-races/${props.olrRace.id}/seasons`);
};
</script>

<template>
    <Head title="Create Season" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Create Season</h1>
                <p class="text-sm text-muted-foreground">Add a new season to {{ olrRace.name }}</p>
            </div>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Season Details</CardTitle>
                    <CardDescription>Enter the details for this season</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="name">Name *</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    required
                                    placeholder="e.g., Season 2025"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="year">Year *</Label>
                                <Input
                                    id="year"
                                    v-model="form.year"
                                    type="number"
                                    required
                                    min="2000"
                                    max="2100"
                                />
                                <InputError :message="form.errors.year" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
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
                            <Label for="status">Status *</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                required
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>

                        <div class="flex gap-3">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Creating...' : 'Create Season' }}
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
