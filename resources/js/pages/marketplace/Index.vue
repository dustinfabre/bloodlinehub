<script setup lang="ts">
import AppLogoWordmark from '@/components/AppLogoWordmark.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { Bird, ChevronLeft, ChevronRight, Phone, User } from 'lucide-vue-next';

interface Parent {
    name: string | null;
    ring_number: string | null;
}

interface Listing {
    id: number;
    price: string | null;
    hide_price: boolean;
    description: string | null;
    contact: string | null;
    additional_photos: string[];
    listed_at: string | null;
    pigeon: {
        name: string | null;
        gender: string | null;
        hatch_date: string | null;
        color: string | null;
        ring_number: string | null;
        personal_number: string | null;
        photo_url: string | null;
        sire: Parent | null;
        dam: Parent | null;
    };
    seller: string;
}

defineProps<{
    sales: {
        data: Listing[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const birdLabel = (bird: Listing['pigeon']) =>
    bird.ring_number || bird.name || bird.personal_number || 'Pigeon';

const parentLabel = (parent: Parent | null) =>
    parent ? parent.ring_number || parent.name : null;

const sex = (gender: string | null) =>
    gender === 'male' ? 'Cock' : gender === 'female' ? 'Hen' : null;

const price = (value: string | null) =>
    value === null
        ? null
        : `$${Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
</script>

<template>
    <Head title="Marketplace" />

    <div class="min-h-screen bg-muted/40">
        <header class="bg-navy">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3"
            >
                <Link :href="home().url" class="flex items-center">
                    <AppLogoWordmark class="h-10" />
                </Link>
                <span class="text-sm text-ivory/70">Marketplace</span>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-6 px-4 py-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    Pigeons for sale
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Birds listed by breeders on Bloodline Hub. Contact the
                    breeder directly to buy.
                </p>
            </div>

            <div
                v-if="!sales.data.length"
                class="rounded-lg border border-dashed bg-background p-10 text-center text-sm text-muted-foreground"
            >
                No pigeons are listed for sale right now.
            </div>

            <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="sale in sales.data"
                    :key="sale.id"
                    class="flex flex-col overflow-hidden rounded-xl border bg-background"
                >
                    <div
                        class="flex aspect-[4/3] items-center justify-center bg-muted"
                    >
                        <img
                            v-if="sale.pigeon.photo_url"
                            :src="sale.pigeon.photo_url"
                            :alt="`Photo of ${birdLabel(sale.pigeon)}`"
                            class="size-full object-cover"
                            loading="lazy"
                        />
                        <Bird
                            v-else
                            class="size-12 text-muted-foreground/50"
                            aria-hidden="true"
                        />
                    </div>

                    <div class="flex flex-1 flex-col gap-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate font-semibold">
                                    {{ birdLabel(sale.pigeon) }}
                                </h2>
                                <p
                                    v-if="
                                        sale.pigeon.name &&
                                        sale.pigeon.ring_number
                                    "
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    {{ sale.pigeon.name }}
                                </p>
                            </div>
                            <p
                                class="shrink-0 text-right font-semibold"
                                :class="{
                                    'text-sm font-normal text-muted-foreground':
                                        sale.price === null,
                                }"
                            >
                                {{ price(sale.price) ?? 'Price on request' }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            <Badge
                                v-if="sex(sale.pigeon.gender)"
                                variant="outline"
                                >{{ sex(sale.pigeon.gender) }}</Badge
                            >
                            <Badge v-if="sale.pigeon.color" variant="outline">{{
                                sale.pigeon.color
                            }}</Badge>
                            <Badge
                                v-if="sale.pigeon.hatch_date"
                                variant="outline"
                                >Hatched
                                {{ sale.pigeon.hatch_date.slice(0, 4) }}</Badge
                            >
                        </div>

                        <p
                            v-if="sale.pigeon.sire || sale.pigeon.dam"
                            class="text-sm text-muted-foreground"
                        >
                            <template v-if="sale.pigeon.sire"
                                >Sire
                                <span class="text-foreground">{{
                                    parentLabel(sale.pigeon.sire)
                                }}</span></template
                            ><template
                                v-if="sale.pigeon.sire && sale.pigeon.dam"
                            >
                                ·
                            </template>
                            <template v-if="sale.pigeon.dam"
                                >Dam
                                <span class="text-foreground">{{
                                    parentLabel(sale.pigeon.dam)
                                }}</span></template
                            >
                        </p>

                        <p
                            v-if="sale.description"
                            class="line-clamp-4 text-sm whitespace-pre-line"
                        >
                            {{ sale.description }}
                        </p>

                        <div
                            v-if="sale.additional_photos.length"
                            class="flex gap-2 overflow-x-auto"
                        >
                            <img
                                v-for="photo in sale.additional_photos"
                                :key="photo"
                                :src="photo"
                                alt=""
                                class="size-16 shrink-0 rounded-md object-cover"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                            />
                        </div>

                        <div
                            class="mt-auto space-y-1 border-t pt-3 text-sm text-muted-foreground"
                        >
                            <p class="flex items-center gap-1.5">
                                <User class="size-3.5" aria-hidden="true" />
                                {{ sale.seller }}
                            </p>
                            <p
                                v-if="sale.contact"
                                class="flex items-start gap-1.5 break-words text-foreground"
                            >
                                <Phone
                                    class="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                {{ sale.contact }}
                            </p>
                        </div>
                    </div>
                </li>
            </ul>

            <nav
                v-if="sales.last_page > 1"
                class="flex items-center justify-between gap-2"
                aria-label="Pages"
            >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!sales.prev_page_url"
                    :as-child="!!sales.prev_page_url"
                >
                    <Link
                        v-if="sales.prev_page_url"
                        :href="sales.prev_page_url"
                        preserve-scroll
                        ><ChevronLeft class="size-4" /> Previous</Link
                    >
                    <template v-else
                        ><ChevronLeft class="size-4" /> Previous</template
                    >
                </Button>
                <span class="text-sm text-muted-foreground"
                    >Page {{ sales.current_page }} of {{ sales.last_page }} ·
                    {{ sales.total }} listings</span
                >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!sales.next_page_url"
                    :as-child="!!sales.next_page_url"
                >
                    <Link v-if="sales.next_page_url" :href="sales.next_page_url"
                        >Next <ChevronRight class="size-4"
                    /></Link>
                    <template v-else
                        >Next <ChevronRight class="size-4"
                    /></template>
                </Button>
            </nav>
        </main>
    </div>
</template>
