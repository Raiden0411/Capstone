<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'Super Admin Platform' }}</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @livewireStyles
</head>

<body class="bg-white dark:bg-black text-black dark:text-white font-sans antialiased">

    <x-headers.superadmin-header />

    <main class="min-h-screen p-8">
        {{ $slot }}
    </main>

    <x-footers.superadmin-footer />

    @livewireScripts
</body>

</html>