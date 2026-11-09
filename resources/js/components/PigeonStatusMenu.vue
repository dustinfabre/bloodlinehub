<script setup lang="ts">
// The status badge of a pigeon as a menu: change the status right there, without opening the edit form
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { PIGEON_STATUSES, statusStyle } from '@/lib/pigeon-status';
import { status as statusRoute } from '@/routes/pigeons';
import { router } from '@inertiajs/vue3';
import { ChevronDown, LoaderCircle } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    pigeon: { id: number; status: string };
    // Ring number or name, for the confirmation message
    label: string;
    class?: HTMLAttributes['class'];
}>();

const inLoft = PIGEON_STATUSES.filter((status) => !status.gone);
const gone = PIGEON_STATUSES.filter((status) => status.gone);

const saving = ref(false);
const current = computed(() => statusStyle(props.pigeon.status));

const labelOf = (value: string) => statusStyle(value).label;

const save = (status: string, undoTo: string | null) => {
    saving.value = true;

    router.patch(
        statusRoute({ pigeon: props.pigeon.id }).url,
        { status },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (undoTo === null) {
                    toast.success(`${props.label} is back to ${labelOf(status)}`);
                    return;
                }

                toast.success(`${props.label} marked ${labelOf(status)}`, {
                    action: { label: 'Undo', onClick: () => save(undoTo, null) },
                });
            },
            onError: (errors) => toast.error(errors.status ?? `Couldn't change the status of ${props.label}`),
            onFinish: () => (saving.value = false),
        },
    );
};

const choose = (value: unknown) => {
    if (typeof value !== 'string' || value === props.pigeon.status) return;

    save(value, props.pigeon.status);
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            :disabled="saving"
            :aria-label="`Status of ${label}: ${current.label}. Change status`"
            :class="
                cn(
                    'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium transition-shadow hover:ring-2 hover:ring-ring/30 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-70',
                    current.className,
                    props.class,
                )
            "
        >
            {{ current.label }}
            <LoaderCircle v-if="saving" class="h-3 w-3 animate-spin" />
            <ChevronDown v-else class="h-3 w-3 opacity-70" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-52">
            <DropdownMenuRadioGroup :model-value="pigeon.status" @update:model-value="choose">
                <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">In the loft</DropdownMenuLabel>
                <DropdownMenuRadioItem v-for="status in inLoft" :key="status.value" :value="status.value">
                    <span :class="['h-2 w-2 rounded-full', status.dotClass]" />
                    {{ status.label }}
                </DropdownMenuRadioItem>
                <DropdownMenuSeparator />
                <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">No longer in the loft</DropdownMenuLabel>
                <DropdownMenuRadioItem v-for="status in gone" :key="status.value" :value="status.value">
                    <span :class="['h-2 w-2 rounded-full', status.dotClass]" />
                    {{ status.label }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
