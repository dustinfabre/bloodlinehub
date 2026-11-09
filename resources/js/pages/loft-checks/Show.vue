<script setup lang="ts">
// One saved loft check: the birds it found missing (with their status now, to change if one comes back), the birds
// that came back, the ones not seen but left as they were, and the ones seen
import PigeonSearchResult from '@/components/PigeonSearchResult.vue';
import PigeonStatusMenu from '@/components/PigeonStatusMenu.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { create as createRoute, destroy as destroyRoute, index as indexRoute, show as showRoute } from '@/routes/loft-checks';
import { show as pigeonShow } from '@/routes/pigeons';
import { type BreadcrumbItem } from '@/types';
import { formatCheckDate, scopeQuery, type LoftCheckResultBird, type LoftCheckSummary } from '@/types/loft-check';
import { Head, Link, router } from '@inertiajs/vue3';
import { RotateCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    check: LoftCheckSummary;
    present: LoftCheckResultBird[];
    missing: LoftCheckResultBird[];
    unchecked: LoftCheckResultBird[];
    returned: LoftCheckResultBird[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Loft Check', href: indexRoute().url },
    { title: formatCheckDate(props.check.created_at), href: showRoute({ loftCheck: props.check.id }).url },
]);

const birdLabel = (bird: LoftCheckResultBird) => bird.ring_number || bird.personal_number || bird.name || `#${bird.id}`;

// Birds this check marked missing that are back in the loft since
const backSince = computed(() => props.missing.filter((bird) => bird.status !== 'missing').length);

const sections = computed(() => [
    {
        key: 'missing',
        title: 'Marked missing',
        hint: backSince.value
            ? `${backSince.value} of them ${backSince.value === 1 ? 'is' : 'are'} back since. Change the status of a bird that comes home.`
            : 'Change the status of a bird that comes home.',
        birds: props.missing,
    },
    { key: 'returned', title: 'Came back', hint: 'Missing before this count, found home.', birds: props.returned },
    { key: 'unchecked', title: 'Not seen, left as is', hint: 'Not ticked, but not marked missing either.', birds: props.unchecked },
]);

const confirmDelete = ref(false);
const deleting = ref(false);

const destroy = () => {
    router.delete(destroyRoute({ loftCheck: props.check.id }).url, {
        onStart: () => (deleting.value = true),
        onFinish: () => (deleting.value = false),
    });
};
</script>

<template>
    <Head :title="`Loft check ${formatCheckDate(check.created_at)}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-muted-foreground">{{ formatCheckDate(check.created_at) }}</p>
                    <h1 class="text-xl font-semibold text-foreground sm:text-2xl">{{ check.label }}</h1>
                    <p v-if="check.note" class="mt-1 text-sm text-muted-foreground italic">{{ check.note }}</p>
                </div>
                <div class="flex gap-2">
                    <Button as-child variant="outline" class="flex-1 sm:flex-none">
                        <Link :href="createRoute({ query: scopeQuery(check.scope) }).url">
                            <RotateCcw class="h-4 w-4" />
                            Count again
                        </Link>
                    </Button>
                    <Button variant="outline" aria-label="Delete this check" @click="confirmDelete = true">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border p-3">
                    <p class="text-xs text-muted-foreground">Seen</p>
                    <p class="text-lg font-semibold tabular-nums">{{ check.present_count }} / {{ check.expected_count }}</p>
                </div>
                <div class="rounded-lg border p-3">
                    <p class="text-xs text-muted-foreground">Marked missing</p>
                    <p class="text-lg font-semibold text-purple-700 tabular-nums dark:text-purple-300">{{ check.missing_count }}</p>
                </div>
                <div class="rounded-lg border p-3">
                    <p class="text-xs text-muted-foreground">Came back</p>
                    <p class="text-lg font-semibold text-green-700 tabular-nums dark:text-green-300">{{ check.returned_count }}</p>
                </div>
                <div class="rounded-lg border p-3">
                    <p class="text-xs text-muted-foreground">Not seen, left as is</p>
                    <p class="text-lg font-semibold tabular-nums">{{ check.unchecked_count }}</p>
                </div>
            </div>

            <template v-for="section in sections" :key="section.key">
                <Card v-if="section.birds.length" class="gap-3">
                    <CardHeader>
                        <CardTitle class="text-base">{{ section.title }} · {{ section.birds.length }}</CardTitle>
                        <p class="text-sm text-muted-foreground">{{ section.hint }}</p>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-hidden rounded-lg border">
                            <div
                                v-for="bird in section.birds"
                                :key="bird.id"
                                class="flex items-start justify-between gap-3 border-b px-4 py-3 last:border-b-0"
                            >
                                <Link :href="pigeonShow({ pigeon: bird.id }).url" class="min-w-0 flex-1 hover:underline">
                                    <PigeonSearchResult :pigeon="bird" />
                                </Link>
                                <PigeonStatusMenu :pigeon="bird" :label="birdLabel(bird)" class="shrink-0" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>

            <Card v-if="present.length" class="gap-3">
                <CardHeader>
                    <CardTitle class="text-base">Seen · {{ present.length }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-1.5">
                    <Link
                        v-for="bird in present"
                        :key="bird.id"
                        :href="pigeonShow({ pigeon: bird.id }).url"
                        class="rounded-full border px-2.5 py-0.5 font-mono text-xs hover:bg-accent"
                    >
                        {{ birdLabel(bird) }}
                    </Link>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="confirmDelete">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete this check?</DialogTitle>
                    <DialogDescription>
                        The count is removed from the history. Birds it marked missing stay missing, and their status
                        history still shows when they went missing.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button variant="outline" class="w-full sm:w-auto" @click="confirmDelete = false">Cancel</Button>
                    <Button variant="destructive" class="w-full sm:w-auto" :disabled="deleting" @click="destroy">
                        {{ deleting ? 'Deleting…' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
