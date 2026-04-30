<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'Business Dashboard' }}</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    @livewireStyles
</head>

<body class="bg-white dark:bg-black text-black dark:text-white font-sans antialiased">
{{-- Sidebar --}}
    {{-- <x-headers.tenant.sidebar /> --}}

    <x-headers.tenant.tenant-header />

    <main class="min-h-screen p-8">
        @if(isset($slot))
            {{ $slot }}
        @else
            @yield('content')
        @endif
    </main>

    <x-footers.tenant.tenant-footer />

    @livewireScripts
</body>

</html>