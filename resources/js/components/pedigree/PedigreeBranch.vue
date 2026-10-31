<script setup lang="ts">
import PedigreeCard from '@/components/pedigree/PedigreeCard.vue';
import { MAX_DEPTH } from '@/lib/pedigree';
import type { PedigreeNode, PedigreeSide } from '@/types/pedigree';
import { computed } from 'vue';

const props = defineProps<{
    node: PedigreeNode | null;
    depth: number;
    side: PedigreeSide;
    linkable?: boolean;
}>();

// Known birds always show both parent slots (as "Unknown" when missing); unknown birds end the branch
const hasParents = computed(() => !!props.node && props.depth < MAX_DEPTH);
</script>

<template>
    <div class="pedigree-branch">
        <div class="pedigree-slot" :data-depth="depth">
            <PedigreeCard
                :node="node"
                :depth="depth"
                :side="side"
                :linkable="linkable"
            />
            <span v-if="hasParents" class="pedigree-line-out" />
        </div>
        <div v-if="hasParents" class="pedigree-children">
            <div class="pedigree-child is-first">
                <span class="pedigree-line-in" />
                <span class="pedigree-line-v" />
                <PedigreeBranch
                    :node="node?.sire ?? null"
                    :depth="depth + 1"
                    side="sire"
                    :linkable="linkable"
                />
            </div>
            <div class="pedigree-child is-last">
                <span class="pedigree-line-in" />
                <span class="pedigree-line-v" />
                <PedigreeBranch
                    :node="node?.dam ?? null"
                    :depth="depth + 1"
                    side="dam"
                    :linkable="linkable"
                />
            </div>
        </div>
    </div>
</template>
