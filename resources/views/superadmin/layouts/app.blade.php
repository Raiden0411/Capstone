<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Dark mode flash prevention --}}
    <script>
        const html = document.querySelector('html');
        const isLightOrAuto = localStorage.getItem('hs_theme') === 'light' ||
            (localStorage.getItem('hs_theme') === 'auto' && !window.matchMedia('(prefers-color-scheme: dark)').matches);
        const isDarkOrAuto = localStorage.getItem('hs_theme') === 'dark' ||
            (localStorage.getItem('hs_theme') === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);

        if (isLightOrAuto && html.classList.contains('dark')) html.classList.remove('dark');
        else if (isDarkOrAuto && html.classList.contains('light')) html.classList.remove('light');
        else if (isDarkOrAuto && !html.classList.contains('dark')) html.classList.add('dark');
        else if (isLightOrAuto && !html.classList.contains('light')) html.classList.add('light');
    </script>

    <title>{{ $title ?? 'Super Admin Platform' }}</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @livewireStyles

    {{-- Chart.js for analytics (CDN) – ensures it's available before any component script --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
</head>

{{-- Alpine state lifted here so header, sidebar, and content can all use it --}}
<body x-data="{ minified: false }"
      class="bg-gray-50 transition-all duration-300 dark:bg-[#0a0f1e]">
    <main id="content">
        
        {{-- Top bar --}}
        <x-headers.admin.superadmin-header />

        {{-- Sidebar --}}
        <x-headers.admin.sidebar />

        {{-- Content wrapper – reacts to minified state --}}
        <div class="w-full transition-all duration-300"
             :class="minified ? 'lg:ps-[3.25rem]' : 'lg:ps-64'">
            <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">
                {{ $slot }}
            </div>
        </div>

    </main>

    @livewireScripts
</body>

</html>