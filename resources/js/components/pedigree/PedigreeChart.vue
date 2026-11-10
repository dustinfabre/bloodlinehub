<script setup lang="ts">
import AppLogoWordmark from '@/components/AppLogoWordmark.vue';
import PedigreeBirdDialog from '@/components/pedigree/PedigreeBirdDialog.vue';
import PedigreeCard from '@/components/pedigree/PedigreeCard.vue';
import { meritChipStyle } from '@/lib/merits';
import {
    collectMerits,
    flattenPedigree,
    formatPedigreeDate,
    GENERATION_LABELS,
    MAX_DEPTH,
    normalizeRing,
    slotGridArea,
    VISIBILITY_LABELS,
} from '@/lib/pedigree';
import type {
    PedigreeMeta,
    PedigreeNode,
    PedigreeSlot,
} from '@/types/pedigree';
import { Mail, MapPin, Phone } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    tree: PedigreeNode;
    meta: PedigreeMeta;
    // The details of loft birds link to their pigeon pages (owner view only)
    linkable?: boolean;
    // A sample for a pairing not yet made (Test Pairing): no serial, QR code or signature lines
    sample?: boolean;
    // Outline ancestors that appear more than once, i.e. what the bird is line-bred on
    markRepeated?: boolean;
}>();

// The fixed-size A4 sheet; exported as-is for image and PDF downloads
const paper = ref<HTMLElement | null>(null);
defineExpose({ paper });

