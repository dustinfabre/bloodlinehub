<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { create, destroy, edit, index as indexRoute, show } from '@/routes/pigeons';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { type BreadcrumbItem } from '@/types';

interface ParentSummary {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
}

interface Pigeon {
    id: number;
    name: string | null;
    gender: string | null;
    status: string;
    pigeon_status: string;
    race_type: string;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    for_sale: boolean;
    sire: ParentSummary | null;
    dam: ParentSummary | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationMeta {
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
}

interface Props {
    pigeons: {
        data: Pigeon[];
        links: PaginationLink[];
        meta: PaginationMeta;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pigeons',
        href: indexRoute().url,
    },
];

const normalizedLinks = computed(() =>
    props.pigeons.links.filter((link) => link.url !== null),
);

const pigeonLabel = (pigeon: Pigeon) =>
    pigeon.name || pigeon.ring_number || pigeon.personal_number || `Pigeon #${pigeon.id}`;

const parentLabel = (parent: ParentSummary | null) => {
    if (!parent) return 'Not set';
    return parent.name || parent.ring_number || parent.personal_number || `#${parent.id}`;
};

const handleDelete = (pigeon: Pigeon) => {
    const label = pigeonLabel(pigeon);
    if (!window.confirm(`Remove ${label}? This action cannot be undone.`)) return;
    
    router.delete(destroy({ pigeon: pigeon.id }).url, {
        preserveScroll: true,
        preserveState: true,
    });
};

const formatDate = (value: string) =>
    new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

const statusBadgeVariant = (status: string) => {
    if (status === 'alive') return 'default';
    if (status === 'deceased') return 'destructive';
    return 'secondary';
};
</script>

<template>
    <Head title="Pigeons" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">
                        Pigeon records
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Track your loft and manage pedigrees.
                    </p>
                </div>
                <Button as-child>
                    <Link :href="create().url">
                        Add pigeon
                    </Link>
                </Button>
            </div>

            <Card class="flex-1">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Pigeons</CardTitle>
                    <p v-if="props.pigeons?.meta" class="text-sm text-muted-foreground">
                        Showing
                        <span class="font-medium text-foreground">
                            {{ props.pigeons.meta.from ?? 0 }}-
                            {{ props.pigeons.meta.to ?? 0 }}
                        </span>
                        of
                        <span class="font-medium text-foreground">
                            {{ props.pigeons.meta.total }}
                        </span>
                    </p>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="props.pigeons.data.length === 0"
                        class="flex flex-col items-center justify-center rounded-lg border border-dashed border-primary/20 bg-primary/5 py-16 text-center"
                    >
                        <h2 class="text-lg font-semibold text-primary">
                            No pigeons yet
                        </h2>
                        <p class="mt-2 max-w-md text-sm text-muted-foreground">
                            Start by adding your first pigeon to begin managing pedigrees.
                        </p>
                        <Button as-child class="mt-6">
                            <Link :href="create().url">
                                Add pigeon
                            </Link>
                        </Button>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[900px] table-auto border-collapse">
                            <thead>
                                <tr class="border-b border-border text-left text-sm text-muted-foreground">
                                    <th class="py-3 pr-6 font-normal">Name/Ring</th>
                                    <th class="py-3 pr-6 font-normal">Gender</th>
                                    <th class="py-3 pr-6 font-normal">Status</th>
                                    <th class="py-3 pr-6 font-normal">Type</th>
                                    <th class="py-3 pr-6 font-normal">Sire</th>
                                    <th class="py-3 pr-6 font-normal">Dam</th>
                                    <th class="py-3 pr-6 font-normal">Added</th>
                                    <th class="py-3 text-right font-normal">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="pigeon in props.pigeons.data"
                                    :key="pigeon.id"
                                    class="border-b border-border/80 text-sm transition-colors hover:bg-accent/50"
                                >
                                    <td class="py-3 pr-6">
                                        <div class="flex items-center gap-2">
                                            <div>
                                                <div class="font-medium text-foreground">
                                                    {{ pigeonLabel(pigeon) }}
                                                </div>
                                                <div v-if="pigeon.color" class="text-xs text-muted-foreground">
                                                    {{ pigeon.color }}
                                                </div>
                                            </div>
                                            <Badge v-if="pigeon.for_sale" variant="default" class="ml-auto">
                                                For Sale
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="py-3 pr-6">
                                        <Badge v-if="pigeon.gender" variant="outline" class="text-xs">
                                            {{ pigeon.gender === 'male' ? 'Cock' : 'Hen' }}
                                        </Badge>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="py-3 pr-6">
                                        <Badge :variant="statusBadgeVariant(pigeon.status)" class="text-xs">
                                            {{ pigeon.status }}
                                        </Badge>
                                    </td>
                                    <td class="py-3 pr-6">
                                        <div class="text-xs">
                                            {{ pigeon.pigeon_status }}
                                        </div>
                                        <div v-if="pigeon.race_type !== 'none'" class="text-xs text-muted-foreground">
                                            {{ pigeon.race_type.toUpperCase() }}
                                        </div>
                                    </td>
                                    <td class="py-3 pr-6 text-xs">
                                        {{ parentLabel(pigeon.sire) }}
                                    </td>
                                    <td class="py-3 pr-6 text-xs">
                                        {{ parentLabel(pigeon.dam) }}
                                    </td>
                                    <td class="py-3 pr-6 text-xs">
                                        {{ formatDate(pigeon.created_at) }}
                                    </td>
                                    <td class="py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button as-child variant="outline" size="sm">
                                                <Link :href="show({ pigeon: pigeon.id }).url">
                                                    View
                                                </Link>
                                            </Button>
                                            <Button as-child variant="outline" size="sm">
                                                <Link :href="edit({ pigeon: pigeon.id }).url">
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                @click="handleDelete(pigeon)"
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <nav
                        v-if="normalizedLinks.length > 1"
                        class="mt-6 flex items-center justify-end gap-2 text-sm"
                    >
                        <Link
                            v-for="link in normalizedLinks"
                            :key="`${link.label}-${link.url}`"
                            :href="link.url ?? '#'"
                            :class="[
                                'rounded-md border px-3 py-1 transition-colors',
                                link.active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-background text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                            ]"
                            v-html="link.label.replace('&laquo;', '‹').replace('&raquo;', '›')"
                        />
                    </nav>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
