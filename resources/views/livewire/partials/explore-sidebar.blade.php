{{-- resources/views/livewire/partials/explore-sidebar.blade.php --}}
<div class="flex h-[calc(100vh-4rem)] md:h-[calc(100vh-5rem)] flex-col bg-white dark:bg-gray-900 mt-16 md:mt-20"
     x-data="{ filterModalOpen: false, mobileOpen: true }">

    {{-- Header --}}
    <div class="shrink-0 border-b border-gray-100 px-4 pb-3 pt-4 dark:border-gray-800">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="flex items-center gap-1.5 text-[9px] font-bold uppercase tracking-[0.18em] text-primary-600 dark:text-blue-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-blue-400"></span>
                    Victorias City
                </p>
                <h2 class="mt-1 text-lg font-extrabold leading-tight tracking-tight text-gray-900 dark:text-white">
                    Explore Destinations
                </h2>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <div class="flex items-center gap-1 rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 dark:border-blue-500/20 dark:bg-blue-500/10">
                    <span class="tabular-nums text-[11px] font-bold text-blue-700 dark:text-blue-300">{{ $this->tenants->count() }}</span>
                    <span class="text-[9px] font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">spots</span>
                </div>

                @if(count($favorites) > 0)
                    <button
                        type="button"
                        wire:click="$toggle('favoritesOnly')"
                        aria-pressed="{{ $favoritesOnly ? 'true' : 'false' }}"
                        @class([
                            'flex items-center gap-1 rounded-full border px-3 py-1.5 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50',
                            'border-rose-200 bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/20' => $favoritesOnly,
                            'border-gray-200 bg-gray-50 hover:border-rose-200 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-rose-500/30' => !$favoritesOnly,
                        ])
                    >
                        <svg class="h-4 w-4" fill="{{ $favoritesOnly ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <span @class([
                            'tabular-nums text-[11px] font-bold',
                            'text-rose-700 dark:text-rose-300' => $favoritesOnly,
                            'text-gray-600 dark:text-gray-400' => !$favoritesOnly,
                        ])>
                            {{ count($favorites) }}
                        </span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Quick nav links --}}
        <div class="mt-3 flex items-center gap-2 text-[10px] font-medium">
            <a href="{{ route('home') }}" wire:navigate
               class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-gray-600 transition-all duration-200 hover:border-primary-300 hover:text-primary-600 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-blue-500/30 dark:hover:text-blue-400">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                Home
            </a>
            @auth
                <a href="{{ route('my-bookings') }}" wire:navigate
                   class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-gray-600 transition-all duration-200 hover:border-primary-300 hover:text-primary-600 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-blue-500/30 dark:hover:text-blue-400">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Reservations
                </a>
            @endauth
        </div>
    </div>

    {{-- Primary Controls (Always Visible) --}}
    <div class="shrink-0 border-b border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex gap-2">
            {{-- Search --}}
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search destinations…"
                    aria-label="Search destinations"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-8 text-xs text-gray-900 placeholder-gray-400 transition focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500 dark:focus:border-blue-500 dark:focus:bg-gray-900 dark:focus:ring-blue-500/20"
                >
                @if($search)
                    <button type="button" wire:click="$set('search','')" class="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 active:scale-95 dark:hover:bg-gray-700" aria-label="Clear search">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>

            {{-- Filter Button --}}
            <button
                type="button"
                @click="filterModalOpen = true"
                @class([
                    'relative flex shrink-0 items-center justify-center rounded-xl border px-3 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2',
                    'border-primary-600 bg-primary-50 text-primary-600 focus-visible:ring-primary-500/50 dark:border-blue-500/50 dark:bg-blue-500/10 dark:text-blue-400' => $this->hasActiveFilters && !$search && !$favoritesOnly, 
                    'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100 focus-visible:ring-gray-500/50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => !($this->hasActiveFilters && !$search && !$favoritesOnly)
                ])
                aria-label="Open filters"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                @if($this->hasActiveFilters)
                    <span class="absolute -right-1 -top-1 flex h-3 w-3 items-center justify-center rounded-full bg-rose-500 ring-2 ring-white dark:ring-gray-900"></span>
                @endif
            </button>
        </div>

        {{-- Active Filters Row (Only visible if filters applied) --}}
        @if($this->hasActiveFilters)
            <div class="scrollbar-hide mt-3 flex snap-x items-center gap-1.5 overflow-x-auto pb-1">
                @if($categoryFilter)
                    <span class="inline-flex shrink-0 snap-start items-center gap-1 rounded-full border border-blue-200 bg-blue-50 py-1 pl-2.5 pr-1 text-[10px] font-semibold text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300">
                        {{ $categoryFilter }}
                        <button type="button" wire:click="$set('categoryFilter','')" class="flex h-4 w-4 items-center justify-center rounded-full hover:bg-blue-200/60 active:scale-95 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"><svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </span>
                @endif
                @if($openNow)
                    <span class="inline-flex shrink-0 snap-start items-center gap-1 rounded-full border border-blue-200 bg-blue-50 py-1 pl-2.5 pr-1 text-[10px] font-semibold text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300">
                        Open Now
                        <button type="button" wire:click="$set('openNow', false)" class="flex h-4 w-4 items-center justify-center rounded-full hover:bg-blue-200/60 active:scale-95 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"><svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </span>
                @endif
                @if($hasOfferings)
                    <span class="inline-flex shrink-0 snap-start items-center gap-1 rounded-full border border-blue-200 bg-blue-50 py-1 pl-2.5 pr-1 text-[10px] font-semibold text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300">
                        Has Offerings
                        <button type="button" wire:click="$set('hasOfferings', false)" class="flex h-4 w-4 items-center justify-center rounded-full hover:bg-blue-200/60 active:scale-95 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"><svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </span>
                @endif
                @if($showEvents)
                    <span class="inline-flex shrink-0 snap-start items-center gap-1 rounded-full border border-purple-200 bg-purple-50 py-1 pl-2.5 pr-1 text-[10px] font-semibold text-purple-700 dark:border-purple-500/20 dark:bg-purple-500/10 dark:text-purple-300">
                        Events
                        <button type="button" wire:click="$set('showEvents', false)" class="flex h-4 w-4 items-center justify-center rounded-full hover:bg-purple-200/60 active:scale-95 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500/50"><svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </span>
                @endif
                <button type="button" wire:click="resetFilters" class="ml-1 shrink-0 whitespace-nowrap text-[10px] font-bold text-gray-400 underline underline-offset-2 transition hover:text-red-500 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 dark:hover:text-red-400">
                    Clear all
                </button>
            </div>
        @endif
    </div>

    {{-- Filter Modal (teleported to body) --}}
    @teleport('body')
    <div x-show="filterModalOpen" x-cloak class="relative z-[100]" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-trap.inert.noscroll="filterModalOpen">
        {{-- Backdrop --}}
        <div x-show="filterModalOpen"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity"
             @click="filterModalOpen = false"></div>

        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                {{-- Modal Panel --}}
                <div x-show="filterModalOpen"
                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="relative w-full max-w-sm transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-2xl transition-all dark:bg-gray-900 dark:ring-1 dark:ring-gray-800">
                    
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white" id="modal-title">Refine Search</h3>
                        <button type="button" @click="filterModalOpen = false" class="rounded-full bg-gray-50 p-1.5 text-gray-500 hover:bg-gray-100 active:scale-95 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500/50 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="space-y-6 px-5 py-5">
                        {{-- Sort By --}}
                        <div>
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sort By</p>
                            <div class="flex gap-1 rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-800">
                                @foreach(['name' => 'A–Z', 'distance' => 'Near Me', 'newest' => 'New'] as $value => $label)
                                    <button
                                        type="button"
                                        wire:click="$set('sortBy','{{ $value }}')"
                                        @class([
                                            'flex-1 rounded-lg py-1.5 text-[10px] font-bold uppercase tracking-wide transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                                            'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-white' => $sortBy === $value,
                                            'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $sortBy !== $value,
                                        ])
                                    >
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Categories --}}
                        <div>
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Category</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    wire:click="$set('categoryFilter','')"
                                    @class([
                                        'rounded-full border px-3 py-1.5 text-[11px] font-semibold transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                                        'border-primary-600 bg-primary-600 text-white shadow-sm shadow-blue-500/20' => blank($categoryFilter),
                                        'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => !blank($categoryFilter),
                                    ])
                                >All</button>
                                @foreach($this->categories as $cat)
                                    <button
                                        type="button"
                                        wire:click="$set('categoryFilter','{{ $cat->type }}')"
                                        @class([
                                            'rounded-full border px-3 py-1.5 text-[11px] font-semibold transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                                            'border-primary-600 bg-primary-600 text-white shadow-sm shadow-blue-500/20' => $categoryFilter === $cat->type,
                                            'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => $categoryFilter !== $cat->type,
                                        ])
                                    >{{ $cat->type }}</button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Features --}}
                        <div>
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Features</p>
                            <div class="flex flex-col gap-3">
                                <label class="flex cursor-pointer items-center justify-between group">
                                    <span class="text-xs font-medium text-gray-700 group-hover:text-gray-900 dark:text-gray-300 dark:group-hover:text-white">Open Now</span>
                                    <input type="checkbox" wire:model.live="openNow" class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                                </label>
                                <label class="flex cursor-pointer items-center justify-between group">
                                    <span class="text-xs font-medium text-gray-700 group-hover:text-gray-900 dark:text-gray-300 dark:group-hover:text-white">Has Offerings</span>
                                    <input type="checkbox" wire:model.live="hasOfferings" class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                                </label>
                                <label class="flex cursor-pointer items-center justify-between group">
                                    <span class="text-xs font-medium text-gray-700 group-hover:text-gray-900 dark:text-gray-300 dark:group-hover:text-white">Upcoming Events</span>
                                    <input type="checkbox" wire:model.live="showEvents" class="h-4 w-4 rounded border-gray-300 bg-gray-50 text-purple-500 focus:ring-purple-500 dark:border-gray-600 dark:bg-gray-800">
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-5 py-4 dark:bg-gray-800/50">
                        <button type="button" @click="filterModalOpen = false" class="w-full rounded-xl bg-primary-600 py-2.5 text-xs font-bold text-white shadow-sm transition-all duration-200 hover:bg-primary-700 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:bg-blue-600 dark:hover:bg-blue-500">
                            Show {{ $this->tenants->count() }} Results
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endteleport

    {{-- Loading skeleton --}}
    <div wire:loading.flex wire:target="search,categoryFilter,openNow,hasOfferings,favoritesOnly,showEvents,sortBy" class="flex-col gap-2 px-4 py-3" aria-hidden="true">
        @for ($i = 0; $i < 5; $i++)
            <div class="flex animate-pulse items-center gap-2.5">
                <div class="h-10 w-10 shrink-0 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
                <div class="flex-1 space-y-1.5">
                    <div class="h-2.5 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-2 w-1/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
            </div>
        @endfor
    </div>

    {{-- Destination List --}}
    <div wire:loading.remove wire:target="search,categoryFilter,openNow,hasOfferings,favoritesOnly,showEvents,sortBy" class="scrollbar-thin scrollbar-track-transparent scrollbar-thumb-gray-200 dark:scrollbar-thumb-gray-700 flex-1 overflow-y-auto py-2">
        @forelse($this->tenants as $tenant)
            @php
                $isFavorite = in_array($tenant->id, $favorites, true);
                $hasDistance = isset($tenant->distance);
                $minPrice = $tenant->min_price ?? null;
            @endphp
            <div
                x-data="{ expanded: false }"
                wire:key="dest-{{ $tenant->id }}"
                @class([
                    'group mx-2 my-1 overflow-hidden rounded-xl border transition-all duration-150',
                    'border-primary-500/30 bg-primary-50/50 ring-1 ring-primary-500/20 dark:border-blue-500/40 dark:bg-blue-500/10 dark:ring-blue-500/30' => $highlightedId === $tenant->id,
                    'border-gray-100 hover:border-gray-200 hover:bg-gray-50 dark:border-gray-800 dark:hover:border-gray-700 dark:hover:bg-gray-800/50' => $highlightedId !== $tenant->id,
                ])
            >
                <div class="flex cursor-pointer items-center gap-2.5 px-2.5 py-2.5"
                     wire:click="flyToTenant({{ $tenant->id }})"
                     wire:loading.class="opacity-60"
                     wire:target="flyToTenant({{ $tenant->id }})"
                     @click="mobileOpen = false">
                    
                    @if($tenant->logo)
                        <img src="{{ asset('storage/'.$tenant->logo) }}" alt="{{ $tenant->name }}" loading="lazy" decoding="async" class="h-10 w-10 shrink-0 rounded-lg border border-gray-200 object-cover dark:border-gray-700">
                    @else
                        <div class="flex h-10 w-10 shrink-0 select-none items-center justify-center rounded-lg text-xs font-black text-white"
                             style="background: linear-gradient(135deg, hsl({{ ($tenant->id * 47) % 360 }},55%,38%) 0%, hsl({{ ($tenant->id * 47) % 360 }},60%,52%) 100%);">
                            {{ strtoupper(substr($tenant->name, 0, 2)) }}
                        </div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold leading-snug text-gray-900 dark:text-white">{{ $tenant->name }}</p>
                        <p class="mt-0.5 text-[10px] font-medium text-gray-500 dark:text-gray-400">{{ $tenant->typeOfTenant?->type ?? 'Business' }}</p>

                        @if($minPrice !== null)
                            <p class="mt-0.5 text-[10px] font-bold text-gray-700 dark:text-gray-300">
                                From ₱{{ number_format($minPrice, 2) }}
                            </p>
                        @endif

                        @if($hasDistance)
                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span class="inline-flex items-center gap-0.5 text-[9px] font-semibold text-gray-500 dark:text-gray-400">
                                    <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="10" r="2" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                                    {{ number_format($tenant->distance, 1) }} km
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="ml-auto flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            wire:click.stop="toggleFavorite({{ $tenant->id }})"
                            @class([
                                'flex h-8 w-8 items-center justify-center rounded-full border transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50',
                                'border-rose-200 bg-rose-50 text-rose-500 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300' => $isFavorite,
                                'border-gray-200 bg-white text-gray-400 hover:border-rose-200 hover:text-rose-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500 dark:hover:border-rose-500/30 dark:hover:text-rose-300' => !$isFavorite,
                            ])
                            aria-label="{{ $isFavorite ? 'Remove from favorites' : 'Save to favorites' }}"
                        >
                            <svg class="h-4 w-4" fill="{{ $isFavorite ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </button>
                        {{-- Quick Directions --}}
                        <button
                            type="button"
                            wire:click.stop="getDirectionsTo({{ $tenant->id }})"
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-400 transition-all duration-200 hover:border-primary-300 hover:text-primary-600 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500 dark:hover:border-blue-500/30 dark:hover:text-blue-400"
                            aria-label="Get directions to {{ $tenant->name }}"
                            title="Directions"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="px-6 py-14 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                    <svg class="h-6 w-6 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="10" r="2" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                </div>
                <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">No destinations match</p>
                <p class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">Try adjusting your filters or search terms.</p>
                <button type="button" wire:click="resetFilters" class="mt-4 inline-flex items-center gap-1 rounded-full bg-primary-600 px-4 py-2 text-[11px] font-bold text-white transition-all duration-200 hover:bg-primary-700 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:bg-blue-600 dark:hover:bg-blue-500">
                    Reset filters
                </button>
            </div>
        @endforelse
    </div>
</div>