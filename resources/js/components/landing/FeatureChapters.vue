<script setup lang="ts">
import CoiSpecimen from '@/components/landing/CoiSpecimen.vue';
import MeritSpecimen from '@/components/landing/MeritSpecimen.vue';
import { vReveal } from '@/lib/reveal';
import { usePage } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { computed } from 'vue';

interface Chapter {
    title: string;
    // Where to find it after logging in, in the app's own menu words
    where: string;
    lead: string;
    items: { term: string; text: string }[];
    specimen?: Component;
}

// Every claim here is checked against the app. Keep it that way when features change.
const page = usePage();
const marketplaceOpen = computed(() => page.props.features.marketplace);

const chapters = computed<Chapter[]>(() => [
    {
        title: 'Loft register',
        where: 'In the menu: Pigeons, Locations',
        lead: 'Every bird is filed under its ring number, with its sire, dam, bloodlines, color, status and the loft it is kept in.',
        items: [
            {
                term: 'Ring numbers',
                text: 'A ring number you already have is caught before you save, and close matches are flagged so a typo does not become a second bird.',
            },
            {
                term: 'Quick Add',
                text: 'Enter a bird with only its ring number, parents and bloodline. Fill in the rest later.',
            },
            {
                term: 'Bloodlines',
                text: 'Give a bird more than one bloodline and mark the primary. The primary one prints on its pedigree.',
            },
            {
                term: 'Photo and papers',
                text: 'One photo per bird, cropped in the app, plus up to two images of its paper pedigree.',
            },
            {
                term: 'Search and filters',
                text: 'Search by name, ring number, bloodline or color. Filter by status, bloodline, location, merit or gender.',
            },
        ],
    },
    {
        title: 'Breeding',
        where: 'In the menu: Breeding, Test Pairing, Lineage',
        lead: 'Check how related two birds are before you pair them, then follow the pair clutch by clutch until the youngsters are ringed.',
        items: [
            {
                term: 'Test Pairing',
                text: 'See the COI of the youngsters, how related the pair is, their common ancestors and how much each one adds.',
            },
            {
                term: 'Breeding plan',
                text: 'Save test pairs for next season with a note. Their COI and Golden Pair status update as your loft changes.',
            },
            {
                term: 'Pairings and clutches',
                text: 'Each pair keeps its clutches with laid and hatched dates. Youngsters added from a clutch get their sire and dam, and the next ring number in your series is offered.',
            },
            {
                term: 'Fostering',
                text: 'Hand a clutch to a foster pair to raise. The youngsters keep their real parents on record.',
            },
            {
                term: 'Lineage and Golden Pairs',
                text: "List a bird's children, grandchildren and siblings, or every bird bred from two birds. A pair with two or more youngsters holding a winning merit is marked Golden Pair.",
            },
        ],
        specimen: CoiSpecimen,
    },
    {
        title: 'Pedigrees',
        where: "On every bird's page",
        lead: 'A five-generation pedigree is drawn from the parents you record. Ancestors you enter behind a loft bird are saved on that bird, so all of its youngsters inherit them.',
        items: [
            {
                term: 'Edit pedigree',
                text: 'Fill empty boxes with bought-in ancestors: ring number, name, color, remarks and merits.',
            },
            {
                term: 'Merits on the chart',
                text: "Codes such as OA, HS and SB print on each card in their own colors, and a bird's best merit colors the edge of its card. The key explains every code used.",
            },
            {
                term: 'PDF, image and print',
                text: 'Download an A4 PDF or an image, or print it from the print view.',
            },
            {
                term: 'Serial and QR code',
                text: 'Every pedigree carries its own serial number and a QR code that links to it online.',
            },
            {
                term: 'Private, password or public',
                text: 'Pedigrees start private. Protect one with a password or make it public. Private notes are never shown.',
            },
        ],
    },
    {
        title: 'Racing and merits',
        where: 'In the menu: Clubs, OLR Races',
        lead: "Keep club seasons and one-loft races down to each bird's result. The results then suggest the merits a bird has earned.",
        items: [
            {
                term: 'Clubs',
                text: 'Seasons with your racing team, events and races. Record position, arrival time and speed, or mark a bird DNA.',
            },
            {
                term: 'OLR',
                text: "One-loft races by season. Enter birds in bulk, and they move to that OLR's location.",
            },
            {
                term: 'Standings',
                text: 'Each season shows laps clocked, best position and overall standing for every bird.',
            },
            {
                term: 'Suggested merits',
                text: 'Top 10 in a hot spot or final, 1st in a race, clocked in every lap: the app suggests the merit, and you add or dismiss it. Training tosses never count.',
            },
            {
                term: 'One set of merits',
                text: 'Twelve fixed merits, from OLY and ACE to FIN and BASE, mean the same and have the same color in every loft. Gold for titles, red for race wins, teal for steady flying, violet for breeding. Lineage and Test Pairing use them to rank proven birds.',
            },
        ],
        specimen: MeritSpecimen,
    },
    {
        title: 'Selling',
        where: 'In the menu: Sales',
        lead: marketplaceOpen.value
            ? 'List a bird from your loft for sale. Active listings go on the public marketplace, which anyone can browse without logging in.'
            : 'List a bird from your loft for sale, with a price, a description and how buyers can reach you. A public marketplace for your listings is coming soon.',
        items: [
            {
                term: 'Listings',
                text: 'Pick a bird, set a price or show "Price on request", add a description and say how buyers can reach you.',
            },
            {
                term: marketplaceOpen.value
                    ? 'Marketplace'
                    : 'Marketplace, coming soon',
                text: marketplaceOpen.value
                    ? 'Buyers see the photo, sire, dam, color and hatch year, then contact you directly.'
                    : 'Buyers will see the photo, sire, dam, color and hatch year, then contact you directly.',
            },
            {
                term: 'Your details',
                text: 'Only your name and the contact you entered are shown, never your account email.',
            },
            {
                term: 'Pedigree for the buyer',
                text: 'Send the pedigree link with a password that lasts 24 hours, 7 days, 30 days or until a date you pick.',
            },
        ],
    },
]);

