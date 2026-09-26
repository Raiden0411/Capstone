<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover activates env(safe-area-inset-*) for notch devices. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="{{ $description ?? config('app.name') . ' — Book your perfect stay.' }}">

    {{-- Theme-color: single tag, updated by applyTheme() below.
         The previous approach used two tags with media="(prefers-color-scheme: ...)"
         — which resolves against the OS setting, not the app's stored
         preference. A user who chose dark in-app but has their OS on light
         got a light-tinted address bar over a dark body. Applying the
         resolved value from JS keeps them in sync in every case. --}}
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
         would download four unused weights per page load. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style"
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

    {{-- Content offset must clear the fixed header.

         The header's own height is now: base (64px mobile / 80px md+) PLUS
         `env(safe-area-inset-top)` when the device has a notch or dynamic
         island. The offset here has to match, otherwise on notched iPhones
         the first ~47–59px of every page's content would be hidden behind
         the header.

         Non-notched devices: env() = 0, so pt-[calc(4rem+0)] = 64px and
         pt-[calc(5rem+0)] = 80px — identical to the previous pt-16 / pt-20.
         Notched devices: pt = 64 + 47 (or 59) and 80 + 47 (or 59), which
         matches the header's actual rendered height. --}}
    <main class="flex-1 pt-[calc(4rem+env(safe-area-inset-top))] md:pt-[calc(5rem+env(safe-area-inset-top))]">
        {{ $slot }}
    </main>

    <x-footers.public-footer />

    {{-- Alpine Collapse is registered in resources/js/app.js via
         `Alpine.plugin(collapse)` on `alpine:init`. No CDN script here. --}}

    {{-- Livewire Scripts (asset pipeline — required for @livewireMapScripts and wire:navigate). --}}
    @livewireScripts

    @livewireMapScripts

    @stack('scripts')
</body>

</html>