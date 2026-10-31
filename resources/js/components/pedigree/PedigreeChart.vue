<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import PedigreeBranch from '@/components/pedigree/PedigreeBranch.vue';
import PedigreeCard from '@/components/pedigree/PedigreeCard.vue';
import {
    collectHighlights,
    formatPedigreeDate,
    GENERATION_LABELS,
} from '@/lib/pedigree';
import type { PedigreeMeta, PedigreeNode } from '@/types/pedigree';
import { computed, ref } from 'vue';

const props = defineProps<{
    tree: PedigreeNode;
    meta: PedigreeMeta;
    // Ring numbers link to the pigeon pages (owner view only)
    linkable?: boolean;
}>();

// The fixed-size A4 sheet; exported as-is for image and PDF downloads
const paper = ref<HTMLElement | null>(null);
defineExpose({ paper });

const highlights = computed(() => collectHighlights(props.tree));
const shareLabel = computed(() =>
    props.meta.share_url.replace(/^https?:\/\//, ''),
);
</script>

<template>
    <div ref="paper" class="pedigree-paper">
        <div class="pedigree-frame" aria-hidden="true" />

        <header class="pedigree-header">
            <div class="pedigree-brand">
                <AppLogoIcon class="pedigree-logo" />
                <div>
                    <div class="pedigree-eyebrow">BloodlineHub</div>
                    <div class="pedigree-title">Pedigree</div>
                </div>
            </div>
            <dl class="pedigree-facts">
                <div>
                    <dt>Breeder</dt>
                    <dd>{{ meta.breeder }}</dd>
                </div>
                <div>
                    <dt>Issued</dt>
                    <dd>{{ formatPedigreeDate(meta.generated_at) }}</dd>
                </div>
                <div>
                    <dt>Pedigree No.</dt>
                    <dd class="is-mono">{{ meta.serial }}</dd>
                </div>
            </dl>
        </header>

        <div class="pedigree-gens">
            <span v-for="label in GENERATION_LABELS" :key="label">{{
                label
            }}</span>
        </div>

        <div class="pedigree-tree">
            <div class="pedigree-subject">
                <img
                    v-if="meta.photo_url"
                    :src="meta.photo_url"
                    alt=""
                    class="pedigree-photo"
                />
                <PedigreeCard :node="tree" :depth="0" :side="null" />
                <span class="pedigree-line-out" />
                <div class="pedigree-qr-block">
                    <div class="pedigree-qr" v-html="meta.qr_svg" />
                    <div>
                        <div class="pedigree-qr-label">Scan to verify</div>
                        <div class="pedigree-qr-serial is-mono">
                            {{ meta.serial }}
                        </div>
                        <div class="pedigree-qr-hint">
                            {{
                                meta.is_public
                                    ? 'Public pedigree'
                                    : 'Private pedigree'
                            }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="pedigree-children">
                <div class="pedigree-child is-first">
                    <span class="pedigree-line-in" />
                    <span class="pedigree-line-v" />
                    <PedigreeBranch
                        :node="tree.sire"
                        :depth="1"
                        side="sire"
                        :linkable="linkable"
                    />
                </div>
                <div class="pedigree-child is-last">
                    <span class="pedigree-line-in" />
                    <span class="pedigree-line-v" />
                    <PedigreeBranch
                        :node="tree.dam"
                        :depth="1"
                        side="dam"
                        :linkable="linkable"
                    />
                </div>
            </div>
        </div>

        <footer class="pedigree-footer">
            <div class="pedigree-legend">
                <template v-if="highlights.length">
                    <span
                        v-for="highlight in highlights"
                        :key="`${highlight.name}-${highlight.color}`"
                        class="pedigree-legend-item"
                    >
                        <i :style="{ backgroundColor: highlight.color }" />
                        {{ highlight.name }}
                    </span>
                </template>
                <span v-else>♂ Sire line above · ♀ Dam line below</span>
            </div>
            <div>Remarks supplied by the breeder · {{ shareLabel }}</div>
        </footer>
    </div>
</template>

<style>
/*
 * The pedigree sheet uses its own fixed palette (not the app theme) so it always
 * renders as a white document: on screen, in dark mode, in print and in exports.
 */
.pedigree-paper {
    --c0: 214px;
    --c1: 226px;
    --c2: 206px;
    --c3: 186px;
    --gap: 16px;
    --ink: #17252a;
    --muted: #5f706c;
    --faint: #8b9b97;
    --rule: #dbe4e1;
    --line: #a3b6b1;
    --brand: #176b57;
    --brand-tint: #eff6f3;
    --sire: #3a6ea5;
    --dam: #b4506b;

    position: relative;
    display: flex;
    flex-direction: column;
    width: 1122px;
    height: 793px;
    padding: 28px 30px 20px;
    box-sizing: border-box;
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

.pedigree-frame {
    position: absolute;
    inset: 10px;
    border: 1px solid var(--rule);
    border-top: 3px solid var(--brand);
    border-radius: 8px;
    pointer-events: none;
}

.pedigree-paper .is-mono {
    font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
    letter-spacing: 0.04em;
}

/* Header */
.pedigree-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    padding: 2px 0 10px;
    border-bottom: 1px solid var(--rule);
}

.pedigree-brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.pedigree-logo {
    width: 28px;
    height: 30px;
    color: var(--brand);
}

.pedigree-eyebrow {
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--brand);
}

.pedigree-title {
    margin-top: 2px;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 28px;
    font-weight: 700;
    line-height: 1;
    letter-spacing: -0.01em;
}

.pedigree-facts {
    display: flex;
    gap: 28px;
    margin: 0;
}

.pedigree-facts dt {
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--faint);
}

