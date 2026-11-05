<script setup lang="ts">
import { topMerit } from '@/lib/merits';
import { relationName, sexSymbol } from '@/lib/pedigree';
import { show as showRoute } from '@/routes/pigeons';
import type { PedigreeSlot } from '@/types/pedigree';
import { Link } from '@inertiajs/vue3';
import { Home, Pencil, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    entry: PedigreeSlot;
    // Whether this position can hold an external ancestor (empty or already external)
    editable: boolean;
}>();

defineEmits<{ edit: [] }>();

const node = computed(() => props.entry.node);
const gender = computed(() =>
    props.entry.side === 'sire'
        ? 'male'
        : props.entry.side === 'dam'
          ? 'female'
          : (node.value?.gender ?? null),
);
const label = computed(
    () => node.value?.ring_number || node.value?.name || 'Unnamed',
);
const detail = computed(() =>
    [node.value?.ring_number ? node.value.name : null, node.value?.color]
        .filter(Boolean)
        .join(' · '),
);
// Same accent as the printed pedigree: a stripe in the color of the bird's most significant merit
const accent = computed(() => {
    const best = topMerit(node.value?.merits);
    return best ? { boxShadow: `inset 3px 0 0 ${best.color}` } : undefined;
});
const compact = computed(() => props.entry.depth >= 3);
</script>

<template>
    <!-- The bird itself -->
    <div
        v-if="entry.depth === 0 && node"
        class="w-full rounded-xl bg-navy p-4 text-ivory shadow-sm"
        :style="accent"
    >
        <p
            class="text-[10px] font-semibold tracking-[0.2em] text-gold uppercase"
        >
            This bird
        </p>
        <p class="mt-1 truncate text-base font-bold text-gold tabular-nums">
            {{ sexSymbol(gender) }} {{ label }}
        </p>
        <p v-if="detail" class="truncate text-sm text-ivory/75">
            {{ detail }}
        </p>
    </div>

    <!-- A bird from the loft: read-only here, it brings its own pedigree -->
    <div
        v-else-if="node && !node.external"
        class="flex w-full min-w-0 items-center gap-2 rounded-lg border bg-card shadow-xs"
        :class="compact ? 'px-2 py-1' : 'px-3 py-2'"
        :style="accent"
        :title="`${relationName(entry.key)} in your loft. Its details and pedigree come from its own record.`"
    >
        <span
            class="flex shrink-0 items-center justify-center rounded-full bg-secondary text-navy dark:text-gold"
            :class="compact ? 'size-5' : 'size-6'"
        >
            <Home :class="compact ? 'size-3' : 'size-3.5'" />
        </span>
        <div class="min-w-0 flex-1">
            <Link
                v-if="node.id"
                :href="showRoute({ pigeon: node.id }).url"
                class="block truncate font-semibold tabular-nums hover:underline"
                :class="compact ? 'text-xs' : 'text-sm'"
                >{{ label }}</Link
            >
            <p
                class="truncate text-muted-foreground"
                :class="compact ? 'text-[11px]' : 'text-xs'"
            >
                {{ detail || 'Loft bird' }}
            </p>
        </div>
    </div>

    <!-- An external bird: click to edit -->
    <button
        v-else-if="node && editable"
        type="button"
        class="group relative flex w-full min-w-0 items-center gap-2 rounded-lg border border-transparent bg-secondary/70 text-left transition hover:border-gold hover:bg-secondary focus-visible:border-gold focus-visible:outline-none"
        :class="compact ? 'px-2 py-1' : 'px-3 py-2'"
        :style="accent"
        @click="$emit('edit')"
    >
        <span
            class="flex shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
            :class="[
                compact ? 'size-5' : 'size-6',
                gender === 'male'
                    ? 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300'
                    : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
            ]"
            >{{ sexSymbol(gender) }}</span
        >
        <div class="min-w-0 flex-1">
            <p
                class="truncate font-semibold tabular-nums"
                :class="compact ? 'text-xs' : 'text-sm'"
            >
                {{ label }}
            </p>
            <p
                v-if="detail"
                class="truncate text-muted-foreground"
                :class="compact ? 'text-[11px]' : 'text-xs'"
            >
                {{ detail }}
            </p>
            <p
                v-if="node.remarks && entry.depth <= 2"
                class="mt-1 line-clamp-2 text-xs text-muted-foreground"
            >
                {{ node.remarks }}
            </p>
        </div>
        <Pencil
            class="absolute top-1.5 right-1.5 size-3 text-muted-foreground opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100"
        />
    </button>

    <!-- A missing ancestor whose offspring is known: click to add -->
    <button
        v-else-if="editable"
        type="button"
        class="flex w-full min-w-0 items-center gap-2 rounded-lg border border-dashed border-border text-left text-muted-foreground transition hover:border-gold hover:bg-accent/60 hover:text-foreground focus-visible:border-gold focus-visible:outline-none"
        :class="compact ? 'px-2 py-1.5 text-xs' : 'px-3 py-2.5 text-sm'"
        @click="$emit('edit')"
    >
        <Plus class="size-3.5 shrink-0" />
        <span class="truncate"
            >Add {{ relationName(entry.key).toLowerCase() }}</span
        >
    </button>
</template>
