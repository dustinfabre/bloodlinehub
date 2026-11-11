<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { FEEDBACK_CATEGORIES, formatDate } from '@/lib/billing';
import { shrinkImage } from '@/lib/images';
import { cn } from '@/lib/utils';
import { create, store } from '@/routes/feedback';
import type { AppPageProps, BreadcrumbItem } from '@/types';
import type { FeedbackCategory } from '@/types/billing';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    Bug,
    HelpCircle,
    ImagePlus,
    Lightbulb,
    MessageCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    // The page the user came from, if the link passed it
    from: string | null;
    recent: {
        id: number;
        category: FeedbackCategory;
        message: string;
        seen: boolean;
        created_at: string;
    }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Feedback', href: create().url },
];

const page = usePage<AppPageProps>();
const { success } = useToast();

const icons = {
    bug: Bug,
    idea: Lightbulb,
    question: HelpCircle,
    other: MessageCircle,
};
const categories = Object.entries(FEEDBACK_CATEGORIES) as [
    FeedbackCategory,
    (typeof FEEDBACK_CATEGORIES)[FeedbackCategory],
][];

const form = useForm<{
    category: FeedbackCategory;
    message: string;
    page_url: string | null;
    screenshot: File | null;
}>({
    category: 'idea',
    message: '',
    page_url: props.from,
    screenshot: null,
});

const preview = ref<string | null>(null);
const preparing = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const pickScreenshot = async (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    preparing.value = true;
    try {
        form.screenshot = await shrinkImage(file);
    } finally {
        preparing.value = false;
    }
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = URL.createObjectURL(form.screenshot);
    form.clearErrors('screenshot');
};

const clearScreenshot = () => {
    form.screenshot = null;
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null;
    if (fileInput.value) fileInput.value.value = '';
};

const submit = () => {
    form.post(store().url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('message');
            clearScreenshot();
            success(
                `Thanks! We'll reply to ${page.props.auth.user.email} if we need to.`,
            );
        },
    });
};
</script>

<template>
    <Head title="Send feedback" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-8 p-4 sm:p-6">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Send feedback</h1>
                <p class="mt-1 text-muted-foreground">
                    Found a bug, missing a feature, or stuck on something? Tell
                    us. We read every message.
                </p>
            </div>

            <Card>
                <CardContent>
                    <form class="grid gap-6" @submit.prevent="submit">
                        <fieldset class="grid gap-2">
                            <legend class="mb-2 text-sm font-medium">
                                What is it about?
                            </legend>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    v-for="[value, category] in categories"
                                    :key="value"
                                    :class="
                                        cn(
                                            'flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition-colors has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50',
                                            form.category === value
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border hover:border-foreground/30',
                                        )
                                    "
                                >
                                    <input
                                        v-model="form.category"
                                        type="radio"
                                        name="category"
                                        class="sr-only"
                                        :value="value"
                                    />
                                    <component
                                        :is="icons[value]"
                                        class="size-4 shrink-0 text-muted-foreground"
                                    />
                                    {{ category.label }}
                                </label>
                            </div>
                            <InputError :message="form.errors.category" />
                        </fieldset>

                        <div class="grid gap-2">
                            <Label for="message">Your message</Label>
                            <Textarea
                                id="message"
                                v-model="form.message"
                                rows="6"
                                required
                                maxlength="5000"
                                :placeholder="
                                    form.category === 'bug'
                                        ? 'What did you do, what happened, and what did you expect?'
                                        : 'Tell us more…'
                                "
                            />
                            <InputError :message="form.errors.message" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="screenshot">
                                Screenshot
                                <span class="font-normal text-muted-foreground"
                                    >(optional)</span
                                >
                            </Label>
                            <div
                                v-if="preview"
                                class="flex items-center gap-3 rounded-lg border border-border p-3"
                            >
                                <img
                                    :src="preview"
                                    alt="Screenshot preview"
                                    class="size-16 rounded object-cover"
                                />
                                <span class="min-w-0 flex-1 truncate text-sm">{{
                                    form.screenshot?.name
                                }}</span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="clearScreenshot"
                                    >Remove</Button
                                >
                            </div>
                            <label
                                v-else
                                for="screenshot"
                                class="inline-flex min-h-11 w-fit cursor-pointer items-center gap-2 rounded-lg border border-dashed border-border px-4 text-sm text-muted-foreground transition-colors hover:border-foreground/30 hover:text-foreground has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                            >
                                <Spinner v-if="preparing" class="size-4" />
                                <ImagePlus v-else class="size-4" />
                                {{
                                    preparing
                                        ? 'Preparing…'
                                        : 'Add a screenshot'
                                }}
                            </label>
                            <input
                                id="screenshot"
                                ref="fileInput"
                                type="file"
                                accept="image/*"
                                class="sr-only"
                                @change="pickScreenshot"
                            />
                            <InputError :message="form.errors.screenshot" />
                        </div>

                        <div
                            class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <p class="text-sm text-muted-foreground">
                                We'll reply to {{ page.props.auth.user.email }}.
                            </p>
                            <Button
                                type="submit"
                                :disabled="form.processing || preparing"
                            >
                                <Spinner v-if="form.processing" />
                                Send feedback
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <section
                v-if="recent.length"
                class="space-y-3"
                aria-labelledby="recent"
            >
                <h2 id="recent" class="text-lg font-semibold">What you sent</h2>
                <ul
                    class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
                >
                    <li
                        v-for="item in recent"
                        :key="item.id"
                        class="space-y-1 p-4"
                    >
                        <div
                            class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                        >
                            <span class="font-medium text-foreground">{{
                                FEEDBACK_CATEGORIES[item.category].hint
                            }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ formatDate(item.created_at) }}</span>
                            <Badge
                                v-if="item.seen"
                                variant="secondary"
                                class="ml-auto"
                                >Read</Badge
                            >
                        </div>
                        <p class="text-sm wrap-break-word whitespace-pre-line">
                            {{ item.message }}
                        </p>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
