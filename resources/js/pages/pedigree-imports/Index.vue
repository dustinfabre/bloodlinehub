<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { shrinkImage } from '@/lib/images';
import { usePremium } from '@/lib/premium';
import { index as billing } from '@/routes/billing';
import {
    destroy,
    index as importsIndex,
    retry,
    show,
    store,
} from '@/routes/pedigree-imports';
import { pedigree, index as pigeonsIndex } from '@/routes/pigeons';
import { type BreadcrumbItem } from '@/types';
import type {
    ImportSaved,
    PedigreeImportAllowance,
    PedigreeImportItem,
} from '@/types/pedigree-import';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    FileText,
    Home,
    ListChecks,
    LoaderCircle,
    RotateCcw,
    ScanLine,
    Sun,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    imports: PedigreeImportItem[];
    allowance: PedigreeImportAllowance;
    // Whether importing is set up on the server
    available: boolean;
    lastSaved: ImportSaved | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: pigeonsIndex().url },
    { title: 'Import pedigrees', href: importsIndex().url },
];

const { success, error, warning } = useToast();
const { premium } = usePremium();

const toCheck = computed(() =>
    props.imports.filter((item) => item.status === 'ready'),
);
const reading = computed(() =>
    props.imports.filter(
        (item) => item.status === 'pending' || item.status === 'reading',
    ),
);
const failed = computed(() =>
    props.imports.filter((item) => item.status === 'failed'),
);
const saved = computed(() =>
    props.imports.filter((item) => item.status === 'saved'),
);

// While sheets are waiting or being read, reload the list. Reloading also keeps the server reading them.
const poll = usePoll(
    3000,
    { only: ['imports', 'allowance'] },
    { autoStart: false },
);

// ----- Uploading: one file per request, so a big batch never hits the server's upload limits -----

const fileInput = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const queue = ref<File[]>([]);
const uploaded = ref(0);
const uploading = computed(() => queue.value.length > 0);
const uploadErrors = ref<{ name: string; message: string }[]>([]);

const ACCEPTED = /\.(jpe?g|png|webp|pdf)$/i;

const uploadOne = (file: File) =>
    new Promise<void>((resolve) => {
        router.post(
            store().url,
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                only: ['imports', 'allowance'],
                onError: (errors) =>
                    uploadErrors.value.push({
                        name: file.name,
                        message:
                            errors.file ?? 'This file could not be uploaded.',
                    }),
                onFinish: () => resolve(),
            },
        );
    });

const addFiles = async (files: FileList | File[] | null) => {
    // Added while an upload is running: they join the same queue
    const running = uploading.value;
    if (!running) uploadErrors.value = [];

    const picked = [...(files ?? [])].filter((file) => {
        if (ACCEPTED.test(file.name) || /^image\/|pdf$/.test(file.type))
            return true;
        uploadErrors.value.push({
            name: file.name,
            message: 'Upload a photo (JPG, PNG or WebP) or a PDF.',
        });
        return false;
    });
    if (!picked.length) return;

    queue.value.push(...picked);
    if (running) return;

    uploaded.value = 0;
    poll.stop();

    while (queue.value.length) {
        const file = queue.value[0];
        await uploadOne(await shrinkImage(file));
        uploaded.value++;
        queue.value.shift();
    }

    if (fileInput.value) fileInput.value.value = '';
    if (uploadErrors.value.length)
        error(
            uploadErrors.value.length === 1
                ? 'One file was not uploaded'
                : `${uploadErrors.value.length} files were not uploaded`,
        );
};

const onDrop = (event: DragEvent) => {
    dragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
};

// ----- Waiting for sheets to be read -----

watch(
    () => reading.value.length > 0 && !uploading.value,
    (busy) => (busy ? poll.start() : poll.stop()),
    { immediate: true },
);

// ----- Actions on a sheet -----

const busyId = ref<number | null>(null);

const retryImport = (item: PedigreeImportItem) =>
    router.post(
        retry(item.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (busyId.value = item.id),
            onFinish: () => (busyId.value = null),
            onError: (errors) =>
                error(errors.import ?? 'Could not read it again'),
        },
    );

const removeImport = (item: PedigreeImportItem) =>
    router.delete(destroy(item.id).url, {
        preserveScroll: true,
        onStart: () => (busyId.value = item.id),
        onFinish: () => (busyId.value = null),
        onSuccess: () => success('Sheet removed'),
    });

const label = (item: PedigreeImportItem) =>
    item.bird?.ring_number ||
    item.bird?.name ||
    item.file_name ||
    'Pedigree sheet';

const announceSaved = (report: ImportSaved) => {
    success(
        `Saved ${report.pigeon.label ?? 'the pigeon'} with ${report.ancestors} ${report.ancestors === 1 ? 'ancestor' : 'ancestors'}`,
    );
    if (report.kept.length)
        warning(
            `${report.kept.length} ${report.kept.length === 1 ? 'place already had a different bird' : 'places already had a different bird'}, so ${report.kept.length === 1 ? 'it was' : 'they were'} kept`,
        );
    if (report.sheet_failed)
        warning(
            'The sheet could not be saved to your Google Drive. The pedigree itself was saved.',
        );
};

