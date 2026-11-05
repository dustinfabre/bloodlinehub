<script setup lang="ts">
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
} from '@/components/ui/popover';
import { birdLabel, genderName } from '@/lib/lineage';
import { search as searchRoute } from '@/routes/lineage';
import type { PickerPigeon } from '@/types/lineage';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import { LoaderCircle, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// Anything with a label works as the current selection (picker results or lineage bird summaries)
type Selected = Pick<
    PickerPigeon,
    'name' | 'ring_number' | 'personal_number' | 'gender'
> & { id: number | null };

const props = defineProps<{
    id?: string;
    selected: Selected | null;
    gender?: 'male' | 'female';
    placeholder?: string;
}>();

const emit = defineEmits<{ select: [pigeon: PickerPigeon]; clear: [] }>();

const open = ref(false);
const query = ref('');
const results = ref<PickerPigeon[]>([]);
const loading = ref(false);
const highlighted = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);
let requestId = 0;

// Clicking back into the search box must not count as "outside" and close the list
const keepOpenForInput = (event: CustomEvent<{ originalEvent?: Event }>) => {
    const target = (event.detail?.originalEvent?.target ??
        event.target) as Node | null;
    if (target && inputRef.value?.contains(target)) event.preventDefault();
};

const selectedText = computed(() => {
    if (!props.selected) return '';
    const label = birdLabel(props.selected);
    return props.selected.name && props.selected.ring_number
        ? `${label} · ${props.selected.ring_number}`
        : label;
});

const load = async () => {
    const current = ++requestId;
    loading.value = true;
    try {
        const { data } = await axios.get(searchRoute().url, {
            params: { q: query.value || undefined, gender: props.gender },
        });
        // Ignore responses that arrive after a newer search
        if (current !== requestId) return;
        results.value = data.pigeons;
        highlighted.value = 0;
    } finally {
        if (current === requestId) loading.value = false;
    }
};

const debouncedLoad = useDebounceFn(load, 250);

watch(query, () => {
    open.value = true;
    debouncedLoad();
});

const onFocus = () => {
    open.value = true;
    if (results.value.length === 0) load();
};

const choose = (pigeon: PickerPigeon) => {
    emit('select', pigeon);
    query.value = '';
    open.value = false;
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        open.value = true;
        highlighted.value = Math.min(
            highlighted.value + 1,
            results.value.length - 1,
        );
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlighted.value = Math.max(highlighted.value - 1, 0);
    } else if (
        event.key === 'Enter' &&
        open.value &&
        results.value[highlighted.value]
    ) {
        event.preventDefault();
        choose(results.value[highlighted.value]);
    } else if (event.key === 'Escape') {
        open.value = false;
    }
};
</script>

<template>
    <Popover v-model:open="open">
        <PopoverAnchor as-child>
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    :id="id"
                    ref="inputRef"
                    v-model="query"
                    type="text"
                    role="combobox"
                    autocomplete="off"
                    :aria-expanded="open"
                    :placeholder="
                        selected
                            ? selectedText
                            : (placeholder ??
                              'Search by ring number or name...')
                    "
                    class="flex h-10 w-full rounded-md border border-input bg-transparent py-2 pr-9 pl-9 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    :class="
                        selected && !query
                            ? 'placeholder:font-medium placeholder:text-foreground'
                            : 'placeholder:text-muted-foreground'
                    "
                    @focus="onFocus"
                    @click="open = true"
                    @keydown="onKeydown"
                />
                <LoaderCircle
                    v-if="loading"
                    class="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                />
                <button
                    v-else-if="selected"
                    type="button"
                    class="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    aria-label="Clear selection"
                    @click="emit('clear')"
                >
                    <X class="size-4" />
                </button>
            </div>
        </PopoverAnchor>
        <PopoverContent
            align="start"
            class="w-(--reka-popover-trigger-width) min-w-64 p-1"
            @open-auto-focus.prevent
            @interact-outside="keepOpenForInput"
        >
            <div class="max-h-72 overflow-y-auto" role="listbox">
                <button
                    v-for="(pigeon, index) in results"
                    :key="pigeon.id"
                    type="button"
                    role="option"
                    :aria-selected="index === highlighted"
                    class="flex w-full items-center justify-between gap-3 rounded-sm px-2 py-2 text-left text-sm"
                    :class="
                        index === highlighted ? 'bg-accent' : 'hover:bg-accent'
                    "
                    @mouseenter="highlighted = index"
                    @click="choose(pigeon)"
                >
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{
                            birdLabel(pigeon)
                        }}</span>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                        >
                            {{
                                [
                                    pigeon.name ? pigeon.ring_number : null,
                                    pigeon.color,
                                    pigeon.bloodline,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </span>
                    </span>
                    <span
                        v-if="genderName(pigeon.gender)"
                        class="shrink-0 text-xs text-muted-foreground"
                        >{{ genderName(pigeon.gender) }}</span
                    >
                </button>
                <p
                    v-if="!loading && results.length === 0"
                    class="px-2 py-4 text-center text-sm text-muted-foreground"
                >
                    No pigeons found
                </p>
            </div>
        </PopoverContent>
    </Popover>
</template>
