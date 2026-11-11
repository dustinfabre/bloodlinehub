<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Pager from '@/components/Pager.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import { FEEDBACK_CATEGORIES, formatDate, formatDateTime } from '@/lib/billing';
import {
    labelClass,
    labelSwatch,
    NOTIFIABLE_STATUSES,
    statusClass,
    type FeedbackLabel,
    type StatusMap,
} from '@/lib/inbox';
import { cn } from '@/lib/utils';
import {
    destroy,
    duplicate as duplicateRoute,
    index,
    lookup,
    screenshot,
    status as statusRoute,
    update,
} from '@/routes/admin/inbox';
import {
    destroy as destroyLabel,
    store as storeLabel,
    update as updateLabel,
} from '@/routes/admin/inbox/labels';
import type { FeedbackCategory, Paginated } from '@/types/billing';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import {
    Archive,
    ArchiveRestore,
    Bug,
    CircleHelp,
    Copy,
    ExternalLink,
    Inbox,
    Lightbulb,
    Mail,
    MessageSquare,
    Paperclip,
    Reply,
    Search,
    Star,
    StickyNote,
    Tags,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Message {
    id: number;
    category: FeedbackCategory;
    status: string;
    status_changed_at: string | null;
    message: string;
    page_url: string | null;
    user_agent: string | null;
    has_screenshot: boolean;
    read: boolean;
    archived: boolean;
    starred: boolean;
    admin_note: string | null;
    labels: number[];
    // This sender plus everyone marked "same as" this message
    requests: number;
    also_asked: { id: number; name: string; created_at: string }[];
    duplicate_of: { id: number; message: string; user: string | null } | null;
    created_at: string;
    user: { id: number; name: string; email: string; premium: boolean } | null;
}

type Folder = 'inbox' | 'unread' | 'starred' | 'archived';

interface Filters {
    folder: Folder;
    category: FeedbackCategory | null;
    // "open", "closed" or one of the category's statuses
    status: string | null;
    label: number | null;
    q: string;
    sort: 'newest' | 'requested';
}

const props = defineProps<{
    messages: Paginated<Message>;
    filters: Filters;
    counts: Record<Folder, number>;
    labels: FeedbackLabel[];
    statuses: StatusMap;
    closedStatuses: string[];
    labelColors: string[];
}>();

const { success, error } = useToast();

const folders: { value: Folder; label: string }[] = [
    { value: 'inbox', label: 'Inbox' },
    { value: 'unread', label: 'Unread' },
    { value: 'starred', label: 'Starred' },
    { value: 'archived', label: 'Archived' },
];

const categoryIcons = {
    bug: Bug,
    idea: Lightbulb,
    question: CircleHelp,
    other: MessageSquare,
} as const;

const categories = Object.entries(FEEDBACK_CATEGORIES) as [
    FeedbackCategory,
    { label: string; hint: string },
][];

const labelById = computed(
    () => new Map(props.labels.map((label) => [label.id, label])),
);

const statusLabel = (message: Pick<Message, 'category' | 'status'>) =>
    props.statuses[message.category]?.[message.status] ?? message.status;

// Filters live in the URL
const queryFor = (changes: Partial<Filters>) => {
    const next = { ...props.filters, ...changes };
    // A category's own statuses don't carry over to another category
    if (
        'category' in changes &&
        next.status &&
        !['open', 'closed'].includes(next.status)
    ) {
        next.status = null;
    }
    return {
        folder: next.folder,
        ...(next.category ? { category: next.category } : {}),
        ...(next.status ? { status: next.status } : {}),
        ...(next.label ? { label: next.label } : {}),
        ...(next.q ? { q: next.q } : {}),
        ...(next.sort === 'requested' ? { sort: next.sort } : {}),
    };
};

const urlFor = (changes: Partial<Filters>) =>
    index({ query: queryFor(changes) }).url;

const go = (changes: Partial<Filters>) =>
    router.get(
        urlFor(changes),
        {},
        { preserveState: true, preserveScroll: true },
    );

const pillClass = (active: boolean) =>
    cn(
        'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition-colors',
        active
            ? 'border-foreground bg-foreground text-background'
            : 'border-border text-muted-foreground hover:bg-muted hover:text-foreground',
    );

// Search
const search = ref(props.filters.q);
const applySearch = useDebounceFn(() => {
    if (search.value.trim() === props.filters.q) return;
    router.get(
        urlFor({ q: search.value.trim() }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 350);
watch(search, applySearch);

const statusFilter = computed({
    get: () => props.filters.status ?? 'any',
    set: (value: string) => go({ status: value === 'any' ? null : value }),
});

const sortFilter = computed({
    get: () => props.filters.sort,
    set: (value: string) => go({ sort: value as Filters['sort'] }),
});

// The open message. A copy, so it stays open when a change takes it out of the current folder.
const selected = ref<Message | null>(null);
const showMessage = computed({
    get: () => selected.value !== null,
    set: (open: boolean) => {
        if (!open) selected.value = null;
    },
});

watch(
    () => props.messages.data,
    (messages) => {
        if (!selected.value) return;
        const fresh = messages.find((m) => m.id === selected.value?.id);
        if (fresh) {
            selected.value = { ...fresh };
            note.value = fresh.admin_note ?? '';
        }
    },
);

const patch = (
    message: Message,
    data: Record<string, unknown>,
    done?: string,
) => {
    router.patch(update(message.id).url, data, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (done) success(done);
        },
        onError: () => error('Could not update this message.'),
    });
};

const open = (message: Message) => {
    selected.value = { ...message };
    note.value = message.admin_note ?? '';
    statusForm.status = message.status;
    statusForm.notify = false;
    statusForm.note = '';
    picking.value = false;
    if (!message.read) {
        selected.value.read = true;
        patch(message, { read: true });
    }
};

// Star
const toggleStar = (message: Message) => {
    if (selected.value?.id === message.id) {
        selected.value.starred = !message.starred;
    }
    patch(message, { starred: !message.starred });
};

const markUnread = () => {
    const message = selected.value;
    if (!message) return;
    selected.value = null;
    patch(message, { read: false }, 'Marked unread.');
};

const toggleArchive = () => {
    const message = selected.value;
    if (!message) return;
    selected.value = null;
    patch(
        message,
        { archived: !message.archived },
        message.archived ? 'Moved to the inbox.' : 'Archived.',
    );
};

// Status, with an optional email to everyone who sent it
const statusForm = useForm({ status: 'new', notify: false, note: '' });
const canNotify = computed(() =>
    NOTIFIABLE_STATUSES.includes(statusForm.status),
);
watch(canNotify, (can) => {
    if (!can) statusForm.notify = false;
});

const saveStatus = () => {
    const message = selected.value;
    if (!message) return;
    const notified = statusForm.notify;
    statusForm.post(statusRoute(message.id).url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            success(
                notified
                    ? `Status saved. ${message.requests > 1 ? `${message.requests} senders` : 'The sender'} will be emailed.`
                    : 'Status saved.',
            );
            statusForm.notify = false;
            statusForm.note = '';
        },
    });
};

