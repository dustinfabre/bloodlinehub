<script setup lang="ts">
import { privacy } from '@/routes';
import { Link } from '@inertiajs/vue3';
import { Cookie } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

/*
 * First-visit cookie notice, mounted once beside the Inertia app (app.ts) so it shows on every page until dismissed.
 *
 * BloodlineHub only sets essential cookies (session, XSRF-TOKEN, remember me, appearance, sidebar state), which need no
 * consent, so this is a notice with a single "Got it" rather than accept/reject choices. If non-essential cookies are
 * ever added (analytics, ads), this must become a real consent prompt that blocks them until accepted: bump
 * NOTICE_VERSION so everyone sees the new prompt.
 *
 * The dismissal is kept in localStorage, not a cookie, so the notice adds nothing to every request.
 */
const STORAGE_KEY = 'bloodlinehub:cookie-notice';
const NOTICE_VERSION = '1';

const visible = ref(false);

onMounted(() => {
    try {
        visible.value =
            window.localStorage.getItem(STORAGE_KEY) !== NOTICE_VERSION;
    } catch {
        // Storage blocked (private browsing, site data off): show it; dismissing then lasts for this page load only
        visible.value = true;
    }
});

const dismiss = () => {
    visible.value = false;

    try {
        window.localStorage.setItem(STORAGE_KEY, NOTICE_VERSION);
    } catch {
        // Nowhere to remember it; the notice comes back on the next full load
    }
};
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out motion-reduce:transition-none"
        enter-from-class="translate-y-3 opacity-0"
        leave-active-class="transition duration-200 ease-in motion-reduce:transition-none"
        leave-to-class="translate-y-3 opacity-0"
    >
        <section
            v-if="visible"
            aria-label="Cookie notice"
            class="cookie-notice fixed inset-x-3 bottom-[calc(0.75rem+env(safe-area-inset-bottom))] z-50 rounded-lg border border-white/10 bg-navy p-4 text-ivory shadow-[0_12px_32px_rgba(10,18,30,0.28)] sm:right-auto sm:bottom-5 sm:left-5 sm:max-w-sm sm:p-5 print:hidden"
        >
            <div class="flex items-start gap-3">
                <Cookie
                    class="mt-0.5 size-5 shrink-0 text-gold"
                    aria-hidden="true"
                />
                <p class="text-sm leading-relaxed text-ivory/75">
                    <span class="font-semibold text-ivory"
                        >We only use essential cookies.</span
                    >
                    They keep you signed in, protect your forms and remember
                    your settings. No tracking or advertising.
                </p>
            </div>

            <div class="mt-4 flex items-center justify-end gap-5">
                <Link
                    :href="`${privacy().url}#cookies`"
                    class="inline-flex min-h-11 items-center text-sm font-medium text-ivory/80 underline decoration-gold/60 underline-offset-4 transition-colors duration-150 hover:text-ivory hover:decoration-gold sm:min-h-9"
                >
                    Privacy Policy
                </Link>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center rounded-md bg-gold px-5 text-sm font-semibold text-navy transition-colors duration-150 hover:bg-gold/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold sm:min-h-9"
                    @click="dismiss"
                >
                    Got it
                </button>
            </div>
        </section>
    </Transition>
</template>

<!-- Not scoped: Vue drops everything after :global(...) in a scoped selector, which would move <body> instead -->
<style>
/* On phones, sit above the app's bottom navigation (h-16 plus the safe area) instead of covering it */
@media (max-width: 47.99rem) {
    body:has([data-mobile-bottom-nav]) .cookie-notice {
        bottom: calc(4rem + env(safe-area-inset-bottom) + 0.75rem);
    }
}
</style>
