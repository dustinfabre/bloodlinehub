<script setup lang="ts">
import Pager from '@/components/Pager.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useToast } from '@/composables/useToast';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import { FEEDBACK_CATEGORIES, formatDateTime } from '@/lib/billing';
import { cn } from '@/lib/utils';
import { destroy, index, screenshot, update } from '@/routes/admin/inbox';
import type { FeedbackCategory, Paginated } from '@/types/billing';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    ArchiveRestore,
    Bug,
    CircleHelp,
    ExternalLink,
    Inbox,
    Lightbulb,
    Mail,
    MessageSquare,
    Paperclip,
    Reply,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Message {
    id: number;
    category: FeedbackCategory;
    message: string;
    page_url: string | null;
    user_agent: string | null;
    has_screenshot: boolean;
    read: boolean;
    archived: boolean;
    created_at: string;
    user: { id: number; name: string; email: string; premium: boolean } | null;
}

type Folder = 'inbox' | 'unread' | 'archived';

const props = defineProps<{
    messages: Paginated<Message>;
    filters: { folder: Folder; category: FeedbackCategory | null };
    counts: Record<Folder, number>;
}>();

const { success, error } = useToast();

const folders: { value: Folder; label: string }[] = [
    { value: 'inbox', label: 'Inbox' },
    { value: 'unread', label: 'Unread' },
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

const folderUrl = (folder: Folder, category: FeedbackCategory | null) =>
    index({
        query: { folder, ...(category ? { category } : {}) },
    }).url;

const pillClass = (active: boolean) =>
    cn(
        'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition-colors',
        active
            ? 'border-foreground bg-foreground text-background'
            : 'border-border text-muted-foreground hover:bg-muted hover:text-foreground',
    );

// The open message. A copy, so it stays open when marking it read takes it out of the Unread folder.
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
        if (fresh) selected.value = { ...fresh };
    },
);

const patch = (
    message: Message,
    data: { read?: boolean; archived?: boolean },
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
    if (!message.read) {
        selected.value.read = true;
        patch(message, { read: true });
    }
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

const replyUrl = (message: Message) =>
    message.user
        ? `mailto:${message.user.email}?subject=${encodeURIComponent('Re: your BloodlineHub feedback')}`
        : null;

const isAppPath = (url: string | null) =>
    !!url && url.startsWith('/') && !url.startsWith('//');

const emptyMessage = computed(
    () =>
        ({
            inbox: 'No messages in the inbox.',
            unread: 'Everything has been read.',
            archived: 'Nothing archived.',
        })[props.filters.folder],
);
</script>

<template>
    <Head title="Inbox · Admin" />

    <AdminLayout
        title="Inbox"
        description="Feedback from the feedback page. Reply by email; archive what is dealt with."
    >
        <div class="space-y-5">
            <div class="flex flex-col gap-3">
                <nav class="flex flex-wrap gap-1.5" aria-label="Folders">
                    <Link
                        v-for="folder in folders"
                        :key="folder.value"
                        :href="folderUrl(folder.value, filters.category)"
                        preserve-scroll
                        :aria-current="
                            filters.folder === folder.value ? 'page' : undefined
                        "
                        :class="pillClass(filters.folder === folder.value)"
                    >
                        {{ folder.label }}
                        <span class="tabular-nums opacity-70">{{
                            counts[folder.value]
                        }}</span>
                    </Link>
                </nav>
                <nav class="flex flex-wrap gap-1.5" aria-label="Category">
                    <Link
                        :href="folderUrl(filters.folder, null)"
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
                        :href="folderUrl(filters.folder, value)"
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
                </nav>
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
                <li v-for="message in messages.data" :key="message.id">
                    <button
                        type="button"
                        class="flex w-full items-start gap-3 px-4 py-3.5 text-left transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
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
                                    class="text-muted-foreground"
                                >
                                    {{
                                        FEEDBACK_CATEGORIES[message.category]
                                            .hint
                                    }}
                                </Badge>
                                <Badge
                                    v-if="message.user?.premium"
                                    variant="secondary"
                                    >Premium</Badge
                                >
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
