<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MeritListEditor from '@/components/pedigree/MeritListEditor.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { lineageSteps, listLabels, relationName } from '@/lib/pedigree';
import { removeAncestor, saveAncestor } from '@/routes/pigeons';
import type {
    MeritInput,
    MeritLevelOption,
    MeritTypeOption,
} from '@/types/lineage';
import type { AncestorTarget, PedigreeSyncReport } from '@/types/pedigree';
import type { Page } from '@inertiajs/core';
import { router, useForm } from '@inertiajs/vue3';
import {
    ChevronRight,
    Copy,
    Home,
    Link2,
    LoaderCircle,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    pigeonId: number;
    target: AncestorTarget | null;
    colors: string[];
    bloodlines: string[];
    meritTypes: MeritTypeOption[];
    meritLevels: MeritLevelOption[];
}>();

const meritEditor = ref<InstanceType<typeof MeritListEditor> | null>(null);

const open = defineModel<boolean>('open', { required: true });

// Open "Copy from another pigeon" for this place (a chart key from the pigeon, e.g. "SSS")
const emit = defineEmits<{ copy: [key: string] }>();

const { success, error, warning } = useToast();

const form = useForm({
    anchor_id: 0,
    path: '',
    ring_number: '',
    name: '',
    color: '',
    bloodline: '',
    remarks: '',
    merits: [] as MeritInput[],
    // Also apply the change to this bird's records in other pedigrees
    sync: true,
});

// First error on any merit row, e.g. "merits.0.year"
const meritError = computed(
    () =>
        form.errors.merits ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('merits.'),
        )?.[1],
);

const confirmingRemove = ref(false);
const removing = ref(false);
const removeEverywhere = ref(true);

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

// Other pedigrees this save would also change: the bird's own other records when editing it, or the other
// records of the bird in front of this position when adding a new ancestor behind it
const syncLabels = computed(() =>
    isNew.value
        ? (props.target?.offspringAlsoIn ?? [])
        : (props.target?.alsoIn ?? []),
);
const offspringLabel = computed(
    () => props.target?.offspringLabel ?? 'the bird in front',
);

// Tell the user what else changed, from the report the server leaves on the page
const announce = (page: Page, done: string) => {
    const report = page.props.syncReport as PedigreeSyncReport | null;
    success(
        report?.updated.length
            ? `${done}, also in the pedigrees of ${listLabels(report.updated)}`
            : done,
    );
    if (report?.skipped.length) {
        warning(
            `Not added to ${listLabels(report.skipped)}: a different bird is already recorded there`,
        );
    }
};

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
            bloodline: current?.bloodline ?? '',
            remarks: current?.remarks ?? '',
            merits: (current?.merits ?? []).map((merit) => ({
                type: merit.type,
                level: merit.level,
                year: merit.year,
                detail: merit.detail,
            })),
            sync: true,
        });
        form.reset();
        form.clearErrors();
        confirmingRemove.value = false;
        removeEverywhere.value = true;
    },
    { immediate: true },
);

const save = () => {
    meritEditor.value?.flush();
    form.put(saveAncestor({ pigeon: props.pigeonId }).url, {
        preserveScroll: true,
        onSuccess: (page) => {
            open.value = false;
            announce(page, `${relation.value} saved`);
        },
    });
};

const remove = () => {
    if (!props.target) return;
    router.delete(removeAncestor({ pigeon: props.pigeonId }).url, {
        data: {
            anchor_id: props.target.anchor_id,
            path: props.target.path,
            sync: removeEverywhere.value,
        },
        preserveScroll: true,
        onStart: () => (removing.value = true),
        onFinish: () => (removing.value = false),
        onSuccess: (page) => {
            open.value = false;
            announce(page, `${relation.value} removed`);
        },
        onError: () => error('Could not remove this ancestor'),
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
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
                        <Label for="ancestor_bloodline">Bloodline</Label>
                        <Input
                            id="ancestor_bloodline"
                            v-model="form.bloodline"
                            autocomplete="off"
                            list="ancestor-bloodlines"
                            placeholder="e.g. Janssen"
                        />
                        <datalist id="ancestor-bloodlines">
                            <option
                                v-for="bloodline in bloodlines"
                                :key="bloodline"
                                :value="bloodline"
                            />
                        </datalist>
                        <InputError :message="form.errors.bloodline" />
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
                <div class="grid gap-2">
                    <Label
                        >Merits
                        <span class="font-normal text-muted-foreground"
                            >(shown on pedigree, used in Lineage and Test
                            Pairing)</span
                        ></Label
                    >
                    <MeritListEditor
                        ref="meritEditor"
                        v-model="form.merits"
                        :merit-types="meritTypes"
                        :merit-levels="meritLevels"
                        :error="meritError"
                    />
                </div>
            </form>

            <div
                v-if="syncLabels.length && !confirmingRemove"
                class="grid gap-2 rounded-lg border border-gold/40 bg-secondary/50 p-3 text-sm"
            >
                <p class="flex gap-2">
                    <Link2 class="mt-0.5 size-4 shrink-0 text-gold" />
                    <span v-if="isNew">
                        {{ offspringLabel }} is also recorded in the pedigrees
                        of {{ listLabels(syncLabels) }}.
                    </span>
                    <span v-else>
                        This bird is also recorded in the pedigrees of
                        {{ listLabels(syncLabels) }}, matched by ring number.
                    </span>
                </p>
                <label class="flex items-center gap-2 pl-6">
                    <Checkbox v-model="form.sync" />
                    {{
                        isNew
                            ? `Add this ${relation.toLowerCase()} there too, where that place is empty`
                            : 'Update those records too'
                    }}
                </label>
            </div>

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
                <label
                    v-if="target?.offspringAlsoIn.length"
                    class="mt-2 flex items-start gap-2"
                >
                    <Checkbox v-model="removeEverywhere" class="mt-0.5" />
                    <span
                        >Also remove it behind {{ offspringLabel }} in the
                        pedigrees of {{ listLabels(target.offspringAlsoIn) }},
                        where the same bird is recorded</span
                    >
                </label>
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
                <Button
                    v-else-if="
                        isNew &&
                        target &&
                        target.entry.key.length <= 3 &&
                        !confirmingRemove
                    "
                    variant="ghost"
                    @click="emit('copy', target.entry.key)"
                >
                    <Copy class="size-4" />
                    Copy from another pigeon
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
