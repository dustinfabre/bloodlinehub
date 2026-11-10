<script lang="ts">
// Prompts already shown in this tab. The browser can restore an earlier page (with its prompt) from history,
// and the layout remounts on every page, so this lives outside the component.
const shown = new Set<string>();
</script>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useToast } from '@/composables/useToast';
import { listLabels } from '@/lib/pedigree';
import { makePrivate } from '@/routes/pedigrees';
import type { AppPageProps, PedigreePrompt } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// After birds go missing or die while their pedigree is public, offer to make those pedigrees private, so whoever
// finds a lost bird can't look it up. The server decides when to ask (GoneBirdPedigrees).
const page = usePage<AppPageProps>();
const { success, error } = useToast();

const prompt = ref<PedigreePrompt | null>(null);
const open = ref(false);
const saving = ref(false);

watch(
    () => page.props.pedigreePrompt,
    (next) => {
        if (!next || shown.has(next.id)) return;
        shown.add(next.id);
        prompt.value = next;
        open.value = true;
    },
    { immediate: true },
);

const pigeons = computed(() => prompt.value?.pigeons ?? []);
const single = computed(() => pigeons.value.length === 1);

// "missing", "deceased" or "missing or deceased"
const statusText = computed(() => {
    const statuses = [...new Set(pigeons.value.map((pigeon) => pigeon.status))];
    return statuses.length === 1 ? statuses[0] : 'missing or deceased';
});

const makeThemPrivate = () => {
    saving.value = true;
    router.patch(
        makePrivate().url,
        { pigeon_ids: pigeons.value.map((pigeon) => pigeon.id) },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                success(
                    single.value
                        ? `The pedigree of ${pigeons.value[0].label} is now private`
                        : `${pigeons.value.length} pedigrees are now private`,
                );
                open.value = false;
            },
            onError: () =>
                error('Could not change the pedigree. Please try again.'),
            onFinish: () => {
                saving.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div
                    class="mb-2 flex size-10 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                >
                    <ShieldAlert class="size-5" />
                </div>
                <DialogTitle>
                    {{
                        single
                            ? 'Make its pedigree private?'
                            : 'Make their pedigrees private?'
                    }}
                </DialogTitle>
                <DialogDescription>
                    <template v-if="single">
                        <span class="font-mono font-medium text-foreground">{{
                            pigeons[0]?.label
                        }}</span>
                        is now {{ statusText }}, but its pedigree is still
                        public: anyone with the link or QR code can open it.
                    </template>
                    <template v-else>
                        {{ pigeons.length }} birds are now {{ statusText }}, but
                        their pedigrees are still public:
                        <span class="font-mono font-medium text-foreground">{{
                            listLabels(
                                pigeons.map((pigeon) => pigeon.label),
                                4,
                            )
                        }}</span
                        >.
                    </template>
                    Making
                    {{ single ? 'it' : 'them' }} private hides
                    {{ single ? 'it' : 'them' }} from everyone but you, so
                    whoever finds a lost bird can't look it up. You can share
                    again any time.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button
                    variant="outline"
                    :disabled="saving"
                    @click="open = false"
                >
                    Keep public
                </Button>
                <Button :disabled="saving" @click="makeThemPrivate">
                    <Spinner v-if="saving" class="mr-2 size-4" />
                    Make private
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
