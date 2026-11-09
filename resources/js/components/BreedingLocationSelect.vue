<script setup lang="ts">
import { computed } from 'vue';

export interface BreedingLocationOption {
    id: number;
    name: string;
    type: string;
    is_breeding: boolean;
}

// The breeding location of a pair. Locations marked as breeding locations are listed first.
const props = defineProps<{
    id?: string;
    locations: BreedingLocationOption[];
}>();

const model = defineModel<string>({ required: true });

const breeding = computed(() => props.locations.filter((loc) => loc.is_breeding));
const others = computed(() => props.locations.filter((loc) => !loc.is_breeding));

const label = (loc: BreedingLocationOption) => `${loc.name}${loc.type === 'olr' ? ' (OLR)' : ''}`;
</script>

<template>
    <select
        :id="id"
        v-model="model"
        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
    >
        <option value="">— No location —</option>
        <template v-if="breeding.length > 0">
            <optgroup label="Breeding locations">
                <option v-for="loc in breeding" :key="loc.id" :value="String(loc.id)">{{ label(loc) }}</option>
            </optgroup>
            <optgroup v-if="others.length > 0" label="Other locations">
                <option v-for="loc in others" :key="loc.id" :value="String(loc.id)">{{ label(loc) }}</option>
            </optgroup>
        </template>
        <template v-else>
            <option v-for="loc in others" :key="loc.id" :value="String(loc.id)">{{ label(loc) }}</option>
        </template>
    </select>
</template>
