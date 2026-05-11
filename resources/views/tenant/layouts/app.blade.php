<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Dark mode flash prevention --}}
    <script>
        !function() {
            var t = localStorage.getItem('hs_theme');
            var dark = t === 'dark' || (t !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        }();
    </script>

    <title>{{ $title ?? 'Business Dashboard' }}</title>

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

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    @livewireStyles
</head>

<body x-data="{ minified: false }"
      class="font-sans antialiased min-h-screen text-white">

    {{-- Animated background orbs – same as public layout --}}
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    {{-- Top bar (tenant header) --}}
    <x-headers.tenant.tenant-header />

    {{-- Sidebar --}}
    <x-headers.tenant.sidebar />

    {{-- Main content area --}}
    <div class="w-full transition-all duration-300"
         :class="minified ? 'lg:ps-[3.25rem]' : 'lg:ps-64'">
        <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">
            @if(isset($slot))
                {{ $slot }}
            @else
                @yield('content')
            @endif
        </div>
    </div>

    {{-- Chart.js for analytics – guaranteed to be available --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>

    @livewireScripts

    {{-- Preline JS (as used on the public side) --}}
    <script src="https://unpkg.com/preline/dist/preline.js"></script>
</body>

</html>