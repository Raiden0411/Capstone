{{-- resources/views/components/headers/admin/superadmin-header.blade.php --}}
@php
    $user          = Auth::user();
    $userAvatarUrl = $user?->avatar ? asset('storage/' . $user->avatar) : null;
    $userInitial   = strtoupper(substr($user?->name ?? 'SA', 0, 1));
    $userRole      = $user?->roles->first();
    $roleLabel     = $userRole ? ucwords(str_replace(['-', '_'], ' ', $userRole->name)) : null;

    /*
     * Derive a human-readable page title from the current route name.
     * Cheaper than a config map and stays correct when new routes are
     * added without updating a header-side registry.
     */
    $routeName = (string) request()->route()?->getName();
    $pageTitle = match (true) {
        $routeName === 'superadmin.dashboard'                        => 'Dashboard',
        $routeName === 'superadmin.analytics'                        => 'Analytics',
        $routeName === 'superadmin.profile'                          => 'My Profile',
        str_starts_with($routeName, 'superadmin.business-applications') => 'Business Applications',
        str_starts_with($routeName, 'superadmin.deletion-requests')     => 'Deletion Requests',
        str_starts_with($routeName, 'superadmin.tenants')               => 'Tenants',
        str_starts_with($routeName, 'superadmin.users')                 => 'Users',
        str_starts_with($routeName, 'superadmin.tenant-types')          => 'Tenant Types',
        str_starts_with($routeName, 'superadmin.roles')                 => 'Roles',
        str_starts_with($routeName, 'superadmin.events')                => 'Events',
        str_starts_with($routeName, 'superadmin.homepage')              => 'Homepage Editor',
        str_starts_with($routeName, 'superadmin.about')                 => 'About Page Editor',
        str_starts_with($routeName, 'superadmin.map-markers')           => 'Map Markers',
        str_starts_with($routeName, 'superadmin.marker-categories')     => 'Marker Categories',
        str_starts_with($routeName, 'superadmin.health')                => 'Health',
        default                                                         => 'Platform',
    };

    /*
     * Canonical dropdown-link class — one source of truth for the two
     * links inside the profile dropdown.
     */
    $dropdownLinkClass = 'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm '
        . 'text-gray-700 transition-all duration-200 '
        . 'hover:bg-gray-100 hover:text-gray-900 active:scale-[0.98] '
        . 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 '
        . 'focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800 '
        . 'dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-white';
@endphp

@push('styles')
    @once
        <style>
            /* Rule 69 replacement — CSS keyframe for the profile dropdown. */
            .superadmin-header-dropdown {
                animation: superadminHeaderDropdownIn .15s cubic-bezier(.16,1,.3,1);
            }
            @keyframes superadminHeaderDropdownIn {
                from { opacity: 0; transform: scale(.96) translateY(-4px); }
                to   { opacity: 1; transform: scale(1) translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .superadmin-header-dropdown { animation: none; }
            }
        </style>
    @endonce
@endpush

<header
    x-data="{
        minified: localStorage.getItem('sidebar_minified') === '1',

        /* Theme state mirrors what the layout's applyTheme() has already
           applied to <html>. Reading localStorage here would desync for
           first-time visitors (hs_theme === null + system prefers dark). */
        dark: document.documentElement.classList.contains('dark'),

        toggleDark() {
            this.dark = ! this.dark;
            localStorage.setItem('hs_theme', this.dark ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', this.dark);
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: this.dark }));
        }
    }"
    @sidebar-minified.window="minified = $event.detail"
    :class="minified ? 'lg:ps-20' : 'lg:ps-64'"
    class="sticky top-0 inset-x-0 z-30 flex w-full flex-wrap md:flex-nowrap md:justify-start
           border-b border-gray-200 bg-white/90 dark:bg-gray-900/90 backdrop-blur
           h-16 md:h-20 text-sm transition-all duration-300 dark:border-gray-700"
