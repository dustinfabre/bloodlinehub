<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Crown } from 'lucide-vue-next';

// Small "Premium" tag beside a locked feature
defineProps<{ class?: string; iconOnly?: boolean }>();
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px] leading-none font-semibold text-amber-700 dark:text-amber-300',
                $props.class,
            )
        "
        :title="iconOnly ? 'Premium' : undefined"
    >
        <Crown class="size-3" aria-hidden="true" />
        <span v-if="!iconOnly">Premium</span>
        <span v-else class="sr-only">Premium</span>
    </span>
</template>
