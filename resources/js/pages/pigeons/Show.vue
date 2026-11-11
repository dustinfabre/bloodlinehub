<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import GoldenPairBadge from '@/components/GoldenPairBadge.vue';
import PigeonMerits from '@/components/PigeonMerits.vue';
import PigeonStatusMenu from '@/components/PigeonStatusMenu.vue';
import { isAncestorRelation, relationHint, relationTitle } from '@/lib/pedigree';
import { statusStyle } from '@/lib/pigeon-status';
import { show as loftCheckShow } from '@/routes/loft-checks';
import { formatCheckDate, formatDay } from '@/types/loft-check';
import { edit, editPedigree, index as indexRoute, show as showRoute, pedigree as pedigreeRoute } from '@/routes/pigeons';
import { index as lineageIndex } from '@/routes/lineage';
import { index as pairingAnalysisIndex } from '@/routes/pairing-analysis';
import type { GoldenPairInfo, Merit, MeritLevelOption, MeritSuggestion, MeritTypeOption } from '@/types/lineage';
import { Head, Link } from '@inertiajs/vue3';
import { Eye, FlaskConical, GitFork, Lock, Network, Pencil } from 'lucide-vue-next';
import { computed } from 'vue';
import { type BreadcrumbItem } from '@/types';

interface ParentSummary {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    remarks: string | null;
    notes: string | null;
}

interface ParentDisplay {
    key: 'sire' | 'dam';
    title: string;
    linked: ParentSummary | null;
    label: string;
    details: string;
    remarks: string | null;
    notes: string | null;
    // Notes typed on this pigeon's own record about a linked parent
    recordNotes: string | null;
}

interface Pigeon {
    id: number;
    name: string | null;
    gender: string | null;
    hatch_date: string | null;
    status: string;
    // When the bird got its current status, if recorded
    status_since: string | null;
    ring_number: string | null;
    personal_number: string | null;
    color: string | null;
    remarks: string | null;
    notes: string | null;
    photo_url: string | null;
    // Every photo, the main one first
    photos: string[];
    pedigree_images: string[] | null;
    // Image URL => chart position of the relative it shows; the rest are certificates
    pedigree_image_relations: Record<string, string>;
    // The ones shown on the pedigree
    pedigree_images_public: string[];
    bred_by: string | null;
    // Both parents are the user's birds
    bred_in_loft: boolean;
    sire: ParentSummary | null;
    dam: ParentSummary | null;
    sire_name: string | null;
    sire_ring_number: string | null;
    sire_color: string | null;
    sire_notes: string | null;
    dam_name: string | null;
    dam_ring_number: string | null;
    dam_color: string | null;
    dam_notes: string | null;
    pedigree: { serial: string; is_public: boolean; visibility: 'private' | 'password' | 'public' } | null;
    merits: Merit[];
}

interface StatusChange {
    id: number;
    from_status: string | null;
    to_status: string;
    changed_at: string;
    // The loft check that made the change
    loft_check: { id: number; label: string } | null;
}

interface Child {
    id: number;
    name: string | null;
    ring_number: string | null;
    personal_number: string | null;
    gender: string | null;
    hatch_date: string | null;
    status: string;
    color: string | null;
    // The child's other parent; id is null when it is an external bird
    mate: { side: 'sire' | 'dam'; id: number | null; name: string | null; ring_number: string | null; personal_number: string | null } | null;
}

const props = defineProps<{
    pigeon: Pigeon;
    children: Child[];
    meritTypes: MeritTypeOption[];
    meritLevels: MeritLevelOption[];
    meritSuggestions: MeritSuggestion[];
    // Golden Pair status of this bird's parents (null unless both are in the loft)
    parentPair: GoldenPairInfo | null;
    // Status changes, most recent first: when the bird went missing, came back, etc.
    statusHistory: StatusChange[];
}>();

// Pairing Analysis pre-filled with this bird on its side of the pair
const pairingAnalysisUrl = computed(() => {
    if (props.pigeon.gender === 'male') return pairingAnalysisIndex({ query: { sire: props.pigeon.id } }).url;
    if (props.pigeon.gender === 'female') return pairingAnalysisIndex({ query: { dam: props.pigeon.id } }).url;
    return null;
});

