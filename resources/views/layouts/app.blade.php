<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="{{ $description ?? config('app.name') . ' — Book your perfect stay.' }}">

    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:description" content="{{ $description ?? 'Discover and book premium accommodations.' }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    {{-- Dark mode flash prevention + livewire:navigated re-apply --}}
    <script>
        function applyTheme() {
            var t = localStorage.getItem('hs_theme');
            var dark = t === 'dark' || (t !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        }
        applyTheme();
        document.addEventListener('livewire:navigated', applyTheme);
    </script>

    {{-- Fonts – Inter + Playfair Display --}}
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
    @livewireMapStyles
    @stack('styles')
</head>

<body class="font-sans antialiased flex flex-col min-h-screen bg-[#F8F7F3] dark:bg-gray-900 text-gray-900 dark:text-gray-100 transition-colors duration-300">

    {{-- Subtle background decoration (light/dark aware) --}}
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <div class="absolute inset-0 bg-linear-to-br from-white via-[#F8F7F3] to-gray-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-900"></div>
    </div>

    <x-headers.public-header />

    <main class="flex-1 pt-17">
        {{ $slot }}
    </main>

    <x-footers.public-footer />

    {{-- Livewire Scripts (using CDN to bypass local corruption) --}}
    <script src="https://cdn.jsdelivr.net/npm/livewire@4.0.0/dist/livewire.js"></script>
    
    {{-- Alpine Collapse plugin (required for x-collapse) --}}
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.plugin(window.AlpineCollapse);
        });
    </script>

    @livewireMapScripts

    {{-- Preline JS --}}
    <script src="https://unpkg.com/preline/dist/preline.js"></script>
    @stack('scripts')
</body>
</html>