const ancestorSlots = computed(() => flattenPedigree(props.tree).slice(1));
const meritKey = computed(() => collectMerits(props.tree));
const shareLabel = computed(() =>
    props.meta.share_url.replace(/^https?:\/\//, ''),
);
const hasContact = computed(() =>
    Object.values(props.meta.contact).some(Boolean),
);
// The user bred the bird when both parents are their birds; otherwise they are its owner
const roleLabel = computed(() =>
    props.meta.role === 'owner' ? 'Owner' : 'Breeder',
);
const roleWord = computed(() => roleLabel.value.toLowerCase());

// Chart positions of ancestors found more than once (same ring number, or the same loft bird)
const repeatedKeys = computed(() => {
    const positions = new Map<string, string[]>();
    if (!props.markRepeated) return new Set<string>();
    for (const slot of ancestorSlots.value) {
        const ring = normalizeRing(slot.node?.ring_number);
        const identity = ring
            ? `ring:${ring}`
            : slot.node?.id
              ? `id:${slot.node.id}`
              : null;
        if (!identity) continue;
        positions.set(identity, [...(positions.get(identity) ?? []), slot.key]);
    }
    return new Set(
        [...positions.values()].filter((keys) => keys.length > 1).flat(),
    );
});

// Clicking a card shows everything recorded about that bird
const detailsOpen = ref(false);
const selected = ref<Pick<PedigreeSlot, 'key' | 'node'>>({
    key: '',
    node: null,
});

const showDetails = (key: string, node: PedigreeNode | null) => {
    selected.value = { key, node };
    detailsOpen.value = true;
};
</script>

<template>
    <div ref="paper" class="pedigree-paper">
        <header class="pedigree-band">
            <div class="pedigree-brand">
                <AppLogoWordmark class="pedigree-logo" />
                <span class="pedigree-divider" aria-hidden="true" />
                <div>
                    <div class="pedigree-eyebrow">
                        {{
                            sample ? 'Sample · Test pairing' : 'Five-generation'
                        }}
                    </div>
                    <div class="pedigree-title">Pedigree</div>
                </div>
            </div>
            <dl class="pedigree-facts">
                <div>
                    <dt>{{ roleLabel }}</dt>
                    <dd>{{ meta.breeder }}</dd>
                </div>
                <div>
                    <dt>{{ sample ? 'Prepared' : 'Issued' }}</dt>
                    <dd>{{ formatPedigreeDate(meta.generated_at) }}</dd>
                </div>
                <div v-if="sample">
                    <dt>Status</dt>
                    <dd>Sample, not yet bred</dd>
                </div>
                <div v-else>
                    <dt>Pedigree No.</dt>
                    <dd class="is-mono">{{ meta.serial }}</dd>
                </div>
            </dl>
        </header>

        <div class="pedigree-body">
            <div class="pedigree-gens">
                <span v-for="(label, index) in GENERATION_LABELS" :key="label">
                    <b>{{ String(index + 1).padStart(2, '0') }}</b>
                    {{ label }}
                </span>
            </div>

            <div class="pedigree-tree">
                <div class="pedigree-slot is-subject">
                    <img
                        v-if="meta.photo_url"
                        :src="meta.photo_url"
                        alt=""
                        class="pedigree-photo"
                    />
                    <PedigreeCard
                        :node="tree"
                        :depth="0"
                        :side="null"
                        @open="showDetails('', tree)"
                    />
                    <span class="pedigree-stub" />
                    <span class="pedigree-fork" />
                    <div v-if="sample" class="pedigree-qr-block is-sample">
                        <div>
                            <div class="pedigree-qr-label">Test pairing</div>
                            <div class="pedigree-qr-serial">
                                Sample pedigree
                            </div>
                            <div class="pedigree-qr-hint">
                                What a youngster of this pair would carry
                            </div>
                        </div>
                    </div>
                    <div v-else class="pedigree-qr-block">
                        <div class="pedigree-qr" v-html="meta.qr_svg" />
                        <div>
                            <div class="pedigree-qr-label">Scan to verify</div>
                            <div class="pedigree-qr-serial is-mono">
                                {{ meta.serial }}
                            </div>
                            <div
                                class="pedigree-qr-hint"
                                :data-visibility="meta.visibility"
                            >
                                <i />{{ VISIBILITY_LABELS[meta.visibility] }}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-for="position in ancestorSlots"
                    :key="position.key"
                    class="pedigree-slot"
                    :style="slotGridArea(position)"
                >
                    <PedigreeCard
                        :node="position.node"
                        :depth="position.depth"
                        :side="position.side"
                        :repeated="repeatedKeys.has(position.key)"
                        @open="showDetails(position.key, position.node)"
                    />
                    <template
                        v-if="position.node && position.depth < MAX_DEPTH"
                    >
                        <span class="pedigree-stub" />
                        <span class="pedigree-fork" />
                    </template>
                </div>
            </div>

            <footer class="pedigree-footer">
                <div class="pedigree-footer-main">
                    <div class="pedigree-legend">
                        <template v-if="meritKey.length">
                            <span
                                v-for="merit in meritKey"
                                :key="merit.type"
                                class="pedigree-legend-item is-merit"
                            >
                                <b
                                    class="pc-merit"
                                    :style="meritChipStyle(merit)"
                                    >{{ merit.code }}</b
                                >
                                {{ merit.label }}
                            </span>
                        </template>
                        <span v-else>♂ Sire line above · ♀ Dam line below</span>
                        <span
                            v-if="repeatedKeys.size"
                            class="pedigree-legend-item is-repeat"
                        >
                            <b class="pedigree-repeat-key" />
                            Appears more than once
                        </span>
                    </div>
                    <div v-if="sample" class="pedigree-footnote">
                        Sample from Test Pairing, not a registered bird ·
                        Remarks supplied by the {{ roleWord }}
                    </div>
                    <div v-else class="pedigree-footnote">
                        Remarks supplied by the {{ roleWord }} · Verify at
                        {{ shareLabel }}
                    </div>
                </div>

                <div v-if="hasContact" class="pedigree-contact">
                    <div class="pedigree-footer-label">
                        Contact the {{ roleWord }}
                    </div>
                    <div
                        v-if="meta.contact.phone || meta.contact.email"
                        class="pedigree-contact-line"
                    >
                        <span v-if="meta.contact.phone"
                            ><Phone />{{ meta.contact.phone }}</span
                        >
                        <span v-if="meta.contact.email"
                            ><Mail />{{ meta.contact.email }}</span
                        >
                    </div>
                    <div
                        v-if="meta.contact.address"
                        class="pedigree-contact-line is-address"
                    >
                        <span><MapPin />{{ meta.contact.address }}</span>
                    </div>
                </div>

                <!-- Who bred the bird: the user's loft, or whoever they entered for a bird they own -->
                <div v-if="!sample && meta.bred_by" class="pedigree-bred-by">
                    <div class="pedigree-footer-label">Bred by</div>
                    <div class="pedigree-bred-by-name">{{ meta.bred_by }}</div>
                </div>
            </footer>
        </div>

        <PedigreeBirdDialog
            v-model:open="detailsOpen"
            :position="selected.key"
            :node="selected.node"
            :linkable="linkable"
        />
    </div>
</template>

<style>
/*
 * The pedigree sheet uses its own fixed brand palette (not the app theme) so it always
 * renders the same: on screen, in dark mode, in print and in exports.
 */
.pedigree-paper {
    --c0: 218px;
    --c1: 214px;
    --c2: 196px;
    --c3: 180px;
    --gap: 22px;
    --navy: #14243a;
    --gold: #d4af62;
    --gold-deep: #a07d34;
    --ivory: #f8f6f0;
    --ink: #14243a;
    --muted: #64748b;
    --faint: #8390a2;
    --surface: #f4f2ec;
    --rule: #e6e2d8;
    --line: #c2c7d0;
    --sire: #3a6ea5;
    --dam: #b4506b;

    display: flex;
    flex-direction: column;
    width: 1122px;
    height: 793px;
    overflow: hidden;
    background: #ffffff;
    color: var(--ink);
    font-family:
        'Instrument Sans',
        ui-sans-serif,
        system-ui,
        -apple-system,
        'Segoe UI',
        sans-serif;
    font-size: 10px;
    line-height: 1.25;
    text-align: left;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.pedigree-paper *,
.pedigree-paper *::before,
.pedigree-paper *::after {
    box-sizing: border-box;
}

.pedigree-paper .is-mono {
    font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
    letter-spacing: 0.04em;
}

/* Header band: the wordmark's "Hub" is ivory, so it sits on navy */
.pedigree-band {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: space-between;
    height: 74px;
    padding: 0 28px 0 20px;
    border-bottom: 3px solid var(--gold);
    background: var(--navy);
    color: var(--ivory);
}

.pedigree-brand {
    display: flex;
    align-items: center;
    gap: 16px;
}

.pedigree-logo {
    height: 46px;
}

.pedigree-divider {
    width: 1px;
    height: 34px;
    background: rgba(248, 246, 240, 0.18);
}

.pedigree-eyebrow {
    font-size: 7.5px;
    font-weight: 600;
    letter-spacing: 0.26em;
    text-transform: uppercase;
    color: var(--gold);
}

.pedigree-title {
    margin-top: 3px;
    font-size: 23px;
    font-weight: 600;
    line-height: 1;
    letter-spacing: -0.01em;
}

.pedigree-facts {
    display: flex;
    gap: 28px;
    margin: 0;
}

.pedigree-facts dt {
    font-size: 7px;
    font-weight: 600;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--gold);
}

.pedigree-facts dd {
    margin: 4px 0 0;
    font-size: 12px;
    font-weight: 600;
}

.pedigree-body {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-height: 0;
    padding: 12px 28px 12px;
}

/* Generation labels share the tree's columns */
.pedigree-gens,
.pedigree-tree {
    display: grid;
    grid-template-columns: var(--c0) var(--c1) var(--c2) var(--c3) minmax(
            0,
            1fr
        );
    column-gap: var(--gap);
}

.pedigree-gens {
    margin-bottom: 8px;
    font-size: 7.5px;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--muted);
}