// Labels on the open message
const toggleLabel = (label: FeedbackLabel) => {
    const message = selected.value;
    if (!message) return;
    const labels = message.labels.includes(label.id)
        ? message.labels.filter((id) => id !== label.id)
        : [...message.labels, label.id];
    message.labels = labels;
    patch(message, { labels });
};

// Private note
const note = ref('');
const noteChanged = computed(
    () => (selected.value?.admin_note ?? '') !== note.value,
);
const saveNote = () => {
    const message = selected.value;
    if (!message) return;
    patch(message, { admin_note: note.value }, 'Note saved.');
};

// "Same as…" an earlier message
interface LookupResult {
    id: number;
    category: FeedbackCategory;
    message: string;
    user: string | null;
    requests: number;
    created_at: string;
}

const picking = ref(false);
const lookupQuery = ref('');
const lookupResults = ref<LookupResult[]>([]);
const lookingUp = ref(false);

const runLookup = async () => {
    const message = selected.value;
    if (!message) return;
    lookingUp.value = true;
    try {
        const { data } = await axios.get(
            lookup({
                query: {
                    q: lookupQuery.value.trim(),
                    exclude: message.id,
                    category: message.category,
                },
            }).url,
        );
        lookupResults.value = data.results;
    } catch {
        error('Could not search the messages.');
    } finally {
        lookingUp.value = false;
    }
};
const lookupSoon = useDebounceFn(runLookup, 300);
watch(lookupQuery, lookupSoon);

