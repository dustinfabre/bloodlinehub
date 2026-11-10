import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { registerSW } from 'virtual:pwa-register';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import CookieNotice from './components/CookieNotice.vue';
import { initializeTheme } from './composables/useAppearance';
import { listenForPwaInstall } from './composables/usePwaInstall';

const appName = import.meta.env.VITE_APP_NAME || 'BloodlineHub';

if (import.meta.env.PROD) {
    const updateServiceWorker = registerSW({
        immediate: true,
        onNeedRefresh() {
            window.dispatchEvent(
                new CustomEvent('pwa:update-available', {
                    detail: { updateServiceWorker },
                }),
            );
        },
        onOfflineReady() {
            window.dispatchEvent(new CustomEvent('pwa:offline-ready'));
        },
    });
}

// Before the app mounts: the install prompt event can fire early
listenForPwaInstall();

// A page left open longer than the session gets "Page Expired" (419) on its next save. Instead of Laravel's error
// page, reload the page: that picks up a fresh CSRF token and logs back in through "Remember me" (or goes to the
// login page). The page keeps what was typed, so the user can save again.
router.on('invalid', (event) => {
    if (event.detail.response.status !== 419) return;

    event.preventDefault();
    toast.error('Your session had expired, so that wasn’t saved. Please try again.');
    router.reload();
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // The cookie notice sits beside the app so it shows on every page, whatever the layout
        createApp({ render: () => [h(App, props), h(CookieNotice)] })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#D4AF62',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
