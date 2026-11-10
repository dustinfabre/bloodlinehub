<script setup lang="ts">
import ImageCropper from '@/components/ImageCropper.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    newPhotoItem,
    OTHER_RELATIVE_OPTIONS,
    RELATIVE_OPTIONS,
    shrinkImage,
    type PhotoItem,
} from '@/lib/images';
import {
    ChevronDown,
    Crop,
    Eye,
    ImagePlus,
    Info,
    Lock,
    Star,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

// A list of photos on the pigeon form: add as many as needed, remove any, and optionally crop new ones.
// With `main`, the first photo is the main photo (shown on cards and the pedigree) and any other can be made main.
const props = defineProps<{
    id: string;
    label: string;
    description?: string;
    main?: boolean;
    // Ask what each image shows: a certificate or document, or which relative
    relations?: boolean;
    // A tip shown above the photos
    note?: string;
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

const setRelation = (index: number, relation: string) => {
    items.value = items.value.map((item, i) => {
        if (i !== index) return item;
        const next = relation || null;
        // Saying who a photo shows usually means showing it; the owner can still untick it
        const show = !item.relation && next ? true : (item.public ?? false);
        return { ...item, relation: next, public: show };
    });
};

const setPublic = (index: number, show: boolean) => {
    items.value = items.value.map((item, i) =>
        i === index ? { ...item, public: show } : item,
    );
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
        i === index
            ? {
                  ...cropped,
                  key: item.key,
                  relation: item.relation,
                  public: item.public,
              }
            : item,
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
        <div
            v-if="note"
            class="flex gap-2 rounded-md border border-gold/40 bg-secondary/50 p-2.5 text-xs"
        >
            <Info class="mt-0.5 size-4 shrink-0 text-gold" />
            <p>{{ note }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div
                v-for="(item, index) in items"
                :key="item.key"
                class="flex min-w-0 flex-col gap-2"
            >
                <div
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
                <!-- Who the photo shows: a certificate stays private, a relative is shown on the pedigree -->
                <div v-if="relations" class="grid gap-1">
                    <label
                        :for="`${props.id}-relation-${index}`"
                        class="text-xs font-medium"
                        >Who is this?</label
                    >
                    <div class="relative">
                        <select
                            :id="`${props.id}-relation-${index}`"
                            :value="item.relation ?? ''"
                            class="h-9 w-full min-w-0 cursor-pointer appearance-none truncate rounded-md border border-input bg-background py-1 pr-8 pl-2.5 text-xs shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            @change="
                                setRelation(
                                    index,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="">Certificate / document</option>
                            <optgroup label="Parents & ancestors">
                                <option
                                    v-for="option in RELATIVE_OPTIONS"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </optgroup>
                            <optgroup
                                label="Brothers, sisters & other relatives"
                            >
                                <option
                                    v-for="option in OTHER_RELATIVE_OPTIONS"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </optgroup>
                        </select>
                        <ChevronDown
                            class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                    </div>
                    <!-- The owner decides what is on the pedigree, certificates included -->
                    <label
                        class="mt-1 flex cursor-pointer items-start gap-2 rounded-md border px-2 py-1.5 text-xs transition-colors"
                        :class="
                            item.public
                                ? 'border-emerald-600/40 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-950/40'
                                : 'border-input'
                        "
                    >
                        <Checkbox
                            :model-value="item.public ?? false"
                            class="mt-px"
                            @update:model-value="
                                setPublic(index, $event === true)
                            "
                        />
                        <span class="grid min-w-0 gap-0.5">
                            <span class="font-medium">Show on pedigree</span>
                            <span
                                class="flex items-center gap-1 text-[11px]"
                                :class="
                                    item.public
                                        ? 'text-emerald-700 dark:text-emerald-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                <Eye
                                    v-if="item.public"
                                    class="size-3 shrink-0"
                                />
                                <Lock v-else class="size-3 shrink-0" />
                                {{
                                    item.public
                                        ? 'Anyone with the link sees it'
                                        : 'Private, only you see it'
                                }}
                            </span>
                        </span>
                    </label>
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
