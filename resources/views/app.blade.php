<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#14243a">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="BloodlineHub">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f8f6f0;
            }

            html.dark {
                background-color: #0e1928;
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Link previews on social media and chat apps (shared pedigrees: PedigreeController::openGraph()). Written
             here rather than with <Head> because their crawlers read the server's HTML and don't run JavaScript. --}}
        @isset($og)
            <meta property="og:site_name" content="BloodlineHub">
            <meta property="og:type" content="website">
            <meta property="og:url" content="{{ $og['url'] }}">
            <meta property="og:title" content="{{ $og['title'] }}">
            <meta property="og:description" content="{{ $og['description'] }}">
            @isset($og['image'])
                <meta property="og:image" content="{{ $og['image'] }}">
                <meta property="og:image:type" content="image/png">
                <meta property="og:image:width" content="{{ $og['image_width'] }}">
                <meta property="og:image:height" content="{{ $og['image_height'] }}">
                <meta property="og:image:alt" content="{{ $og['image_alt'] }}">
                <meta name="twitter:card" content="summary_large_image">
            @else
                <meta name="twitter:card" content="summary">
            @endisset
        @endisset

        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="icon" href="/icons/icon-192.png" type="image/png" sizes="192x192">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">

        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        {{-- crossorigin lets the pedigree image export read and embed this font --}}
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" crossorigin="anonymous" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
