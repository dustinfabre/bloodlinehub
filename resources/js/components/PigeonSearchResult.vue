<script setup lang="ts">
import type {
    PigeonSearchItem,
    PigeonSearchParent,
} from '@/types/pigeon-search';

// The standard way a pigeon is shown in every pigeon search and picker (the format of the breeders list).
// Put it inside the clickable result row; the "aside" slot sits beside the title (e.g. a badge) and the
// default slot adds lines underneath. See "Pigeon search results" in CLAUDE.md.
defineProps<{
    pigeon: PigeonSearchItem;
}>();

const parentLabel = (parent: PigeonSearchParent) =>
    parent.ring_number || parent.name;
</script>

<template>
    <div class="min-w-0">
        <div class="flex items-center gap-2">
            <div class="min-w-0 truncate text-sm font-medium">
                {{ pigeon.name || 'Unnamed' }} -
                {{ pigeon.ring_number || pigeon.personal_number || '—' }}
            </div>
            <slot name="aside" />
        </div>
        <div class="mt-1 space-y-0.5 text-xs text-muted-foreground">
            <div v-if="pigeon.bloodline || pigeon.color">
                {{
                    [pigeon.bloodline, pigeon.color].filter(Boolean).join(' • ')
                }}
            </div>
            <div v-if="pigeon.sire || pigeon.dam" class="opacity-90">
                <span v-if="pigeon.sire"
                    >S: {{ parentLabel(pigeon.sire) }}</span
                >
                <span v-if="pigeon.sire && pigeon.dam"> | </span>
                <span v-if="pigeon.dam">D: {{ parentLabel(pigeon.dam) }}</span>
            </div>
            <div v-if="pigeon.notes" class="truncate italic opacity-90">
                {{ pigeon.notes }}
            </div>
            <div v-if="pigeon.remarks" class="truncate italic opacity-90">
                {{ pigeon.remarks }}
            </div>
            <slot />
        </div>
    </div>
</template>
