<script setup lang="ts">
import { useForm, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { index as pairingsIndex, store as pairingsStore } from '@/routes/pairings';
import { type BreadcrumbItem } from '@/types';

interface Pigeon {
    id: number;
    name: string;
    ring_number: string;
    bloodline?: string;
}

interface Props {
    sires: Pigeon[];
    dams: Pigeon[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Breeding', href: pairingsIndex().url },
    { title: 'Create Pairing', href: pairingsStore().url },
];

const form = useForm({
    sire_id: '',
    dam_id: '',
    pair_name: '',
});

const submit = () => {
    form.post(pairingsStore().url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Create Pairing" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div class="max-w-3xl mx-auto w-full space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Create Breeding Pairing</h1>
                    <p class="text-muted-foreground mt-1">Set up a new breeding pair</p>
                </div>
                <Button asChild variant="outline">
                    <Link :href="pairingsIndex().url">Back to Pairings</Link>
                </Button>
            </div>

            <form @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>Pairing Details</CardTitle>
                        <CardDescription>Select the sire and dam for this breeding pair</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Sire Selection -->
                        <div class="space-y-2">
                            <Label for="sire_id">Sire (Male) *</Label>
                            <Select v-model="form.sire_id" required>
                                <SelectTrigger id="sire_id">
                                    <SelectValue placeholder="Select a sire..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="sire in sires" :key="sire.id" :value="sire.id.toString()">
                                        {{ sire.name }} - {{ sire.ring_number }}
                                        <span v-if="sire.bloodline" class="text-muted-foreground text-sm">
                                            ({{ sire.bloodline }})
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.sire_id" class="text-sm text-destructive">
                                {{ form.errors.sire_id }}
                            </p>
                        </div>

                        <!-- Dam Selection -->
                        <div class="space-y-2">
                            <Label for="dam_id">Dam (Female) *</Label>
                            <Select v-model="form.dam_id" required>
                                <SelectTrigger id="dam_id">
                                    <SelectValue placeholder="Select a dam..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="dam in dams" :key="dam.id" :value="dam.id.toString()">
                                        {{ dam.name }} - {{ dam.ring_number }}
                                        <span v-if="dam.bloodline" class="text-muted-foreground text-sm">
                                            ({{ dam.bloodline }})
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.dam_id" class="text-sm text-destructive">
                                {{ form.errors.dam_id }}
                            </p>
                        </div>

                        <!-- Pair Name -->
                        <div class="space-y-2">
                            <Label for="pair_name">Pair Name (Optional)</Label>
                            <Input
                                id="pair_name"
                                v-model="form.pair_name"
                                placeholder="e.g., A1, Golden Pair, etc."
                            />
                            <p class="text-sm text-muted-foreground">
                                Leave blank to auto-generate (e.g., "Pair #1")
                            </p>
                            <p v-if="form.errors.pair_name" class="text-sm text-destructive">
                                {{ form.errors.pair_name }}
                            </p>
                        </div>

                        <!-- Info Box -->
                        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
                            <p class="font-medium mb-2">What happens when you create a pairing:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Both pigeons will be set to "Breeding" status</li>
                                <li>A new clutch session will be created</li>
                                <li>You can add offspring directly from the pairing view</li>
                                <li>When you end the session, pigeons return to "Stock" status</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex justify-end gap-3 mt-6">
                    <Button type="button" variant="outline" asChild>
                        <Link :href="pairingsIndex().url">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Pairing' }}
                    </Button>
                </div>
            </form>
            </div>
        </div>
    </AppLayout>
</template>