onMounted(() => {
    if (props.lastSaved) announceSaved(props.lastSaved);
});

const allowanceText = computed(() => {
    const { left, free_left, free_limit } = props.allowance;
    if (free_left !== null)
        return `${free_left} of ${free_limit} free imports left.`;
    return left < 20 ? `${left} imports left today.` : null;
});
</script>

<template>
    <Head title="Import pedigrees" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6"
        >
            <div>
                <h1 class="text-2xl font-bold sm:text-3xl">Import pedigrees</h1>
                <p class="mt-1 max-w-2xl text-muted-foreground">
                    Photograph or scan your pedigree sheets and upload them.
                    Every bird on them is read for you. You check what was read
                    against the sheet, then save it, so you never have to type a
                    pedigree again.
                </p>
            </div>

            <div
                v-if="!available"
                class="flex gap-3 rounded-xl border border-dashed bg-card p-5"
            >
                <CircleAlert class="mt-0.5 size-5 shrink-0 text-gold" />
                <p>
                    Importing pedigrees is not available yet. You can still add
                    pigeons and their ancestors by hand.
                </p>
            </div>

            <!-- Upload -->
            <section
                v-else
                class="rounded-xl border-2 border-dashed bg-card p-6 text-center transition sm:p-8"
                :class="
                    dragging
                        ? 'border-gold bg-accent/60'
                        : 'border-border hover:border-gold/60'
                "
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <div
                    class="mx-auto flex size-12 items-center justify-center rounded-full bg-secondary text-navy dark:text-gold"
                >
                    <LoaderCircle
                        v-if="uploading"
                        class="size-6 animate-spin"
                    />
                    <ScanLine v-else class="size-6" />
                </div>
                <template v-if="uploading">
                    <p class="mt-3 font-semibold">
                        Uploading {{ uploaded + 1 }} of
                        {{ uploaded + queue.length }}…
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Keep this page open until every file is uploaded.
                    </p>
                </template>
                <template v-else>
                    <p class="mt-3 font-semibold">Drop pedigree sheets here</p>
                    <p class="text-sm text-muted-foreground">
                        Photos, scans or PDFs, one pedigree per file. Upload as
                        many as you like at once.
                    </p>
                    <Button
                        class="mt-4"
                        :disabled="allowance.left === 0"
                        @click="fileInput?.click()"
                    >
                        <Upload class="size-4" />
                        Choose files
                    </Button>
                </template>
                <input
                    ref="fileInput"
                    type="file"
                    class="hidden"
                    multiple
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    @change="
                        addFiles(($event.target as HTMLInputElement).files)
                    "
                />
                <p
                    v-if="allowanceText"
                    class="mt-4 text-xs text-muted-foreground"
                >
                    {{ allowanceText }}
                    <Link
                        v-if="!premium"
                        :href="billing().url"
                        class="font-medium text-foreground underline underline-offset-2"
                        >Premium imports as many as you like.</Link
                    >
                </p>
                <ul
                    v-if="uploadErrors.length"
                    class="mx-auto mt-4 max-w-md space-y-1 text-left text-sm text-destructive"
                >
                    <li v-for="(item, index) in uploadErrors" :key="index">
                        <span class="font-medium">{{ item.name }}:</span>
                        {{ item.message }}
                    </li>
                </ul>
            </section>

            <div
                v-if="available && !imports.length"
                class="grid gap-3 text-sm sm:grid-cols-3"
            >
                <div class="flex gap-3 rounded-xl border bg-card p-4">
                    <Sun class="mt-0.5 size-4 shrink-0 text-gold" />
                    <p>
                        Lay the sheet flat in good light and fill the photo with
                        it. A sharp photo is read best.
                    </p>
                </div>
                <div class="flex gap-3 rounded-xl border bg-card p-4">
                    <ListChecks class="mt-0.5 size-4 shrink-0 text-gold" />
                    <p>
                        Nothing is saved until you check it. You can correct any
                        bird before saving.
                    </p>
                </div>
                <div class="flex gap-3 rounded-xl border bg-card p-4">
                    <Home class="mt-0.5 size-4 shrink-0 text-gold" />
                    <p>
                        Parents already in your loft are found by ring number
                        and linked, and their own pedigrees are filled in too.
                    </p>
                </div>
            </div>

            <!-- Read and waiting to be checked -->
            <section v-if="toCheck.length" class="grid gap-3">
                <h2 class="text-lg font-semibold">
                    Ready to check
                    <span class="font-normal text-muted-foreground"
                        >({{ toCheck.length }})</span
                    >
                </h2>
                <ul class="divide-y rounded-xl border bg-card">
                    <li
                        v-for="item in toCheck"
                        :key="item.id"
                        class="flex flex-wrap items-center gap-3 p-3 sm:flex-nowrap"
                    >
                        <div
                            class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted"
                        >
                            <img
                                v-if="item.file_url && !item.is_pdf"
                                :src="item.file_url"
                                alt=""
                                loading="lazy"
                                class="size-full object-cover"
                            />
                            <FileText
                                v-else
                                class="size-6 text-muted-foreground"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold tabular-nums">
                                {{ label(item) }}
                                <span
                                    v-if="
                                        item.bird?.ring_number &&
                                        item.bird?.name
                                    "
                                    class="font-normal text-muted-foreground"
                                    >· {{ item.bird.name }}</span
                                >
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ item.ancestors }}
                                {{
                                    item.ancestors === 1
                                        ? 'ancestor'
                                        : 'ancestors'
                                }}
                                read
                            </p>
                            <p
                                v-if="item.note"
                                class="truncate text-xs text-amber-700 dark:text-amber-400"
                            >
                                {{ item.note }}
                            </p>
                        </div>
                        <div class="flex w-full gap-2 sm:w-auto">
                            <Button
                                variant="ghost"
                                size="sm"
                                :disabled="busyId === item.id"
                                aria-label="Remove"
                                @click="removeImport(item)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                            <Button
                                as-child
                                size="sm"
                                class="flex-1 sm:flex-none"
                            >
                                <Link :href="show(item.id).url"
                                    >Check and save</Link
                                >
                            </Button>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Being read -->
            <section v-if="reading.length" class="grid gap-3">
                <h2 class="text-lg font-semibold">
                    Reading
                    <span class="font-normal text-muted-foreground"
                        >({{ reading.length }})</span
                    >
                </h2>
                <p class="-mt-2 text-sm text-muted-foreground">
                    Each sheet takes about half a minute. You can leave this
                    page; reading carries on and picks up again when you come
                    back.
                </p>
                <ul class="divide-y rounded-xl border bg-card">
                    <li
                        v-for="item in reading"
                        :key="item.id"
                        class="flex items-center gap-3 p-3"
                    >
                        <LoaderCircle
                            v-if="item.status === 'reading'"
                            class="size-5 shrink-0 animate-spin text-gold"
                        />
                        <ScanLine
                            v-else
                            class="size-5 shrink-0 text-muted-foreground"
                        />
                        <p class="min-w-0 flex-1 truncate">
                            {{ item.file_name || 'Pedigree sheet' }}
                        </p>
                        <span class="shrink-0 text-sm text-muted-foreground">{{
                            item.status === 'reading' ? 'Reading…' : 'Waiting'
                        }}</span>
                    </li>
                </ul>
            </section>

            <!-- Could not be read -->
            <section v-if="failed.length" class="grid gap-3">
                <h2 class="text-lg font-semibold">
                    Could not be read
                    <span class="font-normal text-muted-foreground"
                        >({{ failed.length }})</span
                    >
                </h2>
                <ul class="divide-y rounded-xl border bg-card">
                    <li
                        v-for="item in failed"
                        :key="item.id"
                        class="flex flex-wrap items-center gap-3 p-3 sm:flex-nowrap"
                    >
                        <CircleAlert class="size-5 shrink-0 text-destructive" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                {{ item.file_name || 'Pedigree sheet' }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ item.error }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-if="item.can_retry"
                                variant="outline"
                                size="sm"
                                :disabled="busyId === item.id"
                                @click="retryImport(item)"
                            >
                                <RotateCcw class="size-4" />
                                Try again
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                :disabled="busyId === item.id"
                                @click="removeImport(item)"
                            >
                                <Trash2 class="size-4" />
                                Remove
                            </Button>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Saved in the last 30 days -->
            <section v-if="saved.length" class="grid gap-3">
                <h2 class="text-lg font-semibold">Saved</h2>
                <ul class="divide-y rounded-xl border bg-card">
                    <li
                        v-for="item in saved"
                        :key="item.id"
                        class="flex items-center gap-3 p-3"
                    >
                        <CircleCheck class="size-5 shrink-0 text-emerald-600" />
                        <p class="min-w-0 flex-1 truncate">
                            <template v-if="item.pigeon">
                                {{
                                    item.pigeon.ring_number || item.pigeon.name
                                }}
                                <span
                                    v-if="
                                        item.pigeon.ring_number &&
                                        item.pigeon.name
                                    "
                                    class="text-muted-foreground"
                                    >· {{ item.pigeon.name }}</span
                                >
                            </template>
                            <span v-else class="text-muted-foreground"
                                >Pigeon deleted</span
                            >
                        </p>
                        <Button
                            v-if="item.pigeon"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link :href="pedigree(item.pigeon.id).url"
                                >View pedigree</Link
                            >
                        </Button>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
