<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit, update } from '@/routes/pedigree-details';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Mail, MapPin, Phone } from 'lucide-vue-next';
import { computed } from 'vue';

interface Details {
    display_name: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    show_phone: boolean;
    show_email: boolean;
    show_address: boolean;
}

const props = defineProps<{
    accountName: string;
    details: Details;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Pedigree details', href: edit().url },
];

const form = useForm({
    display_name: props.details.display_name ?? '',
    phone: props.details.phone ?? '',
    email: props.details.email ?? '',
    address: props.details.address ?? '',
    show_phone: props.details.show_phone,
    show_email: props.details.show_email,
    show_address: props.details.show_address,
});

// What the pedigree footer will show with the current (unsaved) values
const preview = computed(() => ({
    breeder: form.display_name.trim() || props.accountName,
    phone: form.show_phone ? form.phone.trim() : '',
    email: form.show_email ? form.email.trim() : '',
    address: form.show_address ? form.address.trim() : '',
}));

const submit = () => {
    form.patch(update().url, { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Pedigree details" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Pedigree details"
                    description="Your loft name, phone number, email and address on every pedigree: on screen, in downloads and when printed."
                />

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="display_name">Breeder or loft name</Label>
                        <Input
                            id="display_name"
                            v-model="form.display_name"
                            :placeholder="accountName"
                            autocomplete="organization"
                        />
                        <p class="text-xs text-muted-foreground">
                            Leave blank to use your account name ({{
                                accountName
                            }}).
                        </p>
                        <InputError :message="form.errors.display_name" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-4">
                            <Label for="phone">Contact number</Label>
                            <label
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <Checkbox v-model="form.show_phone" />
                                Show on pedigrees
                            </label>
                        </div>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            autocomplete="tel"
                            placeholder="e.g. +63 917 123 4567"
                        />
                        <InputError :message="form.errors.phone" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-4">
                            <Label for="pedigree_email">Email</Label>
                            <label
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <Checkbox v-model="form.show_email" />
                                Show on pedigrees
                            </label>
                        </div>
                        <Input
                            id="pedigree_email"
                            v-model="form.email"
                            type="email"
                            autocomplete="off"
                            placeholder="e.g. loft@example.com"
                        />
                        <p class="text-xs text-muted-foreground">
                            Separate from your login email, which is never
                            printed. Use the address buyers should write to.
                        </p>
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-4">
                            <Label for="address">Address</Label>
                            <label
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <Checkbox v-model="form.show_address" />
                                Show on pedigrees
                            </label>
                        </div>
                        <Textarea
                            id="address"
                            v-model="form.address"
                            rows="2"
                            autocomplete="street-address"
                            placeholder="Loft address or town"
                        />
                        <InputError :message="form.errors.address" />
                    </div>

                    <!-- Live preview of the pedigree footer -->
                    <div class="rounded-xl border bg-white p-4 text-navy">
                        <p
                            class="text-[10px] font-semibold tracking-[0.18em] text-slate-500 uppercase"
                        >
                            Preview on the pedigree
                        </p>
                        <div
                            class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                        >
                            <div class="min-w-0 space-y-1 text-sm">
                                <p class="font-semibold">
                                    {{ preview.breeder }}
                                </p>
                                <p
                                    v-if="preview.phone"
                                    class="flex items-center gap-1.5"
                                >
                                    <Phone class="size-3.5 text-gold" />
                                    {{ preview.phone }}
                                </p>
                                <p
                                    v-if="preview.email"
                                    class="flex items-center gap-1.5 break-all"
                                >
                                    <Mail class="size-3.5 shrink-0 text-gold" />
                                    {{ preview.email }}
                                </p>
                                <p
                                    v-if="preview.address"
                                    class="flex items-start gap-1.5"
                                >
                                    <MapPin
                                        class="mt-0.5 size-3.5 shrink-0 text-gold"
                                    />
                                    {{ preview.address }}
                                </p>
                                <p
                                    v-if="
                                        !preview.phone &&
                                        !preview.email &&
                                        !preview.address
                                    "
                                    class="text-slate-500"
                                >
                                    No contact details shown.
                                </p>
                            </div>
                            <div class="flex shrink-0 items-end gap-3">
                                <div class="w-40">
                                    <div class="h-8 border-b border-navy" />
                                    <p
                                        class="mt-1 text-[10px] tracking-[0.14em] text-slate-500 uppercase"
                                    >
                                        Breeder's signature
                                    </p>
                                </div>
                                <div class="w-20">
                                    <div class="h-8 border-b border-navy" />
                                    <p
                                        class="mt-1 text-[10px] tracking-[0.14em] text-slate-500 uppercase"
                                    >
                                        Date
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">
                            The signature and date lines are for printed copies.
                        </p>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save</Button>
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="form.recentlySuccessful"
                                class="text-sm text-muted-foreground"
                            >
                                Saved. All your pedigrees use these details.
                            </p>
                        </Transition>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
