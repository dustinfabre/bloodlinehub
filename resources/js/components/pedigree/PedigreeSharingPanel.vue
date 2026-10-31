<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useToast } from '@/composables/useToast';
import { pedigreeVisibility } from '@/routes/pigeons';
import type { PedigreeSharing, PedigreeVisibility } from '@/types/pedigree';
import { router, useForm } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    ExternalLink,
    Globe,
    KeyRound,
    LoaderCircle,
    Lock,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    pigeonId: number;
    pigeonLabel: string;
    shareUrl: string;
    sharing: PedigreeSharing;
}>();

const { success, error } = useToast();

const MODES: {
    value: PedigreeVisibility;
    label: string;
    icon: typeof Lock;
}[] = [
    { value: 'private', label: 'Private', icon: Lock },
    { value: 'password', label: 'Password', icon: KeyRound },
    { value: 'public', label: 'Public', icon: Globe },
];

// The selected tab; choosing "Password" only opens its form, the other two apply straight away
const mode = ref<PedigreeVisibility>(props.sharing.visibility);
watch(
    () => props.sharing.visibility,
    (visibility) => (mode.value = visibility),
);

const formatDateTime = (value: string) =>
    new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

// yyyy-mm-dd in local time, for the date input
const toDateInput = (value: Date) =>
    `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;

const currentExpiry = computed(() =>
    props.sharing.password_expires_at
        ? new Date(props.sharing.password_expires_at)
        : null,
);

const form = useForm({
    password: '',
    expires: 'never' as 'never' | '1d' | '7d' | '30d' | 'custom',
    // yyyy-mm-dd from the date input; sent as the end of that day
    expires_at: '',
});

// Start from the current setting so saving without changes keeps it
const resetPasswordForm = () => {
    const keepDate =
        props.sharing.visibility === 'password' && currentExpiry.value;
    form.password = '';
    form.expires = keepDate ? 'custom' : 'never';
    form.expires_at = keepDate ? toDateInput(currentExpiry.value!) : '';
    form.clearErrors();
};
resetPasswordForm();

const minDate = computed(() => toDateInput(new Date()));

// Password just set in this session, so the owner can copy it together with the link
const sharedPassword = ref<string | null>(null);

const savingMode = ref<PedigreeVisibility | null>(null);

const choose = (value: PedigreeVisibility) => {
    mode.value = value;
    if (value === 'password') {
        resetPasswordForm();
        return;
    }
    if (value === props.sharing.visibility) return;

    router.patch(
        pedigreeVisibility({ pigeon: props.pigeonId }).url,
        { visibility: value },
        {
            preserveScroll: true,
            onStart: () => (savingMode.value = value),
            onFinish: () => (savingMode.value = null),
            onSuccess: () => {
                sharedPassword.value = null;
                success(
                    value === 'public'
                        ? 'Pedigree is now public'
                        : 'Pedigree is now private',
                );
            },
            onError: () => {
                mode.value = props.sharing.visibility;
                error('Could not update sharing');
            },
        },
    );
};

const savePassword = () => {
    const password = form.password;
    form.transform((data) => {
        const [year, month, day] = data.expires_at.split('-').map(Number);
        return {
            visibility: 'password',
            password: data.password || null,
            expires: data.expires,
            // Access ends at the end of the chosen day, in the owner's timezone
            expires_at:
                data.expires === 'custom' && data.expires_at
                    ? new Date(year, month - 1, day, 23, 59, 59).toISOString()
                    : null,
        };
    }).patch(pedigreeVisibility({ pigeon: props.pigeonId }).url, {
        preserveScroll: true,
        onSuccess: () => {
            if (password) sharedPassword.value = password;
            resetPasswordForm();
            success('Password protection is on');
        },
    });
};

const passwordStatus = computed(() => {
    if (props.sharing.visibility === 'password') {
        return currentExpiry.value
            ? `Password active until ${formatDateTime(props.sharing.password_expires_at!)}`
            : 'Password active, no expiry';
    }
    if (props.sharing.password_expired && props.sharing.password_expires_at) {
        return `The last password expired on ${formatDateTime(props.sharing.password_expires_at)}. Set a new one or extend it.`;
    }
    return null;
});

const copied = ref<'link' | 'invite' | null>(null);

const copy = async (kind: 'link' | 'invite') => {
    const text =
        kind === 'link'
            ? props.shareUrl
            : `Pedigree ${props.pigeonLabel}\n${props.shareUrl}\nPassword: ${sharedPassword.value}`;
    try {
        await navigator.clipboard.writeText(text);
        copied.value = kind;
        window.setTimeout(() => (copied.value = null), 2000);
    } catch {
        error('Could not copy to the clipboard');
    }
};

const selectAll = (event: FocusEvent) =>
    (event.target as HTMLInputElement).select();

const showLink = computed(() => props.sharing.visibility !== 'private');
</script>

<template>
    <section class="rounded-xl border bg-card p-4 sm:p-5">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <h2 class="font-semibold">Sharing</h2>
                <p class="text-sm text-muted-foreground">
                    Who can open this pedigree from its link or QR code.
                </p>
            </div>
            <div
                class="inline-grid shrink-0 grid-cols-3 gap-1 self-start rounded-lg bg-muted p-1"
                role="radiogroup"
                aria-label="Pedigree visibility"
            >
                <button
                    v-for="option in MODES"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="mode === option.value"
                    class="flex items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition"
                    :class="
                        mode === option.value
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :disabled="savingMode !== null"
                    @click="choose(option.value)"
                >
                    <LoaderCircle
                        v-if="savingMode === option.value"
                        class="size-4 animate-spin"
                    />
                    <component :is="option.icon" v-else class="size-4" />
                    {{ option.label }}
                </button>
            </div>
        </div>

        <div class="mt-4 space-y-4">
            <p v-if="mode === 'private'" class="text-sm text-muted-foreground">
                Only you can see this pedigree. Anyone who scans the QR code is
                told it's private.
            </p>
            <p
                v-else-if="mode === 'public'"
                class="text-sm text-muted-foreground"
            >
                Anyone with the link or QR code can view this pedigree. Private
                notes are never shown.
            </p>

            <form
                v-else
                class="grid gap-4 rounded-lg bg-muted/50 p-4"
                @submit.prevent="savePassword"
            >
                <p class="text-sm text-muted-foreground">
                    Anyone with the link and this password can view the
                    pedigree. Changing or removing the password locks out
                    everyone who used the old one.
                </p>
                <p
                    v-if="passwordStatus"
                    class="flex items-center gap-2 text-sm font-medium"
                >
                    <KeyRound class="size-4 text-gold" />
                    {{ passwordStatus }}
                </p>
                <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                    <div class="grid gap-2">
                        <Label for="share_password">{{
                            sharing.has_password ? 'New password' : 'Password'
                        }}</Label>
                        <Input
                            id="share_password"
                            v-model="form.password"
                            type="text"
                            autocomplete="off"
                            spellcheck="false"
                            :placeholder="
                                sharing.has_password
                                    ? 'Leave blank to keep the current password'
                                    : 'At least 4 characters'
                            "
                        />
                        <InputError :message="form.errors.password" />
                    </div>
                    <div class="grid gap-2 sm:w-48">
                        <Label for="share_expires">Access lasts</Label>
                        <Select v-model="form.expires">
                            <SelectTrigger id="share_expires">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="never">Permanent</SelectItem>
                                <SelectItem value="1d">24 hours</SelectItem>
                                <SelectItem value="7d">7 days</SelectItem>
                                <SelectItem value="30d">30 days</SelectItem>
                                <SelectItem value="custom"
                                    >Until a date</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.expires" />
                    </div>
                </div>
                <div
                    v-if="form.expires === 'custom'"
                    class="grid gap-2 sm:w-64"
                >
                    <Label for="share_until">Access ends after</Label>
                    <Input
                        id="share_until"
                        v-model="form.expires_at"
                        type="date"
                        :min="minDate"
                        required
                    />
                    <InputError :message="form.errors.expires_at" />
                </div>
                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle
                            v-if="form.processing"
                            class="size-4 animate-spin"
                        />
                        {{
                            sharing.visibility === 'password'
                                ? 'Save changes'
                                : 'Turn on password'
                        }}
                    </Button>
                </div>
            </form>

            <div v-if="showLink" class="flex flex-wrap gap-2">
                <Input
                    :model-value="shareUrl"
                    readonly
                    class="min-w-0 flex-1 font-mono text-sm"
                    aria-label="Pedigree link"
                    @focus="selectAll"
                />
                <Button
                    variant="outline"
                    size="icon"
                    class="shrink-0"
                    title="Copy link"
                    @click="copy('link')"
                >
                    <Check v-if="copied === 'link'" class="size-4" />
                    <Copy v-else class="size-4" />
                </Button>
                <Button variant="outline" size="icon" class="shrink-0" as-child>
                    <a
                        :href="shareUrl"
                        target="_blank"
                        rel="noopener"
                        title="Open shared page"
                    >
                        <ExternalLink class="size-4" />
                    </a>
                </Button>
                <Button
                    v-if="sharing.visibility === 'password' && sharedPassword"
                    variant="secondary"
                    class="shrink-0"
                    @click="copy('invite')"
                >
                    <Check v-if="copied === 'invite'" class="size-4" />
                    <Copy v-else class="size-4" />
                    Copy link &amp; password
                </Button>
            </div>
        </div>
    </section>
</template>