.pedigree-facts dd {
    margin: 2px 0 0;
    font-size: 12px;
    font-weight: 600;
}

/* Generation labels line up with the tree columns */
.pedigree-gens {
    display: flex;
    gap: var(--gap);
    margin: 10px 0 4px;
    font-size: 7.5px;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--faint);
}

.pedigree-gens span {
    flex-shrink: 0;
}

.pedigree-gens span:nth-child(1) {
    width: var(--c0);
}

.pedigree-gens span:nth-child(2) {
    width: var(--c1);
}

.pedigree-gens span:nth-child(3) {
    width: var(--c2);
}

.pedigree-gens span:nth-child(4) {
    width: var(--c3);
}

.pedigree-gens span:nth-child(5) {
    flex: 1;
}

/* Tree: each generation halves the height of the one before it */
.pedigree-tree {
    display: flex;
    flex: 1;
    min-height: 0;
}

.pedigree-subject {
    position: relative;
    display: flex;
    flex-shrink: 0;
    align-items: center;
    width: var(--c0);
}

.pedigree-subject > .pedigree-card {
    max-height: 290px;
}

.pedigree-photo {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 150px;
    object-fit: cover;
    border: 1px solid var(--rule);
    border-radius: 6px;
}

.pedigree-qr-block {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border: 1px solid var(--rule);
    border-radius: 6px;
    background: #ffffff;
}

.pedigree-qr {
    flex-shrink: 0;
    width: 72px;
    height: 72px;
}

.pedigree-qr svg {
    display: block;
    width: 100%;
    height: 100%;
}

.pedigree-qr-label {
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--faint);
}

.pedigree-qr-serial {
    margin-top: 3px;
    font-size: 11px;
    font-weight: 700;
}

.pedigree-qr-hint {
    margin-top: 2px;
    font-size: 8px;
    color: var(--muted);
}

.pedigree-branch {
    display: flex;
    flex: 1;
    min-width: 0;
}

.pedigree-slot {
    position: relative;
    display: flex;
    flex-shrink: 0;
    align-items: center;
    padding: 3px 0;
}

.pedigree-slot[data-depth='1'] {
    width: var(--c1);
}

.pedigree-slot[data-depth='2'] {
    width: var(--c2);
}

.pedigree-slot[data-depth='3'] {
    width: var(--c3);
}

.pedigree-slot[data-depth='4'] {
    flex: 1;
    min-width: 0;
}

.pedigree-children {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
}

.pedigree-child {
    position: relative;
    display: flex;
    flex: 1 1 0;
    min-height: 0;
    padding-left: var(--gap);
}

/* Bracket connectors: parent stub -> vertical spine -> child stubs */
.pedigree-line-out,
.pedigree-line-in {
    position: absolute;
    top: 50%;
    width: calc(var(--gap) / 2);
    border-top: 1px solid var(--line);
}

.pedigree-line-out {
    left: 100%;
}

