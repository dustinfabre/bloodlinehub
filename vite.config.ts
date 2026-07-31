import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        origin: 'http://192.168.31.66:5173',
        hmr: {
            host: '192.168.31.66',
        },
        cors: true,
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
            detectTls: false,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'prompt',
            injectRegister: null,
            manifest: false,
            workbox: {
                cleanupOutdatedCaches: true,
                globPatterns: ['**/*.{js,css,woff2,png,svg,ico}'],
                additionalManifestEntries: [
                    { url: '/offline.html', revision: null },
                    { url: '/manifest.webmanifest', revision: null },
                ],
                runtimeCaching: [
                    {
                        urlPattern: ({ request }) =>
                            request.mode === 'navigate',
                        handler: 'NetworkOnly',
                        options: {
                            cacheName: 'navigation',
                            precacheFallback: {
                                fallbackURL: '/offline.html',
                            },
                        },
                    },
                ],
            },
        }),
    ],
});
