<script setup lang="ts">
import MeritBadge from '@/components/MeritBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    Merit,
    MeritInput,
    MeritLevelOption,
    MeritTypeOption,
} from '@/types/lineage';
import { Plus, X } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

const props = defineProps<{
    meritTypes: MeritTypeOption[];
    meritLevels: MeritLevelOption[];
    error?: string;
}>();

const merits = defineModel<MeritInput[]>({ required: true });

const NO_LEVEL = 'none';
const draft = reactive({ type: '', level: NO_LEVEL, year: '', detail: '' });

const groups = computed(() => [
    {
        label: 'Racing',
        types: props.meritTypes.filter((type) => type.category === 'racing'),
    },
    {
        label: 'Breeding',
        types: props.meritTypes.filter((type) => type.category === 'breeding'),
    },
]);

// Badge-ready version of each stored merit
const shown = computed<Merit[]>(() =>
    merits.value.map((merit) => {
        const type = props.meritTypes.find((t) => t.value === merit.type);
        const level = props.meritLevels.find((l) => l.value === merit.level);
        return {
            id: null,
            type: merit.type,
            label: type?.label ?? merit.type,
            code: type?.code ?? merit.type,
            category: type?.category ?? 'racing',
            level: merit.level,
            level_label: level?.label ?? null,
            year: merit.year,
            detail: merit.detail,
            short: [
                type?.code,
                level?.label,
                merit.year ? `'${String(merit.year).slice(-2)}` : null,
            ]
                .filter(Boolean)
                .join(' '),
        };
    }),
);

const add = () => {
    if (!draft.type) return;
    const year = Number(draft.year);
    merits.value = [
        ...merits.value,
        {
            type: draft.type,
            level: draft.level === NO_LEVEL ? null : draft.level,
            year: Number.isInteger(year) && year > 0 ? year : null,
            detail: draft.detail.trim() || null,
        },
    ];
    Object.assign(draft, { type: '', level: NO_LEVEL, year: '', detail: '' });
};

// Picked but not added yet: the dialog adds it on save rather than dropping it
defineExpose({ flush: add });

const remove = (index: number) => {
    merits.value = merits.value.filter((_, i) => i !== index);
};
</script>

<template>
    <div class="grid gap-3">
        <ul v-if="shown.length" class="divide-y rounded-lg border">
            <li
                v-for="(merit, index) in shown"
                :key="`${merit.type}-${index}`"
                class="flex items-center justify-between gap-2 px-3 py-2"
            >
                <div class="flex min-w-0 flex-wrap items-center gap-2 text-sm">
                    <MeritBadge :merit="merit" full />
                    <span class="text-muted-foreground">{{
                        [merit.level_label, merit.year, merit.detail]
                            .filter(Boolean)
                            .join(' · ')
                    }}</span>
                </div>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    class="size-7 shrink-0 text-muted-foreground"
                    :aria-label="`Remove ${merit.label}`"
                    @click="remove(index)"
                >
                    <X class="size-4" />
                </Button>
            </li>
        </ul>

        <div class="grid gap-2 sm:grid-cols-[1fr_9rem_5.5rem]">
            <Select v-model="draft.type">
                <SelectTrigger class="w-full" aria-label="Merit">
                    <SelectValue placeholder="Merit" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup v-for="group in groups" :key="group.label">
                        <SelectLabel>{{ group.label }}</SelectLabel>
                        <SelectItem
                            v-for="type in group.types"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                            <span class="text-muted-foreground"
                                >({{ type.code }})</span
                            >
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
            <Select v-model="draft.level">
                <SelectTrigger class="w-full" aria-label="Level">
                    <SelectValue placeholder="Level" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="NO_LEVEL">Any level</SelectItem>
                    <SelectItem
                        v-for="level in meritLevels"
                        :key="level.value"
                        :value="level.value"
                    >
                        {{ level.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Input
                v-model="draft.year"
                type="number"
                inputmode="numeric"
                min="1900"
                placeholder="Year"
                aria-label="Year"
            />
        </div>
        <div class="flex gap-2">
            <Input
                v-model="draft.detail"
                placeholder="Detail, e.g. 1st National Bourges"
                aria-label="Detail"
                @keydown.enter.prevent="add"
            />
            <Button
                type="button"
                variant="outline"
                :disabled="!draft.type"
                @click="add"
            >
                <Plus class="size-4" />
                Add
            </Button>
        </div>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    </div>
</template>
