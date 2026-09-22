{{-- resources/views/public/pages/⚡tourist-spots.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\Booking;
use App\Scopes\TenantScope;

new
#[Layout('layouts.app')]
#[Title('Tourist Spots · Victorias City')]
class extends Component
{
    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'category', history: true)]
    public string $categoryFilter = '';

    #[Url(as: 'view', history: true)]
    public string $viewMode = 'grid'; // grid | list

    /**
     * JSON payload for the Alpine hero carousel.
     * Encoded with JSON_HEX_* flags so it can safely sit inside an
     * HTML data-* attribute — avoids the §6.2 @js()-in-x-data trap.
     */
    #[Computed]
    public function heroImagesJson(): string
    {
        return json_encode(
            $this->heroImages,
            JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG
        );
    }

    #[Computed]
    public function tenants()
    {
        $term = trim($this->search);

        // NOTE: Tenant does NOT use BelongsToTenant (it is the root of the
        // tenant system, so it cannot scope to itself). No withoutGlobalScope
        // call is needed on the ROOT query.
        //
        // HOWEVER — the `properties` and `services` tables DO use
        // BelongsToTenant, and TenantScope applies to the SUBQUERIES that
        // withCount() and withMin() generate. On this public page we must
        // bypass that scope explicitly, or authenticated users (tourists,
        // tenant admins) will see zero counts and null prices. Guests
        // happen to work because TenantScope short-circuits on Auth::check()
        // — that masked the bug in manual testing.
        return Tenant::query()
            ->where('is_active', true)
            ->with(['typeOfTenant:id,type'])
            ->withCount([
                'properties' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'services'   => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            ])
            ->withMin('properties', 'price', fn ($q) => $q->withoutGlobalScope(TenantScope::class))
            ->when($term, fn ($q) => $q->where(fn ($s) =>
                $s->where('name', 'like', "%$term%")
                  ->orWhere('address', 'like', "%$term%")
                  ->orWhereHas('typeOfTenant', fn ($t) => $t->where('type', 'like', "%$term%"))
            ))
            ->when($this->categoryFilter, fn ($q) =>
                $q->whereHas('typeOfTenant', fn ($s) => $s->where('type', $this->categoryFilter))
            )
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'logo', 'address', 'type_of_tenant_id', 'created_at']);
    }

    #[Computed]
    public function featured()
    {
        // Tenant has no global scope. Bookings DO — the withCount bypasses it
        // so counts include every booking on the tenant, not just the current
        // viewer's.
        return Tenant::query()
            ->where('is_active', true)
            ->withCount([
                'bookings' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)
                    ->whereNotIn('status', [Booking::STATUS_CANCELLED]),
            ])
            ->orderByDesc('bookings_count')
            ->orderBy('name')
            ->limit(3)
            ->with(['typeOfTenant:id,type'])
            ->get(['id', 'name', 'slug', 'logo', 'address', 'type_of_tenant_id']);
    }

    #[Computed]
    public function categories()
    {
        // TypeOfTenant has no global scope. The tenants relation points to
        // Tenant, which is also unscoped — no bypass needed.
        return TypeOfTenant::query()
            ->withCount(['tenants' => fn ($q) => $q->where('is_active', true)])
            ->whereHas('tenants', fn ($q) => $q->where('is_active', true))
            ->orderBy('type')
            ->get(['id', 'type']);
    }

    #[Computed]
    public function totalCount(): int
    {
        return Tenant::query()->where('is_active', true)->count();
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->categoryFilter !== '';
    }

    #[Computed]
    public function heroImages(): array
    {
        // Prioritise top picks for the hero carousel.
        $topPicks = $this->featured->filter(fn ($t) => $t->logo);
        if ($topPicks->isNotEmpty()) {
            $images = $topPicks->map(fn ($t) => asset('storage/' . $t->logo))->values()->toArray();
        } else {
            $withLogos = $this->tenants->filter(fn ($t) => $t->logo);
            $images = $withLogos->map(fn ($t) => asset('storage/' . $t->logo))->values()->toArray();
        }

        return array_slice($images, 0, 5);
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'categoryFilter']);
    }
};
?>

@push('styles')
    @once
        <style>
            .scrollbar-hide::-webkit-scrollbar { display: none; }
            .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
    @endonce
@endpush

