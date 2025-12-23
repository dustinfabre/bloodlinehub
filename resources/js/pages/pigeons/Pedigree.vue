<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { index as indexRoute, show as showRoute, pedigree as pedigreeRoute } from '@/routes/pigeons';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { type BreadcrumbItem } from '@/types';

interface PedigreeNode {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    gender: string | null;
    hatch_date: string | null;
    label: string;
    sire: PedigreeNode | null;
    dam: PedigreeNode | null;
    sire_name: string | null;
    sire_ring_number: string | null;
    sire_color: string | null;
    dam_name: string | null;
    dam_ring_number: string | null;
    dam_color: string | null;
}

interface Pigeon {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    gender: string | null;
    color: string | null;
}

const props = defineProps<{
    pigeon: Pigeon;
    pedigree: PedigreeNode;
}>();

const pigeonLabel = computed(() => props.pigeon.name || props.pigeon.ring_number || props.pigeon.personal_number || `Pigeon #${props.pigeon.id}`);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: indexRoute().url },
    { title: pigeonLabel.value, href: showRoute({ pigeon: props.pigeon.id }).url },
    { title: 'Pedigree', href: pedigreeRoute({ pigeon: props.pigeon.id }).url },
];

const getLabel = (node: PedigreeNode | null): string => {
    if (!node) return 'Unknown';
    return node.label;
};

const getGenderBadge = (gender: string | null) => {
    if (gender === 'male') return 'Cock';
    if (gender === 'female') return 'Hen';
    return null;
};
</script>

