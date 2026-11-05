<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePwaInstall } from '@/composables/usePwaInstall';
import { cn } from '@/lib/utils';
import { Download, Share } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        // The surface the button sits on: navy header/hero or ivory/white
        tone?: 'dark' | 'light';
        size?: 'sm' | 'md';
    }>(),
    { tone: 'light', size: 'md' },
);

const { isInstalled, canPrompt, platform, install } = usePwaInstall();

const instructionsOpen = ref(false);

const buttonClass = computed(() =>
    cn(
        'inline-flex min-h-11 items-center justify-center gap-2 rounded-md border font-medium whitespace-nowrap transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
        props.size === 'sm' ? 'px-3 text-sm md:min-h-10' : 'px-5 text-[15px]',
        props.tone === 'dark'
            ? 'border-white/20 text-ivory hover:border-white/35 hover:bg-white/5 focus-visible:ring-gold focus-visible:ring-offset-navy'
            : 'border-border bg-card text-navy hover:border-navy/30 focus-visible:ring-navy/50 focus-visible:ring-offset-background',
    ),
);

const onClick = async () => {
    if (canPrompt.value && (await install())) {
        return;
    }

    instructionsOpen.value = true;
};
</script>

<template>
    <template v-if="!isInstalled">
        <button
            v-bind="$attrs"
            type="button"
            :class="buttonClass"
            @click="onClick"
        >
            <Download class="size-4 shrink-0" aria-hidden="true" />
            <template v-if="size === 'sm'">
                <span class="sm:hidden">Install</span>
                <span class="hidden sm:inline">Install app</span>
            </template>
            <span v-else>Install app</span>
        </button>

        <Dialog v-model:open="instructionsOpen">
            <DialogContent class="gap-5 shadow-sm sm:max-w-md">
                <DialogHeader class="pr-6 text-left">
                    <DialogTitle
                        class="font-display text-xl leading-tight text-navy"
                    >
                        Install BloodlineHub
                    </DialogTitle>
                    <DialogDescription class="text-[15px]">
                        It opens to your dashboard and runs full screen, like an
                        app.
                    </DialogDescription>
                </DialogHeader>

                <ol
                    v-if="platform === 'ios'"
                    class="list-decimal space-y-2.5 pl-5 text-[15px] text-navy marker:font-semibold"
                >
                    <li>
                        Tap the Share button
                        <Share
                            class="inline size-4 align-[-0.15em]"
                            aria-hidden="true"
                        />. If you don't see it, tap
                        <strong class="font-semibold">⋯</strong> first.
                    </li>
                    <li>
                        Choose
                        <strong class="font-semibold">Add to Home Screen</strong
                        >. Scroll the list if it isn't showing.
                    </li>
                    <li>Tap <strong class="font-semibold">Add</strong>.</li>
                </ol>

                <ol
                    v-else-if="platform === 'android'"
                    class="list-decimal space-y-2.5 pl-5 text-[15px] text-navy marker:font-semibold"
                >
                    <li>
                        Open the browser menu
                        <strong class="font-semibold">⋮</strong>.
                    </li>
                    <li>
                        Tap
                        <strong class="font-semibold">Install app</strong> or
                        <strong class="font-semibold">Add to Home screen</strong
                        >.
                    </li>
                    <li>
                        Confirm with
                        <strong class="font-semibold">Install</strong>.
                    </li>
                </ol>

                <template v-else-if="platform === 'mac-safari'">
                    <ol
                        class="list-decimal space-y-2.5 pl-5 text-[15px] text-navy marker:font-semibold"
                    >
                        <li>
                            In the menu bar, open
                            <strong class="font-semibold">File</strong>.
                        </li>
                        <li>
                            Choose
                            <strong class="font-semibold">Add to Dock</strong>,
                            then click
                            <strong class="font-semibold">Add</strong>.
                        </li>
                    </ol>
                    <p class="text-sm text-muted-foreground">
                        Needs macOS Sonoma or later.
                    </p>
                </template>

                <ol
                    v-else-if="platform === 'desktop-chromium'"
                    class="list-decimal space-y-2.5 pl-5 text-[15px] text-navy marker:font-semibold"
                >
                    <li>
                        Click the install icon at the right end of the address
                        bar. Or open the browser menu and choose
                        <strong class="font-semibold"
                            >Install BloodlineHub</strong
                        >
                        (in Chrome it is under
                        <strong class="font-semibold"
                            >Cast, save, and share</strong
                        >, in Edge under
                        <strong class="font-semibold">Apps</strong>).
                    </li>
                    <li>
                        Click <strong class="font-semibold">Install</strong>.
                    </li>
                </ol>

                <div v-else class="space-y-2.5 text-[15px] text-navy">
                    <p>
                        This browser can't install web apps. Open this page in
                        Chrome, Edge or Safari to install it.
                    </p>
                    <p class="text-muted-foreground">
                        Everything works here in the browser as it is.
                    </p>
                </div>

                <p
                    v-if="
                        platform === 'android' ||
                        platform === 'desktop-chromium'
                    "
                    class="text-sm text-muted-foreground"
                >
                    Already installed? Open BloodlineHub from your apps.
                </p>
            </DialogContent>
        </Dialog>
    </template>
</template>