<div x-data="{
        heroIndex: 0,
        heroImages: JSON.parse($el.dataset.heroImages || '[]'),
        heroTimer: null,
        prefersReduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        onVisibilityChange: null,
        init() {
            // Respect prefers-reduced-motion: do not auto-advance. Users
            // who set this preference see a static first slide. (WCAG 2.2.2)
            if (this.prefersReduced) return;
            if (this.heroImages.length <= 1) return;

            this.startTimer();

            // Pause when the tab is hidden; resume when it returns. Without
            // this, background-tab throttling silently advances the index
            // and the user returns to a mid-cycle slide.
            this.onVisibilityChange = () => {
                if (document.hidden) this.stopTimer();
                else this.startTimer();
            };
            document.addEventListener('visibilitychange', this.onVisibilityChange, { passive: true });
        },
        destroy() {
            this.stopTimer();
            if (this.onVisibilityChange) {
                document.removeEventListener('visibilitychange', this.onVisibilityChange);
                this.onVisibilityChange = null;
            }
        },
        startTimer() {
            if (this.prefersReduced) return;
            if (this.heroImages.length <= 1) return;
            this.stopTimer();
            this.heroTimer = setInterval(() => {
                this.heroIndex = (this.heroIndex + 1) % this.heroImages.length;
            }, 5000);
        },
        stopTimer() {
            if (this.heroTimer) {
                clearInterval(this.heroTimer);
                this.heroTimer = null;
            }
        }
     }"
     data-hero-images="{{ $this->heroImagesJson }}"
     class="min-h-screen">

    {{-- Hero with carousel background --}}
    <section class="relative overflow-hidden bg-gray-900 py-14 md:py-20">

        {{-- Rule 69: no <template x-if>. The carousel wrapper is always in
             the DOM; visibility is toggled with :class. The inner
             <template x-for> is safe (no x-transition modifiers). --}}
        <div class="absolute inset-0"
             :class="heroImages.length > 0 ? '' : 'hidden'">
            <template x-for="(img, index) in heroImages" :key="img">
                <img :src="img"
                     class="absolute inset-0 h-full w-full object-cover transition-opacity duration-1000"
                     :class="index === heroIndex ? 'opacity-40' : 'opacity-0'"
                     decoding="async"
                     alt="Tourist spot background" />
            </template>
        </div>
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900 to-gray-800"
             :class="heroImages.length === 0 ? '' : 'hidden'"></div>

        <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/50 to-black/70"></div>

        <div class="relative z-10 mx-auto flex max-w-7xl flex-col justify-between gap-8 px-6 md:flex-row md:items-center lg:px-8">
            <div class="max-w-xl">
                <p class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-primary-400">
                    <span class="h-px w-4 bg-primary-400"></span>
                    Victorias City
                </p>
                <h1 class="font-display text-4xl font-semibold leading-tight text-white sm:text-5xl md:text-6xl">
                    Discover Local<br><em class="italic text-primary-300">Wonders</em>
                </h1>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-white/60">
                    Find the perfect destinations, hidden gems, and must-visit attractions throughout the city and its barangays.
                </p>
            </div>

            <div class="flex items-center gap-4 sm:gap-6 rounded-2xl border border-primary-500/20 bg-primary-500/10 p-6 backdrop-blur md:flex-col md:gap-4">
                <div>
                    <div class="font-display text-3xl font-medium text-primary-300">{{ $this->totalCount }}</div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-white/50">Destinations</div>
                </div>
                <div class="h-10 w-px bg-primary-500/20 md:h-px md:w-10"></div>
                <div>
                    <div class="font-display text-3xl font-medium text-primary-300">{{ $this->categories->count() }}</div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-white/50">Categories</div>
                </div>
                <div class="h-10 w-px bg-primary-500/20 md:h-px md:w-10"></div>
                <div>
                    <div class="font-display text-3xl font-medium text-primary-300">{{ $this->featured->count() }}</div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-white/50">Top Picks</div>
                </div>
            </div>
        </div>
    </section>

    {{-- Sticky Controls --}}
    {{-- `top-16 md:top-20` matches the public layout's header heights
         (h-16 on mobile, h-20 on desktop). Using bare `top-16` left the
         bar overlapping the header on desktop. --}}
    <div class="sticky top-16 md:top-20 z-20 border-b border-gray-200 bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-4 md:flex-row md:items-center lg:px-8">
            {{-- Search (Rule 107 — `.input` + inline padding-left for the icon) --}}
            <div class="relative max-w-xs flex-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       enterkeyhint="search"
                       autocomplete="off"
                       class="input w-full"
                       style="padding-left: 2.25rem; padding-right: 2.25rem;"
                       placeholder="Search destinations…"
                       aria-label="Search destinations">
                @if($search)
                    <button type="button"
                            wire:click="$set('search','')"
                            wire:key="clear-search-btn"
                            class="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:hover:bg-gray-700"
                            aria-label="Clear search">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>

            {{-- Category Pills (Rule 99 canonical — pill + inline count) --}}
            <div class="flex flex-1 gap-2 overflow-x-auto pb-1 scrollbar-hide">
                @php $allActive = blank($categoryFilter); @endphp
                <button type="button"
                        wire:click="$set('categoryFilter','')"
                        wire:key="cat-pill-all"
                        aria-pressed="{{ $allActive ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-2 h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                               transition-all duration-200 active:scale-95 shrink-0
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $allActive
                                    ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400' }}">
                    <span>All</span>
                    <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                                 {{ $allActive ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                        {{ $this->totalCount }}
                    </span>
                </button>
                @foreach($this->categories as $cat)
                    @php $isActive = $categoryFilter === $cat->type; @endphp
                    <button type="button"
                            wire:click="$set('categoryFilter','{{ $cat->type }}')"
                            wire:key="cat-pill-{{ $cat->id }}"
                            aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                            class="inline-flex items-center gap-2 h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                                   transition-all duration-200 active:scale-95 shrink-0
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   {{ $isActive
                                        ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                        : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400' }}">
                        <span>{{ $cat->type }}</span>
                        <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                                     {{ $isActive ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                            {{ $cat->tenants_count }}
                        </span>
                    </button>
                @endforeach
            </div>

            {{-- View Toggle --}}
            <div class="flex shrink-0 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <button type="button"
                        wire:click="$set('viewMode','grid')"
                        wire:key="view-toggle-grid"
                        aria-pressed="{{ $viewMode === 'grid' ? 'true' : 'false' }}"
                        class="flex h-9 w-9 items-center justify-center transition active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $viewMode === 'grid'
                                    ? 'bg-primary-600 text-white'
                                    : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' }}"
                        title="Grid view"
                        aria-label="Grid view">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </button>
                <button type="button"
                        wire:click="$set('viewMode','list')"
                        wire:key="view-toggle-list"
                        aria-pressed="{{ $viewMode === 'list' ? 'true' : 'false' }}"
                        class="flex h-9 w-9 items-center justify-center transition active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $viewMode === 'list'
                                    ? 'bg-primary-600 text-white'
                                    : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' }}"
                        title="List view"
                        aria-label="List view">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Body --}}
    <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8">

        {{-- Featured Destinations --}}
        @if(!$this->hasActiveFilters && $this->featured->isNotEmpty())
            <section class="mb-12">
                <div class="mb-6 flex items-end justify-between">
                    <div>
                        <p class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">
                            <span class="h-px w-4 bg-primary-600 dark:bg-primary-400"></span>
                            Featured
                        </p>
                        <h2 class="font-display text-2xl font-semibold text-gray-900 dark:text-white md:text-3xl">Popular <em class="italic text-primary-600 dark:text-primary-400">Picks</em></h2>
                    </div>
                    <div class="font-display text-5xl font-light text-gray-200 dark:text-gray-800">{{ str_pad($this->featured->count(), 2, '0', STR_PAD_LEFT) }}</div>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->featured as $tenant)
                        @php
                            $img = $tenant->logo ? asset('storage/' . $tenant->logo) : null;
                        @endphp
                        <a href="{{ route('business.offerings', $tenant->slug) }}" wire:navigate wire:key="feat-{{ $tenant->id }}"
                           class="group relative flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-2xl transition-all duration-300 hover:-translate-y-1 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-[0.99]">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $tenant->name }}" class="absolute inset-0 h-full w-full object-cover brightness-75 transition duration-700 group-hover:scale-105 group-hover:brightness-90" loading="lazy" decoding="async">
                            @else
                                <div class="absolute inset-0 bg-gradient-to-br from-gray-900 to-gray-700"></div>
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                            <div class="relative z-10 p-5">
                                <span class="mb-2 inline-flex items-center gap-1 rounded bg-primary-500/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary-300">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    Featured
                                </span>
                                <div class="text-xs font-semibold uppercase tracking-wider text-white/50">{{ $tenant->typeOfTenant?->type ?? 'Destination' }}</div>
                                <h3 class="font-display text-xl font-semibold text-white">{{ $tenant->name }}</h3>
                                @if($tenant->address)
                                    <p class="mt-1 flex items-start gap-1 text-xs text-white/50">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-3 w-3 shrink-0 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                        {{ $tenant->address }}
                                    </p>
                                @endif
                                <span class="mt-3 inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-primary-400 opacity-0 transition group-hover:opacity-100">
                                    Explore
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="my-10 h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent dark:via-gray-800"></div>
            </section>
        @endif

        {{-- All Destinations --}}
        <section>
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">
                        <span class="h-px w-4 bg-primary-600 dark:bg-primary-400"></span>
                        {{ $this->hasActiveFilters ? 'Search Results' : 'All Destinations' }}
                    </p>
                    <h2 class="font-display text-2xl font-semibold text-gray-900 dark:text-white md:text-3xl">
                        @if($this->hasActiveFilters)
                            <em class="italic text-primary-600 dark:text-primary-400">{{ $this->tenants->count() }}</em> {{ \Illuminate\Support\Str::plural('Spot', $this->tenants->count()) }} Found
                        @else
                            Explore <em class="italic text-primary-600 dark:text-primary-400">Everywhere</em>
                        @endif
                    </h2>
                </div>
                <div class="flex items-center gap-4">
                    @if($this->hasActiveFilters)
                        <button type="button"
                                wire:click="resetFilters"
                                wire:key="reset-filters-btn"
                                class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                                       border border-rose-300 dark:border-rose-500/40
                                       bg-white dark:bg-gray-800
                                       text-rose-700 dark:text-rose-300
                                       text-xs font-semibold uppercase tracking-wider
                                       transition-all duration-200 active:scale-95
                                       hover:bg-rose-50 dark:hover:bg-rose-500/10
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Clear
                        </button>
                    @endif
                    <div class="font-display text-5xl font-light text-gray-200 dark:text-gray-800">{{ str_pad($this->tenants->count(), 2, '0', STR_PAD_LEFT) }}</div>
                </div>
            </div>

            <div class="grid gap-6 {{ $viewMode === 'list' ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' }}"
                 wire:loading.class="opacity-50"
                 wire:target="search,categoryFilter,viewMode,resetFilters">
                @forelse($this->tenants as $tenant)
                    @php
                        $img  = $tenant->logo ? asset('storage/' . $tenant->logo) : null;
                        $minP = $tenant->properties_min_price;
                    @endphp
                    <a href="{{ route('business.offerings', $tenant->slug) }}" wire:navigate wire:key="dest-{{ $tenant->id }}"
                       class="group flex overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:border-gray-800 dark:bg-gray-900 active:scale-[0.99] {{ $viewMode === 'list' ? 'flex-row' : 'flex-col' }}">
                        <div class="relative {{ $viewMode === 'list' ? 'h-auto w-32 shrink-0' : 'aspect-[4/3] w-full' }}">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $tenant->name }}" class="h-full w-full object-cover brightness-90 transition duration-700 group-hover:scale-105 group-hover:brightness-100" loading="lazy" decoding="async">
                            @else
                                <div class="flex h-full w-full items-center justify-center bg-gray-100 dark:bg-gray-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                            @endif
                            @if($tenant->typeOfTenant)
                                <span class="absolute bottom-2 left-2 rounded bg-black/70 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white">{{ $tenant->typeOfTenant->type }}</span>
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col p-4">
                            <h3 class="font-display text-lg font-semibold leading-tight text-gray-900 transition group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">{{ $tenant->name }}</h3>
                            @if($tenant->address)
                                <p class="mt-1 flex items-start gap-1 text-xs text-gray-500 dark:text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-3 w-3 shrink-0 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                    <span class="line-clamp-2">{{ $tenant->address }}</span>
                                </p>
                            @endif

                            <div class="mt-auto flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-800">
                                <div>
                                    @if($minP !== null)
                                        <div class="font-display text-lg font-semibold text-gray-900 dark:text-white">₱{{ number_format($minP, 0) }}</div>
                                        <div class="text-[10px] uppercase tracking-wider text-gray-400">from / unit</div>
                                    @else
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $tenant->properties_count + $tenant->services_count }} offering{{ ($tenant->properties_count + $tenant->services_count) !== 1 ? 's' : '' }}</div>
                                    @endif
                                </div>
                                <span class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-primary-600 opacity-0 transition group-hover:opacity-100 dark:text-primary-400">
                                    Explore
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <h3 class="font-display text-xl italic text-gray-500 dark:text-gray-400">No destinations found.</h3>
                        <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">Try a different keyword or clear your filters.</p>
                        @if($this->hasActiveFilters)
                            <button type="button"
                                    wire:click="resetFilters"
                                    class="mt-4 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Reset Filters
                            </button>
                        @endif
                    </div>
                @endforelse
            </div>

            {{-- Loading indicator --}}
            <div wire:loading.block wire:target="search,categoryFilter,viewMode,resetFilters" class="py-8 text-center">
                <div class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent motion-reduce:animate-none"></div>
            </div>
        </section>
    </div>
</div>