>
    <nav class="mx-auto flex w-full basis-full items-center justify-between gap-2 px-4 sm:px-6" aria-label="Super admin header">

        {{-- Left: Mobile sidebar toggle --}}
        <div class="flex items-center gap-2 lg:hidden">
            <button type="button"
                    class="flex size-8 items-center justify-center gap-x-2 rounded-full border border-gray-300 bg-white text-gray-700
                           transition-all duration-200 hover:bg-gray-100 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="$dispatch('toggle-superadmin-sidebar')"
                    aria-label="Toggle navigation">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        {{-- Center: Breadcrumb — current section title. --}}
        {{-- Hidden on mobile (space is tight) but visible from `md` up. --}}
        <div class="hidden md:flex flex-1 items-center gap-2 px-2 min-w-0">
            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Platform</span>
            <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">/</span>
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200 truncate">{{ $pageTitle }}</span>
        </div>

        {{-- Right: Actions --}}
        <div class="ms-auto flex items-center gap-1.5 sm:gap-2">

            {{-- Notifications — Livewire SFC, auto-refreshes --}}
            <livewire:superadmin::partials.notification-bell />

            {{-- Dark mode toggle — Rule 69: :class swap on the icons. --}}
            <button type="button"
                    @click="toggleDark()"
                    class="flex size-9 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-500
                           transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Toggle dark mode">
                <svg xmlns="http://www.w3.org/2000/svg" x-cloak :class="dark ? '' : 'hidden'" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
                </svg>
                <svg xmlns="http://www.w3.org/2000/svg" x-cloak :class="dark ? 'hidden' : ''" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                </svg>
            </button>

            {{-- Profile Dropdown --}}
            <div class="relative"
                 x-data="{ open: false }"
                 @click.outside="open = false"
                 @keydown.escape.window="open = false">
                <button type="button"
                        @click="open = !open"
                        :aria-expanded="open.toString()"
                        aria-haspopup="true"
                        class="flex items-center gap-2 rounded-full px-2 py-1.5 text-gray-600
                               transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white">
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-600 text-xs font-bold text-white">
                        @if($userAvatarUrl)
                            <img src="{{ $userAvatarUrl }}" alt="{{ $user?->name }}" width="24" height="24" loading="lazy" decoding="async" class="h-full w-full object-cover">
                        @else
                            {{ $userInitial }}
                        @endif
                    </div>
                    <span class="hidden max-w-[120px] truncate text-sm font-medium sm:inline">{{ $user?->name ?? 'Super Admin' }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="hidden h-3 w-3 transition-transform sm:block" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- Dropdown Panel — Rule 69: :class toggle + CSS keyframe. --}}
                <div x-cloak
                     :class="open ? 'superadmin-header-dropdown' : 'hidden'"
                     class="absolute right-0 z-50 mt-2 w-60 sm:w-64 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800"
                     role="menu"
                     aria-label="Account menu">

                    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-600 text-sm font-bold text-white">
                                @if($userAvatarUrl)
                                    <img src="{{ $userAvatarUrl }}" alt="{{ $user?->name }}" width="40" height="40" loading="lazy" decoding="async" class="h-full w-full object-cover">
                                @else
                                    {{ $userInitial }}
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $user?->name }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $user?->email }}</p>
                                @if($roleLabel)
                                    <span class="mt-1 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">
                                        {{ $roleLabel }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="space-y-0.5 p-1.5">
                        <a href="{{ route('superadmin.profile') }}" wire:navigate
                           @click="open = false"
                           class="{{ $dropdownLinkClass }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            My Profile
                        </a>

                        <form method="POST" action="{{ route('logout') }}" class="block">
                            @csrf
                            <button type="submit"
                                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-rose-600
                                           transition-all duration-200 hover:bg-rose-50 active:scale-[0.98]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800
                                           dark:text-rose-400 dark:hover:bg-rose-500/10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>