// Photos of relatives, named by their place in the family (father's side first), and the certificates and documents
const relativePhotos = computed(() => {
    const relations = props.pigeon.pedigree_image_relations ?? {};
    // Ancestors generation by generation (father's side first), then other relatives
    const order = (relation: string) =>
        isAncestorRelation(relation) ? `${relation.length}:${relation.replace(/S/g, '0').replace(/D/g, '1')}` : `9:${relation}`;
    return (props.pigeon.pedigree_images ?? [])
        .filter((url) => relations[url])
        .map((url) => ({
            url,
            position: relations[url],
            title: relationTitle(relations[url]),
            hint: relationHint(relations[url]),
            public: isPublic(url),
        }))
        .sort((a, b) => order(a.position).localeCompare(order(b.position)));
});
// Shown on the pedigree, or only to the owner
const isPublic = (url: string) => (props.pigeon.pedigree_images_public ?? []).includes(url);

const certificates = computed(() =>
    (props.pigeon.pedigree_images ?? []).filter((url) => !props.pigeon.pedigree_image_relations?.[url]),
);

const pigeonLabel = computed(() => props.pigeon.name || props.pigeon.ring_number || props.pigeon.personal_number || `Pigeon #${props.pigeon.id}`);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pigeons', href: indexRoute().url },
    { title: pigeonLabel.value, href: showRoute({ pigeon: props.pigeon.id }).url },
];

const genderName = (gender: string | null) => {
    if (gender === 'male') return 'Cock';
    if (gender === 'female') return 'Hen';
    return null;
};

const genderLabel = computed(() => genderName(props.pigeon.gender) ?? 'Not specified');

