<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    familyName,
    familyPath,
    flattenPedigree,
    lineageSteps,
} from '@/lib/pedigree';
import { edit as storageSettings } from '@/routes/storage';
import type { PedigreeNode } from '@/types/pedigree';
import { Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ImageOff,
    ImagePlus,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

// A showcase of the bird on a pedigree: its own photos, then photos of its ancestors grouped by the father's and the
// mother's side, each named by its place in the family: the main photo of each loft ancestor, and photos of
// relatives uploaded on the bird itself (the way outside ancestors get a photo).
// Shows nothing to visitors when no bird on the chart has a photo; the owner is told why and where to add them.
const props = defineProps<{
    tree: PedigreeNode;
    // Every photo of the bird itself, the main one first
    photos?: string[];
    // Photos of relatives uploaded on the bird, with the relative's chart position
    relativePhotos?: { url: string; position: string }[];
    // Owner only: where to add photos of the bird, for the note shown when there are none
    addPhotosHref?: string;
}>();

interface Photo {
    // Unique among the photos on show
    id: string;
    url: string;
    // Chart position: '' for the pigeon itself, 'SD' for its sire's dam, ...
    position: string;
    // "Grandmother"
    title: string;
    // "Father's mother" (ancestors beyond the parents only)
    hint: string | null;
    ringNumber: string | null;
    name: string | null;
}

// Photos that failed to load (e.g. the owner disconnected their Drive) are dropped rather than shown broken
const failed = ref(new Set<string>());

// Every photo URL the pedigree has, whether or not it loads
const photoUrls = computed(() => [
    ...(props.photos ?? []),
    ...flattenPedigree(props.tree).flatMap((slot) =>
        slot.node?.photo_url ? [slot.node.photo_url] : [],
    ),
    ...(props.relativePhotos ?? []).map((photo) => photo.url),
]);

// The bird at each chart position, to name a relative's photo by its ring number
const nodesByPosition = computed(
    () =>
        new Map(
            flattenPedigree(props.tree).map((slot) => [slot.key, slot.node]),
        ),
);

// Generation by generation, top to bottom (sire's line first) within each
const positionOrder = (position: string) =>
    `${position.length}:${position.replace(/S/g, '0').replace(/D/g, '1')}`;
const allFailed = computed(
    () =>
        photoUrls.value.length > 0 &&
        photoUrls.value.every((url) => failed.value.has(url)),
);

const all = computed<Photo[]>(() => {
    const fromChart = flattenPedigree(props.tree)
        .filter((slot) => slot.node?.photo_url)
        .map((slot) => ({
            id: `chart:${slot.key}`,
            url: slot.node!.photo_url!,
            position: slot.key,
            ringNumber: slot.node!.ring_number,
            name: slot.node!.name,
        }));
    const relatives = (props.relativePhotos ?? []).map((photo, index) => {
        const node = nodesByPosition.value.get(photo.position);
        return {
            id: `relative:${index}`,
            url: photo.url,
            position: photo.position,
            ringNumber: node?.ring_number ?? null,
            name: node?.name ?? null,
        };
    });

    return [...fromChart, ...relatives]
        .filter((photo) => !failed.value.has(photo.url))
        .sort((a, b) =>
            positionOrder(a.position).localeCompare(positionOrder(b.position)),
        )
        .map((photo) => ({
            ...photo,
            title: photo.position ? familyName(photo.position) : 'This pigeon',
            hint: photo.position.length > 1 ? familyPath(photo.position) : null,
        }));
});

// A bird can sit more than once on one side (line breeding); show it once per side
const uniqueByUrl = (photos: Photo[]) =>
    photos.filter(
        (photo, index) =>
            photos.findIndex((other) => other.url === photo.url) === index,
    );

// The bird's own photos: all of them when given, otherwise the main photo on the chart
const subjectPhotos = computed<Photo[]>(() => {
    const fromChart = all.value.find((photo) => !photo.position);
    if (!props.photos?.length) return fromChart ? [fromChart] : [];
    return props.photos
        .filter((url) => !failed.value.has(url))
        .map((url, index) => ({
            id: `self:${index}`,
            url,
            position: '',
            title: 'This pigeon',
            hint: null,
            ringNumber: props.tree.ring_number,
            name: props.tree.name,
        }));
});
const subject = computed(() => subjectPhotos.value[0]);
const subjectExtras = computed(() => subjectPhotos.value.slice(1));

const sides = computed(() =>
    [
        {
            key: 'S',
            title: "Father's side",
            term: 'Sire',
            photos: uniqueByUrl(
                all.value.filter((photo) => photo.position.startsWith('S')),
            ),
        },
        {
            key: 'D',
            title: "Mother's side",
            term: 'Dam',
            photos: uniqueByUrl(
                all.value.filter((photo) => photo.position.startsWith('D')),
            ),
        },
    ].filter((side) => side.photos.length),
);

// Everything on show, in display order, for stepping through in the enlarged view
const shown = computed<Photo[]>(() => [
    ...subjectPhotos.value,
    ...sides.value.flatMap((side) => side.photos),
]);

const openId = ref<string | null>(null);
const openIndex = computed(() =>
    shown.value.findIndex((photo) => photo.id === openId.value),
);
const current = computed(() => shown.value[openIndex.value] ?? null);
const open = computed({
    get: () => current.value !== null,
    set: (value) => {
        if (!value) openId.value = null;
    },
});

const step = (by: number) => {
    const count = shown.value.length;
    if (openIndex.value < 0 || count < 2) return;
    openId.value = shown.value[(openIndex.value + by + count) % count].id;
};

const markFailed = (url: string) => {
    failed.value = new Set(failed.value).add(url);
};

const altText = (photo: Photo) =>
    [photo.title, photo.ringNumber || photo.name].filter(Boolean).join(' ');
</script>

<template>
    <section v-if="shown.length" class="rounded-xl border bg-card p-4 sm:p-5">
        <div class="flex flex-col gap-5 md:flex-row">
            <!-- The bird itself -->
            <div v-if="subject" class="shrink-0 md:w-60">
                <button
                    type="button"
                    class="group block w-full rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="openId = subject.id"
                >
                    <div
                        class="aspect-square overflow-hidden rounded-lg border bg-muted"
                    >
                        <img
                            :src="subject.url"
                            :alt="altText(subject)"
                            class="size-full object-cover transition-transform group-hover:scale-105"
                            @error="markFailed(subject.url)"
                        />
                    </div>
                </button>
                <p class="mt-2 text-xs font-medium text-muted-foreground">
                    This pigeon
                </p>
                <p v-if="subject.name" class="truncate font-semibold">
                    {{ subject.name }}
                </p>
                <p
                    v-if="subject.ringNumber"
                    class="truncate font-mono text-sm text-muted-foreground"
                >
                    {{ subject.ringNumber }}
                </p>
                <div
                    v-if="subjectExtras.length"
                    class="mt-3 grid grid-cols-4 gap-1.5"
                >
                    <button
                        v-for="photo in subjectExtras"
                        :key="photo.id"
                        type="button"
                        class="group overflow-hidden rounded-md border bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :aria-label="`Open photo of ${altText(photo)}`"
                        @click="openId = photo.id"
                    >
                        <img
                            :src="photo.url"
                            :alt="altText(photo)"
                            loading="lazy"
                            class="aspect-square w-full object-cover transition-transform group-hover:scale-105"
                            @error="markFailed(photo.url)"
                        />
                    </button>
                </div>
            </div>

            <!-- Its ancestors from the loft, by side of the family -->
            <div
                v-if="sides.length"
                class="grid flex-1 gap-5"
                :class="{ 'lg:grid-cols-2': sides.length > 1 }"
            >
                <div v-for="side in sides" :key="side.key" class="min-w-0">
                    <h3 class="text-sm font-semibold">
                        {{ side.title }}
                        <span class="font-normal text-muted-foreground"
                            >· {{ side.term }}</span
                        >
                    </h3>
                    <div class="mt-2 flex flex-wrap gap-3">
                        <button
                            v-for="photo in side.photos"
                            :key="photo.id"
                            type="button"
                            class="group w-28 rounded-lg text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            @click="openId = photo.id"
                        >
                            <div
                                class="aspect-square overflow-hidden rounded-lg border bg-muted"
                            >
                                <img
                                    :src="photo.url"
                                    :alt="altText(photo)"
                                    loading="lazy"
                                    class="size-full object-cover transition-transform group-hover:scale-105"
                                    @error="markFailed(photo.url)"
                                />
                            </div>
                            <p class="mt-1 truncate text-xs font-medium">
                                {{ photo.title }}
                            </p>
                            <p
                                v-if="photo.hint"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ photo.hint }}
                            </p>
                            <p
                                v-if="photo.ringNumber || photo.name"
                                class="truncate font-mono text-xs text-muted-foreground"
                            >
                                {{ photo.ringNumber || photo.name }}
                            </p>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:open="open">
            <DialogContent
                v-if="current"
                class="sm:max-w-3xl"
                @keydown.left.prevent="step(-1)"
                @keydown.right.prevent="step(1)"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ current.title }}
                        <span
                            v-if="current.ringNumber || current.name"
                            class="font-mono font-normal text-muted-foreground"
                            >· {{ current.ringNumber || current.name }}</span
                        >
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="current.position">
                            {{ familyPath(current.position) }} ·
                            {{
                                [
                                    'Pigeon',
                                    ...lineageSteps(current.position),
                                ].join(' › ')
                            }}
                        </template>
                        <template v-else>The pigeon on this pedigree</template>
                    </DialogDescription>
                </DialogHeader>
                <img
                    :src="current.url"
                    :alt="altText(current)"
                    class="max-h-[70vh] w-full rounded-md bg-muted object-contain"
                />
                <div
                    v-if="shown.length > 1"
                    class="flex items-center justify-between"
                >
                    <Button variant="outline" size="sm" @click="step(-1)">
                        <ChevronLeft class="mr-1 h-4 w-4" />
                        Previous
                    </Button>
                    <span class="text-xs text-muted-foreground">
                        {{ openIndex + 1 }} of {{ shown.length }}
                    </span>
                    <Button variant="outline" size="sm" @click="step(1)">
                        Next
                        <ChevronRight class="ml-1 h-4 w-4" />
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </section>

    <!-- Owner only: why there is nothing to show -->
    <div
        v-else-if="addPhotosHref"
        class="flex flex-col gap-3 rounded-xl border border-dashed p-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex items-start gap-3 text-sm">
            <ImageOff
                v-if="allFailed"
                class="mt-0.5 size-5 shrink-0 text-muted-foreground"
            />
            <ImagePlus
                v-else
                class="mt-0.5 size-5 shrink-0 text-muted-foreground"
            />
            <div>
                <template v-if="allFailed">
                    <p class="font-medium">The photos couldn't be loaded</p>
                    <p class="text-muted-foreground">
                        They are kept in your Google Drive. Check that it is
                        still connected in Settings → Storage.
                    </p>
                </template>
                <template v-else>
                    <p class="font-medium">No photos on this pedigree yet</p>
                    <p class="text-muted-foreground">
                        Add photos of this bird on its edit page. Photos of its
                        parents and grandparents from your loft show here too.
                        Only photos added in the app appear; files put straight
                        into Google Drive can't be seen by the app.
                    </p>
                </template>
            </div>
        </div>
        <Button size="sm" variant="outline" class="shrink-0" as-child>
            <Link v-if="allFailed" :href="storageSettings().url">
                Storage settings
            </Link>
            <Link v-else :href="addPhotosHref">Add photos</Link>
        </Button>
    </div>
</template>
