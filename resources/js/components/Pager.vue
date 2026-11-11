<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types/billing';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

// Previous / next links under a paginated list
defineProps<{
    page: Paginated<unknown>;
    // What is counted, e.g. "payments"
    noun: string;
}>();
</script>

<template>
    <nav
        v-if="page.last_page > 1"
        class="flex items-center justify-between gap-2"
        aria-label="Pages"
    >
        <Button
            variant="outline"
            size="sm"
            :disabled="!page.prev_page_url"
            :as-child="!!page.prev_page_url"
        >
            <Link
                v-if="page.prev_page_url"
                :href="page.prev_page_url"
                preserve-scroll
                ><ChevronLeft class="size-4" /> Previous</Link
            >
            <template v-else><ChevronLeft class="size-4" /> Previous</template>
        </Button>
        <span class="text-sm text-muted-foreground"
            >Page {{ page.current_page }} of {{ page.last_page }} ·
            {{ page.total }} {{ noun }}</span
        >
        <Button
            variant="outline"
            size="sm"
            :disabled="!page.next_page_url"
            :as-child="!!page.next_page_url"
        >
            <Link
                v-if="page.next_page_url"
                :href="page.next_page_url"
                preserve-scroll
                >Next <ChevronRight class="size-4"
            /></Link>
            <template v-else>Next <ChevronRight class="size-4" /></template>
        </Button>
    </nav>
</template>
