<script setup lang="ts">
import ColorTagSelect from '@/components/ColorTagSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import { lineageSteps, relationName } from '@/lib/pedigree';
import { removeAncestor, saveAncestor } from '@/routes/pigeons';
import type { AncestorTarget } from '@/types/pedigree';
import { router, useForm } from '@inertiajs/vue3';
import { ChevronRight, Home, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ColorTag {
    id: number;
    name: string;
    color: string;
}

const props = defineProps<{
    pigeonId: number;
    target: AncestorTarget | null;
    colorTags: ColorTag[];
    colors: string[];
}>();

const open = defineModel<boolean>('open', { required: true });

const { success, error } = useToast();

const form = useForm({
    anchor_id: 0,
    path: '',
    ring_number: '',
    name: '',
    color: '',
    remarks: '',
    color_tag_id: null as number | null,
});

const confirmingRemove = ref(false);
const removing = ref(false);

const node = computed(() => props.target?.entry.node ?? null);
const isNew = computed(() => !node.value);
const relation = computed(() =>
    props.target ? relationName(props.target.entry.key) : '',
);
const anchorLabel = computed(
    () =>
        props.target?.anchor?.ring_number ||
        props.target?.anchor?.name ||
        'that bird',
);

watch(
    () => [open.value, props.target] as const,
    ([isOpen, target]) => {
        if (!isOpen || !target) return;
        const current = target.entry.node;
        form.defaults({
            anchor_id: target.anchor_id,
            path: target.path,
            ring_number: current?.ring_number ?? '',
            name: current?.name ?? '',
            color: current?.color ?? '',
            remarks: current?.remarks ?? '',
            color_tag_id: current?.highlight?.id ?? null,
        });
        form.reset();
        form.clearErrors();
        confirmingRemove.value = false;
    },
    { immediate: true },
);

const save = () => {
    form.put(saveAncestor({ pigeon: props.pigeonId }).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            success(`${relation.value} saved`);
        },
    });
};

const remove = () => {
    if (!props.target) return;
    router.delete(removeAncestor({ pigeon: props.pigeonId }).url, {
        data: { anchor_id: props.target.anchor_id, path: props.target.path },
        preserveScroll: true,
        onStart: () => (removing.value = true),
        onFinish: () => (removing.value = false),
        onSuccess: () => {
            open.value = false;
            success(`${relation.value} removed`);
        },
        onError: () => error('Could not remove this ancestor'),
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isNew ? 'Add' : 'Edit' }} {{ relation.toLowerCase() }}
                </DialogTitle>
                <DialogDescription
                    v-if="target"
                    class="flex flex-wrap items-center gap-1"
                >
                    <span>Bird</span>
                    <template
                        v-for="(step, index) in lineageSteps(target.entry.key)"
                        :key="index"
                    >
                        <ChevronRight class="size-3.5" />
                        <span
                            :class="{
                                'font-medium text-foreground':
                                    index === target.entry.key.length - 1,
                            }"
                            >{{ step }}</span
                        >
                    </template>
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="target?.anchor"
                class="flex gap-2 rounded-lg bg-secondary/60 p-3 text-sm"
            >
                <Home class="mt-0.5 size-4 shrink-0 text-gold" />
                <p>
                    Saved on <strong>{{ anchorLabel }}</strong> from your loft,
                    so every offspring of {{ anchorLabel }} inherits this
                    ancestor.
                </p>
            </div>

            <form id="ancestor-form" class="grid gap-4" @submit.prevent="save">
                <InputError
                    :message="form.errors.path || form.errors.anchor_id"
                />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="ancestor_ring">Ring number</Label>
                        <Input
                            id="ancestor_ring"
                            v-model="form.ring_number"
                            autocomplete="off"
                            placeholder="e.g. BE 2019-1234567"
                        />
                        <InputError :message="form.errors.ring_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ancestor_name">Name</Label>
                        <Input
                            id="ancestor_name"
                            v-model="form.name"
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ancestor_color">Color</Label>
                        <Input
                            id="ancestor_color"
                            v-model="form.color"
                            autocomplete="off"
                            list="ancestor-colors"
                            placeholder="e.g. BLUE BAR"
                        />
                        <datalist id="ancestor-colors">
                            <option
                                v-for="color in colors"
                                :key="color"
                                :value="color"
                            />
                        </datalist>
                        <InputError :message="form.errors.color" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Pedigree highlight color</Label>
                        <ColorTagSelect
                            v-model="form.color_tag_id"
                            :color-tags="colorTags"
                        />
                        <InputError :message="form.errors.color_tag_id" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="ancestor_remarks"
                        >Remarks
                        <span class="font-normal text-muted-foreground"
                            >(shown on pedigree)</span
                        ></Label
                    >
                    <Textarea
                        id="ancestor_remarks"
                        v-model="form.remarks"
                        rows="4"
                        placeholder="Race results, breeder, key traits..."
                    />
                    <InputError :message="form.errors.remarks" />
                </div>
            </form>

            <div
                v-if="confirmingRemove"
                class="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm"
            >
                <p>
                    Remove this {{ relation.toLowerCase()
                    }}<template v-if="target?.behind">
                        and the {{ target.behind }}
                        {{ target.behind === 1 ? 'ancestor' : 'ancestors' }}
                        recorded behind it</template
                    >?
                </p>
                <div class="mt-3 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="confirmingRemove = false"
                        >Keep</Button
                    >
                    <Button
                        variant="destructive"
                        size="sm"
                        :disabled="removing"
                        @click="remove"
                    >
                        <LoaderCircle
                            v-if="removing"
                            class="size-4 animate-spin"
                        />
                        Remove
                    </Button>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:justify-between">
                <Button
                    v-if="!isNew && !confirmingRemove"
                    variant="ghost"
                    class="text-destructive hover:text-destructive"
                    @click="confirmingRemove = true"
                >
                    <Trash2 class="size-4" />
                    Remove
                </Button>
                <span v-else />
                <div class="flex gap-2">
                    <Button variant="outline" @click="open = false"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        form="ancestor-form"
                        :disabled="form.processing"
                    >
                        <LoaderCircle
                            v-if="form.processing"
                            class="size-4 animate-spin"
                        />
                        Save
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
