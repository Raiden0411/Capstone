<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'Welcome' }}</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @livewireStyles
</head>

<body class="font-sans antialiased">
    <x-headers.public-header />
    
    <main class="min-h-screen">
        {{ $slot }}
    </main>
    
    <x-footers.public-footer />
    
    @livewireScripts
    <script src="https://unpkg.com/preline/dist/preline.js"></script>
</body>

</html>