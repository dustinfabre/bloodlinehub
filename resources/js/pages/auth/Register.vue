<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { login, privacy, terms } from '@/routes';
import { store } from '@/routes/register';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    // Days of free Premium a new account starts with (0: no trial)
    trialDays: number;
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
    // Honeypot: hidden from people, filled in by bots; the server ignores any request that has it
    website: '',
});

const submit = () => {
    form.post(store().url, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

// What the free plan includes (the trial adds Premium on top for its first days)
const included = computed(() => [
    'Unlimited pigeons, with photos saved to your own Google Drive',
    'Unlimited pairings and clutches, and printable pedigrees',
    'Lineage, locations, clubs, one-loft races and sales',
    ...(props.trialDays > 0
        ? [
              `Every Premium feature free for your first ${props.trialDays} days: Pairing Analysis, Loft Check, QR code pedigrees and more`,
          ]
        : []),
]);

const inputClass = 'h-11 bg-card text-[15px] text-navy';
</script>

<template>
    <Head title="Create your account" />

    <PublicLayout>
        <section
            class="mx-auto grid max-w-6xl gap-12 px-4 py-12 sm:px-6 sm:py-20 lg:grid-cols-[1fr_minmax(0,28rem)] lg:gap-20 lg:py-24"
        >
            <div class="max-w-xl">
                <h1
                    class="animate-rise font-display text-4xl leading-[1.08] font-semibold tracking-[-0.01em] text-balance text-navy sm:text-5xl"
                >
                    Start your loft record.
                </h1>
                <p
                    class="mt-5 animate-rise text-base leading-relaxed text-pretty text-muted-foreground sm:text-lg"
                    style="--rise-delay: 80ms"
                >
                    Create a free account and add your first birds in minutes.
                    No card needed<template v-if="trialDays > 0"
                        >, and Premium is free for your first
                        {{ trialDays }} days</template
                    >.
                </p>
                <ul
                    class="mt-8 animate-rise space-y-3"
                    style="--rise-delay: 160ms"
                >
                    <li
                        v-for="item in included"
                        :key="item"
                        class="flex gap-3 text-[15px] leading-relaxed text-navy"
                    >
                        <Check
                            class="mt-1 size-4 shrink-0 text-gold"
                            aria-hidden="true"
                        />
                        <span>{{ item }}</span>
                    </li>
                </ul>
            </div>

            <div
                class="animate-rise rounded-lg border border-border bg-card p-5 sm:p-7"
                style="--rise-delay: 120ms"
            >
                <form class="grid gap-5" novalidate @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name" class="text-navy">Your name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            name="name"
                            autocomplete="name"
                            required
                            autofocus
                            placeholder="Juan dela Cruz"
                            :aria-invalid="Boolean(form.errors.name)"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email" class="text-navy"
                            >Email address</Label
                        >
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            inputmode="email"
                            required
                            placeholder="you@example.com"
                            :aria-invalid="Boolean(form.errors.email)"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password" class="text-navy">Password</Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            required
                            placeholder="At least 8 characters"
                            :aria-invalid="Boolean(form.errors.password)"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation" class="text-navy">
                            Confirm password
                        </Label>
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            name="password_confirmation"
                            autocomplete="new-password"
                            required
                            :class="inputClass"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label
                            for="terms"
                            class="flex items-start gap-3 text-sm leading-relaxed font-normal text-muted-foreground"
                        >
                            <Checkbox
                                id="terms"
                                v-model="form.terms"
                                class="mt-0.5"
                                :aria-invalid="Boolean(form.errors.terms)"
                            />
                            <span>
                                I agree to the
                                <a
                                    :href="terms().url"
                                    target="_blank"
                                    rel="noopener"
                                    class="font-medium text-foreground underline decoration-gold underline-offset-4 hover:decoration-current"
                                    >Terms</a
                                >
                                and
                                <a
                                    :href="privacy().url"
                                    target="_blank"
                                    rel="noopener"
                                    class="font-medium text-foreground underline decoration-gold underline-offset-4 hover:decoration-current"
                                    >Privacy Policy</a
                                >.
                            </span>
                        </Label>
                        <InputError :message="form.errors.terms" />
                    </div>

                    <!-- Honeypot, kept off screen and out of the tab order -->
                    <div class="sr-only" aria-hidden="true">
                        <label for="signup-website">Website</label>
                        <input
                            id="signup-website"
                            v-model="form.website"
                            type="text"
                            name="website"
                            tabindex="-1"
                            autocomplete="off"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        data-test="register-button"
                        class="mt-1 inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-navy px-5 text-[15px] font-semibold text-ivory transition-colors duration-150 hover:bg-navy/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy disabled:opacity-70"
                    >
                        <Spinner v-if="form.processing" />
                        Create free account
                    </button>
                </form>

                <p
                    class="mt-6 border-t border-border pt-5 text-sm text-muted-foreground"
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
