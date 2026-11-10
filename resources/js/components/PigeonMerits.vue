<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MeritBadge from '@/components/MeritBadge.vue';
import MeritSuggestionList from '@/components/MeritSuggestionList.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useMeritSuggestions } from '@/composables/useMeritSuggestions';
import { useToast } from '@/composables/useToast';
import {
    destroy as destroyMerit,
    store as storeMerit,
} from '@/routes/pigeons/merits';
import type {
    Merit,
    MeritLevelOption,
    MeritSuggestion,
    MeritTypeOption,
} from '@/types/lineage';
import { router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Sparkles, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Two root nodes (card + dialog): pass layout classes to the card
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    pigeonId: number;
    merits: Merit[];
    meritTypes: MeritTypeOption[];
    meritLevels: MeritLevelOption[];
    // Merits worked out from race results and offspring
    suggestions?: MeritSuggestion[];
}>();

const { success } = useToast();
const { add: addSuggestions, busy: suggestionBusy } = useMeritSuggestions();
const open = ref(false);

const form = useForm({
    type: '',
    level: '',
    year: '' as string | number,
    detail: '',
});

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

const selectedType = computed(() =>
    props.meritTypes.find((type) => type.value === form.type),
);

const openDialog = () => {
    form.reset();
    form.clearErrors();
    open.value = true;
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        level: data.level || null,
        year: data.year || null,
        detail: data.detail || null,
    })).post(storeMerit({ pigeon: props.pigeonId }).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            success('Merit added');
        },
    });
};

const remove = (merit: Merit) => {
    if (merit.id === null) return;
    router.delete(
        destroyMerit({ pigeon: props.pigeonId, merit: merit.id }).url,
        {
            preserveScroll: true,
            onSuccess: () => success('Merit removed'),
        },
    );
};
</script>

<template>
    <Card v-bind="$attrs">
        <CardHeader>
            <div class="flex items-start justify-between gap-2">
                <div class="space-y-1.5">
                    <CardTitle>Merits</CardTitle>
                    <CardDescription>
                        Standard titles shared by every loft, so Lineage and
                        Pairing Analysis can rank proven birds.
                    </CardDescription>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="shrink-0"
                    @click="openDialog"
                >
                    <Plus class="size-4" />
                    Add merit
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="merits.length" class="divide-y rounded-lg border">
                <div
                    v-for="(merit, index) in merits"
                    :key="merit.id ?? `new-${index}`"
                    class="flex items-center justify-between gap-3 p-3"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <MeritBadge :merit="merit" full />
                            <span class="text-sm text-muted-foreground">
                                {{
                                    [merit.level_label, merit.year]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                            </span>
                        </div>
                        <p v-if="merit.detail" class="text-sm">
                            {{ merit.detail }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="shrink-0 text-muted-foreground hover:text-destructive"
                        :aria-label="`Remove ${merit.label}`"
                        @click="remove(merit)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No merits recorded yet.
            </p>

            <section v-if="suggestions?.length" class="mt-5 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3
                            class="flex items-center gap-1.5 text-sm font-medium"
                        >
                            <Sparkles class="size-4 text-gold" />
                            Suggested from results
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Worked out from race results and offspring. Add the
                            ones that are right.
                        </p>
                    </div>
                    <Button
                        v-if="suggestions.length > 1"
                        size="sm"
                        variant="outline"
                        :disabled="suggestionBusy !== null"
                        @click="
                            addSuggestions(
                                suggestions.map((s) => s.key),
                                'all',
                            )
                        "
                    >
                        <LoaderCircle
                            v-if="suggestionBusy === 'all'"
                            class="size-4 animate-spin"
                        />
                        <Plus v-else class="size-4" />
                        Add all {{ suggestions.length }}
                    </Button>
                </div>
                <MeritSuggestionList :suggestions="suggestions" />
            </section>
        </CardContent>
    </Card>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Add merit</DialogTitle>
                <DialogDescription
                    >Pick the title that fits. Add the level and year, and the
                    race or result in the detail.</DialogDescription
                >
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="merit-type">Merit</Label>
                    <Select v-model="form.type">
                        <SelectTrigger id="merit-type" class="w-full">
                            <SelectValue placeholder="Choose a merit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup
                                v-for="group in groups"
                                :key="group.label"
                            >
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
                    <p
                        v-if="selectedType"
                        class="text-sm text-muted-foreground"
                    >
                        {{ selectedType.description }}
                    </p>
                    <InputError :message="form.errors.type" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="merit-level"
                            >Level
                            <span class="font-normal text-muted-foreground"
                                >(optional)</span
                            ></Label
                        >
                        <Select v-model="form.level">
                            <SelectTrigger id="merit-level" class="w-full">
                                <SelectValue placeholder="Any level" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="level in meritLevels"
                                    :key="level.value"
                                    :value="level.value"
                                    >{{ level.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.level" />
                    </div>
                    <div class="space-y-2">
                        <Label for="merit-year"
                            >Year
                            <span class="font-normal text-muted-foreground"
                                >(optional)</span
                            ></Label
                        >
                        <Input
                            id="merit-year"
                            v-model="form.year"
                            type="number"
                            inputmode="numeric"
                            min="1900"
                            :max="new Date().getFullYear() + 1"
                            :placeholder="String(new Date().getFullYear())"
                        />
                        <InputError :message="form.errors.year" />
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="merit-detail"
                        >Detail
                        <span class="font-normal text-muted-foreground"
                            >(optional)</span
                        ></Label
                    >
                    <Input
                        id="merit-detail"
                        v-model="form.detail"
                        maxlength="255"
                        placeholder="e.g. 3rd Overall, 5-lap Summer Derby"
                    />
                    <InputError :message="form.errors.detail" />
                </div>
                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        :disabled="!form.type || form.processing"
                        >{{
                            form.processing ? 'Adding...' : 'Add merit'
                        }}</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
