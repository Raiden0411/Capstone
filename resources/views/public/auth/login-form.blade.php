{{-- resources/views/public/auth/login-form.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        /* Your existing glassmorphism styles */
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 1.5rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }
        .orb { position: absolute; border-radius: 50%; filter: blur(60px); opacity: 0.4; z-index: 0; pointer-events: none; }
        .orb-1 { width: 600px; height: 600px; background: radial-gradient(circle, #22c55e, transparent 70%); top: -200px; left: -150px; }
        .orb-2 { width: 500px; height: 500px; background: radial-gradient(circle, #06b6d4, transparent 70%); bottom: -150px; right: -100px; }
        .orb-3 { width: 400px; height: 400px; background: radial-gradient(circle, #facc15, transparent 70%); top: 40%; left: 60%; }
    </style>
</head>
<body class="font-sans antialiased flex items-center justify-center min-h-screen bg-[#071412] text-white">
    {{-- Background orbs --}}
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <div class="relative z-10 w-full max-w-4xl glass-card flex flex-col lg:flex-row overflow-hidden !rounded-3xl !p-0">

        {{-- Left illustration (same as your original) --}}
        <div class="hidden lg:block relative flex-1 bg-black/40 overflow-hidden border-r border-white/10">
            <svg class="absolute inset-0 w-full h-full" viewBox="0 0 480 620" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
                <rect x="28"  y="22"  width="98"  height="62" rx="6" fill="rgba(34,197,94,0.15)" opacity=".8"/>
                <rect x="142" y="22"  width="78"  height="62" rx="6" fill="rgba(34,197,94,0.15)" opacity=".8"/>
                <rect x="236" y="22"  width="108" height="62" rx="6" fill="rgba(34,197,94,0.15)" opacity=".8"/>
                <rect x="360" y="22"  width="96"  height="62" rx="6" fill="rgba(34,197,94,0.15)" opacity=".8"/>
                <ellipse cx="200" cy="102" rx="58" ry="24" fill="rgba(37, 99, 235, 0.2)" opacity=".6"/>
                <rect x="0"   y="98"  width="480" height="14" fill="rgba(255,255,255,0.05)"/>
                <rect x="124" y="0"   width="10"  height="620" fill="rgba(255,255,255,0.05)"/>
                <rect x="264" y="0"   width="10"  height="620" fill="rgba(255,255,255,0.05)"/>
                <rect x="0"   y="254" width="480" height="10"  fill="rgba(255,255,255,0.05)"/>
                <rect x="0"   y="394" width="480" height="10"  fill="rgba(255,255,255,0.05)"/>
                <line x1="200" y1="0" x2="200" y2="110" stroke="rgba(34,197,94,0.4)" stroke-width="2.5"/>
            </svg>
            <div class="absolute top-4 right-4 size-9 rounded-full bg-white/10 backdrop-blur flex items-center justify-center text-xs font-bold text-brand-400 z-10">N</div>
            <div class="absolute z-10 flex items-center gap-1.5 bg-white/10 backdrop-blur border border-white/10 rounded-lg px-2.5 py-1.5 shadow-md text-xs font-semibold text-white/80" style="top:170px; left:86px;">
                <span class="inline-block size-2 rounded-full bg-pink-500 shrink-0"></span> HQ
            </div>
            <div class="absolute bottom-5 right-5 z-10 inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-white/10 backdrop-blur text-white/80 border border-white/10">3 locations</div>
        </div>

        {{-- Right form panel --}}
        <div class="w-full lg:max-w-md p-8 sm:p-10 relative bg-black/30 backdrop-blur-sm">
            {{-- Brand --}}
            <div class="flex items-center gap-x-3 mb-7">
                <div class="size-10 rounded-xl bg-brand-600 inline-flex items-center justify-center shrink-0">
                    <svg class="size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                </div>
                <span class="text-base font-semibold text-white">{{ config('app.name', 'Capstone') }}</span>
            </div>

            {{-- Heading --}}
            <h1 class="text-2xl font-bold text-white">Sign In</h1>
            <p class="mt-1 text-sm text-white/60">
                New here?
                <a href="{{ route('register') }}" class="font-medium text-brand-400 decoration-2 hover:underline">Create an account</a>
            </p>
            <p class="mt-1 text-sm text-white/60">
                Own a tourist spot?
                <a href="{{ route('register_business') }}" class="font-medium text-brand-400 decoration-2 hover:underline">Register your business</a>
            </p>

            {{-- Errors --}}
            @if ($errors->any())
                <div class="mt-4 text-red-400 text-sm">{{ $errors->first('email') }}</div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('login') }}" class="space-y-4 mt-6">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium mb-2 text-white/70">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="example@email.com"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium mb-2 text-white/70">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                </div>
                <div class="flex items-center gap-x-3">
                    <input type="checkbox" id="remember" name="remember"
                           class="shrink-0 mt-0.5 border-white/20 rounded text-brand-600 focus:ring-brand-500 bg-white/5 dark:checked:bg-brand-600 dark:checked:border-brand-600">
                    <label for="remember" class="text-sm text-white/60">Remember me</label>
                </div>
                <button type="submit"
                        class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-brand-600 text-white hover:bg-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 focus:ring-offset-black/30 disabled:opacity-50 disabled:pointer-events-none transition-colors shadow-lg shadow-brand-500/20">
                    Sign In
                </button>
            </form>
        </div>
    </div>

    @livewireScripts
</body>
</html>