<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

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
    hatch_date: string | null;
    pedigree_image: string | null;
}

const props = defineProps<{
    pigeon: Pigeon;
    pedigree: PedigreeNode;
}>();

const pigeonLabel = computed(() => props.pigeon.name || props.pigeon.ring_number || props.pigeon.personal_number || `Pigeon #${props.pigeon.id}`);

const getLabel = (node: PedigreeNode | null): string => {
    if (!node) return '';
    return node.name || node.ring_number || node.personal_number || '';
};

const getRing = (node: PedigreeNode | null): string => {
    if (!node) return '';
    return node.ring_number || '';
};

const getColor = (node: PedigreeNode | null): string => {
    if (!node) return '';
    return node.color || '';
};

const currentDate = new Date().toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
});

onMounted(() => {
    // Auto-trigger print dialog after a short delay
    setTimeout(() => {
        window.print();
    }, 500);
});
</script>

<template>
    <Head :title="`Pedigree - ${pigeonLabel}`" />
    
    <div class="min-h-screen bg-white p-8 print:p-0">
        <!-- Print Header -->
        <div class="mb-8 border-b-2 border-gray-800 pb-4 print:mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">PEDIGREE CHART</h1>
                    <p class="mt-1 text-sm text-gray-600">BloodlineHub - Racing Pigeon Registry</p>
                </div>
                <div class="text-right text-sm text-gray-600">
                    <p>Printed: {{ currentDate }}</p>
                </div>
            </div>
        </div>

        <!-- Subject Pigeon Info -->
        <div class="mb-8 rounded-lg border-2 border-blue-600 bg-blue-50 p-6 print:mb-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase text-gray-600">Name</p>
                    <p class="text-xl font-bold text-gray-900">{{ pigeon.name || 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-gray-600">Ring Number</p>
                    <p class="text-xl font-bold text-gray-900">{{ pigeon.ring_number || 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-gray-600">Color</p>
                    <p class="text-lg text-gray-900">{{ pigeon.color || 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-gray-600">Hatch Date</p>
                    <p class="text-lg text-gray-900">{{ pigeon.hatch_date || 'N/A' }}</p>
                </div>
            </div>
        </div>

        <!-- Pedigree Tree Table -->
        <div class="overflow-hidden rounded-lg border-2 border-gray-300">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th class="border border-gray-600 p-2 text-left text-xs font-semibold uppercase">Subject</th>
                        <th class="border border-gray-600 p-2 text-left text-xs font-semibold uppercase">Parents</th>
                        <th class="border border-gray-600 p-2 text-left text-xs font-semibold uppercase">Grandparents</th>
                        <th class="border border-gray-600 p-2 text-left text-xs font-semibold uppercase">Great Grandparents</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Subject Row -->
                    <tr>
                        <!-- Subject -->
                        <td rowspan="8" class="border border-gray-300 bg-blue-50 p-3 align-middle">
                            <div class="text-center">
                                <p class="font-bold text-gray-900">{{ getLabel(pedigree) }}</p>
                                <p class="text-sm text-gray-600">{{ getRing(pedigree) }}</p>
                                <p class="text-xs text-gray-500">{{ getColor(pedigree) }}</p>
                            </div>
                        </td>
                        
                        <!-- Sire -->
                        <td rowspan="4" class="border border-gray-300 bg-blue-100/50 p-3 align-middle">
                            <div>
                                <p class="text-xs font-semibold text-blue-800">SIRE</p>
                                <p class="font-semibold text-gray-900">{{ getLabel(pedigree.sire) || pedigree.sire_name || 'Unknown' }}</p>
                                <p class="text-sm text-gray-600">{{ getRing(pedigree.sire) || pedigree.sire_ring_number }}</p>
                                <p class="text-xs text-gray-500">{{ getColor(pedigree.sire) || pedigree.sire_color }}</p>
                            </div>
                        </td>
                        
                        <!-- Sire's Sire -->
                        <td rowspan="2" class="border border-gray-300 p-2 align-middle">
                            <div>
                                <p class="font-medium text-gray-900">{{ getLabel(pedigree.sire?.sire) || pedigree.sire?.sire_name || '—' }}</p>
                                <p class="text-xs text-gray-600">{{ getRing(pedigree.sire?.sire) || pedigree.sire?.sire_ring_number }}</p>
                                <p class="text-xs text-gray-500">{{ getColor(pedigree.sire?.sire) || pedigree.sire?.sire_color }}</p>
                            </div>
                        </td>
                        
                        <!-- Sire's Sire's Sire -->
                        <td class="border border-gray-300 p-2">
                            <p class="text-sm text-gray-900">{{ getLabel(pedigree.sire?.sire?.sire) || pedigree.sire?.sire?.sire_name || '—' }}</p>
                            <p class="text-xs text-gray-500">{{ getRing(pedigree.sire?.sire?.sire) }}</p>
                        </td>
                    </tr>
                    <tr>
                        <!-- Sire's Sire's Dam -->
                        <td class="border border-gray-300 p-2">
                            <p class="text-sm text-gray-900">{{ getLabel(pedigree.sire?.sire?.dam) || pedigree.sire?.sire?.dam_name || '—' }}</p>
                            <p class="text-xs text-gray-500">{{ getRing(pedigree.sire?.sire?.dam) }}</p>
                        </td>
                    </tr>
                    <tr>
                        <!-- Sire's Dam -->
                        <td rowspan="2" class="border border-gray-300 p-2 align-middle">
                            <div>
                                <p class="font-medium text-gray-900">{{ getLabel(pedigree.sire?.dam) || pedigree.sire?.dam_name || '—' }}</p>
                                <p class="text-xs text-gray-600">{{ getRing(pedigree.sire?.dam) || pedigree.sire?.dam_ring_number }}</p>
                                <p class="text-xs text-gray-500">{{ getColor(pedigree.sire?.dam) || pedigree.sire?.dam_color }}</p>
                            </div>
                        </td>
                        
                        <!-- Sire's Dam's Sire -->
                        <td class="border border-gray-300 p-2">
                            <p class="text-sm text-gray-900">{{ getLabel(pedigree.sire?.dam?.sire) || pedigree.sire?.dam?.sire_name || '—' }}</p>
                            <p class="text-xs text-gray-500">{{ getRing(pedigree.sire?.dam?.sire) }}</p>
                        </td>
                    </tr>
                    <tr>
                        <!-- Sire's Dam's Dam -->
                        <td class="border border-gray-300 p-2">
                            <p class="text-sm text-gray-900">{{ getLabel(pedigree.sire?.dam?.dam) || pedigree.sire?.dam?.dam_name || '—' }}</p>
                            <p class="text-xs text-gray-500">{{ getRing(pedigree.sire?.dam?.dam) }}</p>
                        </td>
                    </tr>
                    
                    <!-- Dam Rows -->
                    <tr>
                        <!-- Dam -->
                        <td rowspan="4" class="border border-gray-300 bg-pink-100/50 p-3 align-middle">
                            <div>
                                <p class="text-xs font-semibold text-pink-800">DAM</p>
                                <p class="font-semibold text-gray-900">{{ getLabel(pedigree.dam) || pedigree.dam_name || 'Unknown' }}</p>
                                <p class="text-sm text-gray-600">{{ getRing(pedigree.dam) || pedigree.dam_ring_number }}</p>
                                <p class="text-xs text-gray-500">{{ getColor(pedigree.dam) || pedigree.dam_color }}</p>
                            </div>
                        </td>
                        
                        <!-- Dam's Sire -->
                        <td rowspan="2" class="border border-gray-300 p-2 align-middle">
                            <div>
                                <p class="font-medium text-gray-900">{{ getLabel(pedigree.dam?.sire) || pedigree.dam?.sire_name || '—' }}</p>
                                <p class="text-xs text-gray-600">{{ getRing(pedigree.dam?.sire) || pedigree.dam?.sire_ring_number }}</p>
                                <p class="text-xs text-gray-500">{{ getColor(pedigree.dam?.sire) || pedigree.dam?.sire_color }}</p>
                            </div>
                        </td>
                        
                        <!-- Dam's Sire's Sire -->
                        <td class="border border-gray-300 p-2">
                            <p class="text-sm text-gray-900">{{ getLabel(pedigree.dam?.sire?.sire) || pedigree.dam?.sire?.sire_name || '—' }}</p>
                            <p class="text-xs text-gray-500">{{ getRing(pedigree.dam?.sire?.sire) }}</p>
                        </td>
                    </tr>
                    <tr>
                        <!-- Dam's Sire's Dam -->
                        <td class="border border-gray-300 p-2">
                            <p class="text-sm text-gray-900">{{ getLabel(pedigree.dam?.sire?.dam) || pedigree.dam?.sire?.dam_name || '—' }}</p>
                            <p class="text-xs text-gray-500">{{ getRing(pedigree.dam?.sire?.dam) }}</p>
                        </td>
                    </tr>
                    <tr>
                        <!-- Dam's Dam -->
                        <td rowspan="2" class="border border-gray-300 p-2 align-middle">
                            <div>
                                <p class="font-medium text-gray-900">{{ getLabel(pedigree.dam?.dam) || pedigree.dam?.dam_name || '—' }}</p>
                                <p class="text-xs text-gray-600">{{ getRing(pedigree.dam?.dam) || pedigree.dam?.dam_ring_number }}</p>
                                <p class="text-xs text-gray-500">{{ getColor(pedigree.dam?.dam) || pedigree.dam?.dam_color }}</p>
                            </div>
                        </td>
                        
                        <!-- Dam's Dam's Sire -->
                        <td class="border border-gray-300 p-2">
                            <p class="text-sm text-gray-900">{{ getLabel(pedigree.dam?.dam?.sire) || pedigree.dam?.dam?.sire_name || '—' }}</p>
                            <p class="text-xs text-gray-500">{{ getRing(pedigree.dam?.dam?.sire) }}</p>
                        </td>
                    </tr>
                    <tr>
                        <!-- Dam's Dam's Dam -->
                        <td class="border border-gray-300 p-2">
                            <p class="text-sm text-gray-900">{{ getLabel(pedigree.dam?.dam?.dam) || pedigree.dam?.dam?.dam_name || '—' }}</p>
                            <p class="text-xs text-gray-500">{{ getRing(pedigree.dam?.dam?.dam) }}</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="mt-8 border-t-2 border-gray-300 pt-4 text-center text-sm text-gray-600 print:mt-6">
            <p>This pedigree was generated by BloodlineHub - Racing Pigeon Management System</p>
            <p class="mt-1 text-xs">For more information, visit bloodlinehub.com</p>
        </div>

        <!-- Print Instructions (hidden when printed) -->
        <div class="mt-8 rounded-lg border border-blue-300 bg-blue-50 p-4 print:hidden">
            <p class="text-center text-sm text-blue-900">
                Press <kbd class="rounded bg-white px-2 py-1 font-mono text-xs">Ctrl+P</kbd> (Windows) or 
                <kbd class="rounded bg-white px-2 py-1 font-mono text-xs">Cmd+P</kbd> (Mac) to print or save as PDF
            </p>
        </div>
    </div>
</template>

<style scoped>
@media print {
    @page {
        size: A4 landscape;
        margin: 1cm;
    }
    
    body {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
}
</style>
