{{-- resources/views/livewire/partials/explore-sidebar.blade.php --}}
@push('styles')
    @once
        <style>
            .sidebar-filter-panel {
                animation: sidebarFilterPanelIn .2s cubic-bezier(.16,1,.3,1);
            }
            @keyframes sidebarFilterPanelIn {
                from { opacity: 0; transform: translateY(-4px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .sidebar-filter-panel { animation: none; }
            }

            .tap-area {
                position: relative;
            }
            .tap-area::before {
                content: '';
                position: absolute;
                inset: -10px;
                border-radius: 9999px;
                pointer-events: auto;
            }
            .tap-area--tight::before {
                inset: -8px;
            }
        </style>
    @endonce
@endpush

@php
    $destinationCount = $this->tenants->count();
    $colors = ['#f97316', '#a855f7', '#3b82f6', '#14b8a6', '#eab308', '#10b981', '#8b5cf6', '#f43f5e'];
@endphp

<div class="relative flex h-full flex-col bg-white dark:bg-gray-900">

    <div class="shrink-0 border-b border-gray-200/80 dark:border-gray-800/80">
        <div class="px-3.5 pt-3 pb-2 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <h2 class="font-display text-base font-semibold leading-tight text-gray-900 dark:text-white truncate">
                    Explore <span class="text-gray-400 dark:text-gray-500 font-normal">Victorias City</span>
                </h2>
            </div>

            <div class="flex shrink-0 items-center gap-1.5">
                <span class="inline-flex items-center gap-1 rounded-full border border-primary-200 bg-primary-50 px-2 py-0.5 dark:border-primary-500/20 dark:bg-primary-500/10"
                      title="{{ $destinationCount }} {{ $destinationCount === 1 ? 'destination' : 'destinations' }}">
                    <span class="tabular-nums text-[11px] font-bold text-primary-700 dark:text-primary-300">{{ $destinationCount }}</span>
                    <span class="text-[8px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">spots</span>
                </span>

                <button type="button"
                        @click="$dispatch('map:close-sidebar')"
                        aria-label="Close filters"
                        class="tap-area lg:hidden flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-gray-500
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               hover:border-gray-300 hover:text-gray-700
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="px-3.5 pb-3 flex items-center gap-x-3 gap-y-1 flex-wrap">
            <label class="inline-flex items-center gap-2 cursor-pointer select-none py-2 -my-1 min-h-11
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                <input type="checkbox" wire:model.live="favoritesOnly" class="sr-only peer">
                <span class="relative inline-block w-8 h-[18px] rounded-full
                             bg-gray-200 dark:bg-gray-700
                             transition-colors duration-200
                             peer-checked:bg-rose-500
                             peer-focus-visible:ring-2 peer-focus-visible:ring-rose-500/50 peer-focus-visible:ring-offset-2 dark:peer-focus-visible:ring-offset-gray-900
                             after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                             after:w-[14px] after:h-[14px] after:rounded-full after:bg-white after:shadow-sm
                             after:transition-transform after:duration-200
                             peer-checked:after:translate-x-[14px]"></span>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider
                             text-gray-500 dark:text-gray-400
                             peer-checked:text-rose-600 dark:peer-checked:text-rose-400
                             transition-colors">
                    <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    Saved
                </span>
            </label>

            <label class="inline-flex items-center gap-2 cursor-pointer select-none py-2 -my-1 min-h-11
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                <input type="checkbox" wire:model.live="showEvents" class="sr-only peer">
                <span class="relative inline-block w-8 h-[18px] rounded-full
                             bg-gray-200 dark:bg-gray-700
                             transition-colors duration-200
                             peer-checked:bg-purple-500
                             peer-focus-visible:ring-2 peer-focus-visible:ring-purple-500/50 peer-focus-visible:ring-offset-2 dark:peer-focus-visible:ring-offset-gray-900
                             after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                             after:w-[14px] after:h-[14px] after:rounded-full after:bg-white after:shadow-sm
                             after:transition-transform after:duration-200
                             peer-checked:after:translate-x-[14px]"></span>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider
                             text-gray-500 dark:text-gray-400
                             peer-checked:text-purple-600 dark:peer-checked:text-purple-400
                             transition-colors">
                    <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/>
                        <line x1="3" y1="10" x2="21" y2="10" stroke-width="2"/>
                    </svg>
                    Events
                </span>
            </label>

            @auth
                <a href="{{ route('my-bookings') }}" wire:navigate
                   class="ml-auto inline-flex items-center gap-1 py-2 -my-1 text-[10px] font-semibold
                          text-gray-500 dark:text-gray-400
                          hover:text-primary-600 dark:hover:text-primary-400
                          transition-colors
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded px-1 -mx-1">
                    My Bookings
                    <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @endauth
        </div>
    </div>

    <div x-data="{ filtersOpen: false }"
         wire:key="explore-filter-bar"
         class="shrink-0 border-b border-gray-200/80 dark:border-gray-800/80 bg-white dark:bg-gray-900">

        <div class="px-3 pt-2.5 pb-2">
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search destinations…"
                    x-ref="searchInput"
                    aria-label="Search destinations"
                    style="padding-left: 2.25rem; padding-right: 2.5rem;"
                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 text-base sm:text-sm text-gray-900 placeholder-gray-400 transition
                           focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20
                           dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500 dark:focus:border-primary-500 dark:focus:bg-gray-900 dark:focus:ring-primary-500/20"
                >
                @if($search)
                    <button type="button" wire:click="$set('search','')"
                            wire:key="clear-search-btn"
                            aria-label="Clear search"
                            class="tap-area absolute right-1.5 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full
                                   text-gray-400 transition
                                   hover:bg-gray-100 hover:text-gray-600 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   dark:hover:bg-gray-700 dark:hover:text-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        <div class="px-3 pb-2.5 flex items-stretch gap-1.5">
            <div class="flex-1 flex gap-0.5 rounded-lg border border-gray-200/80 bg-gray-50 p-0.5 dark:border-gray-700/60 dark:bg-gray-800/60 min-w-0">
                @foreach(['name'=>'A–Z','distance'=>'Near','newest'=>'New'] as $v=>$l)
                    <button
                        type="button"
                        wire:key="sort-{{ $v }}"
                        wire:click="$set('sortBy','{{ $v }}')"
                        @class([
                            'flex-1 min-h-11 inline-flex items-center justify-center rounded-md px-1 py-2 text-[10px] sm:text-xs font-bold uppercase tracking-wide transition-all duration-200 active:scale-95
                             [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                             focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                            'bg-white text-gray-900 shadow-sm dark:bg-gray-600 dark:text-white' => $sortBy === $v,
                            'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $sortBy !== $v,
                        ])
                    >
                        {{ $l }}
                    </button>
                @endforeach
            </div>

            @php
                $activeFilterCount = count(array_filter([
                    $categoryFilter, $openNow, $hasOfferings, $recommendedOnly, $showAmenities,
                ]));
            @endphp

            <button
                type="button"
                @click="filtersOpen = !filtersOpen"
                :aria-expanded="filtersOpen.toString()"
                aria-controls="explore-filters-panel"
                @class([
                    'shrink-0 min-h-11 inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-[10px] sm:text-xs font-bold uppercase tracking-wider transition-all duration-200 active:scale-95
                     [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                     focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                    'border-primary-600 bg-primary-600 text-white shadow-sm' => $activeFilterCount > 0,
                    'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600' => $activeFilterCount === 0,
                ])
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 6h18M6 12h12M10 18h4"/>
                </svg>
                <span>Filters</span>
                @if($activeFilterCount > 0)
                    <span class="ml-0.5 inline-flex items-center justify-center min-w-[14px] h-3.5 rounded-full bg-white/25 px-1 text-[9px] font-bold tabular-nums">
                        {{ $activeFilterCount }}
                    </span>
                @endif
            </button>
        </div>

        @if($categoryFilter || $openNow || $hasOfferings || $recommendedOnly || $showAmenities)
            <div class="px-3 pb-2.5 flex flex-wrap items-center gap-1.5">
                @if($categoryFilter)
                    <span class="inline-flex items-center gap-1 rounded-full border border-primary-200 bg-primary-50 pl-2 pr-0.5 py-1 text-[11px] font-semibold text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300">
                        {{ $categoryFilter }}
                        <button type="button" wire:click="$set('categoryFilter','')" aria-label="Remove category filter"
                                class="tap-area tap-area--tight inline-flex items-center justify-center h-6 w-6 rounded-full
                                       hover:bg-primary-200/60 active:scale-95 transition-all duration-200
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       dark:hover:bg-primary-500/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                @endif
                @if($openNow)
                    <span class="inline-flex items-center gap-1 rounded-full border border-primary-200 bg-primary-50 pl-2 pr-0.5 py-1 text-[11px] font-semibold text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300">
                        Open Now
                        <button type="button" wire:click="$set('openNow',false)" aria-label="Remove open-now filter"
                                class="tap-area tap-area--tight inline-flex items-center justify-center h-6 w-6 rounded-full
                                       hover:bg-primary-200/60 active:scale-95 transition-all duration-200
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       dark:hover:bg-primary-500/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                @endif
                @if($hasOfferings)
                    <span class="inline-flex items-center gap-1 rounded-full border border-primary-200 bg-primary-50 pl-2 pr-0.5 py-1 text-[11px] font-semibold text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300">
                        Has Offers
                        <button type="button" wire:click="$set('hasOfferings',false)" aria-label="Remove has-offerings filter"
                                class="tap-area tap-area--tight inline-flex items-center justify-center h-6 w-6 rounded-full
                                       hover:bg-primary-200/60 active:scale-95 transition-all duration-200
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       dark:hover:bg-primary-500/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                @endif
                @if($recommendedOnly)
                    <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 pl-2 pr-0.5 py-1 text-[11px] font-semibold text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                        Top picks
                        <button type="button" wire:click="$set('recommendedOnly',false)" aria-label="Remove top-picks filter"
                                class="tap-area tap-area--tight inline-flex items-center justify-center h-6 w-6 rounded-full
                                       hover:bg-amber-200/60 active:scale-95 transition-all duration-200
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50
                                       dark:hover:bg-amber-500/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                @endif
                @if($showAmenities)
                    <span class="inline-flex items-center gap-1 rounded-full border border-primary-200 bg-primary-50 pl-2 pr-0.5 py-1 text-[11px] font-semibold text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300">
                        Establishments
                        <button type="button" wire:click="toggleEstablishments" wire:loading.attr="disabled" wire:target="toggleEstablishments" @click="$dispatch('map:prepare-rebuild')"
                                aria-label="Hide establishments"
                                class="tap-area tap-area--tight inline-flex items-center justify-center h-6 w-6 rounded-full
                                       hover:bg-primary-200/60 active:scale-95 transition-all duration-200
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       disabled:opacity-60
                                       dark:hover:bg-primary-500/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                @endif
                <button type="button" wire:click="resetFilters" @click="$dispatch('map:prepare-rebuild')"
                        class="ml-0.5 py-1 px-1 -mx-1 -my-1 text-[11px] font-bold uppercase tracking-wider text-gray-400 underline underline-offset-2 transition
                               hover:text-rose-500 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                               dark:text-gray-500 dark:hover:text-rose-400">
                    Clear
                </button>
            </div>
        @endif

        <div id="explore-filters-panel"
             x-cloak
             :class="filtersOpen ? 'sidebar-filter-panel' : 'hidden'"
             class="border-t border-gray-200/60 dark:border-gray-800/60 px-3 py-3 space-y-3 bg-gray-50/40 dark:bg-gray-800/30">

            <div>
                <p class="text-[9px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">
                    Categories
                </p>
                <div class="scrollbar-hide flex gap-1.5 overflow-x-auto pb-1">
                    <button type="button" wire:click="$set('categoryFilter','')"
                            @class([
                                'flex-shrink-0 rounded-full border px-3 py-2 text-[11px] sm:text-xs font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95
                                 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                                'border-primary-600 bg-primary-600 text-white shadow-sm' => blank($categoryFilter),
                                'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => !blank($categoryFilter),
                            ])>
                        All
                    </button>
                    @foreach($this->categories as $cat)
                        <button type="button" wire:key="cat-{{ $cat->id }}"
                                wire:click="$set('categoryFilter','{{ $cat->type }}')"
                                @class([
                                    'flex-shrink-0 rounded-full border px-3 py-2 text-[11px] sm:text-xs font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95
                                     [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                     focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                                    'border-primary-600 bg-primary-600 text-white shadow-sm' => $categoryFilter === $cat->type,
                                    'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => $categoryFilter !== $cat->type,
                                ])>
                            {{ $cat->type }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="text-[9px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">
                    Quick filters
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" wire:click="$toggle('openNow')"
                            @class([
                                'inline-flex items-center gap-1 rounded-full border px-3 py-2 text-[11px] sm:text-xs font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95
                                 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                                'border-primary-600 bg-primary-600 text-white shadow-sm' => $openNow,
                                'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => !$openNow,
                            ])>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Open
                    </button>
                    <button type="button" wire:click="$toggle('hasOfferings')"
                            @class([
                                'inline-flex items-center gap-1 rounded-full border px-3 py-2 text-[11px] sm:text-xs font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95
                                 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                                'border-primary-600 bg-primary-600 text-white shadow-sm' => $hasOfferings,
                                'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => !$hasOfferings,
                            ])>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        Offers
                    </button>
                    <button type="button" wire:click="$toggle('recommendedOnly')"
                            @class([
                                'inline-flex items-center gap-1 rounded-full border px-3 py-2 text-[11px] sm:text-xs font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95
                                 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50',
                                'border-amber-500 bg-amber-500 text-white shadow-sm' => $recommendedOnly,
                                'border-gray-200 bg-white text-gray-600 hover:border-amber-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => !$recommendedOnly,
                            ])>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="{{ $recommendedOnly ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                        Top picks
                    </button>

                    <button type="button"
                            wire:click="toggleEstablishments"
                            wire:loading.attr="disabled"
                            wire:target="toggleEstablishments"
                            @click="$dispatch('map:prepare-rebuild')"
                            @class([
                                'inline-flex items-center gap-1 rounded-full border px-3 py-2 text-[11px] sm:text-xs font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95
                                 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                 disabled:opacity-60 disabled:cursor-wait',
                                'border-primary-600 bg-primary-600 text-white shadow-sm' => $showAmenities,
                                'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' => !$showAmenities,
                            ])
                            title="Show all establishments inside each tourist spot">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 22V12h6v10"/>
                        </svg>
                        Establishments
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div wire:loading.block wire:target="search,categoryFilter,openNow,hasOfferings,favoritesOnly,recommendedOnly,showEvents,sortBy"
         aria-hidden="true"
         class="flex-1 overflow-hidden px-2.5 py-2">
        @for($i = 0; $i < 7; $i++)
            <div class="mb-2 flex animate-pulse items-center gap-3 rounded-lg px-3 py-3">
                <div class="h-10 w-10 shrink-0 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
                <div class="flex-1 space-y-2">
                    <div class="h-2.5 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-2 w-1/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
            </div>
        @endfor
    </div>

    <div wire:loading.remove wire:target="search,categoryFilter,openNow,hasOfferings,favoritesOnly,recommendedOnly,showEvents,sortBy"
         class="custom-scrollbar flex-1 overflow-y-auto px-2 py-2 overscroll-contain">

        @forelse($this->tenants as $tenant)
            @php
                $tc = $colors[$loop->index % count($colors)];
                $isFav = in_array($tenant->id, $favorites, true);
                $isHL  = $highlightedId === $tenant->id;

                $logo  = $tenant->logo ? '/storage/' . ltrim($tenant->logo, '/') : null;

                $primaryLat = $tenant->coordinates[0]['lat'] ?? null;
                $primaryLng = $tenant->coordinates[0]['lng'] ?? null;

                $driving = $drivingDistances["{$tenant->id}:0"] ?? null;
                $dist = $driving
                    ? (float) $driving['distance_km']
                    : (($userLat && $userLng && $primaryLat !== null && $primaryLng !== null)
                        ? $this->distance($primaryLat, $primaryLng)
                        : null);
                $isDriving = $driving !== null;

                $establishments = collect($tenant->coordinates)
                    ->skip(1)
                    ->map(function ($coord, $originalIndex) {
                        $type     = $coord['type'] ?? '';
                        $category = collect($this->markerCategories)->firstWhere('key', $type);

                        return [
                            'index'    => (int) $originalIndex,
                            'name'     => $coord['name'] ?? 'Unnamed place',
                            'label'    => $category['label'] ?? 'Uncategorized',
                            'color'    => $category['color'] ?? '#94a3b8',
                            'icon_svg' => $category['icon_svg'] ?? null,
                        ];
                    })
                    ->values()
                    ->all();

                $establishmentCount = count($establishments);

                $clickAction = $isHL
                    ? 'closeDetail'
                    : 'flyToTenant(' . $tenant->id . ')';
            @endphp

            <div wire:key="sb-{{ $tenant->id }}"
                 @class([
                     'group mx-1 my-1 rounded-xl border transition-all duration-150 overflow-hidden',
                     'border-primary-500/30 bg-primary-50/50 ring-1 ring-primary-500/20 dark:border-primary-500/40 dark:bg-primary-500/10 dark:ring-primary-500/30' => $isHL,
                     'border-gray-100 hover:border-gray-200 hover:bg-gray-50 dark:border-gray-800 dark:hover:border-gray-700 dark:hover:bg-gray-800/50' => !$isHL,
                 ])>

                <div
                    role="button"
                    tabindex="0"
                    aria-label="{{ $isHL ? 'Close details for ' . $tenant->name : 'View ' . $tenant->name . ' on the map' }}"
                    aria-expanded="{{ $isHL ? 'true' : 'false' }}"
                    class="flex cursor-pointer items-center gap-3 px-3 py-3 min-h-[60px]
                           transition-transform duration-100 active:scale-[0.99]
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-inset"
                    wire:click="{{ $clickAction }}"
                    @keydown.enter.prevent="$dispatch('map:close-sidebar'); $wire.{{ $clickAction }}"
                    @keydown.space.prevent="$dispatch('map:close-sidebar'); $wire.{{ $clickAction }}"
                    @click="$dispatch('map:close-sidebar')">

                    <div class="relative h-11 w-11 flex-shrink-0 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
                         style="background: hsl({{ ($tenant->id*47)%360 }}, 30%, 18%);">
                        @if($logo)
                            <img src="{{ $logo }}" alt="{{ $tenant->name }}" loading="lazy" decoding="async" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center font-display text-sm font-semibold" style="color: {{ $tc }};">
                                {{ strtoupper(substr($tenant->name,0,2)) }}
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $tenant->name }}</p>
                            @if($establishmentCount > 0)
                                <span class="shrink-0 rounded-full bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 text-[9px] font-bold tabular-nums text-gray-500 dark:text-gray-400"
                                      title="{{ $establishmentCount }} establishment{{ $establishmentCount === 1 ? '' : 's' }} nearby">
                                    +{{ $establishmentCount }}
                                </span>
                            @endif
                            @if($isHL)
                                <span class="shrink-0 inline-flex items-center gap-0.5 text-[9px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                    <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
                                    </svg>
                                    Selected
                                </span>
                            @endif
                        </div>
                        <div class="mt-1 flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                            <span class="truncate">{{ $tenant->typeOfTenant?->type ?? 'Business' }}</span>
                            @if($dist !== null && $dist < PHP_FLOAT_MAX)
                                <span class="h-0.5 w-0.5 shrink-0 rounded-full bg-gray-400 dark:bg-gray-600"></span>
                                <span class="shrink-0" title="{{ $isDriving ? 'Driving distance from your location.' : 'Approximate straight-line distance. Share your location for driving distance.' }}">
                                    {{ $isDriving ? '' : '~' }}{{ $this->formatDistance($dist) }}
                                </span>
                            @endif
                        </div>
                        @if($tenant->min_price !== null)
                            <p class="mt-1 text-[11px] font-medium text-gray-600 dark:text-gray-300">
                                From ₱{{ number_format($tenant->min_price, 0) }}
                            </p>
                        @endif
                    </div>

                    <div class="flex flex-shrink-0 items-center gap-1">
                        <button type="button"
                                wire:click.stop="toggleFavorite({{ $tenant->id }})"
                                @keydown.stop
                                @click.stop
                                @class([
                                    'tap-area tap-area--tight flex h-8 w-8 items-center justify-center rounded-full border transition-all duration-200 active:scale-95
                                     [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                     focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50',
                                    'border-rose-200 bg-rose-50 text-rose-500 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300' => $isFav,
                                    'border-gray-200 bg-white text-gray-400 hover:border-rose-200 hover:text-rose-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500 dark:hover:border-rose-500/30 dark:hover:text-rose-300' => !$isFav,
                                ])
                                title="{{ $isFav ? 'Remove from saved' : 'Save' }}"
                                aria-label="{{ $isFav ? 'Remove '.$tenant->name.' from saved places' : 'Save '.$tenant->name.' to your places' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="{{ $isFav ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </button>
                        <button type="button"
                                wire:click.stop="getDirectionsTo({{ $tenant->id }})"
                                @keydown.stop
                                @click.stop="$dispatch('map:close-sidebar')"
                                class="tap-area tap-area--tight flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-400 transition-all duration-200
                                       hover:border-primary-300 hover:text-primary-600 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500 dark:hover:border-primary-500/30 dark:hover:text-primary-400"
                                title="Get directions"
                                aria-label="Get directions to {{ $tenant->name }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                        </button>
                    </div>
                </div>

                @if($isHL && $establishmentCount > 0)
                    <div class="border-t border-primary-200/60 dark:border-primary-500/20 bg-white/60 dark:bg-gray-900/40 px-2 py-2 space-y-0.5">

                        <div class="flex items-center gap-1.5 px-2 pb-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-primary-500 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                {{ $establishmentCount }} establishment{{ $establishmentCount === 1 ? '' : 's' }}
                            </span>
                        </div>

                        @foreach($establishments as $est)
                            <button type="button"
                                    wire:key="est-{{ $tenant->id }}-{{ $est['index'] }}"
                                    wire:click.stop="openDetail({{ $tenant->id }}, {{ $est['index'] }})"
                                    @click.stop="$dispatch('map:close-sidebar')"
                                    title="View {{ $est['name'] }}"
                                    class="w-full flex items-center gap-2.5 px-2 py-2.5 rounded-lg min-h-[48px]
                                           hover:bg-primary-50/70 dark:hover:bg-primary-500/10
                                           transition-colors active:scale-[0.99]
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">

                                <div class="shrink-0 flex h-8 w-8 items-center justify-center rounded-lg border"
                                     style="border-color: {{ $est['color'] }}33; background-color: {{ $est['color'] }}14;">
                                    @if($est['icon_svg'])
                                        <div class="w-4 h-4" style="color: {{ $est['color'] }};">
                                            <x-safe-svg :svg="$est['icon_svg']" class="w-full h-full fill-none stroke-current stroke-2" />
                                        </div>
                                    @else
                                        <span class="text-[10px] font-bold tabular-nums" style="color: {{ $est['color'] }};">
                                            {{ strtoupper(substr($est['label'], 0, 1)) }}
                                        </span>
                                    @endif
                                </div>

                                <div class="flex-1 min-w-0 text-left">
                                    <p class="text-xs font-medium text-gray-900 dark:text-white truncate">{{ $est['name'] }}</p>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate">{{ $est['label'] }}</p>
                                </div>

                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 dark:text-gray-600 shrink-0"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

        @empty
            <div class="px-4 py-12 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <p class="font-display text-base italic text-gray-500 dark:text-gray-400">No destinations found.</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Try a different search or clear your filters.</p>
                <button type="button" wire:click="resetFilters"
                        class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-primary-600 px-4 py-2.5 min-h-[44px] text-sm font-semibold text-white
                               transition-all duration-200 hover:bg-primary-700 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               dark:bg-primary-500 dark:hover:bg-primary-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reset filters
                </button>
            </div>
        @endforelse
    </div>

    <div class="flex shrink-0 items-center justify-between gap-2 border-t border-gray-200/80 bg-white px-3 py-2.5
                pb-[max(0.625rem,env(safe-area-inset-bottom))]
                dark:border-gray-800/80 dark:bg-gray-900">
        <div class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            <span class="font-display text-base font-semibold text-primary-600 dark:text-primary-400">{{ $destinationCount }}</span>
            {{ $destinationCount === 1 ? 'spot' : 'spots' }}
        </div>

        @if($this->hasActiveFilters)
            <button type="button" wire:click="resetFilters" @click="$dispatch('map:prepare-rebuild')"
                    class="inline-flex items-center gap-0.5 py-2 px-1 -mx-1 -my-1 text-[11px] font-semibold uppercase tracking-wider text-gray-500 underline underline-offset-2 transition
                           hover:text-rose-500 active:scale-95
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                           dark:text-gray-400 dark:hover:text-rose-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Clear
            </button>
        @endif

        <button type="button"
                @click="$dispatch('map:close-sidebar')"
                class="lg:hidden ml-auto inline-flex items-center gap-1.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white
                       px-4 py-2.5 min-h-[44px] text-xs font-bold uppercase tracking-wider transition active:scale-95
                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            View Map
        </button>
    </div>

</div>