<script setup lang="ts">
import AppLogoWordmark from '@/components/AppLogoWordmark.vue';
import InputError from '@/components/InputError.vue';
import PedigreeChart from '@/components/pedigree/PedigreeChart.vue';
import PedigreePhotos from '@/components/pedigree/PedigreePhotos.vue';
import PedigreeViewer from '@/components/pedigree/PedigreeViewer.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Toaster } from '@/components/ui/sonner';
import { home, login } from '@/routes';
import { unlock } from '@/routes/pedigrees';
import {
    edit as editPigeon,
    pedigree as pedigreeRoute,
} from '@/routes/pigeons';
import { type AppPageProps } from '@/types';
import type { PedigreeMeta, PedigreeNode } from '@/types/pedigree';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Clock, KeyRound, LoaderCircle, Lock } from 'lucide-vue-next';
import { computed } from 'vue';

// tree/meta are null while the pedigree is locked: 'private' (owner only) or 'password' (needs the share password)
const props = defineProps<{
    serial: string;
    isOwner: boolean;
    pigeonId?: number | null;
    locked: 'private' | 'password' | null;
    accessExpiresAt?: string | null;
    tree: PedigreeNode | null;
    meta: PedigreeMeta | null;
}>();

const page = usePage<AppPageProps>();
const isGuest = computed(() => !page.props.auth?.user);

const subjectLabel = computed(
    () => props.tree?.ring_number || props.tree?.name || props.serial,
);

const form = useForm({ password: '' });

const submitPassword = () => {
    form.post(unlock({ serial: props.serial }).url, {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
};

const accessUntil = computed(() =>
    props.accessExpiresAt
        ? new Date(props.accessExpiresAt).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : null,
);
</script>

<template>
    <Head
        :title="
            tree
                ? `Pedigree ${subjectLabel}`
                : locked === 'password'
                  ? 'Password protected pedigree'
                  : 'Private pedigree'
        "
    >
        <!-- Never in search engines, so a lost bird can't be traced to its pedigree (also sent as X-Robots-Tag) -->
        <meta
            head-key="robots"
            name="robots"
            content="noindex, nofollow, noarchive, noimageindex"
        />
    </Head>

    <div class="min-h-screen bg-muted/40">
        <header class="bg-navy">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3"
            >
                <Link :href="home().url" class="flex items-center">
                    <AppLogoWordmark class="h-10" />
                </Link>
                <span class="font-mono text-sm text-ivory/70">{{
                    serial
                }}</span>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-4 px-4 py-6">
            <template v-if="tree && meta">
                <div
                    v-if="isOwner && !meta.is_public"
                    class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                >
                    <template v-if="meta.visibility === 'password'"
                        >Others need the share password to see this
                        page.</template
                    >
                    <template v-else
                        >This pedigree is private, so only you can see this
                        page.</template
                    >
                    <Link
                        v-if="pigeonId"
                        :href="pedigreeRoute({ pigeon: pigeonId }).url"
                        class="font-medium underline"
                        >Manage sharing</Link
                    >
                </div>

                <div
                    v-if="accessUntil"
                    class="flex items-center gap-2 rounded-lg border bg-background p-3 text-sm text-muted-foreground"
                >
                    <Clock class="size-4 shrink-0 text-gold" />
                    The owner shared this pedigree with you until
                    {{ accessUntil }}.
                </div>

                <div>
                    <h1 class="text-2xl font-bold">
                        Pedigree {{ subjectLabel }}
                    </h1>
                    <p class="text-muted-foreground">
                        {{ meta.role === 'owner' ? 'Owned' : 'Bred' }} by
                        {{ meta.breeder }}
                        <template v-if="meta.role === 'owner' && meta.bred_by">
                            · Bred by {{ meta.bred_by }}</template
                        >
                    </p>
                </div>

                <PedigreePhotos
                    :tree="tree"
                    :photos="meta.photos"
                    :relative-photos="meta.relative_photos"
                    :documents="meta.documents"
                    :add-photos-href="
                        isOwner && pigeonId
                            ? editPigeon({ pigeon: pigeonId }).url
                            : undefined
                    "
                />

                <PedigreeViewer>
                    <PedigreeChart :tree="tree" :meta="meta" />
                </PedigreeViewer>
            </template>

            <form
                v-else-if="locked === 'password'"
                class="mx-auto mt-10 max-w-md rounded-xl border bg-background p-8 shadow-sm"
                @submit.prevent="submitPassword"
            >
                <div class="text-center">
                    <div
                        class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-secondary text-navy dark:text-gold"
                    >
                        <KeyRound class="h-6 w-6" />
                    </div>
                    <h1 class="text-xl font-semibold">
                        This pedigree is password protected
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Enter the password the owner shared with you to view
                        pedigree <span class="font-mono">{{ serial }}</span
                        >.
                    </p>
                </div>
                <div class="mt-6 grid gap-2">
                    <Label for="pedigree_password">Password</Label>
                    <Input
                        id="pedigree_password"
                        v-model="form.password"
                        type="password"
                        autocomplete="off"
                        autofocus
                        required
                    />
                    <InputError :message="form.errors.password" />
                </div>
                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :disabled="form.processing"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="size-4 animate-spin"
                    />
                    View pedigree
                </Button>
                <p v-if="isGuest" class="mt-4 text-center text-sm">
                    <Link
                        :href="login().url"
                        class="text-muted-foreground underline underline-offset-2"
                        >Are you the owner? Log in</Link
                    >
                </p>
            </form>

            <div
                v-else
                class="mx-auto mt-10 max-w-md rounded-xl border bg-background p-8 text-center shadow-sm"
            >
                <div
                    class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
                >
                    <Lock class="h-6 w-6" />
                </div>
                <h1 class="text-xl font-semibold">This pedigree is private</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Pedigree <span class="font-mono">{{ serial }}</span> exists,
                    but its owner hasn't shared it.
                </p>
                <Button v-if="isGuest" class="mt-6" as-child>
                    <Link :href="login().url"
                        >Log in to view your own pedigree</Link
                    >
                </Button>
            </div>
        </main>

        <Toaster
            position="top-right"
            :richColors="true"
            :closeButton="true"
            :duration="3000"
        />
    </div>
</template>
