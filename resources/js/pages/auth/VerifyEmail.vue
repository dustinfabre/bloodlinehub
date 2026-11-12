<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { send } from '@/routes/verification';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    status?: string;
}>();

const email = computed(() => usePage().props.auth.user?.email);
</script>

<template>
    <AuthLayout
        title="Verify your email"
        description="We emailed you a link. Click it to confirm your email address and open your account."
    >
        <Head title="Email verification" />

        <p v-if="email" class="text-center text-sm text-muted-foreground">
            Sent to
            <span class="font-medium break-all text-foreground">{{
                email
            }}</span
            >. Not there? Check your spam folder.
        </p>

        <div
            v-if="status === 'verification-link-sent'"
            class="text-center text-sm font-medium text-green-600 dark:text-green-500"
        >
            A new verification link has been sent to your email address.
        </div>

        <Form
            v-bind="send.form()"
            class="space-y-6 text-center"
            v-slot="{ processing }"
        >
            <Button :disabled="processing" variant="secondary">
                <Spinner v-if="processing" />
                Resend verification email
            </Button>

            <p class="text-sm text-muted-foreground">
                Wrong address?
                <TextLink :href="editProfile()">Change your email</TextLink>
            </p>

            <TextLink
                :href="logout()"
                as="button"
                class="mx-auto block text-sm"
            >
                Log out
            </TextLink>
        </Form>
    </AuthLayout>
</template>
