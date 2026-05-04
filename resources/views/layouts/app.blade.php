<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Dark mode flash prevention (Preline Theme Switch) --}}
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

    <title>{{ $title ?? 'Welcome' }}</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @livewireStyles
</head>

<body class="font-sans antialiased bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100">
    <x-headers.public-header />
    
    <main class="min-h-screen">
        {{ $slot }}
    </main>
    
    <x-footers.public-footer />
    
    @livewireScripts
    <script src="https://unpkg.com/preline/dist/preline.js"></script>
</body>

</html>