{{-- resources/views/components/headers/public-header.blade.php --}}
@php
    use App\Models\User;

    $siteName = \App\Models\SiteSetting::getValue('site_name', 'Victorias City Tourism');

    $versioned = function (?string $path): ?string {
        if (! $path) return null;
        $full = public_path('storage/' . ltrim($path, '/'));

        return file_exists($full) ? '/storage/' . ltrim($path, '/') . '?v=' . filemtime($full) : null;
    };

    $authUser  = Auth::user();
    $logoUrl   = $versioned(\App\Models\SiteSetting::getValue('site_logo'));
    $avatarUrl = $versioned($authUser?->avatar);
    $initial   = $authUser ? strtoupper(substr($authUser->name, 0, 1)) : '';

    $showRegisterBusiness = ! $authUser || ($authUser instanceof User && $authUser->canRegisterBusiness());
    $registerBusinessUrl  = $authUser
        ? route('register_business')
        : route('register', ['redirect' => route('register_business')]);

    $canSwitchModes = $authUser instanceof User && $authUser->canSwitchModes();
    $isBusinessMode = $canSwitchModes && $authUser->active_mode === User::MODE_BUSINESS;
    $modeTarget     = $isBusinessMode ? User::MODE_TOURIST : User::MODE_BUSINESS;
    $modeLabel      = $isBusinessMode ? 'Switch to Tourist' : 'Switch to Business';

    $navLinks = [
        ['home', 'Home', '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
        ['explore.map', 'Explore', '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>'],
        ['tourist-spots.index', 'Tourist Spots', '<path d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>'],
        ['events', 'Events', '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>'],
        ['about', 'About', '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>'],
    ];

    $accountLinks = array_values(array_filter([
        $canSwitchModes ? ['Business Dashboard', route('tenant.dashboard'), 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V10'] : null,
        ['My Bookings', route('my-bookings'), 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['My Profile', route('profile'), 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        $showRegisterBusiness ? ['Register Business', $registerBusinessUrl, 'M12 4v16m8-8H4'] : null,
    ]));

    $ring   = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50';
    $iconBtn = "grid size-11 shrink-0 place-items-center rounded-full border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 active:scale-95 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white {$ring}";
    $item    = "flex min-h-[44px] items-center gap-3 rounded-xl px-3 text-sm text-gray-700 transition hover:bg-gray-100 hover:text-primary-600 active:scale-95 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-white {$ring}";
    $drawerItem = "flex min-h-[44px] items-center rounded-xl px-3 text-base font-medium transition active:scale-95 {$ring}";
    $label   = 'inline-flex items-center gap-2 px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400';
@endphp

<div x-data="{
        scrolled: false,
        mobileOpen: false,
        userOpen: false,
        dark: document.documentElement.classList.contains('dark'),
        init() {
            const stored = localStorage.getItem('hs_theme');
            if (stored === 'dark' || stored === 'light') {
                this.dark = stored === 'dark';
                document.documentElement.classList.toggle('dark', this.dark);
            }
            this.scrolled = window.scrollY > 10;
            this.$watch('mobileOpen', open => document.body.classList.toggle('overflow-hidden', open && window.innerWidth < 1024));
        },
        toggleTheme() {
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            localStorage.setItem('hs_theme', this.dark ? 'dark' : 'light');
            window.dispatchEvent(new CustomEvent('theme-changed'));
        }
    }"
     @scroll.window.throttle.100ms.passive="scrolled = window.scrollY > 10"
     @resize.window="if (window.innerWidth >= 1024) mobileOpen = false"
     @keydown.escape.window="mobileOpen = false; userOpen = false">

    <header class="glass fixed inset-x-0 top-0 z-50 flex min-h-16 w-full items-center border-x-0 border-t-0 pt-[env(safe-area-inset-top)] transition-shadow duration-300 md:min-h-20"
            :class="scrolled && 'shadow-lg shadow-gray-900/5'">

        <nav class="mx-auto flex w-full max-w-[90rem] items-center justify-between gap-3 ps-[max(1rem,env(safe-area-inset-left))] pe-[max(1rem,env(safe-area-inset-right))] sm:ps-[max(1.5rem,env(safe-area-inset-left))] sm:pe-[max(1.5rem,env(safe-area-inset-right))] lg:ps-[max(2rem,env(safe-area-inset-left))] lg:pe-[max(2rem,env(safe-area-inset-right))]">

            <a href="{{ route('home') }}" wire:navigate
               class="flex min-h-[44px] min-w-0 items-center gap-2.5 rounded-xl transition active:scale-95 {{ $ring }}">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }} logo" decoding="async"
                         class="size-9 shrink-0 rounded-full object-contain ring-1 ring-black/5 shadow-sm dark:ring-white/10 md:size-10">
                @else
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-primary-600 text-base font-bold text-white ring-1 ring-black/5 shadow-sm dark:ring-white/10 md:size-10" role="img" aria-label="{{ $siteName }} logo">
                        {{ strtoupper(substr($siteName, 0, 1)) }}
                    </span>
                @endif
                <span class="truncate font-display text-base font-bold leading-none tracking-tight text-gray-900 dark:text-white sm:text-lg md:text-xl">{{ $siteName }}</span>
            </a>

            <div class="hidden shrink-0 items-center gap-1 lg:flex">
                @foreach($navLinks as [$route, $text])
                    @php $active = request()->routeIs($route); @endphp
                    <a wire:key="desktop-{{ $route }}" href="{{ route($route) }}" wire:navigate
                       @if($active) aria-current="page" @endif
                       class="inline-flex min-h-[44px] items-center rounded-full px-4 text-sm transition active:scale-95 {{ $ring }}
                              {{ $active ? 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white' }}">
                        {{ $text }}
                    </a>
                @endforeach
            </div>

            <div class="flex shrink-0 items-center gap-1.5 md:gap-2">

                @if($canSwitchModes)
                    <form method="POST" action="{{ route('mode.switch') }}" class="hidden md:block">
                        @csrf
                        <input type="hidden" name="mode" value="{{ $modeTarget }}">
                        <button type="submit" @click.stop class="btn-secondary min-h-[44px] px-3.5 py-2 text-xs lg:px-4" aria-label="{{ $modeLabel }}">
                            <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 1l4 4-4 4M3 11V9a4 4 0 014-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 01-4 4H3"/></svg>
                            <span class="hidden lg:inline">{{ $modeLabel }}</span>
                        </button>
                    </form>
                @endif

                @auth
                    <livewire:public::partials.notification-bell />
                @endauth

                <button type="button" @click.stop="toggleTheme()" class="{{ $iconBtn }}" aria-label="Toggle dark mode">
                    <svg x-cloak :class="dark ? '' : 'hidden'" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2m-7.07-15.07 1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                    <svg x-cloak :class="dark ? 'hidden' : ''" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                </button>

                @guest
                    <a href="{{ route('login') }}" wire:navigate class="btn-primary hidden min-h-[44px] px-5 py-2.5 sm:inline-flex">Login / Sign Up</a>
                    @if($showRegisterBusiness)
                        <a href="{{ $registerBusinessUrl }}" wire:navigate class="btn-secondary hidden min-h-[44px] px-5 py-2.5 md:inline-flex">Register Business</a>
                    @endif
                @endguest

                @auth
                    <div class="relative shrink-0 max-sm:static" @click.outside="userOpen = false">
                        <button type="button" @click.stop="userOpen = !userOpen" :aria-expanded="userOpen" aria-haspopup="true"
                                class="flex min-h-[44px] items-center gap-2 rounded-full py-1.5 ps-1.5 pe-1.5
                                       bg-transparent text-gray-700 transition active:scale-95
                                       hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800
                                       sm:bg-primary-600 sm:pe-4 sm:text-white sm:hover:bg-primary-700 sm:dark:hover:bg-primary-700
                                       {{ $ring }}">
                            @if($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="" decoding="async"
                                     class="size-8 shrink-0 rounded-full object-cover ring-1 ring-black/5 shadow-sm dark:ring-white/10 sm:ring-white/30 sm:dark:ring-white/30">
                            @else
                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-primary-600 text-sm font-bold text-white ring-1 ring-black/5 shadow-sm dark:ring-white/10 sm:bg-white/20 sm:ring-white/30 sm:dark:ring-white/30">{{ $initial }}</span>
                            @endif
                            <span class="hidden max-w-[120px] truncate sm:inline">{{ $authUser->name }}</span>
                            <svg class="hidden size-4 shrink-0 transition-transform duration-200 sm:block" :class="userOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m19 9-7 7-7-7"/></svg>
                        </button>

                        <div x-cloak x-show="userOpen"
                             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95 -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                             role="region" aria-label="Account menu"
                             class="glass absolute right-0 z-50 mt-2 w-72 max-w-[calc(100vw-2rem)] origin-top-right overflow-hidden rounded-3xl shadow-2xl
                                    max-sm:fixed max-sm:inset-x-3 max-sm:top-full max-sm:mt-2 max-sm:w-auto max-sm:max-w-none max-sm:origin-top max-sm:rounded-2xl max-sm:max-h-[calc(100dvh-6rem)] max-sm:overflow-y-auto">

                            <div class="flex items-center gap-3 border-b border-gray-200/60 p-4 dark:border-white/10">
                                @if($avatarUrl)
                                    <img src="{{ $avatarUrl }}" alt="{{ $authUser->name }}" decoding="async" class="size-11 shrink-0 rounded-full object-cover ring-1 ring-black/5 dark:ring-white/10">
                                @else
                                    <span class="grid size-11 shrink-0 place-items-center rounded-full bg-primary-600 text-base font-bold text-white">{{ $initial }}</span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $authUser->name }}</p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $authUser->email }}</p>
                                    @php
                                        $badge = $authUser->hasRole('super-admin') ? 'Super Admin'
                                            : ($canSwitchModes ? ($isBusinessMode ? 'Business mode' : 'Tourist mode')
                                            : ($authUser->hasRole('tourist') ? 'Tourist' : null));
                                    @endphp
                                    @if($badge)
                                        <span class="mt-1.5 inline-flex rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">{{ $badge }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="p-2">
                                <p class="{{ $label }}"><span class="h-px w-4 bg-amber-500"></span>Browse</p>
                                @foreach($navLinks as [$route, $text, $icon])
                                    <a wire:key="dropdown-{{ $route }}" href="{{ route($route) }}" wire:navigate @click="userOpen = false"
                                       @if(request()->routeIs($route)) aria-current="page" @endif class="{{ $item }}">
                                        <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">{!! $icon !!}</svg>
                                        {{ $text }}
                                    </a>
                                @endforeach

                                <p class="{{ $label }} mt-1"><span class="h-px w-4 bg-amber-500"></span>Account</p>
                                @foreach($accountLinks as [$text, $url, $icon])
                                    <a href="{{ $url }}" wire:navigate @click="userOpen = false" class="{{ $item }}">
                                        <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                                        {{ $text }}
                                    </a>
                                @endforeach

                                @if($canSwitchModes)
                                    <form method="POST" action="{{ route('mode.switch') }}" class="mt-2 px-1">
                                        @csrf
                                        <input type="hidden" name="mode" value="{{ $modeTarget }}">
                                        <button type="submit" @click.stop class="btn-secondary min-h-[44px] w-full">{{ $modeLabel }}</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t border-gray-200/60 pt-2 dark:border-white/10">
                                    @csrf
                                    <button type="submit" @click.stop class="{{ $item }} w-full text-rose-600 hover:bg-rose-50 hover:text-rose-600 dark:text-rose-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-400">
                                        <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endauth

                <button type="button" @click.stop="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen" aria-label="Toggle navigation" class="{{ $iconBtn }} lg:hidden">
                    <svg x-cloak :class="mobileOpen ? 'hidden' : ''" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                    <svg x-cloak :class="mobileOpen ? '' : 'hidden'" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
        </nav>

        <div x-cloak x-show="mobileOpen"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             data-mobile-drawer role="navigation" aria-label="Mobile navigation"
             class="glass absolute left-0 right-0 top-full z-40 max-h-[calc(100dvh-4rem-env(safe-area-inset-top))] space-y-1 overflow-y-auto overscroll-contain border-x-0 border-t-0 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-4 shadow-lg ps-[max(1rem,env(safe-area-inset-left))] pe-[max(1rem,env(safe-area-inset-right))] sm:ps-[max(1.5rem,env(safe-area-inset-left))] sm:pe-[max(1.5rem,env(safe-area-inset-right))] md:max-h-[calc(100dvh-5rem-env(safe-area-inset-top))]">

            <p class="{{ $label }}"><span class="h-px w-4 bg-amber-500"></span>Browse</p>
            @foreach($navLinks as [$route, $text])
                @php $active = request()->routeIs($route); @endphp
                <a wire:key="mobile-{{ $route }}" href="{{ route($route) }}" wire:navigate @click="mobileOpen = false"
                   @if($active) aria-current="page" @endif
                   class="{{ $drawerItem }} {{ $active ? 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/50' }}">
                    {{ $text }}
                </a>
            @endforeach

            @auth
                <p class="{{ $label }} mt-2"><span class="h-px w-4 bg-amber-500"></span>Account</p>
                @foreach($accountLinks as [$text, $url])
                    <a href="{{ $url }}" wire:navigate @click="mobileOpen = false"
                       class="{{ $drawerItem }} text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/50">{{ $text }}</a>
                @endforeach

                <div class="flex flex-col gap-2 pt-3">
                    @if($canSwitchModes)
                        <form method="POST" action="{{ route('mode.switch') }}">
                            @csrf
                            <input type="hidden" name="mode" value="{{ $modeTarget }}">
                            <button type="submit" @click.stop class="btn-secondary min-h-[44px] w-full">{{ $modeLabel }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" @click.stop class="btn-secondary min-h-[44px] w-full text-rose-600 dark:text-rose-400">Logout</button>
                    </form>
                </div>
            @endauth

            @guest
                <p class="{{ $label }} mt-2"><span class="h-px w-4 bg-amber-500"></span>Get started</p>
                <div class="flex flex-col gap-2 pt-1">
                    <a href="{{ route('login') }}" wire:navigate @click="mobileOpen = false" class="btn-primary min-h-[44px]">Login / Sign Up</a>
                    @if($showRegisterBusiness)
                        <a href="{{ $registerBusinessUrl }}" wire:navigate @click="mobileOpen = false" class="btn-secondary min-h-[44px]">Register Business</a>
                    @endif
                </div>
            @endguest
        </div>
    </header>

    <div x-cloak :class="mobileOpen ? 'opacity-100' : 'pointer-events-none opacity-0'"
         class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm transition-opacity duration-200 lg:hidden"
         @click="mobileOpen = false" aria-hidden="true"></div>
</div>