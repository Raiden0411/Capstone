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

    #[Url(as: 'sort', history: true)]
    public string $sortBy = 'name';   // name | newest | popular | price_asc | price_desc

    /**
     * Sorts that require the bookings_count aggregate.
     */
    private const SORTS_NEEDING_BOOKINGS = ['popular'];

    /**
     * All non-default sort keys. Guards against typo'd URL params.
     */
    private const SORTS_NON_DEFAULT = ['newest', 'popular', 'price_asc', 'price_desc'];

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

        return Tenant::query()
            ->where('is_active', true)
            ->with(['typeOfTenant:id,type'])
            ->withCount([
                'properties' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'services'   => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            ])
            ->withMin('properties', 'price', fn ($q) => $q->withoutGlobalScope(TenantScope::class))
            ->when(in_array($this->sortBy, self::SORTS_NEEDING_BOOKINGS, true), fn ($q) => $q->withCount([
                'bookings' => fn ($sub) => $sub
                    ->withoutGlobalScope(TenantScope::class)
                    ->whereNotIn('status', [Booking::STATUS_CANCELLED]),
            ]))
            ->when($term, fn ($q) => $q->where(fn ($s) =>
                $s->where('name', 'like', "%$term%")
                  ->orWhere('address', 'like', "%$term%")
                  ->orWhereHas('typeOfTenant', fn ($t) => $t->where('type', 'like', "%$term%"))
            ))
            ->when($this->categoryFilter, fn ($q) =>
                $q->whereHas('typeOfTenant', fn ($s) => $s->where('type', $this->categoryFilter))
            )
            ->when($this->sortBy === 'newest',     fn ($q) => $q->orderByDesc('created_at')->orderBy('name'))
            ->when($this->sortBy === 'popular',    fn ($q) => $q->orderByDesc('bookings_count')->orderBy('name'))
            ->when($this->sortBy === 'price_asc',  fn ($q) => $q->orderByRaw('properties_min_price IS NULL ASC, properties_min_price ASC')->orderBy('name'))
            ->when($this->sortBy === 'price_desc', fn ($q) => $q->orderByRaw('properties_min_price IS NULL ASC, properties_min_price DESC')->orderBy('name'))
            ->when(! in_array($this->sortBy, self::SORTS_NON_DEFAULT, true), fn ($q) => $q->orderBy('name'))
            ->get(['id', 'name', 'slug', 'logo', 'address', 'type_of_tenant_id', 'created_at']);
    }

    #[Computed]
    public function featured()
    {
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
        // Sort is a preference, not a filter — deliberately preserved.
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
            // Respect prefers-reduced-motion: do not auto-advance. (WCAG 2.2.2)
            if (this.prefersReduced) return;
            if (this.heroImages.length <= 1) return;

            // Only start the interval when the tab is actually visible.
            if (!document.hidden) this.startTimer();

            // Pause when the tab is hidden; resume when it returns.
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
            if (document.hidden) return;   // Rule 142
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

    {{-- ═══════════ HERO ═══════════
         Padding bumped from py-14 md:py-20 → py-16 sm:py-20 md:py-24.
         The stat cards moved from a single bordered block into three
         separate glass surfaces inside a horizontal scroll strip that
         fits them on desktop and lets them breathe on mobile. --}}
    <section class="relative overflow-hidden bg-gray-900 py-16 sm:py-20 md:py-24">

        {{-- Rule 69: no <template x-if>. Visibility toggled with :class.
             Decorative images — alt="" + aria-hidden="true" so screen
             readers skip them and don't read five generic alt phrases. --}}
        <div class="absolute inset-0"
             :class="heroImages.length > 0 ? '' : 'hidden'">
            <template x-for="(img, index) in heroImages" :key="img">
                <img :src="img"
                     class="absolute inset-0 h-full w-full object-cover transition-opacity duration-1000"
                     :class="index === heroIndex ? 'opacity-40' : 'opacity-0'"
                     decoding="async"
                     alt=""
                     aria-hidden="true" />
            </template>
        </div>
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900 to-gray-800"
             :class="heroImages.length === 0 ? '' : 'hidden'"></div>

        <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/50 to-black/70"></div>

        {{-- Soft amber corner wash — matches the homepage hero accent. --}}
        <div class="absolute inset-x-0 top-0 h-1/3
                    bg-gradient-to-b from-amber-500/12 via-amber-500/4 to-transparent
                    pointer-events-none" aria-hidden="true"></div>

        <div class="relative z-10 mx-auto flex max-w-7xl flex-col gap-8 px-4 sm:px-6 md:flex-row md:items-end md:justify-between md:gap-12 lg:px-8">
            <div class="max-w-2xl">
                {{-- Editorial eyebrow — amber, matching the platform-wide pattern. --}}
                <p class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-amber-300">
                    <span class="h-px w-4 bg-amber-400" aria-hidden="true"></span>
                    Victorias City
                </p>
                <h1 class="font-display text-4xl font-bold leading-[1.05] tracking-tight text-white sm:text-5xl md:text-6xl">
                    Discover Local<br><em class="italic text-primary-300">Wonders</em>
                </h1>
                <p class="mt-4 max-w-lg text-sm leading-relaxed text-white/70 sm:text-base">
                    Find the perfect destinations, hidden gems, and must-visit attractions throughout the city and its barangays.
                </p>
            </div>

            {{-- Hero stats.
                 Three separate glass cards. On mobile they scroll
                 horizontally (each min-w prevents them from squishing);
                 on `md` they sit side-by-side with no scroll. --}}
            <div class="flex shrink-0 gap-3 overflow-x-auto scrollbar-hide pb-1
                        md:overflow-visible md:pb-0">
                <div class="shrink-0 min-w-[120px] md:min-w-[128px]
                            rounded-2xl border border-white/10 bg-white/[0.06] backdrop-blur-md
                            px-4 py-3.5">
                    <div class="font-display text-3xl font-semibold text-white tabular-nums leading-none">
                        {{ $this->totalCount }}
                    </div>
                    <div class="mt-2 text-[10px] font-bold uppercase tracking-[0.15em] text-white/60">
                        Destinations
                    </div>
                </div>

                <div class="shrink-0 min-w-[120px] md:min-w-[128px]
                            rounded-2xl border border-white/10 bg-white/[0.06] backdrop-blur-md
                            px-4 py-3.5">
                    <div class="font-display text-3xl font-semibold text-white tabular-nums leading-none">
                        {{ $this->categories->count() }}
                    </div>
                    <div class="mt-2 text-[10px] font-bold uppercase tracking-[0.15em] text-white/60">
                        Categories
                    </div>
                </div>

                <div class="shrink-0 min-w-[120px] md:min-w-[128px]
                            rounded-2xl border border-white/10 bg-white/[0.06] backdrop-blur-md
                            px-4 py-3.5">
                    <div class="font-display text-3xl font-semibold text-white tabular-nums leading-none">
                        {{ $this->featured->count() }}
                    </div>
                    <div class="mt-2 text-[10px] font-bold uppercase tracking-[0.15em] text-white/60">
                        Top Picks
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════ STICKY CONTROLS ═══════════
         The public header is now `min-h-16 md:min-h-20` PLUS
         `pt-[env(safe-area-inset-top)]`. On notched iPhones that
         adds ~47–59px of height. The sticky bar must offset by the
         SAME amount so it doesn't slide behind the header.

         Non-notched devices: env() = 0, so top-[calc(4rem+0)] = 64px
         (mobile) and top-[calc(5rem+0)] = 80px (md+) — identical to
         the previous top-16 md:top-20.

         Mobile padding tightened to py-3 / gap-3 so the sticky bar
         eats less vertical space on small screens.
         All interactive controls are h-11 (44px) on mobile and h-9
         (36px) on sm+ — WCAG AAA tap floor on mobile. --}}
    <div class="sticky z-20 border-b border-gray-200 bg-white/95 backdrop-blur
                dark:border-gray-800 dark:bg-gray-900/95
                top-[calc(4rem+env(safe-area-inset-top))]
                md:top-[calc(5rem+env(safe-area-inset-top))]">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 sm:px-6 sm:py-4 md:flex-row md:items-center md:gap-4 lg:px-8">

            {{-- Search.
                 `.input` sets text-base sm:text-sm (iOS-zoom safe).
                 Padding moved off inline `style=""` onto Tailwind
                 utilities so it stays in the class chain. --}}
            <div class="relative max-w-full flex-1 md:max-w-xs">
                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       enterkeyhint="search"
                       autocomplete="off"
                       class="input w-full pl-9 pr-11"
                       placeholder="Search destinations…"
                       aria-label="Search destinations">
                @if($search)
                    {{-- Expand the touch target to 44px via a pseudo-element
                         without changing the visual size. Rule: WCAG 2.5.5. --}}
                    <button type="button"
                            wire:click="$set('search','')"
                            wire:key="clear-search-btn"
                            class="absolute right-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full
                                   text-gray-400 transition hover:bg-gray-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   dark:hover:bg-gray-700
                                   before:absolute before:content-[''] before:-inset-2.5 before:rounded-full
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]"
                            aria-label="Clear search">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>

            {{-- Category Pills — h-11 on mobile (44px), h-9 at sm. --}}
            <div class="flex flex-1 gap-2 overflow-x-auto pb-1 scrollbar-hide">
                @php $allActive = blank($categoryFilter); @endphp
                <button type="button"
                        wire:click="$set('categoryFilter','')"
                        wire:key="cat-pill-all"
                        aria-pressed="{{ $allActive ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-2 h-11 sm:h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                               transition-all duration-200 active:scale-95 shrink-0
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
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
                            class="inline-flex items-center gap-2 h-11 sm:h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                                   transition-all duration-200 active:scale-95 shrink-0
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
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

            {{-- Sort + View toggle --}}
            <div class="flex shrink-0 items-center gap-2">

                {{-- Sort dropdown — h-11 on mobile (44px), h-9 at sm. --}}
                <div class="relative">
                    <select wire:model.live="sortBy"
                            wire:key="sort-select"
                            aria-label="Sort destinations"
                            class="appearance-none h-11 sm:h-9 pl-3 pr-8 rounded-lg border border-gray-200 dark:border-gray-700
                                   bg-white dark:bg-gray-900
                                   text-xs font-semibold text-gray-700 dark:text-gray-300
                                   focus:outline-none focus:ring-2 focus:ring-primary-500/50
                                   transition cursor-pointer
                                   [touch-action:manipulation]">
                        <option value="name">Alphabetical</option>
                        <option value="newest">Newest first</option>
                        <option value="popular">Most booked</option>
                        <option value="price_asc">Price: low → high</option>
                        <option value="price_desc">Price: high → low</option>
                    </select>
                    <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute right-2.5 top-1/2 h-3 w-3 -translate-y-1/2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m6 9 6 6 6-6"/>
                    </svg>
                </div>

                {{-- View toggle — h-11 w-11 on mobile (44×44), h-9 w-9 at sm. --}}
                <div class="flex shrink-0 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <button type="button"
                            wire:click="$set('viewMode','grid')"
                            wire:key="view-toggle-grid"
                            aria-pressed="{{ $viewMode === 'grid' ? 'true' : 'false' }}"
                            class="flex h-11 w-11 sm:h-9 sm:w-9 items-center justify-center transition active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
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
                            class="flex h-11 w-11 sm:h-9 sm:w-9 items-center justify-center transition active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
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

        {{-- Subtle animated progress hairline while Livewire is updating.
             Replaces the plain spinner that used to sit BELOW the grid
             and cause a visible layout jump. This is a zero-shift
             indicator that lives inside the sticky bar's own border. --}}
        <div class="relative h-0.5 overflow-hidden" aria-hidden="true">
            <div wire:loading.delay.shortest
                 wire:target="search,categoryFilter,viewMode,resetFilters,sortBy"
                 class="absolute inset-y-0 left-0 w-1/3 bg-gradient-to-r from-transparent via-primary-500 to-transparent
                        animate-[progressSweep_1.2s_ease-in-out_infinite] motion-reduce:animate-none"
                 style="animation-name: progressSweep;"></div>
        </div>
    </div>

    @push('styles')
        @once
            <style>
                @keyframes progressSweep {
                    0%   { transform: translateX(-100%); }
                    100% { transform: translateX(400%); }
                }
            </style>
        @endonce
    @endpush

    {{-- ═══════════ BODY ═══════════
         x-data="revealOnScroll" — one IntersectionObserver +
         one MutationObserver covers everything inside. Morph-added
         nodes (grid updates, @if toggles) get auto-observed. --}}
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8" x-data="revealOnScroll">

        {{-- ═══════════ FEATURED ═══════════ --}}
        @if(!$this->hasActiveFilters && $this->featured->isNotEmpty())
            <section class="mb-12">
                <div data-reveal class="mb-6 flex items-end justify-between gap-4">
                    <div>
                        <p class="mb-1 flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-amber-600 dark:text-amber-400">
                            <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                            Featured
                        </p>
                        <h2 class="font-display text-2xl font-bold tracking-tight text-gray-900 dark:text-white md:text-3xl">
                            Popular <em class="italic text-primary-600 dark:text-primary-400">Picks</em>
                        </h2>
                    </div>
                    <div class="font-display text-5xl font-light text-gray-200 dark:text-gray-800 tabular-nums leading-none">
                        {{ str_pad($this->featured->count(), 2, '0', STR_PAD_LEFT) }}
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3">
                    @foreach($this->featured as $tenant)
                        @php $img = $tenant->logo ? asset('storage/' . $tenant->logo) : null; @endphp
                        <a href="{{ route('business.offerings', $tenant->slug) }}" wire:navigate wire:key="feat-{{ $tenant->id }}"
                           data-reveal
                           style="--reveal-delay: {{ min($loop->index, 2) * 80 }}ms"
                           class="group relative flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-2xl
                                  shadow-sm hover:shadow-2xl hover:shadow-amber-500/10
                                  ring-1 ring-transparent hover:ring-amber-400/30
                                  transition-all duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]
                                  hover:-translate-y-1
                                  focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                  active:scale-[0.99]
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $tenant->name }}" class="absolute inset-0 h-full w-full object-cover brightness-75 transition duration-700 group-hover:scale-105 group-hover:brightness-90" loading="lazy" decoding="async">
                            @else
                                <div class="absolute inset-0 bg-gradient-to-br from-gray-900 to-gray-700"></div>
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>
                            <div class="relative z-10 p-5">
                                <span class="mb-2 inline-flex items-center gap-1 rounded-full bg-amber-500/20 backdrop-blur-sm px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-200 ring-1 ring-inset ring-amber-400/20">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    Featured
                                </span>
                                <div class="text-xs font-semibold uppercase tracking-wider text-white/60">{{ $tenant->typeOfTenant?->type ?? 'Destination' }}</div>
                                <h3 class="font-display text-xl font-semibold text-white leading-tight">{{ $tenant->name }}</h3>
                                @if($tenant->address)
                                    <p class="mt-1 flex items-start gap-1 text-xs text-white/60">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-3 w-3 shrink-0 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                        <span class="line-clamp-1">{{ $tenant->address }}</span>
                                    </p>
                                @endif
                                <span class="mt-3 inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-amber-300 opacity-0 transition group-hover:opacity-100">
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

        {{-- ═══════════ ALL DESTINATIONS ═══════════ --}}
        <section>
            <div data-reveal class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="mb-1 flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-amber-600 dark:text-amber-400">
                        <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                        {{ $this->hasActiveFilters ? 'Search Results' : 'All Destinations' }}
                    </p>
                    <h2 class="font-display text-2xl font-bold tracking-tight text-gray-900 dark:text-white md:text-3xl">
                        @if($this->hasActiveFilters)
                            <em class="italic text-primary-600 dark:text-primary-400">{{ $this->tenants->count() }}</em> {{ \Illuminate\Support\Str::plural('Spot', $this->tenants->count()) }} Found
                        @else
                            Explore <em class="italic text-primary-600 dark:text-primary-400">Everywhere</em>
                        @endif
                    </h2>
                </div>
                <div class="flex items-center gap-4">
                    @if($this->hasActiveFilters)
                        {{-- Reset Filters — h-11 on mobile (44px), h-9 at sm. --}}
                        <button type="button"
                                wire:click="resetFilters"
                                wire:key="reset-filters-btn"
                                class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-lg
                                       border border-rose-300 dark:border-rose-500/40
                                       bg-white dark:bg-gray-800
                                       text-rose-700 dark:text-rose-300
                                       text-xs font-semibold uppercase tracking-wider
                                       transition-all duration-200 active:scale-95
                                       hover:bg-rose-50 dark:hover:bg-rose-500/10
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Clear
                        </button>
                    @endif
                    <div class="font-display text-5xl font-light text-gray-200 dark:text-gray-800 tabular-nums leading-none">
                        {{ str_pad($this->tenants->count(), 2, '0', STR_PAD_LEFT) }}
                    </div>
                </div>
            </div>

            {{-- Grid.
                 `transition-opacity duration-300` for a smooth fade.
                 `pointer-events-none` while loading so a mid-update tap
                 can't land on a card that's about to be replaced. --}}
            <div class="grid gap-5 sm:gap-6 transition-opacity duration-300
                        {{ $viewMode === 'list' ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' }}"
                 wire:loading.class="opacity-40 pointer-events-none"
                 wire:target="search,categoryFilter,viewMode,resetFilters,sortBy">
                @forelse($this->tenants as $tenant)
                    @php
                        $img  = $tenant->logo ? asset('storage/' . $tenant->logo) : null;
                        $minP = $tenant->properties_min_price;
                        $offeringCount = $tenant->properties_count + $tenant->services_count;
                    @endphp
                    <a href="{{ route('business.offerings', $tenant->slug) }}" wire:navigate wire:key="dest-{{ $tenant->id }}"
                       data-reveal
                       style="--reveal-delay: {{ min($loop->index % 4, 3) * 60 }}ms"
                       class="group flex overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm
                              transition-all duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]
                              hover:-translate-y-1 hover:shadow-lg hover:shadow-primary-500/5
                              hover:border-primary-200 dark:hover:border-primary-500/30
                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                              dark:border-gray-800 dark:bg-gray-900
                              active:scale-[0.99]
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              {{ $viewMode === 'list' ? 'flex-row' : 'flex-col' }}">
                        <div class="relative {{ $viewMode === 'list' ? 'h-auto w-32 shrink-0' : 'aspect-[4/3] w-full' }}">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $tenant->name }}" class="h-full w-full object-cover brightness-90 transition duration-700 group-hover:scale-105 group-hover:brightness-100" loading="lazy" decoding="async">
                            @else
                                <div class="flex h-full w-full items-center justify-center bg-gray-100 dark:bg-gray-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                            @endif
                            @if($tenant->typeOfTenant)
                                <span class="absolute bottom-2 left-2 rounded-full bg-black/70 backdrop-blur-sm px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white">{{ $tenant->typeOfTenant->type }}</span>
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col p-4">
                            <h3 class="font-display text-lg font-semibold leading-tight text-gray-900 transition group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">{{ $tenant->name }}</h3>
                            @if($tenant->address)
                                <p class="mt-1 flex items-start gap-1 text-xs text-gray-500 dark:text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-3 w-3 shrink-0 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                    <span class="line-clamp-2">{{ $tenant->address }}</span>
                                </p>
                            @endif

                            <div class="mt-auto flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-800">
                                <div>
                                    @if($minP !== null)
                                        <div class="font-display text-lg font-semibold text-gray-900 dark:text-white tabular-nums leading-none">
                                            ₱{{ number_format($minP, 0) }}
                                        </div>
                                        <div class="mt-1 text-[10px] uppercase tracking-wider text-gray-400">from / unit</div>
                                    @else
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $offeringCount }} offering{{ $offeringCount !== 1 ? 's' : '' }}</div>
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
                    <div class="col-span-full flex flex-col items-center justify-center text-center gap-3
                                rounded-2xl border border-dashed border-gray-300 dark:border-gray-700
                                bg-gray-50/60 dark:bg-gray-900/40
                                px-6 py-16">
                        <div class="p-3 rounded-2xl bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500 shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <h3 class="font-display text-lg font-semibold text-gray-900 dark:text-white">
                            No destinations found
                        </h3>
                        <p class="max-w-xs text-sm text-gray-500 dark:text-gray-400">
                            Try a different keyword or clear your filters to see everything.
                        </p>
                        @if($this->hasActiveFilters)
                            <button type="button"
                                    wire:click="resetFilters"
                                    class="mt-3 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-lg shadow-primary-600/20
                                           transition-all duration-200 active:scale-95
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Reset Filters
                            </button>
                        @endif
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>