const hatchDateFormatted = computed(() => {
    if (!props.pigeon.hatch_date) return 'Not recorded';
    return new Date(props.pigeon.hatch_date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

const buildParent = (key: 'sire' | 'dam', title: string): ParentDisplay | null => {
    const linked = props.pigeon[key];
    if (linked) {
        return {
            key,
            title,
            linked,
            label: linked.name || linked.ring_number || linked.personal_number || `Pigeon #${linked.id}`,
            details: [linked.name ? linked.ring_number : null, linked.color].filter(Boolean).join(' · '),
            remarks: linked.remarks,
            notes: linked.notes,
            recordNotes: props.pigeon[`${key}_notes`],
        };
    }

    const name = props.pigeon[`${key}_name`];
    const ringNumber = props.pigeon[`${key}_ring_number`];
    const color = props.pigeon[`${key}_color`];
    const remarks = props.pigeon[`${key}_notes`];
    if (!name && !ringNumber && !color && !remarks) return null;

    // External parents only have remarks, which are shown on the pedigree
    return {
        key,
        title,
        linked: null,
        label: name || ringNumber || 'Unknown',
        details: [name ? ringNumber : null, color].filter(Boolean).join(' · '),
        remarks,
        notes: null,
        recordNotes: null,
    };
};

const parents = computed(() =>
    [buildParent('sire', 'Sire (Father)'), buildParent('dam', 'Dam (Mother)')].filter((parent): parent is ParentDisplay => parent !== null),
);

const childLabel = (child: Child) => child.name || child.ring_number || child.personal_number || `Pigeon #${child.id}`;

const mateLabel = (mate: NonNullable<Child['mate']>) => mate.name || mate.ring_number || mate.personal_number || 'Unknown';

const childDetails = (child: Child) => {
    // Hatch dates are plain dates, so format them in UTC to avoid shifting a day
    const hatched = child.hatch_date
        ? `Hatched ${new Date(child.hatch_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' })}`
        : null;
    return [child.name ? child.ring_number : null, child.color, hatched].filter(Boolean).join(' · ');
};
</script>

<template>
    <Head :title="pigeonLabel" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-3xl font-bold">{{ pigeonLabel }}</h1>
                <div class="flex flex-wrap gap-2">
                    <Link :href="lineageIndex({ query: { pigeon: pigeon.id } }).url">
                        <Button variant="outline">
                            <GitFork class="mr-2 h-4 w-4" />
                            Lineage
                        </Button>
                    </Link>
                    <Link v-if="pairingAnalysisUrl" :href="pairingAnalysisUrl">
                        <Button variant="outline">
                            <FlaskConical class="mr-2 h-4 w-4" />
                            Pairing Analysis
                        </Button>
                    </Link>
                    <Link :href="pedigreeRoute({ pigeon: pigeon.id }).url">
                        <Button variant="outline">
                            <Network class="mr-2 h-4 w-4" />
                            View Pedigree
                        </Button>
                    </Link>
                    <Link :href="edit({ pigeon: pigeon.id }).url">
                        <Button>Edit pigeon</Button>
                    </Link>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- General Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>General Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Photos: the main one large, the rest underneath -->
                        <div v-if="pigeon.photos.length" class="mb-4 space-y-2">
                            <a :href="pigeon.photos[0]" target="_blank" class="block overflow-hidden rounded-lg border">
                                <img
                                    :src="pigeon.photos[0]"
                                    :alt="pigeonLabel"
                                    class="h-48 w-full object-cover transition-transform hover:scale-105"
                                />
                            </a>
                            <div v-if="pigeon.photos.length > 1" class="grid grid-cols-4 gap-2">
                                <a
                                    v-for="(photo, index) in pigeon.photos.slice(1)"
                                    :key="photo"
                                    :href="photo"
                                    target="_blank"
                                    class="block overflow-hidden rounded-md border"
                                >
                                    <img
                                        :src="photo"
                                        :alt="`${pigeonLabel} photo ${index + 2}`"
                                        loading="lazy"
                                        class="aspect-square w-full object-cover transition-transform hover:scale-105"
                                    />
                                </a>
                            </div>
                        </div>
                        <div v-if="pigeon.name">
                            <p class="text-sm text-muted-foreground">Name</p>
                            <p class="font-medium">{{ pigeon.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Gender</p>
                            <Badge variant="outline">{{ genderLabel }}</Badge>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Hatch Date</p>
                            <p class="font-medium">{{ hatchDateFormatted }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Status</p>
                            <PigeonStatusMenu :pigeon="pigeon" :label="pigeonLabel" />
                            <p v-if="pigeon.status_since" class="mt-1 text-xs text-muted-foreground">
                                since {{ formatDay(pigeon.status_since) }}
                            </p>
                        </div>
                        <div v-if="pigeon.ring_number">
                            <p class="text-sm text-muted-foreground">Ring Number</p>
                            <p class="font-medium">{{ pigeon.ring_number }}</p>
                        </div>
                        <div v-if="pigeon.personal_number">
                            <p class="text-sm text-muted-foreground">Personal Number</p>
                            <p class="font-medium">{{ pigeon.personal_number }}</p>
                        </div>
                        <div v-if="pigeon.color">
                            <p class="text-sm text-muted-foreground">Color</p>
                            <p class="font-medium">{{ pigeon.color }}</p>
                        </div>
                        <div v-if="pigeon.bred_in_loft || pigeon.bred_by">
                            <p class="text-sm text-muted-foreground">Bred by</p>
                            <p class="font-medium">{{ pigeon.bred_in_loft ? 'Your loft' : pigeon.bred_by }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Parents -->
                <Card>
                    <CardHeader>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <CardTitle>Parents</CardTitle>
                            <GoldenPairBadge
                                v-if="parentPair?.golden"
                                :winners="parentPair.winner_count"
                                :offspring="parentPair.offspring"
                            />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div v-for="parent in parents" :key="parent.key" class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-muted-foreground">{{ parent.title }}</p>
                                <Badge v-if="!parent.linked" variant="outline" class="text-xs">External</Badge>
                            </div>
                            <Link v-if="parent.linked" :href="showRoute({ pigeon: parent.linked.id }).url" class="font-medium text-primary hover:underline">
                                {{ parent.label }}
                            </Link>
                            <p v-else class="font-medium">{{ parent.label }}</p>
                            <p v-if="parent.details" class="text-sm text-muted-foreground">{{ parent.details }}</p>
                            <div v-if="parent.remarks" class="rounded-md bg-muted/50 p-3">
                                <p class="text-xs font-medium text-muted-foreground">Remarks · shown on pedigree</p>
                                <p class="mt-1 whitespace-pre-wrap text-sm">{{ parent.remarks }}</p>
                            </div>
                            <div v-if="parent.notes" class="rounded-md bg-muted/50 p-3">
                                <p class="text-xs font-medium text-muted-foreground">Notes · private</p>
                                <p class="mt-1 whitespace-pre-wrap text-sm">{{ parent.notes }}</p>
                            </div>
                            <div v-if="parent.recordNotes" class="rounded-md bg-muted/50 p-3">
                                <p class="text-xs font-medium text-muted-foreground">Notes on this pigeon's record</p>
                                <p class="mt-1 whitespace-pre-wrap text-sm">{{ parent.recordNotes }}</p>
                            </div>
                        </div>

                        <p v-if="parents.length === 0" class="text-sm text-muted-foreground">No parent information available</p>

                        <!-- Photos of relatives uploaded on this bird -->
                        <div v-if="relativePhotos.length" class="space-y-2 border-t pt-4">
                            <p class="text-sm font-semibold text-muted-foreground">Photos of relatives</p>
                            <div class="grid grid-cols-3 gap-3">
                                <a v-for="photo in relativePhotos" :key="photo.url" :href="photo.url" target="_blank" class="group block min-w-0">
                                    <div class="aspect-square overflow-hidden rounded-md border bg-muted">
                                        <img :src="photo.url" :alt="`${pigeonLabel} ${photo.title}`" loading="lazy" class="size-full object-cover transition-transform group-hover:scale-105" />
                                    </div>
                                    <p class="mt-1 truncate text-xs font-medium">{{ photo.title }}</p>
                                    <p v-if="photo.hint" class="truncate text-xs text-muted-foreground">{{ photo.hint }}</p>
                                    <p class="flex items-center gap-1 text-[11px]" :class="photo.public ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground'">
                                        <Eye v-if="photo.public" class="size-3 shrink-0" />
                                        <Lock v-else class="size-3 shrink-0" />
                                        {{ photo.public ? 'On pedigree' : 'Private' }}
                                    </p>
                                </a>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Merits -->
                <PigeonMerits
                    class="lg:col-span-2"
                    :pigeon-id="pigeon.id"
                    :merits="pigeon.merits"
                    :merit-types="meritTypes"
                    :merit-levels="meritLevels"
                    :suggestions="meritSuggestions"
                />

                <!-- Offspring -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <CardTitle>Offspring</CardTitle>
                                <Badge v-if="children.length > 0" variant="secondary">{{ children.length }}</Badge>
                            </div>
                            <Link
                                v-if="children.length > 0"
                                :href="lineageIndex({ query: { pigeon: pigeon.id } }).url"
                                class="text-sm text-primary hover:underline"
                            >
                                Grandchildren & more
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="children.length > 0" class="divide-y rounded-lg border">
                            <div
                                v-for="child in children"
                                :key="child.id"
                                class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="min-w-0 space-y-0.5">
                                    <Link :href="showRoute({ pigeon: child.id }).url" class="font-medium text-primary hover:underline">
                                        {{ childLabel(child) }}
                                    </Link>
                                    <p v-if="childDetails(child)" class="text-sm text-muted-foreground">{{ childDetails(child) }}</p>
                                    <p v-if="child.mate" class="text-sm text-muted-foreground">
                                        {{ child.mate.side === 'sire' ? 'Sire' : 'Dam' }}:
                                        <Link v-if="child.mate.id" :href="showRoute({ pigeon: child.mate.id }).url" class="text-primary hover:underline">
                                            {{ mateLabel(child.mate) }}
                                        </Link>
                                        <template v-else>{{ mateLabel(child.mate) }}</template>
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center gap-2">
                                    <Badge v-if="genderName(child.gender)" variant="outline">{{ genderName(child.gender) }}</Badge>
                                    <span
                                        :class="[statusStyle(child.status).className, 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium']"
                                    >
                                        {{ statusStyle(child.status).label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">No offspring recorded for this pigeon</p>
                    </CardContent>
                </Card>

                <!-- Additional Info -->
                <Card v-if="pigeon.remarks || pigeon.notes" class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Additional Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="pigeon.remarks">
                            <p class="text-sm text-muted-foreground">Remarks · shown on pedigree</p>
                            <p class="whitespace-pre-wrap">{{ pigeon.remarks }}</p>
                        </div>
                        <div v-if="pigeon.notes">
                            <p class="text-sm text-muted-foreground">Notes · private</p>
                            <p class="whitespace-pre-wrap">{{ pigeon.notes }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Status history -->
                <Card v-if="statusHistory.length" class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Status history</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol class="space-y-3">
                            <li v-for="change in statusHistory" :key="change.id" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                <span class="w-full text-muted-foreground sm:w-48">{{ formatCheckDate(change.changed_at) }}</span>
                                <span
                                    v-if="change.from_status"
                                    :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusStyle(change.from_status).className]"
                                >
                                    {{ statusStyle(change.from_status).label }}
                                </span>
                                <span v-if="change.from_status" class="text-muted-foreground" aria-label="to">→</span>
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusStyle(change.to_status).className]">
                                    {{ statusStyle(change.to_status).label }}
                                </span>
                                <Link
                                    v-if="change.loft_check"
                                    :href="loftCheckShow({ loftCheck: change.loft_check.id }).url"
                                    class="text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                >
                                    Loft check: {{ change.loft_check.label }}
                                </Link>
                            </li>
                        </ol>
                    </CardContent>
                </Card>

                <!-- Pedigree -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-2">
                            <CardTitle>Pedigree</CardTitle>
                            <Badge v-if="pigeon.pedigree" :variant="pigeon.pedigree.visibility === 'private' ? 'secondary' : 'default'">
                                {{ { public: 'Public', password: 'Password', private: 'Private' }[pigeon.pedigree.visibility] }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="flex flex-col gap-3 rounded-lg border border-dashed p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium">5-generation pedigree chart</p>
                                <p class="text-sm text-muted-foreground">
                                    <template v-if="$page.props.auth.premium">View it with a photo showcase of the bird and its ancestors, save it as an image or print it, and share it with a QR code.</template>
                                    <template v-else>View it with a photo showcase of the bird and its ancestors and print it. With Premium, also save it as an image and share it with a QR code.</template>
                                    <template v-if="pigeon.pedigree">No. <span class="font-mono">{{ pigeon.pedigree.serial }}</span></template>
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <Link :href="editPedigree({ pigeon: pigeon.id }).url">
                                    <Button variant="outline">
                                        <Pencil class="mr-2 h-4 w-4" />
                                        Edit Pedigree
                                    </Button>
                                </Link>
                                <Link :href="pedigreeRoute({ pigeon: pigeon.id }).url">
                                    <Button>
                                        <Network class="mr-2 h-4 w-4" />
                                        View Pedigree
                                    </Button>
                                </Link>
                            </div>
                        </div>

                        <!-- Certificates and documents (photos of relatives are under Parents) -->
                        <div v-if="certificates.length > 0">
                            <p class="text-sm font-medium text-muted-foreground">Certificates &amp; documents</p>
                            <p class="mb-2 text-xs text-muted-foreground">
                                On the
                                <Link :href="edit({ pigeon: pigeon.id }).url" class="underline underline-offset-2">edit page</Link>,
                                tick Show on pedigree to put one on the pedigree, or say who a photo of a relative shows.
                            </p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div v-for="(image, index) in certificates" :key="image" class="space-y-1">
                                    <a :href="image" target="_blank" class="block overflow-hidden rounded-lg border">
                                        <img :src="image" :alt="`${pigeonLabel} certificate ${index + 1}`" class="w-full object-contain transition-transform hover:scale-105" />
                                    </a>
                                    <p class="flex items-center gap-1 text-xs" :class="isPublic(image) ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground'">
                                        <Eye v-if="isPublic(image)" class="size-3.5 shrink-0" />
                                        <Lock v-else class="size-3.5 shrink-0" />
                                        {{ isPublic(image) ? 'Shown on the pedigree' : 'Private, only you see it' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