.pedigree-gens b {
    margin-right: 4px;
    color: var(--gold-deep);
    font-weight: 700;
}

/* Tree: 16 rows, one per bird in the last generation; each bird spans the rows of its own ancestors */
.pedigree-tree {
    flex: 1;
    min-height: 0;
    grid-template-rows: repeat(16, minmax(0, 1fr));
}

.pedigree-slot {
    position: relative;
    display: flex;
    align-items: center;
    min-width: 0;
    min-height: 0;
    padding: 2px 0;
}

.pedigree-slot.is-subject {
    grid-row: 1 / -1;
    grid-column: 1;
}

.pedigree-slot.is-subject > .pedigree-card {
    max-height: 280px;
}

/* Connectors: a stub out of each bird, then a rounded fork to its sire (above) and dam (below) */
.pedigree-stub {
    position: absolute;
    top: 50%;
    left: 100%;
    width: calc(var(--gap) / 2);
    border-top: 1.25px solid var(--line);
}

.pedigree-fork {
    position: absolute;
    top: 25%;
    bottom: 25%;
    left: calc(100% + var(--gap) / 2);
    width: calc(var(--gap) / 2);
    border: 1.25px solid var(--line);
    border-right: 0;
    border-radius: 8px 0 0 8px;
}

.pedigree-photo {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 14px;
}

