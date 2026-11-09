<script setup lang="ts">
// Loft check history: every count, newest first, with the birds each one marked missing and where they are now
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { create as createRoute, index as indexRoute, show as showRoute } from '@/routes/loft-checks';
import { index as pigeonsIndex, show as pigeonShow } from '@/routes/pigeons';
import { type BreadcrumbItem } from '@/types';
import { formatCheckDate, scopeQuery, type LoftCheckSummary } from '@/types/loft-check';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, ClipboardCheck, Plus, RotateCcw } from 'lucide-vue-next';

interface MissingBird {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    // Where the bird is now: still missing, or back in the loft
    status: string;
}

interface PaginatedChecks {
    data: (LoftCheckSummary & { missing: MissingBird[] })[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}

defineProps<{
    checks: PaginatedChecks;
    // Birds missing right now, across the whole loft
    missingNow: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Loft Check', href: indexRoute().url }];

const birdLabel = (bird: MissingBird) => bird.ring_number || bird.personal_number || bird.name || `#${bird.id}`;
</script>

<template>
    <Head title="Loft Check" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4 sm:gap-6 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-foreground sm:text-2xl">Loft check</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Count your birds after training and find out which ones are missing.
                    </p>
                </div>
                <Button as-child class="w-full sm:w-auto">
                    <Link :href="createRoute().url">
                        <Plus class="h-4 w-4" />
                        Start a count
                    </Link>
                </Button>
            </div>

            <Link
                v-if="missingNow"
                :href="pigeonsIndex({ query: { status: ['missing'] } }).url"
                class="flex items-center justify-between gap-2 rounded-lg border border-purple-300 bg-purple-50 px-4 py-3 text-sm text-purple-800 hover:bg-purple-100 dark:border-purple-500/40 dark:bg-purple-500/10 dark:text-purple-200 dark:hover:bg-purple-500/20"
            >
                <span><strong>{{ missingNow }}</strong> {{ missingNow === 1 ? 'bird is' : 'birds are' }} missing right now</span>
                <ChevronRight class="h-4 w-4 shrink-0" />
            </Link>

            <Card v-if="!checks.data.length">
                <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                    <ClipboardCheck class="h-12 w-12 text-muted-foreground/60" />
                    <h3 class="mt-4 font-semibold">No counts yet</h3>
                    <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                        After a training toss, start a count, pick the location or ring series, and tick each bird you see.
                    </p>
                    <Button as-child class="mt-4">
                        <Link :href="createRoute().url">
                            <Plus class="h-4 w-4" />
                            Start a count
                        </Link>
                    </Button>
                </CardContent>
            </Card>

            <div v-else class="space-y-3">
                <Card v-for="check in checks.data" :key="check.id" class="gap-0 py-0">
                    <CardContent class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <Link :href="showRoute({ loftCheck: check.id }).url" class="min-w-0 group">
                                <p class="text-sm text-muted-foreground">{{ formatCheckDate(check.created_at) }}</p>
                                <p class="truncate font-medium group-hover:underline">{{ check.label }}</p>
                            </Link>
                            <Button as-child variant="ghost" size="sm" class="shrink-0">
                                <Link :href="createRoute({ query: scopeQuery(check.scope) }).url">
                                    <RotateCcw class="h-4 w-4" />
                                    Count again
                                </Link>
                            </Button>
                        </div>

                        <p class="text-sm">
                            <span class="font-semibold tabular-nums">{{ check.present_count }}</span>
                            <span class="text-muted-foreground"> of {{ check.expected_count }} seen</span>
                            <span v-if="check.missing_count" class="ml-2 font-medium text-purple-700 dark:text-purple-300">
                                {{ check.missing_count }} marked missing
                            </span>
                            <span v-if="check.returned_count" class="ml-2 font-medium text-green-700 dark:text-green-300">
                                {{ check.returned_count }} came back
                            </span>
                            <span v-if="check.unchecked_count" class="ml-2 text-muted-foreground">
                                {{ check.unchecked_count }} not seen, left as is
                            </span>
                        </p>

                        <p v-if="check.note" class="text-sm text-muted-foreground italic">{{ check.note }}</p>

                        <div v-if="check.missing.length" class="flex flex-wrap gap-1.5">
                            <Link
                                v-for="bird in check.missing"
                                :key="bird.id"
                                :href="pigeonShow({ pigeon: bird.id }).url"
                                :class="[
                                    'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 font-mono text-xs',
                                    bird.status === 'missing'
                                        ? 'border-purple-300 text-purple-700 hover:bg-purple-50 dark:border-purple-500/40 dark:text-purple-300 dark:hover:bg-purple-500/10'
                                        : 'border-green-600/50 text-green-700 hover:bg-green-50 dark:text-green-300 dark:hover:bg-green-500/10',
                                ]"
                                :title="bird.status === 'missing' ? 'Still missing' : 'Back in the loft'"
                            >
                                {{ birdLabel(bird) }}
                                <span v-if="bird.status !== 'missing'" class="font-sans">· back</span>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="checks.last_page > 1" class="flex flex-wrap items-center justify-center gap-1">
                <template v-for="(link, index) in checks.links" :key="index">
                    <Button
                        v-if="link.url"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        class="min-w-[2.5rem]"
                        @click="router.visit(link.url)"
                    >
                        <span v-html="link.label" />
                    </Button>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
