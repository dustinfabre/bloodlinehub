<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    primaryMobileNavItems,
    secondaryMobileNavItems,
} from '@/lib/navigation';
import { toUrl } from '@/lib/utils';
import { logout } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Download, LogOut, Menu, Settings, Share } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const page = usePage();
const moreOpen = ref(false);
const installPrompt = ref<BeforeInstallPromptEvent | null>(null);
const isIos = computed(
    () =>
        typeof navigator !== 'undefined' &&
        /iPad|iPhone|iPod/.test(navigator.userAgent) &&
        !('standalone' in navigator && navigator.standalone),
);

const isSectionActive = (href: Parameters<typeof toUrl>[0]) => {
    const target = toUrl(href);

    if (target === '/dashboard') {
        return page.url === target;
    }

    return page.url === target || page.url.startsWith(`${target}/`);
};

const moreIsActive = computed(() =>
    secondaryMobileNavItems.some((item) => isSectionActive(item.href)),
);

const handleLogout = () => {
    moreOpen.value = false;
    router.flushAll();
};

const captureInstallPrompt = (event: Event) => {
    event.preventDefault();
    installPrompt.value = event as BeforeInstallPromptEvent;
};

const installApp = async () => {
    if (!installPrompt.value) return;

    await installPrompt.value.prompt();
    await installPrompt.value.userChoice;
    installPrompt.value = null;
};

onMounted(() => {
    window.addEventListener('beforeinstallprompt', captureInstallPrompt);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', captureInstallPrompt);
});
</script>

<template>
    <nav
        aria-label="Primary navigation"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-border/80 bg-background/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_24px_rgba(15,23,20,0.08)] backdrop-blur md:hidden"
    >
        <div class="grid h-16 grid-cols-4 px-2">
            <Link
                v-for="item in primaryMobileNavItems"
                :key="item.title"
                :href="item.href"
                :aria-current="isSectionActive(item.href) ? 'page' : undefined"
                class="flex min-h-11 flex-col items-center justify-center gap-1 rounded-md px-1 text-[11px] font-medium text-muted-foreground transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="
                    isSectionActive(item.href)
                        ? 'text-primary'
                        : 'hover:text-foreground'
                "
            >
                <component :is="item.icon" class="size-5" aria-hidden="true" />
                <span>{{ item.title }}</span>
            </Link>
            <button
                type="button"
                class="flex min-h-11 flex-col items-center justify-center gap-1 rounded-md px-1 text-[11px] font-medium text-muted-foreground transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="moreIsActive ? 'text-primary' : 'hover:text-foreground'"
                :aria-expanded="moreOpen"
                aria-controls="mobile-more-navigation"
                @click="moreOpen = true"
            >
                <Menu class="size-5" aria-hidden="true" />
                <span>More</span>
            </button>
        </div>
    </nav>

    <Sheet v-model:open="moreOpen">
        <SheetContent
            id="mobile-more-navigation"
            side="bottom"
            class="max-h-[85svh] overflow-y-auto rounded-t-lg px-4 pb-[calc(1rem+env(safe-area-inset-bottom))] md:hidden"
        >
            <SheetHeader class="pr-8 text-left">
                <SheetTitle>More</SheetTitle>
                <SheetDescription
                    >Navigation and account options</SheetDescription
                >
            </SheetHeader>

            <div class="mt-2 grid grid-cols-2 gap-2">
                <Link
                    v-for="item in secondaryMobileNavItems"
                    :key="item.title"
                    :href="item.href"
                    :aria-current="
                        isSectionActive(item.href) ? 'page' : undefined
                    "
                    class="flex min-h-14 items-center gap-3 rounded-md border border-border bg-card px-3 font-medium transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        isSectionActive(item.href)
                            ? 'border-primary/40 bg-primary/10 text-primary'
                            : ''
                    "
                    @click="moreOpen = false"
                >
                    <component
                        :is="item.icon"
                        class="size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="text-sm">{{ item.title }}</span>
                </Link>
            </div>

            <button
                v-if="installPrompt"
                type="button"
                class="mt-3 flex min-h-11 w-full items-center gap-3 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                @click="installApp"
            >
                <Download class="size-5" aria-hidden="true" />
                Install BloodlineHub
            </button>
            <div
                v-else-if="isIos"
                class="mt-3 flex items-start gap-3 rounded-md border border-border bg-muted/60 p-3 text-sm"
            >
                <Share
                    class="mt-0.5 size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <p>
                    To install BloodlineHub, tap Share and then Add to Home
                    Screen.
                </p>
            </div>

            <div class="mt-4 border-t border-border pt-4">
                <UserInfo :user="page.props.auth.user" :show-email="true" />
                <div class="mt-3 grid gap-2">
                    <Link
                        :href="profileEdit()"
                        class="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="moreOpen = false"
                    >
                        <Settings class="size-5" aria-hidden="true" />
                        Settings
                    </Link>
                    <Link
                        :href="logout()"
                        as="button"
                        class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 text-sm font-medium text-destructive hover:bg-destructive/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="handleLogout"
                    >
                        <LogOut class="size-5" aria-hidden="true" />
                        Log out
                    </Link>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
