<script setup lang="ts">
import MeritBadge from '@/components/MeritBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useMeritSuggestions } from '@/composables/useMeritSuggestions';
import { useToast } from '@/composables/useToast';
import { birdLabel } from '@/lib/lineage';
import { show as showPigeon } from '@/routes/pigeons';
import type { SeasonStanding } from '@/types/lineage';
import { Link, router } from '@inertiajs/vue3';
import { Info, LoaderCircle, Plus, Sparkles, X } from 'lucide-vue-next';
import { computed } from 'vue';

interface Entry {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
}

const props = defineProps<{
    entries: Entry[];
    standings: Record<number, SeasonStanding>;
    seasonStatus: string;
    // PATCH target for an entry, e.g. /olr-races/1/seasons/2/entries/{id}
    entryUrl: (pigeonId: number) => string;
}>();

const { add, skip, busy } = useMeritSuggestions();
const { success, error } = useToast();

const ordinal = (n: number) => {
    const rem = n % 100;
    const suffix =
        rem >= 11 && rem <= 13
            ? 'th'
            : (({ 1: 'st', 2: 'nd', 3: 'rd' } as Record<number, string>)[
                  n % 10
              ] ?? 'th');
    return `${n}${suffix}`;
};

const rows = computed(() =>
    props.entries
        .map((entry) => ({
            entry,
            standing: props.standings[entry.id] ?? {
                laps: 0,
                clocked: 0,
                best: null,
                overall_position: null,
                suggestions: [],
            },
        }))
        // Overall standing first, then most laps clocked, then best position
        .sort(
            (a, b) =>
                (a.standing.overall_position ?? Infinity) -
                    (b.standing.overall_position ?? Infinity) ||
                b.standing.clocked - a.standing.clocked ||
                (a.standing.best ?? Infinity) - (b.standing.best ?? Infinity),
        ),
);

const allKeys = computed(() =>
    rows.value.flatMap((row) => row.standing.suggestions.map((s) => s.key)),
);

const saveOverall = (pigeonId: number, raw: string, current: number | null) => {
    const value = raw.trim() === '' ? null : Number(raw);
    if (value === current) return;
    if (value !== null && (!Number.isInteger(value) || value < 1)) {
        error('Overall position must be 1 or more');
        return;
    }
    router.patch(
        props.entryUrl(pigeonId),
        { overall_position: value },
        {
            preserveScroll: true,
            onSuccess: () => success('Overall position saved'),
        },
    );
};
</script>

<template>
    <Card>
        <CardHeader>
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="space-y-1.5">
                    <CardTitle class="flex items-center gap-2">
                        <Sparkles class="size-5 text-gold" />
                        Standings &amp; merits
                    </CardTitle>
                    <CardDescription>
                        Laps clocked, best position and overall standing. Merits
                        are suggested from these results.
                    </CardDescription>
                </div>
                <Button
                    v-if="allKeys.length > 1"
                    size="sm"
                    class="w-full sm:w-auto"
                    :disabled="busy !== null"
                    @click="add(allKeys, 'all')"
                >
                    <LoaderCircle
                        v-if="busy === 'all'"
                        class="size-4 animate-spin"
                    />
                    <Plus v-else class="size-4" />
                    Add all {{ allKeys.length }} suggested merits
                </Button>
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <div
                class="flex gap-2 rounded-lg bg-secondary/60 p-3 text-sm"
                role="note"
            >
                <Info class="mt-0.5 size-4 shrink-0 text-gold" />
                <p>
                    <template v-if="seasonStatus !== 'completed'">
                        Superset and Finisher are suggested once the season is
                        marked <strong>completed</strong>.
                    </template>
                    Set each race's type (hot spot, final) and birds released on
                    the race page for Final, Hot Spot and Prize Winner
                    suggestions. Enter an overall position of 1–10 for Overall
                    Winner.
                </p>
            </div>

            <ul class="divide-y rounded-lg border">
                <li
                    v-for="{ entry, standing } in rows"
                    :key="entry.id"
                    class="flex flex-col gap-3 p-3 md:flex-row md:items-center"
                >
                    <div class="min-w-0 md:w-56 md:shrink-0">
                        <Link
                            :href="showPigeon({ pigeon: entry.id }).url"
                            class="font-medium hover:text-primary hover:underline"
                        >
                            {{ birdLabel(entry) }}
                        </Link>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="standing.laps">
                                Clocked {{ standing.clocked }}/{{
                                    standing.laps
                                }}
                                {{ standing.laps === 1 ? 'lap' : 'laps' }}
                                <template v-if="standing.best">
                                    · best {{ ordinal(standing.best) }}
                                </template>
                            </template>
                            <template v-else>No laps recorded yet</template>
                        </p>
                    </div>

                    <label
                        class="flex items-center gap-2 text-sm text-muted-foreground md:w-36 md:shrink-0"
                    >
                        Overall
                        <Input
                            type="number"
                            min="1"
                            inputmode="numeric"
                            class="h-8 w-20"
                            placeholder="–"
                            :model-value="standing.overall_position ?? ''"
                            :aria-label="`Overall position of ${birdLabel(entry)}`"
                            @change="
                                saveOverall(
                                    entry.id,
                                    ($event.target as HTMLInputElement).value,
                                    standing.overall_position,
                                )
                            "
                        />
                    </label>

                    <div
                        v-if="standing.suggestions.length"
                        class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5"
                    >
                        <span
                            v-for="suggestion in standing.suggestions"
                            :key="suggestion.key"
                            class="inline-flex items-center gap-0.5 rounded-full border border-dashed py-0.5 pr-0.5 pl-1"
                            :title="suggestion.reason"
                        >
                            <MeritBadge :merit="suggestion.merit" />
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-6 rounded-full"
                                :disabled="busy !== null"
                                :aria-label="`Add ${suggestion.merit.label}: ${suggestion.reason}`"
                                @click="add([suggestion.key])"
                            >
                                <LoaderCircle
                                    v-if="busy === suggestion.key"
                                    class="size-3.5 animate-spin"
                                />
                                <Plus v-else class="size-3.5" />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-6 rounded-full text-muted-foreground"
                                :disabled="busy !== null"
                                :aria-label="`Dismiss ${suggestion.merit.label}`"
                                @click="skip([suggestion.key])"
                            >
                                <X class="size-3.5" />
                            </Button>
                        </span>
                    </div>
                    <p v-else class="flex-1 text-sm text-muted-foreground">
                        No merits to suggest
                    </p>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
