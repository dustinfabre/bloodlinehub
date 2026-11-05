<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/invite-requests';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

// Registration is closed; this page collects invite requests instead
const form = useForm({
    email: '',
    // Honeypot: hidden from people, filled in by bots; the server ignores any request that has it
    website: '',
});

const requestedFor = ref('');

const submit = () => {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            requestedFor.value = form.email.trim();
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Registration coming soon" />

    <PublicLayout>
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24 lg:py-28">
            <div class="max-w-xl">
                <h1
                    class="animate-rise font-display text-4xl leading-[1.08] font-semibold tracking-[-0.01em] text-balance text-navy sm:text-5xl"
                >
                    Registration is coming soon.
                </h1>
                <p
                    class="mt-5 animate-rise text-base leading-relaxed text-pretty text-muted-foreground sm:text-lg"
                    style="--rise-delay: 80ms"
                >
                    Accounts are by invitation for now. Leave your email and
                    we'll send you an invite.
                </p>

                <div
                    class="mt-10 animate-rise"
                    style="--rise-delay: 160ms"
                    aria-live="polite"
                >
                    <div
                        v-if="requestedFor"
                        class="rounded-lg border border-border bg-card p-5 sm:p-6"
                    >
                        <p class="font-display text-xl font-semibold text-navy">
                            Thanks, you're on the list.
                        </p>
                        <p
                            class="mt-2 text-[15px] leading-relaxed text-muted-foreground"
                        >
                            We'll send your invite to
                            <span
                                class="font-medium wrap-break-word text-navy"
                                >{{ requestedFor }}</span
                            >.
                        </p>
                    </div>

                    <form v-else novalidate @submit.prevent="submit">
                        <Label for="invite-email" class="text-navy">
                            Email address
                        </Label>
                        <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                            <Input
                                id="invite-email"
                                v-model="form.email"
                                type="email"
                                name="email"
                                autocomplete="email"
                                inputmode="email"
                                required
                                placeholder="you@example.com"
                                :aria-invalid="Boolean(form.errors.email)"
                                aria-describedby="invite-email-error"
                                class="h-11 min-w-0 flex-1 bg-card text-[15px] text-navy"
                            />
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-md bg-navy px-5 text-[15px] font-semibold text-ivory transition-colors duration-150 hover:bg-navy/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy disabled:opacity-70"
                            >
                                <Spinner v-if="form.processing" />
                                Request an invite
                            </button>
                        </div>
                        <InputError
                            id="invite-email-error"
                            class="mt-2"
                            :message="form.errors.email"
                        />

                        <!-- Honeypot, kept off screen and out of the tab order -->
                        <div class="sr-only" aria-hidden="true">
                            <label for="invite-website">Website</label>
                            <input
                                id="invite-website"
                                v-model="form.website"
                                type="text"
                                name="website"
                                tabindex="-1"
                                autocomplete="off"
                            />
                        </div>
                    </form>
                </div>

                <p
                    class="mt-10 animate-rise border-t border-border pt-6 text-sm text-muted-foreground"
                    style="--rise-delay: 240ms"
                >
                    Already have an account?
                    <Link
                        :href="login()"
                        class="font-medium text-navy underline decoration-gold underline-offset-4 transition-colors duration-150 hover:decoration-navy"
                    >
                        Log in
                    </Link>
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
