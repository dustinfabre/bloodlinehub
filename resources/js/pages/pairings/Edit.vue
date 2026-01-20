<script setup lang="ts">
import { useForm, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { show as pairingsShow, update as pairingsUpdate, index as pairingsIndex } from '@/routes/pairings';
import { useToast } from '@/composables/useToast';
import { type BreadcrumbItem } from '@/types';

interface Pigeon {
    id: number;
    name: string;
    ring_number: string;
    bloodline?: string;
}

interface Pairing {
    id: number;
    sire_id: number;
    dam_id: number;
    pair_name: string;
    status: string;
    current_clutch_number: number;
}

interface Props {
    pairing: Pairing;
    sires: Pigeon[];
    dams: Pigeon[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Breeding', href: pairingsIndex().url },
    { title: props.pairing.pair_name, href: pairingsShow({ pairing: props.pairing.id }).url },
    { title: 'Edit', href: pairingsUpdate(props.pairing.id).url },
];

const form = useForm({
    pair_name: props.pairing.pair_name,
});

const { success } = useToast();

const submit = () => {
    form.patch(pairingsUpdate(props.pairing.id).url, {
        preserveScroll: true,
        onSuccess: () => success('Pairing updated successfully!'),
    });
};
</script>

<template>
    <Head title="Edit Pairing" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div class="max-w-3xl mx-auto w-full space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Edit Pairing</h1>
                    <p class="text-muted-foreground mt-1">Update pairing details</p>
                </div>
                <Button asChild variant="outline">
                    <Link :href="pairingsShow({ pairing: pairing.id }).url">Back to Pairing</Link>
                </Button>
            </div>

            <form @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>Pairing Details</CardTitle>
                        <CardDescription>Update the pair name (sire and dam cannot be changed)</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Sire (Read-only) -->
                        <div class="space-y-2">
                            <Label>Sire (Male)</Label>
                            <div class="rounded-md border px-3 py-2 bg-muted text-muted-foreground">
                                {{ sires.find(s => s.id === pairing.sire_id)?.name }} - 
                                {{ sires.find(s => s.id === pairing.sire_id)?.ring_number }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Sire cannot be changed after creation
                            </p>
                        </div>

                        <!-- Dam (Read-only) -->
                        <div class="space-y-2">
                            <Label>Dam (Female)</Label>
                            <div class="rounded-md border px-3 py-2 bg-muted text-muted-foreground">
                                {{ dams.find(d => d.id === pairing.dam_id)?.name }} - 
                                {{ dams.find(d => d.id === pairing.dam_id)?.ring_number }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Dam cannot be changed after creation
                            </p>
                        </div>

                        <!-- Pair Name (Editable) -->
                        <div class="space-y-2">
                            <Label for="pair_name">Pair Name</Label>
                            <Input
                                id="pair_name"
                                v-model="form.pair_name"
                                placeholder="e.g., A1, Golden Pair, etc."
                            />
                            <p v-if="form.errors.pair_name" class="text-sm text-destructive">
                                {{ form.errors.pair_name }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex justify-end gap-3 mt-6">
                    <Button type="button" variant="outline" asChild>
                        <Link :href="pairingsShow({ pairing: pairing.id }).url">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
            </div>
        </div>
    </AppLayout>
</template>
