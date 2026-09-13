{{-- resources/views/livewire/partials/explore-sidebar.blade.php --}}
<div class="relative flex h-full flex-col bg-white dark:bg-gray-900">

    {{-- Neat Header --}}
    <div class="shrink-0 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">
                    <span class="inline-block h-px w-4 bg-primary-400 dark:bg-primary-500"></span>
                    Victorias City
                </p>
                <h2 class="font-display mt-1.5 text-xl font-semibold leading-tight text-gray-900 dark:text-white">
                    Explore Destinations
                </h2>
            </div>

            <div class="flex shrink-0 items-center gap-2 pt-0.5">
                <div class="flex items-center gap-1 rounded-full border border-primary-200 bg-primary-50 px-2.5 py-1 dark:border-primary-500/20 dark:bg-primary-500/10">
                    <span class="tabular-nums text-sm font-semibold text-primary-700 dark:text-primary-300">{{ $this->tenants->count() }}</span>
                    <span class="text-[9px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">spots</span>
                </div>

                @if(count($favorites) > 0)
                    <button
                        type="button"
                        wire:click="$toggle('favoritesOnly')"
                        aria-pressed="{{ $favoritesOnly ? 'true' : 'false' }}"
                        @class([
                            'flex h-8 w-8 items-center justify-center rounded-full border transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-rose-500/50',
                            'border-rose-300 bg-rose-100 text-rose-600 dark:border-rose-500/30 dark:bg-rose-500/20 dark:text-rose-300' => $favoritesOnly,
                            'border-gray-200 bg-gray-50 text-gray-400 hover:border-rose-300 hover:text-rose-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500 dark:hover:border-rose-500/30 dark:hover:text-rose-300' => !$favoritesOnly,
                        ])
                        title="Saved places"
                    >
                        <svg class="h-4 w-4" fill="{{ $favoritesOnly ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </button>
                @endif

                {{-- Mobile close button — only visible on < lg --}}
                <button type="button"
                        @click="mobileOpen = false"
                        aria-label="Close filters"
                        class="lg:hidden flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-gray-500
                               transition-all duration-200 active:scale-95
                               hover:border-gray-300 hover:text-gray-700
                               focus:outline-none focus:ring-2 focus:ring-primary-500/50
                               dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Compact nav links --}}
        <div class="mt-2.5 flex items-center gap-1.5">
            <a href="{{ route('home') }}" wire:navigate
               class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-[10px] font-medium text-gray-600 transition-all duration-200 hover:border-primary-300 hover:text-primary-600 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-primary-500/30 dark:hover:text-primary-400">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                Home
            </a>
            @auth
                <a href="{{ route('my-bookings') }}" wire:navigate
                   class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-[10px] font-medium text-gray-600 transition-all duration-200 hover:border-primary-300 hover:text-primary-600 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-primary-500/30 dark:hover:text-primary-400">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    My Bookings
                </a>
            @endauth
        </div>
    </div>

    {{-- Search + Active Filters --}}
    <div class="shrink-0 border-b border-gray-200 bg-gray-50/50 px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
        <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search…"
                x-ref="searchInput"
                aria-label="Search destinations"
                class="w-full rounded-lg border border-gray-200 bg-white py-1.5 pl-8 pr-8 text-sm text-gray-900 placeholder-gray-400 transition focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500 dark:focus:border-primary-500 dark:focus:bg-gray-900 dark:focus:ring-primary-500/20"
            >
            @if($search)
                <button type="button" wire:click="$set('search','')" class="absolute right-1.5 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:hover:bg-gray-700 dark:hover:text-gray-200" aria-label="Clear search">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            @endif
        </div>

        @if($this->hasActiveFilters)
            <div class="mt-1.5 flex flex-wrap items-center gap-1">
                @if($categoryFilter)
                    <span class="inline-flex items-center gap-0.5 rounded-full border border-primary-200 bg-primary-50 px-2 py-0.5 text-[10px] font-semibold text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300">
                        {{ $categoryFilter }}
                        <button type="button" wire:click="$set('categoryFilter','')" class="flex h-3.5 w-3.5 items-center justify-center rounded-full hover:bg-primary-200/60 active:scale-95 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:hover:bg-primary-500/20">
                            <svg class="h-2 w-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                @endif
                @if($openNow)
                    <span class="inline-flex items-center gap-0.5 rounded-full border border-primary-200 bg-primary-50 px-2 py-0.5 text-[10px] font-semibold text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300">
                        Open Now
                        <button type="button" wire:click="$set('openNow',false)" class="flex h-3.5 w-3.5 items-center justify-center rounded-full hover:bg-primary-200/60 active:scale-95 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:hover:bg-primary-500/20">
                            <svg class="h-2 w-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                @endif
                @if($hasOfferings)
                    <span class="inline-flex items-center gap-0.5 rounded-full border border-primary-200 bg-primary-50 px-2 py-0.5 text-[10px] font-semibold text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300">
                        Has Offers
                        <button type="button" wire:click="$set('hasOfferings',false)" class="flex h-3.5 w-3.5 items-center justify-center rounded-full hover:bg-primary-200/60 active:scale-95 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:hover:bg-primary-500/20">
                            <svg class="h-2 w-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                @endif
                @if($showEvents)
                    <span class="inline-flex items-center gap-0.5 rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-[10px] font-semibold text-purple-700 dark:border-purple-500/20 dark:bg-purple-500/10 dark:text-purple-300">
                        Events
                        <button type="button" wire:click="$set('showEvents',false)" class="flex h-3.5 w-3.5 items-center justify-center rounded-full hover:bg-purple-200/60 active:scale-95 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-purple-500/50 dark:hover:bg-purple-500/20">
                            <svg class="h-2 w-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                @endif
                @if($favoritesOnly)
                    <span class="inline-flex items-center gap-0.5 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                        Saved
                        <button type="button" wire:click="$set('favoritesOnly',false)" class="flex h-3.5 w-3.5 items-center justify-center rounded-full hover:bg-rose-200/60 active:scale-95 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-rose-500/50 dark:hover:bg-rose-500/20">
                            <svg class="h-2 w-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                @endif
                <button type="button" wire:click="resetFilters" class="ml-0.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 underline underline-offset-2 transition hover:text-red-500 active:scale-95 focus:outline-none focus:ring-2 focus:ring-red-500/50 dark:text-gray-500 dark:hover:text-red-400">
                    Clear
                </button>
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="shrink-0 border-b border-gray-200 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-0.5 dark:border-gray-700 dark:bg-gray-800">
            @foreach(['name'=>'A–Z','distance'=>'Near','newest'=>'New','popular'=>'Pop'] as $v=>$l)
                <button
                    type="button"
                    wire:click="$set('sortBy','{{ $v }}')"
                    @class([
                        'flex-1 rounded-md px-1 py-1 text-[9px] font-bold uppercase tracking-wide transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50',
                        'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-white' => $sortBy === $v,
                        'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $sortBy !== $v,
                    ])
                >
                    {{ $l }}
                </button>
            @endforeach
        </div>

        <div class="scrollbar-hide mt-1.5 flex gap-1 overflow-x-auto pb-0.5">
            <button type="button" wire:click="$set('categoryFilter','')"
                    @class([
                        'flex-shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50',
                        'border-primary-600 bg-primary-600 text-white shadow-sm' => blank($categoryFilter),
                        'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => !blank($categoryFilter),
                    ])>
                All
            </button>
            @foreach($this->categories as $cat)
                <button type="button" wire:click="$set('categoryFilter','{{ $cat->type }}')"
                        @class([
                            'flex-shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50',
                            'border-primary-600 bg-primary-600 text-white shadow-sm' => $categoryFilter === $cat->type,
                            'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => $categoryFilter !== $cat->type,
                        ])>
                    {{ $cat->type }}
                </button>
            @endforeach
        </div>

        <div class="mt-1.5 flex flex-wrap gap-1">
            <button type="button" wire:click="$toggle('openNow')"
                    @class([
                        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50',
                        'border-primary-600 bg-primary-600 text-white shadow-sm' => $openNow,
                        'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => !$openNow,
                    ])>
                <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Open
            </button>
            <button type="button" wire:click="$toggle('hasOfferings')"
                    @class([
                        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50',
                        'border-primary-600 bg-primary-600 text-white shadow-sm' => $hasOfferings,
                        'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => !$hasOfferings,
                    ])>
                <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                Offers
            </button>
            <button type="button" wire:click="$toggle('showEvents')"
                    @class([
                        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-purple-500/50',
                        'border-purple-600 bg-purple-600 text-white shadow-sm' => $showEvents,
                        'border-gray-200 bg-gray-50 text-gray-600 hover:border-purple-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => !$showEvents,
                    ])>
                <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/><line x1="3" y1="10" x2="21" y2="10" stroke-width="2"/></svg>
                Events
            </button>
            <button type="button" wire:click="$toggle('recommendedOnly')"
                    @class([
                        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-amber-500/50',
                        'border-amber-500 bg-amber-500 text-white shadow-sm' => $recommendedOnly,
                        'border-gray-200 bg-gray-50 text-gray-600 hover:border-amber-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => !$recommendedOnly,
                    ])>
                <svg class="h-2.5 w-2.5" fill="{{ $recommendedOnly ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                Top
            </button>
        </div>
    </div>

    {{-- Skeleton --}}
    <div wire:loading.block wire:target="search,categoryFilter,openNow,hasOfferings,favoritesOnly,recommendedOnly,showEvents,sortBy"
         class="flex-1 overflow-hidden px-2 py-1">
        @for($i = 0; $i < 7; $i++)
            <div class="mb-1 flex animate-pulse items-center gap-2 rounded-lg px-2 py-1.5">
                <div class="h-8 w-8 shrink-0 rounded-md bg-gray-200 dark:bg-gray-700"></div>
                <div class="flex-1 space-y-1.5">
                    <div class="h-2.5 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-2 w-1/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
            </div>
        @endfor
    </div>

    {{-- Destination list --}}
    <div wire:loading.remove wire:target="search,categoryFilter,openNow,hasOfferings,favoritesOnly,recommendedOnly,showEvents,sortBy"
         class="scrollbar-thin scrollbar-track-transparent scrollbar-thumb-gray-200 dark:scrollbar-thumb-gray-700 flex-1 overflow-y-auto px-1.5 py-1.5 overscroll-contain">

        @forelse($this->tenants as $tenant)
            @php
                $colors = ['#f97316','#a855f7','#3b82f6','#14b8a6','#eab308','#10b981','#8b5cf6','#f43f5e'];
                $tc = $colors[$loop->index % count($colors)];
                $isFav = in_array($tenant->id, $favorites, true);
                $isHL  = $highlightedId === $tenant->id;
                $logo  = $tenant->logo ? asset('storage/'.$tenant->logo) : null;

                $primaryLat = $tenant->coordinates[0]['lat'] ?? null;
                $primaryLng = $tenant->coordinates[0]['lng'] ?? null;
                $dist = ($userLat && $userLng && $primaryLat !== null && $primaryLng !== null)
                    ? $this->distance($primaryLat, $primaryLng)
                    : null;
            @endphp

            <div wire:key="sb-{{ $tenant->id }}"
                 @class([
                     'group mx-0.5 my-0.5 flex cursor-pointer items-center gap-2.5 rounded-lg border px-2.5 py-2 transition-all duration-150',
                     'border-primary-500/30 bg-primary-50/50 ring-1 ring-primary-500/20 dark:border-primary-500/40 dark:bg-primary-500/10 dark:ring-primary-500/30' => $isHL,
                     'border-gray-100 hover:border-gray-200 hover:bg-gray-50 dark:border-gray-800 dark:hover:border-gray-700 dark:hover:bg-gray-800/50' => !$isHL,
                 ])
                 wire:click="flyToTenant({{ $tenant->id }})"
                 @click="mobileOpen = false">

                <div class="relative h-9 w-9 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700"
                     style="background: hsl({{ ($tenant->id*47)%360 }}, 30%, 18%);">
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $tenant->name }}" loading="lazy" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center font-display text-sm font-semibold" style="color: {{ $tc }};">
                            {{ strtoupper(substr($tenant->name,0,2)) }}
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $tenant->name }}</p>
                    <div class="mt-0.5 flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                        <span>{{ $tenant->typeOfTenant?->type ?? 'Business' }}</span>
                        @if($dist !== null && $dist < PHP_FLOAT_MAX)
                            <span class="h-0.5 w-0.5 rounded-full bg-gray-400 dark:bg-gray-600"></span>
                            <span>{{ $this->formatDistance($dist) }}</span>
                        @endif
                    </div>
                    @if($tenant->min_price !== null)
                        <p class="mt-0.5 text-[11px] font-medium text-gray-600 dark:text-gray-300">
                            From ₱{{ number_format($tenant->min_price, 0) }}
                        </p>
                    @endif
                </div>

                <div class="flex flex-shrink-0 items-center gap-0.5">
                    <button type="button"
                            wire:click.stop="toggleFavorite({{ $tenant->id }})"
                            @class([
                                'flex h-7 w-7 items-center justify-center rounded-full border transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-rose-500/50',
                                'border-rose-200 bg-rose-50 text-rose-500 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300' => $isFav,
                                'border-gray-200 bg-white text-gray-400 hover:border-rose-200 hover:text-rose-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500 dark:hover:border-rose-500/30 dark:hover:text-rose-300' => !$isFav,
                            ])
                            title="{{ $isFav ? 'Remove from saved' : 'Save' }}">
                        <svg class="h-3.5 w-3.5" fill="{{ $isFav ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </button>
                    <button type="button"
                            wire:click.stop="getDirectionsTo({{ $tenant->id }})"
                            @click.stop="mobileOpen = false"
                            class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-400 transition-all duration-200 hover:border-primary-300 hover:text-primary-600 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500 dark:hover:border-primary-500/30 dark:hover:text-primary-400"
                            title="Get directions">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                    </button>
                </div>
            </div>

        @empty
            <div class="px-4 py-10 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <p class="font-display text-base italic text-gray-500 dark:text-gray-400">No destinations found.</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Try a different search or clear your filters.</p>
                <button type="button" wire:click="resetFilters"
                        class="mt-3 inline-flex items-center gap-1 rounded-full bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white transition-all duration-200 hover:bg-primary-700 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50 dark:bg-primary-500 dark:hover:bg-primary-400">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset filters
                </button>
            </div>
        @endforelse
    </div>

    {{-- Footer --}}
    <div class="flex shrink-0 items-center justify-between border-t border-gray-200 bg-white px-3 py-1.5 dark:border-gray-800 dark:bg-gray-900">
        <div class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            <span class="font-display text-base font-semibold text-primary-600 dark:text-primary-400">{{ $this->tenants->count() }}</span>
            dest.
        </div>
        @if($this->hasActiveFilters)
            <button type="button" wire:click="resetFilters"
                    class="inline-flex items-center gap-0.5 text-[10px] font-semibold uppercase tracking-wider text-gray-500 underline underline-offset-2 transition hover:text-red-500 active:scale-95 focus:outline-none focus:ring-2 focus:ring-red-500/50 dark:text-gray-400 dark:hover:text-red-400">
                <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Clear
            </button>
        @endif

        {{-- Mobile "View Map" button — closes the sidebar and drops back to the map --}}
        <button type="button"
                @click="mobileOpen = false"
                class="lg:hidden ml-2 inline-flex items-center gap-1 rounded-full bg-primary-600 hover:bg-primary-700 text-white
                       px-3 py-1 text-[10px] font-bold uppercase tracking-wider transition active:scale-95
                       focus:outline-none focus:ring-2 focus:ring-primary-500/50">
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            View Map
        </button>
    </div>

</div>