const chapterNumber = (index: number) => String(index + 1).padStart(2, '0');
</script>

<template>
    <section
        aria-labelledby="chapters-heading"
        class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28"
    >
        <div class="max-w-2xl">
            <h2
                id="chapters-heading"
                class="font-display text-3xl leading-tight font-semibold tracking-[-0.01em] text-balance text-navy sm:text-4xl"
            >
                One record per bird, from the egg to the sale.
            </h2>
            <p
                class="mt-5 max-w-[62ch] text-base leading-relaxed text-pretty text-muted-foreground"
            >
                The chapters follow a bird through your loft, and each one feeds
                the next: a clutch fills in the youngster's parents, a race
                result suggests its merit, and the merit prints on its pedigree.
            </p>
        </div>

        <ol class="mt-14 border-b border-border sm:mt-20">
            <li
                v-for="(chapter, index) in chapters"
                :key="chapter.title"
                class="grid gap-y-8 border-t border-border py-12 sm:py-16 lg:grid-cols-12 lg:grid-rows-[auto_1fr] lg:gap-x-10 lg:gap-y-10"
            >
                <div v-reveal class="lg:col-span-4">
                    <div class="flex items-baseline gap-4 lg:block">
                        <span
                            class="font-display text-4xl leading-none font-semibold text-gold tabular-nums lg:text-6xl"
                            aria-hidden="true"
                            >{{ chapterNumber(index) }}</span
                        >
                        <h3
                            class="font-display text-2xl leading-snug font-semibold text-navy lg:mt-5"
                        >
                            {{ chapter.title }}
                        </h3>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ chapter.where }}
                    </p>
                </div>

                <div
                    v-reveal="80"
                    class="lg:col-span-8 lg:col-start-5 lg:row-span-2 lg:row-start-1"
                >
                    <p
                        class="max-w-[60ch] text-[17px] leading-relaxed text-pretty text-navy sm:text-lg"
                    >
                        {{ chapter.lead }}
                    </p>
                    <ul class="mt-8 grid gap-x-10 gap-y-6 md:grid-cols-2">
                        <li
                            v-for="item in chapter.items"
                            :key="item.term"
                            class="relative pl-6 text-[15px] leading-relaxed sm:text-base"
                        >
                            <span
                                class="absolute top-[0.8em] left-0 h-0 w-3.5 border-t border-gold"
                                aria-hidden="true"
                            />
                            <span class="font-medium text-navy"
                                >{{ item.term }}.</span
                            >
                            {{ ' ' }}
                            <span class="text-muted-foreground">{{
                                item.text
                            }}</span>
                        </li>
                    </ul>
                </div>

                <!-- On large screens the specimen sits under the chapter title, beside the list -->
                <div
                    v-if="chapter.specimen"
                    class="w-full max-w-md lg:col-span-4 lg:col-start-1 lg:row-start-2 lg:max-w-none lg:self-start"
                >
                    <component :is="chapter.specimen" />
                </div>
            </li>
        </ol>
    </section>
</template>
