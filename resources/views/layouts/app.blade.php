<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover activates env(safe-area-inset-*) for notch devices. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="{{ $description ?? config('app.name') . ' — Book your perfect stay.' }}">

    {{-- Theme-color: single tag, updated by applyTheme() below. --}}
    <meta name="theme-color" content="#F8F7F3">

    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:description" content="{{ $description ?? 'Discover and book premium accommodations.' }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    {{-- Hide x-cloak elements before Alpine boots. Tailwind v4 does not emit this rule. --}}
    <style>[x-cloak]{display:none!important}</style>

    {{-- Dark mode flash prevention + livewire:navigated re-apply. --}}
    <script>
        function applyTheme() {
            var t = localStorage.getItem('hs_theme');
            var dark = t === 'dark' || (t !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);

            var meta = document.querySelector('meta[name="theme-color"]');
            if (meta) meta.setAttribute('content', dark ? '#111827' : '#F8F7F3');
        }
        applyTheme();
        document.addEventListener('livewire:navigated', applyTheme);
    </script>

    {{-- Fonts – Inter. Playfair Display was retired when --font-display
         was re-pointed at Inter in resources/css/app.css; loading it here
         would download four unused weights per page load.

         `crossorigin` on both preconnects is REQUIRED: Google Fonts is
         CORS-enabled, so a preconnect without it opens a throwaway
         connection that the subsequent fetch cannot reuse.

         `crossorigin="anonymous"` on the preload of the CSS is also
         required — without it, the browser fetches the stylesheet
         twice (once for the preload, once for the actual <link>). --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" crossorigin="anonymous"
          href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap">
    <link rel="stylesheet"
          media="print"
          onload="this.media='all'"
          href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap">
    <noscript>
        <link rel="stylesheet"
              href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap">
    </noscript>

    <title>{{ isset($title) ? $title . ' — ' . config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @livewireMapStyles
    @stack('styles')
</head>

<body class="font-sans antialiased flex flex-col min-h-screen min-h-[100dvh] bg-[#F8F7F3] dark:bg-gray-900 text-gray-900 dark:text-gray-100">

    {{-- Subtle background decoration (light/dark aware). --}}
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute inset-0 bg-linear-to-br from-white via-[#F8F7F3] to-gray-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-900"></div>
    </div>

    <x-headers.public-header />

    {{-- Content offset must clear the fixed header. --}}
    <main class="flex-1 pt-[calc(4rem+env(safe-area-inset-top))] md:pt-[calc(5rem+env(safe-area-inset-top))]">
        {{ $slot }}
    </main>

    <x-footers.public-footer />

    {{-- Alpine Collapse is registered in resources/js/app.js via
         `Alpine.plugin(collapse)` on `alpine:init`. No CDN script here. --}}

    @livewireScripts

    @livewireMapScripts

    @stack('scripts')
</body>

</html>