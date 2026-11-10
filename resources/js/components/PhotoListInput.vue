<script setup lang="ts">
import ImageCropper from '@/components/ImageCropper.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { newPhotoItem, type PhotoItem, shrinkImage } from '@/lib/images';
import { Crop, ImagePlus, Star, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

// A list of photos on the pigeon form: add as many as needed, remove any, and optionally crop new ones.
// With `main`, the first photo is the main photo (shown on cards and the pedigree) and any other can be made main.
const props = defineProps<{
    id: string;
    label: string;
    description?: string;
    main?: boolean;
    error?: string;
}>();

const items = defineModel<PhotoItem[]>({ required: true });

const input = ref<HTMLInputElement | null>(null);
const adding = ref(false);

const isLocal = (src: string) => src.startsWith('blob:');

// Free the local previews of photos that leave the list (removed, cropped, or the form was reset)
watch(items, (next, previous) => {
    const kept = new Set(next.map((item) => item.src));
    previous
        ?.filter((item) => isLocal(item.src) && !kept.has(item.src))
        .forEach((item) => URL.revokeObjectURL(item.src));
});

onBeforeUnmount(() => {
    items.value
        .filter((item) => isLocal(item.src))
        .forEach((item) => URL.revokeObjectURL(item.src));
});

const add = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files ?? []);
    // Let the same file be chosen again later
    target.value = '';
    if (!files.length) return;

    adding.value = true;
    try {
        const prepared = await Promise.all(files.map(shrinkImage));
        items.value = [...items.value, ...prepared.map(newPhotoItem)];
    } finally {
        adding.value = false;
    }
};

const remove = (index: number) => {
    items.value = items.value.filter((_, i) => i !== index);
};

const makeMain = (index: number) => {
    const list = [...items.value];
    const [item] = list.splice(index, 1);
    items.value = [item, ...list];
};

// Cropping is optional, for photos added now
const cropIndex = ref<number | null>(null);
const cropOpen = ref(false);
const cropItem = computed(() =>
    cropIndex.value === null ? null : (items.value[cropIndex.value] ?? null),
);

const startCrop = (index: number) => {
    cropIndex.value = index;
    cropOpen.value = true;
};

const applyCrop = (file: File) => {
    const index = cropIndex.value;
    if (index === null || !items.value[index]) return;
    const cropped = newPhotoItem(file);
    items.value = items.value.map((item, i) =>
        i === index ? { ...cropped, key: item.key } : item,
    );
    cropIndex.value = null;
};

watch(cropOpen, (open) => {
    if (!open) cropIndex.value = null;
});
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-baseline justify-between gap-2">
            <Label :for="props.id">{{ label }}</Label>
            <span v-if="items.length" class="text-xs text-muted-foreground">
                {{ items.length }} {{ items.length === 1 ? 'photo' : 'photos' }}
            </span>
        </div>
        <p v-if="description" class="text-xs text-muted-foreground">
            {{ description }}
        </p>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div
                v-for="(item, index) in items"
                :key="item.key"
                class="relative overflow-hidden rounded-lg border bg-muted"
            >
                <img
                    :src="item.src"
                    :alt="`${label} ${index + 1}`"
                    class="aspect-square w-full object-contain"
                />
                <span
                    v-if="main && index === 0"
                    class="absolute top-1.5 left-1.5 rounded bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground"
                >
                    Main
                </span>
                <span
                    v-else-if="!item.saved"
                    class="absolute top-1.5 left-1.5 rounded bg-background/90 px-1.5 py-0.5 text-[10px] font-semibold"
                >
                    New
                </span>
                <div
                    class="absolute inset-x-0 bottom-0 flex justify-end gap-1 bg-gradient-to-t from-black/60 to-transparent p-1.5 pt-6"
                >
                    <Button
                        v-if="main && index > 0"
                        type="button"
                        size="icon"
                        variant="secondary"
                        class="size-7"
                        title="Make main photo"
                        aria-label="Make main photo"
                        @click="makeMain(index)"
                    >
                        <Star class="size-4" />
                    </Button>
                    <Button
                        v-if="item.file"
                        type="button"
                        size="icon"
                        variant="secondary"
                        class="size-7"
                        title="Crop"
                        aria-label="Crop photo"
                        @click="startCrop(index)"
                    >
                        <Crop class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="destructive"
                        class="size-7"
                        title="Remove"
                        aria-label="Remove photo"
                        @click="remove(index)"
                    >
                        <X class="size-4" />
                    </Button>
                </div>
            </div>

            <button
                type="button"
                class="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60"
                :disabled="adding"
                @click="input?.click()"
            >
                <Spinner v-if="adding" class="size-5" />
                <ImagePlus v-else class="size-6" />
                {{ adding ? 'Preparing…' : 'Add photos' }}
            </button>
        </div>

        <input
            :id="props.id"
            ref="input"
            type="file"
            accept="image/*"
            multiple
            class="sr-only"
            @change="add"
        />
        <InputError :message="error" />

        <ImageCropper
            v-model:open="cropOpen"
            :image-src="cropItem?.src ?? null"
            :file-name="cropItem?.file?.name"
            @crop="applyCrop"
        />
    </div>
</template>