.pedigree-qr-block {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    border-radius: 14px;
    background: var(--surface);
}

.pedigree-qr {
    flex-shrink: 0;
    width: 68px;
    height: 68px;
    padding: 4px;
    border-radius: 8px;
    background: #ffffff;
}

.pedigree-qr svg {
    display: block;
    width: 100%;
    height: 100%;
}

.pedigree-qr-label {
    font-size: 7px;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--muted);
}

.pedigree-qr-serial {
    margin-top: 4px;
    font-size: 11px;
    font-weight: 700;
}

.pedigree-qr-hint {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 4px;
    font-size: 8px;
    color: var(--muted);
}

.pedigree-qr-hint i {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: var(--faint);
}

.pedigree-qr-hint[data-visibility='public'] i {
    background: #2f9e6b;
}

.pedigree-qr-hint[data-visibility='password'] i {
    background: var(--gold);
}

/* Bird cards: soft filled tiles, no outlines */
.pedigree-card {
    position: relative;
    width: 100%;
    max-height: 100%;
    overflow: hidden;
    padding: 7px 10px;
    border-radius: 10px;
    background: var(--surface);
}

/* A bird with merits: a stripe in the color of its most significant merit */
.pedigree-card.has-merit {
    box-shadow: inset 3px 0 0 var(--pc-accent);
}

/* An ancestor found more than once in the pedigree (line breeding): a gold ring on a warm tile */
.pedigree-card.is-repeated,
.pedigree-repeat-key {
    background: #fbf3df;
    box-shadow: inset 0 0 0 1.5px var(--gold-deep);
}

.pedigree-card.has-merit.is-repeated {
    box-shadow:
        inset 3px 0 0 var(--pc-accent),
        inset 0 0 0 1.5px var(--gold-deep);
}

.pedigree-repeat-key {
    display: inline-block;
    width: 14px;
    height: 9px;
    border-radius: 3px;
}

/* Clicking a card opens the bird's details (screen only; exports never show the hover ring) */
.pedigree-card.is-clickable {
    cursor: pointer;
    outline: 0 solid transparent;
    transition: outline-color 0.15s ease;
}

.pedigree-card.is-clickable:hover,
.pedigree-card.is-clickable:focus-visible {
    outline: 1.5px solid var(--gold);
    outline-offset: -1.5px;
}

.pedigree-card.is-empty {
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px dashed #d8d4c9;
    background: transparent;
    color: var(--faint);
}

.pc-top {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
}

.pc-sex {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 15px;
    height: 15px;
    border-radius: 999px;
    font-size: 9px;
    font-weight: 700;
    line-height: 1;
}

.pc-sex:empty {
    display: none;
}

.pc-sex.is-male {
    background: #e3ebf5;
    color: var(--sire);
}

.pc-sex.is-female {
    background: #f6e4ea;
    color: var(--dam);
}

