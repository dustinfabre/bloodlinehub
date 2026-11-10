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
import type { PedigreeNode } from '@/types/pedigree';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// A showcase of the bird on a pedigree: its own photos, then the main photos of its loft ancestors grouped by the
// father's and the mother's side, each named by its place in the family. Outside ancestors have no photos.
// Shows nothing when no bird on the chart has a photo.
const props = defineProps<{
    tree: PedigreeNode;
    // Every photo of the bird itself, the main one first
    photos?: string[];
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

const all = computed<Photo[]>(() =>
    flattenPedigree(props.tree)
        .filter(
            (slot) =>
                slot.node?.photo_url && !failed.value.has(slot.node.photo_url),
        )
        // Generation by generation, top to bottom within each
        .sort((a, b) => a.depth - b.depth || a.index - b.index)
        .map((slot) => ({
            id: `chart:${slot.key}`,
            url: slot.node!.photo_url!,
            position: slot.key,
            title: slot.key ? familyName(slot.key) : 'This pigeon',
            hint: slot.key.length > 1 ? familyPath(slot.key) : null,
            ringNumber: slot.node!.ring_number,
            name: slot.node!.name,
        })),
);

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
</template>
