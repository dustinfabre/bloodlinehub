import { computed, onMounted, readonly, ref, shallowRef } from 'vue';

// Chromium's install prompt event; it is not part of lib.dom.
interface BeforeInstallPromptEvent extends Event {
    readonly platforms: string[];
    readonly userChoice?: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
    prompt(): Promise<void>;
}

export type PwaPlatform =
    | 'ios'
    | 'android'
    | 'mac-safari'
    | 'desktop-chromium'
    | 'unsupported';

// Module-level so every component reads the same install state
const deferredPrompt = shallowRef<BeforeInstallPromptEvent | null>(null);
const installedThisSession = ref(false);
const standalone = ref(false);
const platform = ref<PwaPlatform>('unsupported');
// False on the server and during the first client render, so markup matches SSR
const ready = ref(false);

const isInstalled = computed(
    () => ready.value && (standalone.value || installedThisSession.value),
);
const canPrompt = computed(() => ready.value && deferredPrompt.value !== null);

let listening = false;

const standaloneQuery = (): MediaQueryList | null =>
    typeof window.matchMedia === 'function'
        ? window.matchMedia('(display-mode: standalone)')
        : null;

const detectStandalone = (): boolean =>
    standaloneQuery()?.matches === true ||
    // iOS Safari's home screen flag
    (navigator as Navigator & { standalone?: boolean }).standalone === true;

const detectPlatform = (): PwaPlatform => {
    const ua = navigator.userAgent;
    // iPadOS Safari sends a Mac user agent; only the touch points give it away
    const isIPadOS = /Macintosh/.test(ua) && navigator.maxTouchPoints > 1;

    if (/iPhone|iPad|iPod/.test(ua) || isIPadOS) {
        return 'ios';
    }

    if (/Android/.test(ua)) {
        return 'android';
    }

    // Only Chromium browsers implement the install prompt event
    if ('onbeforeinstallprompt' in window) {
        return 'desktop-chromium';
    }

    if (
        /Macintosh/.test(ua) &&
        /Version\/[\d.]+.*Safari\//.test(ua) &&
        !/Chrome|Chromium|CriOS|Edg|OPR|Firefox|FxiOS/.test(ua)
    ) {
        return 'mac-safari';
    }

    return 'unsupported';
};

/**
 * Call once from app.ts before the app mounts: Chromium can fire `beforeinstallprompt` before any component exists.
 */
export function listenForPwaInstall(): void {
    if (typeof window === 'undefined' || listening) {
        return;
    }

    listening = true;

    window.addEventListener('beforeinstallprompt', (event) => {
        // Hold back the browser's own banner so installing starts from our button
        event.preventDefault();
        deferredPrompt.value = event as BeforeInstallPromptEvent;
    });

    window.addEventListener('appinstalled', () => {
        installedThisSession.value = true;
        deferredPrompt.value = null;
    });

    // Chromium moves the tab into an app window right after installing
    standaloneQuery()?.addEventListener?.('change', (event) => {
        standalone.value = event.matches;
    });
}

/**
 * Shared install state for the landing page. Call from a component's setup; detection runs on mount (client only).
 */
export function usePwaInstall() {
    onMounted(() => {
        listenForPwaInstall();

        if (ready.value) {
            return;
        }

        standalone.value = detectStandalone();
        platform.value = detectPlatform();
        ready.value = true;
    });

    /**
     * Opens the native install prompt. Resolves false when there was no prompt to show,
     * so the caller can fall back to manual instructions.
     */
    const install = async (): Promise<boolean> => {
        const event = deferredPrompt.value;

        if (!event) {
            return false;
        }

        // A prompt event can only be used once
        deferredPrompt.value = null;

        try {
            await event.prompt();
        } catch {
            return false;
        }

        try {
            const choice = await event.userChoice;

            if (choice?.outcome === 'accepted') {
                installedThisSession.value = true;
            }
        } catch {
            // The prompt was shown; the outcome is not needed
        }

        return true;
    };

    return {
        isInstalled,
        canPrompt,
        platform: readonly(platform),
        install,
    };
}
