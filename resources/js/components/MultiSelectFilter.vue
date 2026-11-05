<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

export interface FilterOption {
    value: string;
    label: string;
    // Optional swatch shown before the label: a class (e.g. status color) or a CSS color (e.g. merit color)
    dotClass?: string;
    dotColor?: string;
}

const props = withDefaults(
    defineProps<{
        id?: string;
        options: FilterOption[];
        placeholder: string;
        // Show a search box inside the list; defaults to on for long lists
        searchable?: boolean;
        emptyText?: string;
        class?: string;
    }>(),
    { searchable: undefined, emptyText: 'No options' },
);

const model = defineModel<string[]>({ default: () => [] });

const open = ref(false);
const query = ref('');

const showSearch = computed(() => props.searchable ?? props.options.length > 8);

const visibleOptions = computed(() => {
    const q = query.value.trim().toLowerCase();
    return q
        ? props.options.filter((o) => o.label.toLowerCase().includes(q))
        : props.options;
});

const selectedLabels = computed(() =>
    props.options
        .filter((o) => model.value.includes(o.value))
        .map((o) => o.label),
);

const triggerText = computed(() => {
    if (selectedLabels.value.length === 0) return props.placeholder;
    if (selectedLabels.value.length <= 2)
        return selectedLabels.value.join(', ');
    return `${selectedLabels.value.length} selected`;
});

const toggle = (value: string) => {
    model.value = model.value.includes(value)
        ? model.value.filter((v) => v !== value)
        : [...model.value, value];
};

watch(open, (isOpen) => {
    if (!isOpen) query.value = '';
});
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <button
                :id="id"
                type="button"
                :class="
                    cn(
                        'flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-sm whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30 dark:hover:bg-input/50',
                        props.class,
                    )
                "
            >
                <span
                    class="truncate"
                    :class="{ 'text-muted-foreground': model.length === 0 }"
                    >{{ triggerText }}</span
                >
                <span class="flex shrink-0 items-center gap-1">
                    <span
                        v-if="model.length > 2"
                        class="rounded-full bg-primary px-1.5 text-[10px] leading-4 font-semibold text-primary-foreground"
                    >
                        {{ model.length }}
                    </span>
                    <ChevronDown class="size-4 text-muted-foreground" />
                </span>
            </button>
        </PopoverTrigger>
        <PopoverContent
            align="start"
            class="w-(--reka-popover-trigger-width) min-w-56 p-0"
        >
            <div v-if="showSearch" class="relative border-b">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="query"
                    type="text"
                    placeholder="Search..."
                    class="h-9 w-full bg-transparent pr-3 pl-9 text-sm outline-none placeholder:text-muted-foreground"
                />
            </div>
            <div
                class="max-h-64 overflow-y-auto p-1"
                role="listbox"
                aria-multiselectable="true"
            >
                <button
                    v-for="option in visibleOptions"
                    :key="option.value"
                    type="button"
                    role="option"
                    :aria-selected="model.includes(option.value)"
                    class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm outline-none hover:bg-accent focus-visible:bg-accent"
                    @click="toggle(option.value)"
                >
                    <Checkbox
                        :model-value="model.includes(option.value)"
                        tabindex="-1"
                        class="pointer-events-none"
                    />
                    <span
                        v-if="option.dotClass || option.dotColor"
                        class="size-2 shrink-0 rounded-full"
                        :class="option.dotClass"
                        :style="
                            option.dotColor
                                ? { backgroundColor: option.dotColor }
                                : undefined
                        "
                    />
                    <span class="truncate">{{ option.label }}</span>
                </button>
                <p
                    v-if="visibleOptions.length === 0"
                    class="px-2 py-4 text-center text-sm text-muted-foreground"
                >
                    {{ query ? 'No matches' : emptyText }}
                </p>
            </div>
            <div v-if="model.length > 0" class="border-t p-1">
                <button
                    type="button"
                    class="flex w-full items-center justify-center gap-1 rounded-sm px-2 py-1.5 text-sm text-muted-foreground hover:bg-accent hover:text-foreground"
                    @click="model = []"
                >
                    <X class="size-3.5" />
                    Clear selection
                </button>
            </div>
        </PopoverContent>
    </Popover>
</template>