.pc-ring {
    min-width: 0;
    overflow: hidden;
    color: var(--ink);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.005em;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Line 1: sex, ring number, color. The ring number keeps its room; the color takes what is left */
.pc-ring {
    flex: 0 1 auto;
}

.pc-color {
    flex: 1 1 0;
    min-width: 0;
    overflow: hidden;
    color: var(--muted);
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Line 2: name, then merits */
.pc-row {
    display: flex;
    align-items: center;
    gap: 3px 6px;
    min-width: 0;
    margin-top: 3px;
}

.pc-name,
.pc-meta {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pc-name {
    flex: 0 1 auto;
    min-width: 0;
    font-weight: 600;
}

/* Line 3: bloodline */
.pc-meta {
    margin-top: 1px;
    color: var(--muted);
}

.pc-unknown {
    font-size: 0.95em;
    letter-spacing: 0.02em;
}

.pc-remarks {
    display: -webkit-box;
    margin: 5px 0 0;
    padding-top: 5px;
    overflow: hidden;
    border-top: 1px solid rgba(20, 36, 58, 0.08);
    color: #334155;
    white-space: pre-line;
    overflow-wrap: anywhere;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: var(--pc-lines, 2);
}

/* Merits: each type has its own fixed color (set inline from MeritType::color()), explained in the footer */
.pc-merits {
    display: flex;
    flex: 0 100 auto;
    gap: 2px 3px;
    min-width: 0;
    overflow: hidden;
}

.pc-merit {
    flex-shrink: 0;
    padding: 1px 5px;
    border-radius: 999px;
    font-size: 7.5px;
    font-weight: 700;
    line-height: 1.35;
    letter-spacing: 0.03em;
    white-space: nowrap;
}

.pc-merit.is-more {
    background: transparent;
    color: var(--muted);
}

/* Columns with room to spare move a long color onto its own line instead of cutting it short */
.pedigree-card[data-depth='0'] .pc-top,
.pedigree-card[data-depth='1'] .pc-top,
.pedigree-card[data-depth='2'] .pc-top {
    flex-wrap: wrap;
    row-gap: 1px;
}

.pedigree-card[data-depth='0'] .pc-color,
.pedigree-card[data-depth='1'] .pc-color,
.pedigree-card[data-depth='2'] .pc-color {
    flex: 0 1 auto;
}

/* The two front columns have room to wrap the merits under a long name */
.pedigree-card[data-depth='0'] .pc-row,
.pedigree-card[data-depth='1'] .pc-row,
.pedigree-card[data-depth='0'] .pc-merits,
.pedigree-card[data-depth='1'] .pc-merits {
    flex-wrap: wrap;
}

/* Last column: small code chips */
.pedigree-card[data-depth='4'] .pc-merit {
    padding: 0 4px;
    font-size: 7px;
}

.pedigree-legend-item.is-merit {
    padding-left: 3px;
}

.pedigree-legend-item.is-merit .pc-merit {
    font-size: 7px;
}

.pedigree-card[data-depth='0'] .pc-row {
    gap: 6px 8px;
    margin-top: 6px;
}

.pedigree-card[data-depth='0'] .pc-merits {
    gap: 4px;
}

.pedigree-card[data-depth='0'] .pc-color {
    color: rgba(248, 246, 240, 0.7);
    font-size: 11px;
}

/* Dark merit colors would melt into the navy card, so its chips get a faint light ring */
.pedigree-card[data-depth='0'] .pc-merit {
    padding: 2px 8px;
    box-shadow: 0 0 0 1px rgba(248, 246, 240, 0.28);
    font-size: 9px;
}

.pedigree-card[data-depth='0'] .pc-merit.is-more {
    background: transparent;
    box-shadow: none;
    color: rgba(248, 246, 240, 0.7);
}

.pedigree-card[data-depth='1'] .pc-merit {
    font-size: 8.5px;
}

/* The bird itself: a navy hero card */
.pedigree-card[data-depth='0'] {
    --pc-lines: 9;
    padding: 15px 16px;
    border-radius: 14px;
    background: var(--navy);
    color: var(--ivory);
    font-size: 10.5px;
}

.pedigree-card[data-depth='0'].has-merit {
    box-shadow: inset 4px 0 0 var(--pc-accent);
}

.pedigree-card[data-depth='0'] .pc-sex {
    width: 19px;
    height: 19px;
    font-size: 11px;
    background: rgba(248, 246, 240, 0.1);
}

.pedigree-card[data-depth='0'] .pc-sex.is-male {
    color: #9cc0ea;
}

.pedigree-card[data-depth='0'] .pc-sex.is-female {
    color: #f0a9bf;
}

.pedigree-card[data-depth='0'] .pc-ring {
    color: var(--gold);
    font-size: 18px;
}

.pedigree-card[data-depth='0'] .pc-name {
    color: #ffffff;
    font-size: 14px;
    white-space: normal;
}

.pedigree-card[data-depth='0'] .pc-meta {
    margin-top: 3px;
    color: rgba(248, 246, 240, 0.7);
}

.pedigree-card[data-depth='0'] .pc-remarks {
    margin-top: 10px;
    padding-top: 9px;
    border-top-color: rgba(248, 246, 240, 0.14);
    color: rgba(248, 246, 240, 0.88);
}

/* Each generation gets less room, so less detail */
.pedigree-card[data-depth='1'] {
    --pc-lines: 12;
    padding: 9px 11px;
    font-size: 9.5px;
}

.pedigree-card[data-depth='1'] .pc-ring {
    font-size: 13px;
}

.pedigree-card[data-depth='1'] .pc-name {
    font-size: 11.5px;
}

.pedigree-card[data-depth='2'] {
    --pc-lines: 6;
    font-size: 9px;
}

.pedigree-card[data-depth='2'] .pc-ring {
    font-size: 11.5px;
}

.pedigree-card[data-depth='2'] .pc-name {
    font-size: 10px;
}

.pedigree-card[data-depth='3'] {
    --pc-lines: 2;
    padding: 5px 8px;
    border-radius: 8px;
    font-size: 8.5px;
}

.pedigree-card[data-depth='3'] .pc-ring {
    font-size: 10.5px;
}

.pedigree-card[data-depth='3'] .pc-remarks {
    margin-top: 3px;
    padding-top: 3px;
}

.pedigree-card[data-depth='4'] {
    padding: 4px 8px;
    border-radius: 7px;
    font-size: 8px;
}

.pedigree-card[data-depth='4'] .pc-ring {
    font-size: 9.5px;
}

.pedigree-card[data-depth='3'] .pc-sex,
.pedigree-card[data-depth='4'] .pc-sex {
    width: 13px;
    height: 13px;
    font-size: 8px;
}

/* Footer: key and verify note, contact details, then who bred the bird */
.pedigree-footer {
    display: flex;
    flex-shrink: 0;
    align-items: stretch;
    gap: 20px;
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid var(--rule);
    color: var(--muted);
    font-size: 8.5px;
}

.pedigree-footer-main {
    display: flex;
    flex: 1;
    flex-direction: column;
    justify-content: space-between;
    gap: 6px;
    min-width: 0;
}

.pedigree-footnote {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pedigree-footer-label {
    font-size: 6.5px;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--muted);
}

.pedigree-contact {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    gap: 3px;
    width: 250px;
    padding-left: 14px;
    border-left: 1px solid var(--rule);
    color: var(--ink);
}

.pedigree-contact-line {
    display: flex;
    flex-wrap: wrap;
    gap: 2px 10px;
}

.pedigree-contact-line span {
    display: inline-block;
    min-width: 0;
    overflow-wrap: anywhere;
}

.pedigree-contact-line.is-address span {
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.pedigree-contact svg {
    /* Tailwind's base styles make SVGs block-level; keep the icons on the text line */
    display: inline-block;
    flex-shrink: 0;
    width: 8px;
    height: 8px;
    margin-right: 4px;
    color: var(--gold-deep);
    vertical-align: -1px;
}

.pedigree-bred-by {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    gap: 3px;
    max-width: 220px;
    padding-left: 14px;
    border-left: 1px solid var(--rule);
}

.pedigree-bred-by-name {
    color: var(--ink);
    font-size: 10px;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.pedigree-legend {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 6px;
}

.pedigree-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 9px 2px 6px;
    border-radius: 999px;
    background: var(--surface);
    color: var(--ink);
    font-weight: 600;
}
</style>
