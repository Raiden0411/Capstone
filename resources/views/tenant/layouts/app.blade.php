<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover activates env(safe-area-inset-*) for notch devices. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">

    {{-- Android Chrome address-bar tint, matched to the page background. --}}
    <meta name="theme-color" content="#F8F7F3" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#111827" media="(prefers-color-scheme: dark)">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    {{-- Hide x-cloak elements before Alpine boots. Tailwind v4 does not emit this rule. --}}
    <style>[x-cloak]{display:none!important}</style>

    {{-- Dark mode flash prevention + livewire:navigated re-apply. --}}
    <script>
        function applyTheme() {
            var t = localStorage.getItem('hs_theme');
            var dark = t === 'dark' || (t !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        }
        applyTheme();
        document.addEventListener('livewire:navigated', applyTheme);
    </script>

    <title>{{ $title ?? 'Business Dashboard' }}</title>

    {{-- Fonts – Inter + Playfair Display (non-blocking) --}}
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

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @livewireMapStyles

    @stack('styles')
</head>

<body
    x-data="{
        // Persisted across wire:navigate — otherwise the sidebar collapses
        // back to expanded every time the user navigates to a new page.
        //
        // KEY CONTRACT (must match the tenant sidebar + tenant header):
        //   - localStorage key: `tenant_sidebar_minified` ('1' = collapsed)
        //   - window event:     `sidebar-minified-tenant` with detail = boolean
        //
        // The sidebar owns the toggle and fires the event; the header and
        // this layout consume it. Both also read localStorage directly so
        // a cold page load with a collapsed sidebar renders correctly
        // without waiting for the event.
        minified: localStorage.getItem('tenant_sidebar_minified') === '1',
    }"
    @sidebar-minified-tenant.window="minified = $event.detail"
    x-init="
        $watch('minified', v => {
            try { localStorage.setItem('tenant_sidebar_minified', v ? '1' : '0'); } catch (e) {}
        });
    "
    class="font-sans antialiased min-h-screen min-h-[100dvh] bg-[#F8F7F3] dark:bg-gray-900 text-gray-900 dark:text-gray-100"
>

    {{-- Subtle background decoration (light/dark aware) --}}
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <div class="absolute inset-0 bg-linear-to-br from-white via-gray-50 to-gray-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-900"></div>
    </div>

    {{-- Top bar (tenant header) --}}
    <x-headers.tenant.tenant-header />

    {{-- Sidebar --}}
    <x-headers.tenant.sidebar />

    {{-- Content wrapper.
         The padding-start transition mirrors the sidebar's own width
         transition (300ms ease-out) so the two animate in lockstep.
         `lg:ps-20` = 80px (matches the collapsed sidebar's `lg:w-20`);
         `lg:ps-64` = 256px (matches `lg:w-64`). --}}
    <div class="w-full transition-[padding] duration-300 ease-out"
         :class="minified ? 'lg:ps-20' : 'lg:ps-64'">
        <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">
            {{ $slot }}
        </div>
    </div>

    {{-- Alpine Collapse is registered in resources/js/app.js via
         `Alpine.plugin(collapse)` on `alpine:init`. No CDN script here. --}}
    {{-- Chart.js is now opt-in per page via @push('scripts'). The analytics
         and dashboard pages push it themselves. --}}

    @livewireScripts
    @livewireMapScripts

    @stack('scripts')
</body>

</html>