.pedigree-line-in {
    left: calc(var(--gap) / 2);
}

.pedigree-line-v {
    position: absolute;
    left: calc(var(--gap) / 2);
    border-left: 1px solid var(--line);
}

.pedigree-child.is-first > .pedigree-line-v {
    top: 50%;
    bottom: 0;
}

.pedigree-child.is-last > .pedigree-line-v {
    top: 0;
    bottom: 50%;
}

/* Bird cards */
.pedigree-card {
    position: relative;
    width: 100%;
    max-height: 100%;
    overflow: hidden;
    padding: 6px 8px;
    border: 1px solid var(--rule);
    border-radius: 5px;
    background: #ffffff;
}

.pedigree-card.is-highlighted {
    border-left: 4px solid var(--pc-highlight);
    background: var(--pc-highlight-tint);
}

.pedigree-card.is-empty {
    display: flex;
    align-items: center;
    gap: 4px;
    border-style: dashed;
    background: transparent;
    color: var(--faint);
}

.pc-top {
    display: flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
}

.pc-sex {
    flex-shrink: 0;
    font-size: 1.05em;
    font-weight: 700;
    line-height: 1;
}

.pc-sex.is-male {
    color: var(--sire);
}

.pc-sex.is-female {
    color: var(--dam);
}

.pc-ring {
    min-width: 0;
    overflow: hidden;
    color: var(--ink);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}

a.pc-ring:hover {
    color: var(--brand);
    text-decoration: underline;
}

.pc-tag {
    flex-shrink: 0;
    max-width: 45%;
    margin-left: auto;
    overflow: hidden;
    padding: 1px 6px;
    border-radius: 999px;
    font-size: 7px;
    font-weight: 700;
    line-height: 1.4;
    letter-spacing: 0.06em;
    text-overflow: ellipsis;
    text-transform: uppercase;
    white-space: nowrap;
}

.pc-tag-row {
    margin-bottom: 6px;
}

.pc-tag-row .pc-tag {
    display: inline-block;
    max-width: 100%;
    margin-left: 0;
    font-size: 8px;
}

.pc-name,
.pc-meta,
.pc-line {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pc-name {
    margin-top: 2px;
    font-weight: 600;
}

.pc-meta,
.pc-line {
    margin-top: 1px;
    color: var(--muted);
}

.pc-unknown {
    font-style: italic;
}

.pc-remarks {
    display: -webkit-box;
    margin: 4px 0 0;
    padding-top: 4px;
    overflow: hidden;
    border-top: 1px dashed var(--rule);
    color: #33443f;
    white-space: pre-line;
    overflow-wrap: anywhere;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: var(--pc-lines, 2);
}

/* Each generation gets less room, so less detail */
.pedigree-card[data-depth='0'] {
    --pc-lines: 9;
    padding: 12px 14px;
    border: 1.5px solid var(--brand);
    background: var(--brand-tint);
    font-size: 10.5px;
}

.pedigree-card[data-depth='0'].is-highlighted {
    border-left: 5px solid var(--pc-highlight);
    background: var(--pc-highlight-tint);
}

.pedigree-card[data-depth='0'] .pc-ring {
    font-size: 17px;
}

.pedigree-card[data-depth='0'] .pc-name {
    margin-top: 4px;
    font-size: 14px;
    white-space: normal;
}

.pedigree-card[data-depth='0'] .pc-meta {
    margin-top: 3px;
}

.pedigree-card[data-depth='1'] {
    --pc-lines: 12;
    padding: 8px 10px;
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
    padding: 4px 7px;
    font-size: 8.5px;
}

.pedigree-card[data-depth='3'] .pc-ring {
    font-size: 10.5px;
}

.pedigree-card[data-depth='3'] .pc-remarks {
    margin-top: 2px;
    padding-top: 2px;
}

.pedigree-card[data-depth='4'] {
    padding: 3px 6px;
    font-size: 8px;
}

.pedigree-card[data-depth='4'] .pc-ring {
    font-size: 9.5px;
}

/* Footer */
.pedigree-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 8px;
    padding-top: 7px;
    border-top: 1px solid var(--rule);
    color: var(--muted);
    font-size: 8.5px;
}

.pedigree-legend {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 12px;
}

.pedigree-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--ink);
    font-weight: 600;
}

.pedigree-legend-item i {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 999px;
}
</style>
