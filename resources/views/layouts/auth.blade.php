<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    <style>[x-cloak]{display:none!important}</style>

    <script>
        function applyTheme() {
            var t = localStorage.getItem('hs_theme');
            var dark = t === 'dark' || (t !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        }
        applyTheme();
        document.addEventListener('livewire:navigated', applyTheme);
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&display=swap">
    <link rel="stylesheet"
          media="print"
          onload="this.media='all'"
          href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&display=swap">
    <noscript>
        <link rel="stylesheet"
              href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&display=swap">
    </noscript>

    <title>{{ isset($title) ? $title . ' — ' . config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
</head>

<body class="font-sans antialiased text-gray-900 bg-[#F8F7F3] dark:bg-gray-900 dark:text-white min-h-screen transition-colors duration-300">

    {{-- Global dark mode toggle. Lives once, on the layout, so every
         auth page gets it for free. --}}
    <div
        x-data="{
            dark: localStorage.getItem('hs_theme') === 'dark'
                || (localStorage.getItem('hs_theme') !== 'light'
                    && window.matchMedia('(prefers-color-scheme: dark)').matches),
        }"
        x-init="
            $watch('dark', v => {
                localStorage.setItem('hs_theme', v ? 'dark' : 'light');
                document.documentElement.classList.toggle('dark', v);
            });
        "
        class="fixed top-4 right-4 z-50"
    >
        <button type="button"
                @click="dark = !dark"
                class="flex items-center justify-center size-10 md:size-9 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 shadow-sm transition-all duration-200 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                aria-label="Toggle dark mode">
            <svg x-show="dark" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2"/><path d="M12 20v2"/>
                <path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/>
                <path d="M2 12h2"/><path d="M20 12h2"/>
                <path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
            </svg>
            <svg x-show="!dark" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
            </svg>
        </button>
    </div>

    {{ $slot }}

    {{-- Alpine Collapse is registered in resources/js/app.js via
         `Alpine.plugin(collapse)` on `alpine:init`. No CDN script here. --}}

    @livewireScripts

    @stack('scripts')
</body>

</html>