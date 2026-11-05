<script setup lang="ts">
import MeritBadge from '@/components/MeritBadge.vue';
import { Button } from '@/components/ui/button';
import { useMeritSuggestions } from '@/composables/useMeritSuggestions';
import type { MeritSuggestion } from '@/types/lineage';
import { LoaderCircle, Plus, X } from 'lucide-vue-next';

defineProps<{
    suggestions: MeritSuggestion[];
}>();

const { add, skip, busy } = useMeritSuggestions();
</script>

<template>
    <ul class="divide-y rounded-lg border border-dashed">
        <li
            v-for="suggestion in suggestions"
            :key="suggestion.key"
            class="flex items-center justify-between gap-3 p-3"
        >
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <MeritBadge :merit="suggestion.merit" full />
                    <span class="text-sm text-muted-foreground">
                        {{
                            [
                                suggestion.merit.level_label,
                                suggestion.merit.year,
                            ]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </span>
                </div>
                <p class="text-sm">{{ suggestion.reason }}</p>
                <p
                    v-if="suggestion.merit.detail"
                    class="text-xs text-muted-foreground"
                >
                    {{ suggestion.merit.detail }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="busy !== null"
                    @click="add([suggestion.key])"
                >
                    <LoaderCircle
                        v-if="busy === suggestion.key"
                        class="size-4 animate-spin"
                    />
                    <Plus v-else class="size-4" />
                    Add
                </Button>
                <Button
                    size="icon"
                    variant="ghost"
                    class="text-muted-foreground"
                    :disabled="busy !== null"
                    :aria-label="`Dismiss ${suggestion.merit.label}`"
                    title="Dismiss: don't suggest this again"
                    @click="skip([suggestion.key])"
                >
                    <X class="size-4" />
                </Button>
            </div>
        </li>
    </ul>
</template>
