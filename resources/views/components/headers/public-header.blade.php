{{-- resources/views/components/headers/public-header.blade.php --}}
@php
    $logoPath = \App\Models\SiteSetting::getValue('site_logo');
    $siteName = \App\Models\SiteSetting::getValue('site_name', 'Tourism Management');

    $logoUrl = null;
    if ($logoPath) {
        $fullPath = public_path('storage/' . $logoPath);
        $logoUrl = asset('storage/' . $logoPath);
        if (file_exists($fullPath)) {
            $logoUrl .= '?v=' . filemtime($fullPath);
        } else {
            $logoUrl .= '?v=' . time();
        }
    }
@endphp

<div x-data="{ scrolled: false, mobileOpen: false, userDropdownOpen: false, dark: document.documentElement.classList.contains('dark') }"
     @scroll.window="scrolled = window.scrollY > 10"
     @keydown.escape.window="mobileOpen = false; userDropdownOpen = false">

    <header
        class="fixed top-0 left-0 right-0 z-50 flex items-center w-full h-16 md:h-20 bg-white/90 dark:bg-gray-900/90 backdrop-blur border-b transition-all duration-300"
        :class="scrolled ? 'border-gray-200 dark:border-gray-700 shadow-lg shadow-gray-900/5' : 'border-gray-200 dark:border-gray-700'">

        <nav class="w-full max-w-[90rem] px-4 sm:px-6 lg:px-8 mx-auto flex items-center justify-between gap-4">

            {{-- Logo & Brand --}}
            <a class="flex items-center gap-3 group shrink-0" href="{{ route('home') }}" wire:navigate>
                @if($logoUrl)
                    <img src="{{ $logoUrl }}"
                         alt="{{ $siteName }} logo"
                         class="h-8 md:h-10 w-auto object-contain">
                @else
                    <div class="flex items-center justify-center h-8 md:h-10 w-8 md:w-10 rounded-lg bg-primary-600 text-white"
                         role="img" aria-label="{{ $siteName }} logo">
                        <span class="text-base md:text-lg font-bold">{{ strtoupper(substr($siteName, 0, 1)) }}</span>
                    </div>
                @endif
                <span class="text-lg md:text-2xl font-bold text-primary-700 dark:text-white leading-none tracking-tight">
                    {{ $siteName }}
                </span>
            </a>

            {{-- Desktop Nav --}}
            <div class="hidden lg:flex items-center gap-8">
                <a href="{{ route('home') }}" wire:navigate
                   class="text-[15px] transition-colors {{ request()->routeIs('home') ? 'text-primary-600 dark:text-blue-400 font-bold' : 'font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white' }}">
                    Home
                </a>
                <a href="{{ route('about') }}" wire:navigate
                   class="text-[15px] transition-colors {{ request()->routeIs('about') ? 'text-primary-600 dark:text-blue-400 font-bold' : 'font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white' }}">
                    About
                </a>
                <a href="{{ route('explore.map') }}" wire:navigate
                   class="text-[15px] transition-colors {{ request()->routeIs('explore.map') ? 'text-primary-600 dark:text-blue-400 font-bold' : 'font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white' }}">
                    Explore
                </a>
                <a href="{{ route('events') }}" wire:navigate
                   class="text-[15px] transition-colors {{ request()->routeIs('events') ? 'text-primary-600 dark:text-blue-400 font-bold' : 'font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white' }}">
                    Events
                </a>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-2 sm:gap-4">

                {{-- Dark mode toggle --}}
                <button type="button"
                        @click="
                            dark = !dark;
                            document.documentElement.classList.toggle('dark', dark);
                            localStorage.setItem('hs_theme', dark ? 'dark' : 'light');
                        "
                        class="flex justify-center items-center size-10 md:size-9 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 transition-colors"
                        aria-label="Toggle dark mode">
                    <svg x-show="dark" x-cloak class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                    <svg x-show="!dark" x-cloak class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                </button>

                @guest
                    <a href="{{ route('login') }}" wire:navigate
                       class="hidden sm:inline-flex px-5 py-2.5 text-sm font-medium text-white transition bg-primary-600 rounded-full hover:bg-primary-700 focus-visible:ring-2 focus-visible:ring-primary-600/50">
                        Login / Sign Up
                    </a>
                    <a href="{{ route('register_business') }}" wire:navigate
                       class="hidden md:inline-flex px-5 py-2.5 text-sm font-medium text-primary-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition focus-visible:ring-2 focus-visible:ring-primary-600/50">
                        Register Business
                    </a>
                @endguest

                @auth
                    <div class="relative" @click.outside="userDropdownOpen = false">
                        <button type="button"
                                @click="userDropdownOpen = !userDropdownOpen"
                                :aria-expanded="userDropdownOpen.toString()"
                                class="flex items-center gap-2 text-sm font-medium py-1.5 px-3 rounded-full bg-primary-600 text-white hover:bg-primary-700 transition-all duration-200 shadow-sm focus-visible:ring-2 focus-visible:ring-primary-600/50">
                            {{-- Avatar --}}
                            <div x-data="{ 
                                avatarUrl: '{{ Auth::user()->avatar ? asset('storage/'. Auth::user()->avatar) : '' }}' 
                            }"
                            @avatar-updated.window="avatarUrl = $event.detail.url">
                                <img x-show="avatarUrl" :src="avatarUrl"
                                     class="object-cover w-6 h-6 rounded-full shrink-0">
                                <div x-show="!avatarUrl"
                                     class="flex items-center justify-center w-6 h-6 text-sm font-bold text-white rounded-full bg-white/20 shrink-0">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                            </div>
                            <span class="hidden sm:inline max-w-[120px] truncate">{{ Auth::user()->name }}</span>
                            <svg class="hidden sm:block w-4 h-4 transition-transform duration-200" :class="userDropdownOpen ? 'rotate-180' : ''" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m19 9-7 7-7-7"/></svg>
                        </button>

                        <div x-cloak x-show="userDropdownOpen"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                             class="absolute right-0 mt-2 min-w-[220px] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-2 shadow-xl z-50 origin-top-right">
                            {{-- User info --}}
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-3">
                                    <div x-data="{ 
                                        avatarUrl: '{{ Auth::user()->avatar ? asset('storage/'. Auth::user()->avatar) : '' }}' 
                                    }"
                                    @avatar-updated.window="avatarUrl = $event.detail.url">
                                        <img x-show="avatarUrl" :src="avatarUrl"
                                             class="object-cover w-9 h-9 rounded-full shrink-0">
                                        <div x-show="!avatarUrl"
                                             class="flex items-center justify-center w-9 h-9 text-sm font-bold text-white bg-primary-600 rounded-full shrink-0">
                                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ Auth::user()->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ Auth::user()->email }}</p>
                                        @if(Auth::user()->hasRole('tourist'))
                                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300">
                                                Tourist
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="p-1.5 space-y-0.5">
                                <a class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-white transition-colors"
                                   href="{{ route('explore.map') }}" wire:navigate @click="userDropdownOpen = false">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    Explore Map
                                </a>
                                <a class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-white transition-colors"
                                   href="{{ route('events') }}" wire:navigate @click="userDropdownOpen = false">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                                    Events
                                </a>
                                <a class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-white transition-colors"
                                   href="{{ route('my-bookings') }}" wire:navigate @click="userDropdownOpen = false">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    My Bookings
                                </a>
                                <a class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-white transition-colors"
                                   href="{{ route('profile') }}" wire:navigate @click="userDropdownOpen = false">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    My Profile
                                </a>
                                <div class="border-t border-gray-100 dark:border-gray-700 my-2"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-3 py-2 px-3 rounded-lg text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endauth

                {{-- Mobile hamburger --}}
                <div class="lg:hidden">
                    <button type="button"
                            @click="mobileOpen = !mobileOpen"
                            :aria-expanded="mobileOpen.toString()"
                            class="w-10 h-10 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 flex items-center justify-center text-gray-700 dark:text-gray-200 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50"
                            aria-label="Toggle navigation">
                        <svg x-show="!mobileOpen" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/>
                        </svg>
                        <svg x-show="mobileOpen" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </nav>

        {{-- Mobile drawer --}}
        <div x-cloak x-show="mobileOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="lg:hidden absolute top-full left-0 right-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 sm:px-6 py-5 space-y-4 z-40 shadow-lg max-h-[calc(100vh-4rem)] overflow-y-auto">

            <a class="block text-base font-medium {{ request()->routeIs('home') ? 'text-primary-600 dark:text-blue-400 font-bold' : 'text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white' }}" 
               href="{{ route('home') }}" wire:navigate @click="mobileOpen = false">Home</a>
            <a class="block text-base font-medium {{ request()->routeIs('about') ? 'text-primary-600 dark:text-blue-400 font-bold' : 'text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white' }}" 
               href="{{ route('about') }}" wire:navigate @click="mobileOpen = false">About</a>
            <a class="block text-base font-medium {{ request()->routeIs('explore.map') ? 'text-primary-600 dark:text-blue-400 font-bold' : 'text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white' }}" 
               href="{{ route('explore.map') }}" wire:navigate @click="mobileOpen = false">Explore</a>
            <a class="block text-base font-medium {{ request()->routeIs('events') ? 'text-primary-600 dark:text-blue-400 font-bold' : 'text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white' }}" 
               href="{{ route('events') }}" wire:navigate @click="mobileOpen = false">Events</a>

            @auth
                <div class="pt-4 mt-4 border-t border-gray-100 dark:border-gray-700">
                    <a class="block text-base font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white mb-4" 
                       href="{{ route('my-bookings') }}" wire:navigate @click="mobileOpen = false">My Bookings</a>
                    <a class="block text-base font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white mb-4" 
                       href="{{ route('profile') }}" wire:navigate @click="mobileOpen = false">My Profile</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-center text-base font-medium py-3 rounded-full bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors">Logout</button>
                    </form>
                </div>
            @endauth

            @guest
                <div class="flex flex-col gap-3 pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('login') }}" wire:navigate @click="mobileOpen = false"
                       class="w-full text-center text-base font-medium py-3 rounded-full bg-primary-600 text-white hover:bg-primary-700 transition-colors">Login / Sign Up</a>
                    <a href="{{ route('register') }}" wire:navigate @click="mobileOpen = false"
                       class="w-full text-center text-base font-medium py-3 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Create Account</a>
                    <a href="{{ route('register_business') }}" wire:navigate @click="mobileOpen = false"
                       class="w-full text-center text-base font-medium py-3 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Register Business</a>
                </div>
            @endguest
        </div>
    </header>

    {{-- Mobile drawer backdrop --}}
    <div x-cloak x-show="mobileOpen" x-transition.opacity
         class="fixed inset-0 z-40 bg-black/50 lg:hidden"
         @click="mobileOpen = false"
         aria-hidden="true"></div>
</div>