<template>
    <Head :title="`Pedigree - ${pigeonLabel}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Pedigree Chart</h1>
                    <p class="text-muted-foreground">{{ pigeonLabel }}</p>
                </div>
                <Link :href="showRoute({ pigeon: pigeon.id }).url">
                    <Button variant="outline">Back to details</Button>
                </Link>
            </div>

            <!-- Pedigree Tree Layout -->
            <Card>
                <CardHeader>
                    <CardTitle>5 Generation Pedigree</CardTitle>
                </CardHeader>
                <CardContent class="overflow-x-auto">
                    <div class="min-w-[1400px] space-y-6">
                        <!-- Generation 1 (Subject) -->
                        <div class="grid grid-cols-1">
                            <div class="rounded-lg border-2 border-primary bg-primary/5 p-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold">{{ getLabel(pedigree) }}</span>
                                    <Badge v-if="getGenderBadge(pedigree.gender)" variant="outline" class="text-xs">{{ getGenderBadge(pedigree.gender) }}</Badge>
                                </div>
                                <p v-if="pedigree.color" class="text-sm text-muted-foreground">{{ pedigree.color }}</p>
                                <p v-if="pedigree.hatch_date" class="text-xs text-muted-foreground">{{ pedigree.hatch_date }}</p>
                            </div>
                        </div>

                        <!-- Generation 2 (Parents) -->
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Sire -->
                            <div v-if="pedigree.sire || pedigree.sire_name" class="rounded-lg border border-blue-200 bg-blue-50/50 p-3">
                                <div class="flex items-center gap-2">
                                    <Link v-if="pedigree.sire" :href="showRoute({ pigeon: pedigree.sire.id }).url" class="font-medium text-primary hover:underline">
                                        {{ getLabel(pedigree.sire) }}
                                    </Link>
                                    <span v-else class="font-medium">{{ pedigree.sire_name || 'Unknown Sire' }}</span>
                                    <Badge variant="outline" class="text-xs">Sire</Badge>
                                </div>
                                <p v-if="pedigree.sire?.color || pedigree.sire_color" class="text-sm text-muted-foreground">
                                    {{ pedigree.sire?.color || pedigree.sire_color }}
                                </p>
                            </div>
                            <div v-else class="rounded-lg border border-dashed p-3 text-center text-sm text-muted-foreground">
                                Sire unknown
                            </div>

                            <!-- Dam -->
                            <div v-if="pedigree.dam || pedigree.dam_name" class="rounded-lg border border-pink-200 bg-pink-50/50 p-3">
                                <div class="flex items-center gap-2">
                                    <Link v-if="pedigree.dam" :href="showRoute({ pigeon: pedigree.dam.id }).url" class="font-medium text-primary hover:underline">
                                        {{ getLabel(pedigree.dam) }}
                                    </Link>
                                    <span v-else class="font-medium">{{ pedigree.dam_name || 'Unknown Dam' }}</span>
                                    <Badge variant="outline" class="text-xs">Dam</Badge>
                                </div>
                                <p v-if="pedigree.dam?.color || pedigree.dam_color" class="text-sm text-muted-foreground">
                                    {{ pedigree.dam?.color || pedigree.dam_color }}
                                </p>
                            </div>
                            <div v-else class="rounded-lg border border-dashed p-3 text-center text-sm text-muted-foreground">
                                Dam unknown
                            </div>
                        </div>

                        <!-- Generation 3 (Grandparents) -->
                        <div class="grid grid-cols-4 gap-3">
                            <!-- Sire's Sire -->
                            <div v-if="pedigree.sire?.sire || pedigree.sire?.sire_name" class="rounded border border-slate-200 bg-slate-50/50 p-2">
                                <Link v-if="pedigree.sire?.sire" :href="showRoute({ pigeon: pedigree.sire.sire.id }).url" class="text-sm font-medium text-primary hover:underline">
                                    {{ getLabel(pedigree.sire.sire) }}
                                </Link>
                                <span v-else class="text-sm font-medium">{{ pedigree.sire?.sire_name || 'Unknown' }}</span>
                                <p v-if="pedigree.sire?.sire?.color || pedigree.sire?.sire_color" class="text-xs text-muted-foreground">
                                    {{ pedigree.sire?.sire?.color || pedigree.sire?.sire_color }}
                                </p>
                            </div>
                            <div v-else class="rounded border border-dashed p-2 text-center text-xs text-muted-foreground">—</div>

                            <!-- Sire's Dam -->
                            <div v-if="pedigree.sire?.dam || pedigree.sire?.dam_name" class="rounded border border-slate-200 bg-slate-50/50 p-2">
                                <Link v-if="pedigree.sire?.dam" :href="showRoute({ pigeon: pedigree.sire.dam.id }).url" class="text-sm font-medium text-primary hover:underline">
                                    {{ getLabel(pedigree.sire.dam) }}
                                </Link>
                                <span v-else class="text-sm font-medium">{{ pedigree.sire?.dam_name || 'Unknown' }}</span>
                                <p v-if="pedigree.sire?.dam?.color || pedigree.sire?.dam_color" class="text-xs text-muted-foreground">
                                    {{ pedigree.sire?.dam?.color || pedigree.sire?.dam_color }}
                                </p>
                            </div>
                            <div v-else class="rounded border border-dashed p-2 text-center text-xs text-muted-foreground">—</div>

                            <!-- Dam's Sire -->
                            <div v-if="pedigree.dam?.sire || pedigree.dam?.sire_name" class="rounded border border-slate-200 bg-slate-50/50 p-2">
                                <Link v-if="pedigree.dam?.sire" :href="showRoute({ pigeon: pedigree.dam.sire.id }).url" class="text-sm font-medium text-primary hover:underline">
                                    {{ getLabel(pedigree.dam.sire) }}
                                </Link>
                                <span v-else class="text-sm font-medium">{{ pedigree.dam?.sire_name || 'Unknown' }}</span>
                                <p v-if="pedigree.dam?.sire?.color || pedigree.dam?.sire_color" class="text-xs text-muted-foreground">
                                    {{ pedigree.dam?.sire?.color || pedigree.dam?.sire_color }}
                                </p>
                            </div>
                            <div v-else class="rounded border border-dashed p-2 text-center text-xs text-muted-foreground">—</div>

                            <!-- Dam's Dam -->
                            <div v-if="pedigree.dam?.dam || pedigree.dam?.dam_name" class="rounded border border-slate-200 bg-slate-50/50 p-2">
                                <Link v-if="pedigree.dam?.dam" :href="showRoute({ pigeon: pedigree.dam.dam.id }).url" class="text-sm font-medium text-primary hover:underline">
                                    {{ getLabel(pedigree.dam.dam) }}
                                </Link>
                                <span v-else class="text-sm font-medium">{{ pedigree.dam?.dam_name || 'Unknown' }}</span>
                                <p v-if="pedigree.dam?.dam?.color || pedigree.dam?.dam_color" class="text-xs text-muted-foreground">
                                    {{ pedigree.dam?.dam?.color || pedigree.dam?.dam_color }}
                                </p>
                            </div>
                            <div v-else class="rounded border border-dashed p-2 text-center text-xs text-muted-foreground">—</div>
                        </div>

                        <!-- Generation 4 & 5 indicator -->
                        <div class="rounded-lg border border-dashed bg-muted/30 p-4 text-center">
                            <p class="text-sm text-muted-foreground">Additional generations (4-5) can be viewed by clicking on linked pigeons above.</p>
                            <p class="text-xs text-muted-foreground mt-1">Navigate through the pedigree tree by selecting any linked ancestor.</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
