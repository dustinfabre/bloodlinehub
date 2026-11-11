<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useToast } from '@/composables/useToast';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import { durationLabel, formatMoney } from '@/lib/billing';
import { destroy, store, update } from '@/routes/admin/plans';
import type { Plan } from '@/types/billing';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Tag, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type AdminPlan = Plan & {
    is_active: boolean;
    sort_order: number;
    payments_count: number;
};

defineProps<{
    plans: AdminPlan[];
}>();

const { success, error } = useToast();

// Create / edit
const editing = ref<AdminPlan | null>(null);
const showForm = ref(false);

const form = useForm({
    name: '',
    description: '',
    duration_months: 1 as number | string,
    price_php: '' as number | string,
    price_usd: '' as number | string,
    is_active: true,
    sort_order: 0 as number | string,
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (plan: AdminPlan) => {
    editing.value = plan;
    form.clearErrors();
    form.name = plan.name;
    form.description = plan.description ?? '';
    form.duration_months = plan.duration_months;
    form.price_php = plan.price_php;
    form.price_usd = plan.price_usd ?? '';
    form.is_active = plan.is_active;
    form.sort_order = plan.sort_order;
    showForm.value = true;
};

const submit = () => {
    const plan = editing.value;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            success(plan ? 'Plan updated.' : 'Plan added.');
        },
    };

    if (plan) {
        form.patch(update(plan.id).url, options);
    } else {
        form.post(store().url, options);
    }
};

// Delete
const deleting = ref<AdminPlan | null>(null);
const showDelete = computed({
    get: () => deleting.value !== null,
    set: (open: boolean) => {
        if (!open) deleting.value = null;
    },
});

const confirmDelete = () => {
    const plan = deleting.value;
    if (!plan) return;
    router.delete(destroy(plan.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
            success('Plan deleted.');
        },
        onError: () => error('Could not delete this plan.'),
    });
};
</script>

<template>
    <Head title="Plans & prices · Admin" />

    <AdminLayout
        title="Plans & prices"
        description="What users can pay for. A price change only affects new payments."
    >
        <div class="space-y-5">
            <div class="flex justify-end">
                <Button @click="openCreate"
                    ><Plus class="size-4" /> Add plan</Button
                >
            </div>

            <div
                v-if="plans.length === 0"
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border px-4 py-16 text-center"
            >
                <Tag class="mb-3 size-10 text-muted-foreground/40" />
                <p class="font-medium">No plans yet</p>
                <p class="mt-1 max-w-md text-sm text-muted-foreground">
                    Users can't pay until there is at least one. Start with, for
                    example,
                    <span class="font-medium text-foreground"
                        >Premium · 1 month</span
                    >
                    and
                    <span class="font-medium text-foreground"
                        >Premium · 1 year</span
                    >, with a peso price and a dollar price for payers abroad.
                </p>
                <Button class="mt-4" @click="openCreate"
                    ><Plus class="size-4" /> Add plan</Button
                >
            </div>

            <div v-else class="grid gap-3 lg:grid-cols-2">
                <Card
                    v-for="plan in plans"
                    :key="plan.id"
                    :class="['py-0', plan.is_active ? '' : 'opacity-70']"
                >
                    <CardContent class="space-y-3 p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold wrap-break-word">
                                        {{ plan.name }}
                                    </p>
                                    <Badge
                                        :variant="
                                            plan.is_active
                                                ? 'secondary'
                                                : 'outline'
                                        "
                                    >
                                        {{
                                            plan.is_active
                                                ? 'On sale'
                                                : 'Hidden'
                                        }}
                                    </Badge>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ durationLabel(plan.duration_months) }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-xl font-bold tabular-nums">
                                    {{ formatMoney(plan.price_php, 'PHP') }}
                                </p>
                                <p
                                    class="text-sm text-muted-foreground tabular-nums"
                                >
                                    {{
                                        plan.price_usd
                                            ? formatMoney(plan.price_usd, 'USD')
                                            : 'Not sold abroad'
                                    }}
                                </p>
                            </div>
                        </div>
                        <p
                            v-if="plan.description"
                            class="text-sm wrap-break-word text-muted-foreground"
                        >
                            {{ plan.description }}
                        </p>
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3"
                        >
                            <span class="text-xs text-muted-foreground">
                                {{ plan.payments_count }}
                                {{
                                    plan.payments_count === 1
                                        ? 'payment'
                                        : 'payments'
                                }}
                                · order {{ plan.sort_order }}
                            </span>
                            <div class="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openEdit(plan)"
                                >
                                    <Pencil class="size-4" /> Edit
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="deleting = plan"
                                >
                                    <Trash2 class="size-4" /> Delete
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Create / edit -->
        <Dialog v-model:open="showForm">
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Edit plan' : 'Add plan'
                    }}</DialogTitle>
                    <DialogDescription>
                        Approving a payment for this plan adds its length to the
                        payer's Premium.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="plan-name">Name</Label>
                        <Input
                            id="plan-name"
                            v-model="form.name"
                            required
                            placeholder="e.g. Premium · 1 year"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="plan-description"
                            >Short description (optional)</Label
                        >
                        <Input
                            id="plan-description"
                            v-model="form.description"
                            placeholder="e.g. Two months free compared with monthly"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="plan-duration">Length in months</Label>
                        <Input
                            id="plan-duration"
                            v-model="form.duration_months"
                            type="number"
                            min="1"
                            max="120"
                            required
                        />
                        <InputError :message="form.errors.duration_months" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="plan-php">Price in pesos (PHP)</Label>
                            <Input
                                id="plan-php"
                                v-model="form.price_php"
                                type="number"
                                min="1"
                                step="0.01"
                                inputmode="decimal"
                                required
                            />
                            <InputError :message="form.errors.price_php" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="plan-usd">Price in dollars (USD)</Label>
                            <Input
                                id="plan-usd"
                                v-model="form.price_usd"
                                type="number"
                                min="1"
                                step="0.01"
                                inputmode="decimal"
                                placeholder="Optional"
                            />
                            <p class="text-xs text-muted-foreground">
                                Leave blank if payers abroad can't buy it.
                            </p>
                            <InputError :message="form.errors.price_usd" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 sm:items-end">
                        <Label
                            for="plan-active"
                            class="flex items-start gap-2 font-normal"
                        >
                            <Checkbox
                                id="plan-active"
                                v-model="form.is_active"
                                class="mt-0.5"
                            />
                            <span>
                                On sale
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >Untick to hide it from the billing
                                    page.</span
                                >
                            </span>
                        </Label>
                        <div class="grid gap-2">
                            <Label for="plan-order">Order</Label>
                            <Input
                                id="plan-order"
                                v-model="form.sort_order"
                                type="number"
                                min="0"
                                max="999"
                            />
                            <InputError :message="form.errors.sort_order" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showForm = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="form.processing">
                            <Spinner v-if="form.processing" />
                            {{ editing ? 'Save' : 'Add plan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete -->
        <Dialog v-model:open="showDelete">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete {{ deleting?.name }}?</DialogTitle>
                    <DialogDescription>
                        It can no longer be bought. Past payments keep the
                        plan's name and length. To stop selling it for a while,
                        edit it and untick "On sale" instead.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleting = null"
                        >Cancel</Button
                    >
                    <Button variant="destructive" @click="confirmDelete">
                        <Trash2 class="size-4" /> Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