const startPicking = () => {
    picking.value = true;
    lookupQuery.value = '';
    runLookup();
};

const markDuplicate = (target: LookupResult | null) => {
    const message = selected.value;
    if (!message) return;
    router.post(
        duplicateRoute(message.id).url,
        { duplicate_of: target?.id ?? null },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                picking.value = false;
                success(
                    target
                        ? `Marked as the same as #${target.id} and archived.`
                        : 'No longer marked as the same.',
                );
                if (target) selected.value = null;
            },
            onError: (errors) =>
                error(errors.duplicate_of ?? 'Could not mark this message.'),
        },
    );
};

// Open another message: from this page if it's here, otherwise by searching the inbox for its number
const openById = (id: number) => {
    const found = props.messages.data.find((m) => m.id === id);
    if (found) {
        open(found);
        return;
    }
    selected.value = null;
    search.value = `#${id}`;
    router.get(
        urlFor({
            folder: 'inbox',
            category: null,
            status: null,
            label: null,
            q: `#${id}`,
        }),
    );
};

// Delete
const deleting = ref<Message | null>(null);
const showDelete = computed({
    get: () => deleting.value !== null,
    set: (open: boolean) => {
        if (!open) deleting.value = null;
    },
});

const askDelete = () => {
    deleting.value = selected.value;
    selected.value = null;
};

const confirmDelete = () => {
    const message = deleting.value;
    if (!message) return;
    router.delete(destroy(message.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
            success('Message deleted.');
        },
        onError: () => error('Could not delete this message.'),
    });
};

// Manage labels
const showLabels = ref(false);
const newLabel = useForm({ name: '', color: 'blue' });
const addLabel = () =>
    newLabel.post(storeLabel().url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            newLabel.reset('name');
            success('Label added.');
        },
    });

const editingLabel = ref<number | null>(null);
const labelEdit = useForm({ name: '', color: 'gray' });
const startEditLabel = (label: FeedbackLabel) => {
    editingLabel.value = label.id;
    labelEdit.name = label.name;
    labelEdit.color = label.color;
    labelEdit.clearErrors();
};
const saveLabel = () => {
    if (editingLabel.value === null) return;
    labelEdit.patch(updateLabel(editingLabel.value).url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editingLabel.value = null;
            success('Label saved.');
        },
    });
};
const removeLabel = (label: FeedbackLabel) => {
    if (
        !window.confirm(
            `Delete the label "${label.name}"? It comes off ${label.feedback_count ?? 0} messages; the messages stay.`,
        )
    )
        return;
    router.delete(destroyLabel(label.id).url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => success('Label deleted.'),
    });
};

const replyUrl = (message: Message) =>
    message.user
        ? `mailto:${message.user.email}?subject=${encodeURIComponent('Re: your BloodlineHub feedback')}`
        : null;

const isAppPath = (url: string | null) =>
    !!url && url.startsWith('/') && !url.startsWith('//');

const hasFilters = computed(
    () =>
        !!props.filters.category ||
        !!props.filters.status ||
        !!props.filters.label ||
        !!props.filters.q,
);

const emptyMessage = computed(() =>
    hasFilters.value
        ? 'No messages match these filters.'
        : {
              inbox: 'No messages in the inbox.',
              unread: 'Everything has been read.',
              starred: 'Star a message to find it here later.',
              archived: 'Nothing archived.',
          }[props.filters.folder],
);
</script>

