<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MeritListEditor from '@/components/pedigree/MeritListEditor.vue';
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
import { lineageSteps, relationName } from '@/lib/pedigree';
import type { MeritLevelOption, MeritTypeOption } from '@/types/lineage';
import type { ImportBird } from '@/types/pedigree-import';
import { ChevronRight, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

// Correct one ancestor read from a pedigree sheet. Changes stay on the review page until the whole pedigree is saved.
const props = defineProps<{
    // Place in the pedigree, e.g. "SD"
    position: string;
    bird: ImportBird | null;
    // Ancestors recorded behind this one, removed along with it
    behind: number;
    colors: string[];
    bloodlines: string[];
    meritTypes: MeritTypeOption[];
    meritLevels: MeritLevelOption[];
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{ save: [bird: ImportBird]; remove: [] }>();

const meritEditor = ref<InstanceType<typeof MeritListEditor> | null>(null);
const confirmingRemove = ref(false);
const missingId = ref(false);

// Text fields as the inputs edit them; empty ones become null when saved
const form = reactive<
    Record<
        'ring_number' | 'name' | 'color' | 'bloodline' | 'remarks',
        string
    > & {
        merits: ImportBird['merits'];
    }
>({
    ring_number: '',
    name: '',
    color: '',
    bloodline: '',
    remarks: '',
    merits: [],
});

const relation = computed(() =>
    props.position ? relationName(props.position) : '',
);
const isNew = computed(() => !props.bird);

watch(
    () => [open.value, props.bird, props.position] as const,
    ([isOpen, bird]) => {
        if (!isOpen) return;
        form.ring_number = bird?.ring_number ?? '';
        form.name = bird?.name ?? '';
        form.color = bird?.color ?? '';
        form.bloodline = bird?.bloodline ?? '';
        form.remarks = bird?.remarks ?? '';
        form.merits = (bird?.merits ?? []).map((merit) => ({ ...merit }));
        confirmingRemove.value = false;
        missingId.value = false;
    },
    { immediate: true },
);

const blankToNull = (value: string | null) => value?.trim() || null;

const save = () => {
    meritEditor.value?.flush();
    if (!form.ring_number?.trim() && !form.name?.trim()) {
        missingId.value = true;
        return;
    }
    emit('save', {
        ring_number: blankToNull(form.ring_number),
        name: blankToNull(form.name),
        color: blankToNull(form.color),
        bloodline: blankToNull(form.bloodline),
        remarks: blankToNull(form.remarks),
        merits: form.merits,
    });
    open.value = false;
};

const remove = () => {
    emit('remove');
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isNew ? 'Add' : 'Check' }} {{ relation.toLowerCase() }}
                </DialogTitle>
                <DialogDescription class="flex flex-wrap items-center gap-1">
                    <span>Bird</span>
                    <template
                        v-for="(step, index) in lineageSteps(position)"
                        :key="index"
                    >
                        <ChevronRight class="size-3.5" />
                        <span
                            :class="{
                                'font-medium text-foreground':
                                    index === position.length - 1,
                            }"
                            >{{ step }}</span
                        >
                    </template>
                </DialogDescription>
            </DialogHeader>

            <form
                id="import-bird-form"
                class="grid gap-4"
                @submit.prevent="save"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="import_bird_ring">Ring number</Label>
                        <Input
                            id="import_bird_ring"
                            v-model="form.ring_number"
                            autocomplete="off"
                            placeholder="e.g. BE 2019-1234567"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="import_bird_name">Name</Label>
                        <Input
                            id="import_bird_name"
                            v-model="form.name"
                            autocomplete="off"
                        />
                    </div>
                    <InputError
                        v-if="missingId"
                        class="sm:col-span-2"
                        message="Enter a ring number or a name."
                    />
                    <div class="grid gap-2">
                        <Label for="import_bird_color">Color</Label>
                        <Input
                            id="import_bird_color"
                            v-model="form.color"
                            autocomplete="off"
                            list="import-bird-colors"
                        />
                        <datalist id="import-bird-colors">
                            <option
                                v-for="color in colors"
                                :key="color"
                                :value="color"
                            />
                        </datalist>
                    </div>
                    <div class="grid gap-2">
                        <Label for="import_bird_bloodline">Bloodline</Label>
                        <Input
                            id="import_bird_bloodline"
                            v-model="form.bloodline"
                            autocomplete="off"
                            list="import-bird-bloodlines"
                        />
                        <datalist id="import-bird-bloodlines">
                            <option
                                v-for="bloodline in bloodlines"
                                :key="bloodline"
                                :value="bloodline"
                            />
                        </datalist>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="import_bird_remarks"
                        >Remarks
                        <span class="font-normal text-muted-foreground"
                            >(shown on pedigree)</span
                        ></Label
                    >
                    <Textarea
                        id="import_bird_remarks"
                        v-model="form.remarks"
                        rows="5"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Merits</Label>
                    <MeritListEditor
                        ref="meritEditor"
                        v-model="form.merits"
                        :merit-types="meritTypes"
                        :merit-levels="meritLevels"
                    />
                </div>
            </form>

            <div
                v-if="confirmingRemove"
                class="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm"
            >
                <p>
                    Leave this {{ relation.toLowerCase() }} out<template
                        v-if="behind"
                    >
                        with the {{ behind }}
                        {{ behind === 1 ? 'ancestor' : 'ancestors' }} behind
                        it</template
                    >?
                </p>
                <div class="mt-3 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="confirmingRemove = false"
                        >Keep</Button
                    >
                    <Button variant="destructive" size="sm" @click="remove"
                        >Leave out</Button
                    >
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
                    Leave out
                </Button>
                <span v-else />
                <div class="flex gap-2">
                    <Button variant="outline" @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" form="import-bird-form">Done</Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