<template>
    <Head title="Inbox · Admin" />

    <AdminLayout
        title="Inbox"
        description="Feedback from the feedback page. Star it, label it, move it along, and tell the sender when it's done."
    >
        <div class="space-y-5">
            <!-- Folders, search and labels -->
            <div class="flex flex-col gap-3">
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <nav class="flex flex-wrap gap-1.5" aria-label="Folders">
                        <Link
                            v-for="folder in folders"
                            :key="folder.value"
                            :href="urlFor({ folder: folder.value })"
                            preserve-scroll
                            :aria-current="
                                filters.folder === folder.value
                                    ? 'page'
                                    : undefined
                            "
                            :class="pillClass(filters.folder === folder.value)"
                        >
                            <Star
                                v-if="folder.value === 'starred'"
                                class="size-3.5"
                            />
                            {{ folder.label }}
                            <span class="tabular-nums opacity-70">{{
                                counts[folder.value]
                            }}</span>
                        </Link>
                    </nav>
                    <div class="relative lg:w-72">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            class="pl-9"
                            placeholder="Search text, sender or #number"
                            aria-label="Search messages"
                        />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <Link
                        :href="urlFor({ category: null })"
                        preserve-scroll
                        :class="
                            cn(
                                pillClass(filters.category === null),
                                'px-2.5 py-1 text-xs',
                            )
                        "
                    >
                        All types
                    </Link>
                    <Link
                        v-for="[value, category] in categories"
                        :key="value"
                        :href="urlFor({ category: value })"
                        preserve-scroll
                        :class="
                            cn(
                                pillClass(filters.category === value),
                                'px-2.5 py-1 text-xs',
                            )
                        "
                    >
                        <component
                            :is="categoryIcons[value]"
                            class="size-3.5"
                        />
                        {{ category.hint }}
                    </Link>

                    <div class="ml-auto flex flex-wrap gap-2">
                        <Select v-model="statusFilter">
                            <SelectTrigger
                                class="h-8 w-40 text-xs"
                                aria-label="Status"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="any">Any status</SelectItem>
                                <SelectItem value="open"
                                    >Open (not done)</SelectItem
                                >
                                <SelectItem value="closed"
                                    >Closed (done)</SelectItem
                                >
                                <template v-if="filters.category">
                                    <SelectItem
                                        v-for="(label, key) in statuses[
                                            filters.category
                                        ]"
                                        :key="key"
                                        :value="String(key)"
                                        >{{ label }}</SelectItem
                                    >
                                </template>
                            </SelectContent>
                        </Select>
                        <Select v-model="sortFilter">
                            <SelectTrigger
                                class="h-8 w-40 text-xs"
                                aria-label="Sort"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="newest"
                                    >Newest first</SelectItem
                                >
                                <SelectItem value="requested"
                                    >Most requested</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        v-for="label in labels"
                        :key="label.id"
                        type="button"
                        :class="
                            cn(
                                'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium transition-opacity',
                                labelClass(label.color),
                                filters.label && filters.label !== label.id
                                    ? 'opacity-50 hover:opacity-100'
                                    : '',
                                filters.label === label.id
                                    ? 'ring-2 ring-ring/50'
                                    : '',
                            )
                        "
                        :aria-pressed="filters.label === label.id"
                        @click="
                            go({
                                label:
                                    filters.label === label.id
                                        ? null
                                        : label.id,
                            })
                        "
                    >
                        {{ label.name }}
                        <X v-if="filters.label === label.id" class="size-3" />
                    </button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 text-xs"
                        @click="showLabels = true"
                    >
                        <Tags class="size-3.5" />
                        {{ labels.length ? 'Manage labels' : 'Add labels' }}
                    </Button>
                </div>
            </div>

            <div
                v-if="messages.data.length === 0"
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border py-16 text-center"
            >
                <Inbox class="mb-3 size-10 text-muted-foreground/40" />
                <p class="text-muted-foreground">{{ emptyMessage }}</p>
            </div>

            <ul
                v-else
                class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
            >
                <li
                    v-for="message in messages.data"
                    :key="message.id"
                    class="flex items-start gap-1 pl-2 transition-colors hover:bg-muted/50"
                >
                    <button
                        type="button"
                        class="mt-2 flex size-9 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:text-amber-500 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :aria-label="message.starred ? 'Unstar' : 'Star'"
                        :aria-pressed="message.starred"
                        @click="toggleStar(message)"
                    >
                        <Star
                            :class="
                                cn(
                                    'size-4',
                                    message.starred &&
                                        'fill-amber-400 text-amber-500',
                                )
                            "
                        />
                    </button>
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 items-start gap-3 py-3.5 pr-4 text-left focus-visible:outline-none"
                        @click="open(message)"
                    >
                        <span
                            class="mt-1.5 flex size-2 shrink-0 items-center justify-center"
                        >
                            <span
                                v-if="!message.read"
                                class="size-2 rounded-full bg-primary"
                                aria-label="Unread"
                            />
                        </span>
                        <component
                            :is="categoryIcons[message.category]"
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <span class="min-w-0 flex-1">
                            <span
                                class="flex items-baseline justify-between gap-3"
                            >
                                <span
                                    :class="[
                                        'truncate',
                                        message.read
                                            ? 'text-foreground/80'
                                            : 'font-semibold text-foreground',
                                    ]"
                                    >{{
                                        message.user?.name ?? 'Deleted account'
                                    }}</span
                                >
                                <span
                                    class="shrink-0 text-xs text-muted-foreground"
                                >
                                    #{{ message.id }} ·
                                    {{ formatDateTime(message.created_at) }}
                                </span>
                            </span>
                            <span
                                :class="[
                                    'mt-0.5 line-clamp-2 text-sm wrap-break-word',
                                    message.read
                                        ? 'text-muted-foreground'
                                        : 'text-foreground',
                                ]"
                                >{{ message.message }}</span
                            >
                            <span
                                class="mt-1.5 flex flex-wrap items-center gap-1.5"
                            >
                                <Badge
                                    variant="outline"
                                    :class="statusClass(message.status)"
                                >
                                    {{ statusLabel(message) }}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    class="text-muted-foreground"
                                >
                                    {{
                                        FEEDBACK_CATEGORIES[message.category]
                                            .hint
                                    }}
                                </Badge>
                                <Badge
                                    v-if="message.requests > 1"
                                    variant="outline"
                                    class="border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300"
                                >
                                    <Users class="size-3" />
                                    {{ message.requests }} asked
                                </Badge>
                                <Badge
                                    v-if="message.duplicate_of"
                                    variant="outline"
                                    class="text-muted-foreground"
                                >
                                    <Copy class="size-3" />
                                    Same as #{{ message.duplicate_of.id }}
                                </Badge>
                                <span
                                    v-for="id in message.labels"
                                    :key="id"
                                    :class="
                                        cn(
                                            'rounded-full border px-2 py-0.5 text-[11px] font-medium',
                                            labelClass(
                                                labelById.get(id)?.color ??
                                                    'gray',
                                            ),
                                        )
                                    "
                                    >{{ labelById.get(id)?.name }}</span
                                >
                                <Badge
                                    v-if="message.user?.premium"
                                    variant="secondary"
                                    >Premium</Badge
                                >
                                <span
                                    v-if="message.admin_note"
                                    class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                                    title="Has a private note"
                                >
                                    <StickyNote class="size-3" /> Note
                                </span>
                                <span
                                    v-if="message.has_screenshot"
                                    class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                                >
                                    <Paperclip class="size-3" /> Screenshot
                                </span>
                            </span>
                        </span>
                    </button>
                </li>
            </ul>

            <Pager :page="messages" noun="messages" />
        </div>

        <!-- Message -->
        <Dialog v-model:open="showMessage">
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                <template v-if="selected">
                    <DialogHeader>
                        <DialogTitle class="flex flex-wrap items-center gap-2">
                            <component
                                :is="categoryIcons[selected.category]"
                                class="size-4"
                            />
                            {{ FEEDBACK_CATEGORIES[selected.category].label }}
                            <span
                                class="text-sm font-normal text-muted-foreground"
                                >#{{ selected.id }}</span
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                class="ml-auto h-8"
                                :aria-pressed="selected.starred"
                                @click="toggleStar(selected)"
                            >
                                <Star
                                    :class="
                                        cn(
                                            'size-4',
                                            selected.starred &&
                                                'fill-amber-400 text-amber-500',
                                        )
                                    "
                                />
                                {{ selected.starred ? 'Starred' : 'Star' }}
                            </Button>
                        </DialogTitle>
                        <DialogDescription
                            class="flex flex-wrap items-center gap-x-2 gap-y-1"
                        >
                            <template v-if="selected.user">
                                <span class="font-medium text-foreground">{{
                                    selected.user.name
                                }}</span>
                                <a
                                    :href="`mailto:${selected.user.email}`"
                                    class="break-all hover:text-foreground hover:underline"
                                    >{{ selected.user.email }}</a
                                >
                                <Badge
                                    v-if="selected.user.premium"
                                    variant="secondary"
                                    >Premium</Badge
                                >
                            </template>
                            <span v-else>Deleted account</span>
                            <span
                                >·
                                {{ formatDateTime(selected.created_at) }}</span
                            >
                        </DialogDescription>
                    </DialogHeader>

                    <!-- A repeat of an earlier message -->
                    <div
                        v-if="selected.duplicate_of"
                        class="flex flex-col gap-2 rounded-lg border border-border bg-muted/50 p-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="min-w-0">
                            <span class="font-medium"
                                >Same as #{{ selected.duplicate_of.id }}</span
                            >
                            <span class="text-muted-foreground">
                                · {{ selected.duplicate_of.user }}: "{{
                                    selected.duplicate_of.message
                                }}"</span
                            >
                        </p>
                        <div class="flex shrink-0 gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openById(selected.duplicate_of.id)"
                                >Open #{{ selected.duplicate_of.id }}</Button
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="markDuplicate(null)"
                                >Not the same</Button
                            >
                        </div>
                    </div>

                    <p
                        class="text-[15px] leading-relaxed wrap-break-word whitespace-pre-wrap"
                    >
                        {{ selected.message }}
                    </p>

                    <a
                        v-if="selected.has_screenshot"
                        :href="screenshot(selected.id).url"
                        target="_blank"
                        rel="noopener"
                        class="block overflow-hidden rounded-md border border-border bg-muted/40"
                    >
                        <img
                            :src="screenshot(selected.id).url"
                            alt="Screenshot sent with this feedback"
                            class="mx-auto max-h-[50vh] w-auto max-w-full object-contain"
                        />
                    </a>

                    <div class="space-y-1 text-xs text-muted-foreground">
                        <p
                            v-if="isAppPath(selected.page_url)"
                            class="flex min-w-0 items-center gap-1"
                        >
                            Sent from
                            <Link
                                :href="selected.page_url!"
                                class="inline-flex min-w-0 items-center gap-1 font-mono text-foreground hover:underline"
                            >
                                <span class="truncate">{{
                                    selected.page_url
                                }}</span>
                                <ExternalLink class="size-3 shrink-0" />
                            </Link>
                        </p>
                        <p v-if="selected.user_agent" class="break-all">
                            {{ selected.user_agent }}
                        </p>
                    </div>

                    <!-- Everyone who asked for the same -->
                    <div
                        v-if="selected.also_asked.length"
                        class="rounded-lg border border-violet-500/30 bg-violet-500/5 p-3 text-sm"
                    >
                        <p class="flex items-center gap-2 font-medium">
                            <Users class="size-4" />
                            {{ selected.requests }} people asked for this
                        </p>
                        <ul class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1">
                            <li
                                v-for="repeat in selected.also_asked"
                                :key="repeat.id"
                                class="text-muted-foreground"
                            >
                                {{ repeat.name }} ·
                                {{ formatDate(repeat.created_at) }}
                            </li>
                        </ul>
                    </div>

                    <!-- Status -->
                    <section
                        class="space-y-3 rounded-lg border border-border p-4"
                        aria-label="Status"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-end"
                        >
                            <div class="grid flex-1 gap-2">
                                <Label for="message-status">Status</Label>
                                <Select v-model="statusForm.status">
                                    <SelectTrigger
                                        id="message-status"
                                        class="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="(label, key) in statuses[
                                                selected.category
                                            ]"
                                            :key="key"
                                            :value="String(key)"
                                            >{{ label }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                :disabled="
                                    statusForm.processing ||
                                    (statusForm.status === selected.status &&
                                        !statusForm.notify)
                                "
                                @click="saveStatus"
                            >
                                <Spinner v-if="statusForm.processing" />
                                {{
                                    statusForm.notify
                                        ? 'Save and email'
                                        : 'Save status'
                                }}
                            </Button>
                        </div>
                        <Label
                            v-if="canNotify && selected.user"
                            for="status-notify"
                            class="flex items-start gap-2 font-normal"
                        >
                            <Checkbox
                                id="status-notify"
                                v-model="statusForm.notify"
                                class="mt-0.5"
                            />
                            <span>
                                Email
                                {{
                                    selected.requests > 1
                                        ? `all ${selected.requests} senders`
                                        : 'the sender'
                                }}
                                that it's
                                {{
                                    statuses[selected.category][
                                        statusForm.status
                                    ]?.toLowerCase()
                                }}
                            </span>
                        </Label>
                        <div v-if="statusForm.notify" class="grid gap-2">
                            <Label for="status-note"
                                >Add a message
                                <span class="font-normal text-muted-foreground"
                                    >(optional, in the email)</span
                                ></Label
                            >
                            <Textarea
                                id="status-note"
                                v-model="statusForm.note"
                                rows="3"
                                placeholder="e.g. It's in today's update. Refresh the app to get it."
                            />
                        </div>
                        <InputError
                            :message="
                                statusForm.errors.status ??
                                statusForm.errors.notify ??
                                statusForm.errors.note
                            "
                        />
                        <p
                            v-if="selected.status_changed_at"
                            class="text-xs text-muted-foreground"
                        >
                            {{ statusLabel(selected) }} since
                            {{ formatDateTime(selected.status_changed_at) }}
                        </p>
                    </section>

                    <!-- Labels -->
                    <section class="space-y-2" aria-label="Labels">
                        <p class="text-sm font-medium">Labels</p>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button
                                v-for="label in labels"
                                :key="label.id"
                                type="button"
                                :class="
                                    cn(
                                        'rounded-full border px-2.5 py-0.5 text-xs font-medium transition-opacity',
                                        labelClass(label.color),
                                        selected.labels.includes(label.id)
                                            ? 'ring-2 ring-ring/50'
                                            : 'opacity-50 hover:opacity-100',
                                    )
                                "
                                :aria-pressed="
                                    selected.labels.includes(label.id)
                                "
                                @click="toggleLabel(label)"
                            >
                                {{ label.name }}
                            </button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 text-xs"
                                @click="showLabels = true"
                            >
                                <Tags class="size-3.5" />
                                {{
                                    labels.length
                                        ? 'Edit labels'
                                        : 'Add a label'
                                }}
                            </Button>
                        </div>
                    </section>

                    <!-- Private note -->
                    <section class="space-y-2" aria-label="Private note">
                        <Label for="admin-note">Private note</Label>
                        <Textarea
                            id="admin-note"
                            v-model="note"
                            rows="2"
                            placeholder="Only admins see this, e.g. fixed in today's update"
                        />
                        <Button
                            v-if="noteChanged"
                            size="sm"
                            variant="outline"
                            @click="saveNote"
                            >Save note</Button
                        >
                    </section>

                    <!-- Same as an earlier message -->
                    <section
                        v-if="!selected.duplicate_of"
                        class="space-y-2"
                        aria-label="Same as another message"
                    >
                        <Button
                            v-if="!picking"
                            variant="outline"
                            size="sm"
                            @click="startPicking"
                        >
                            <Copy class="size-4" /> Same as another message…
                        </Button>
                        <div
                            v-else
                            class="space-y-2 rounded-lg border border-border p-3"
                        >
                            <div class="flex items-center gap-2">
                                <Input
                                    v-model="lookupQuery"
                                    type="search"
                                    placeholder="Search earlier messages or #number"
                                    aria-label="Find the earlier message"
                                />
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="picking = false"
                                    >Cancel</Button
                                >
                            </div>
                            <p class="text-xs text-muted-foreground">
                                This message is archived and counted as one more
                                request on the one you pick.
                            </p>
                            <div
                                v-if="lookingUp"
                                class="flex justify-center py-3"
                            >
                                <Spinner />
                            </div>
                            <p
                                v-else-if="lookupResults.length === 0"
                                class="py-2 text-sm text-muted-foreground"
                            >
                                No earlier
                                {{
                                    FEEDBACK_CATEGORIES[
                                        selected.category
                                    ].hint.toLowerCase()
                                }}
                                messages match.
                            </p>
                            <ul
                                v-else
                                class="max-h-64 space-y-1 overflow-y-auto"
                            >
                                <li
                                    v-for="result in lookupResults"
                                    :key="result.id"
                                >
                                    <button
                                        type="button"
                                        class="w-full rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent"
                                        @click="markDuplicate(result)"
                                    >
                                        <span class="font-medium"
                                            >#{{ result.id }}</span
                                        >
                                        <span class="text-muted-foreground">
                                            ·
                                            {{
                                                result.user ?? 'Deleted account'
                                            }}
                                            ·
                                            {{ result.requests }}
                                            {{
                                                result.requests === 1
                                                    ? 'request'
                                                    : 'requests'
                                            }}</span
                                        >
                                        <span
                                            class="block truncate text-muted-foreground"
                                            >{{ result.message }}</span
                                        >
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <DialogFooter class="flex-wrap gap-2 sm:justify-between">
                        <div class="flex flex-wrap gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive"
                                @click="askDelete"
                            >
                                <Trash2 class="size-4" /> Delete
                            </Button>
                            <Button
                                v-if="selected.read"
                                variant="ghost"
                                size="sm"
                                @click="markUnread"
                            >
                                <Mail class="size-4" /> Mark unread
                            </Button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="toggleArchive"
                            >
                                <template v-if="selected.archived">
                                    <ArchiveRestore class="size-4" /> Move to
                                    inbox
                                </template>
                                <template v-else
                                    ><Archive class="size-4" />
                                    Archive</template
                                >
                            </Button>
                            <Button
                                v-if="replyUrl(selected)"
                                size="sm"
                                as-child
                            >
                                <a :href="replyUrl(selected)!"
                                    ><Reply class="size-4" /> Reply by email</a
                                >
                            </Button>
                        </div>
                    </DialogFooter>
                </template>
            </DialogContent>
        </Dialog>

        <!-- Labels -->
        <Dialog v-model:open="showLabels">
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Labels</DialogTitle>
                    <DialogDescription>
                        Group messages by part of the app or anything else, e.g.
                        pedigree, billing, mobile.
                    </DialogDescription>
                </DialogHeader>

                <ul v-if="labels.length" class="divide-y divide-border">
                    <li v-for="label in labels" :key="label.id" class="py-2.5">
                        <form
                            v-if="editingLabel === label.id"
                            class="space-y-2"
                            @submit.prevent="saveLabel"
                        >
                            <Input
                                v-model="labelEdit.name"
                                maxlength="40"
                                aria-label="Label name"
                                required
                            />
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="color in labelColors"
                                    :key="color"
                                    type="button"
                                    :class="
                                        cn(
                                            'size-6 rounded-full ring-offset-2 ring-offset-background',
                                            labelSwatch(color),
                                            labelEdit.color === color &&
                                                'ring-2 ring-ring',
                                        )
                                    "
                                    :aria-label="color"
                                    :aria-pressed="labelEdit.color === color"
                                    @click="labelEdit.color = color"
                                />
                            </div>
                            <InputError :message="labelEdit.errors.name" />
                            <div class="flex gap-2">
                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="labelEdit.processing"
                                    >Save</Button
                                >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="editingLabel = null"
                                    >Cancel</Button
                                >
                            </div>
                        </form>
                        <div v-else class="flex items-center gap-2">
                            <span
                                :class="
                                    cn(
                                        'rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                        labelClass(label.color),
                                    )
                                "
                                >{{ label.name }}</span
                            >
                            <span class="flex-1 text-xs text-muted-foreground"
                                >{{ label.feedback_count ?? 0 }}
                                {{
                                    label.feedback_count === 1
                                        ? 'message'
                                        : 'messages'
                                }}</span
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="startEditLabel(label)"
                                >Edit</Button
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive"
                                :aria-label="`Delete ${label.name}`"
                                @click="removeLabel(label)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </li>
                </ul>

                <form
                    class="space-y-2 border-t border-border pt-4"
                    @submit.prevent="addLabel"
                >
                    <Label for="new-label">New label</Label>
                    <div class="flex gap-2">
                        <Input
                            id="new-label"
                            v-model="newLabel.name"
                            maxlength="40"
                            placeholder="e.g. pedigree"
                            required
                        />
                        <Button type="submit" :disabled="newLabel.processing"
                            >Add</Button
                        >
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="color in labelColors"
                            :key="color"
                            type="button"
                            :class="
                                cn(
                                    'size-6 rounded-full ring-offset-2 ring-offset-background',
                                    labelSwatch(color),
                                    newLabel.color === color &&
                                        'ring-2 ring-ring',
                                )
                            "
                            :aria-label="color"
                            :aria-pressed="newLabel.color === color"
                            @click="newLabel.color = color"
                        />
                    </div>
                    <InputError :message="newLabel.errors.name" />
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete -->
        <Dialog v-model:open="showDelete">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete this message?</DialogTitle>
                    <DialogDescription>
                        The message and its screenshot are removed for good.
                        Archive it instead to keep it.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleting = null"
                        >Cancel</Button
                    >
                    <Button variant="destructive" @click="confirmDelete">
                        <Trash2 class="size-4" /> Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
