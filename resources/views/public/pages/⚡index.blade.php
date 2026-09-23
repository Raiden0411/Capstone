{{-- resources/views/public/pages/⚡index.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Models\Tenant;
use App\Models\Event;
use App\Models\Booking;
use App\Models\SiteSetting;
use App\Scopes\TenantScope;
use Illuminate\Support\Str;

new
#[Layout('layouts.app')]
class extends Component
{
    public ?int $homeHighlightedLocation = null;
    public string $searchQuery = '';

    /** Loaded from SiteSetting — never trusted from the client. */
    #[Locked] public array $markerCategories = [];

    public function mount(): void
    {
        $this->markerCategories = SiteSetting::getValue('marker_categories', []);
    }

    #[Computed]
    public function hero(): array
    {
        // Single batched cache read for every hero + discover key.
        $keys = [
            'hero_title', 'hero_subtitle', 'hero_description',
            'hero_background_image',
            'hero_side_image_1', 'hero_side_image_2', 'hero_side_image_3', 'hero_side_image_4',
            'discover_title', 'discover_description',
        ];

        // Versioned batch cache — a single cache entry, invalidated
        // instantly when any of these keys is written via
        // SiteSetting::setValue(). See SiteSetting::getBatch().
        // NOTE: this caches a SCALAR array, not Eloquent models —
        // safe with the database driver.
        $settings = SiteSetting::getBatch($keys, 'homepage_hero');

        $fallbacks = [
            1 => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&q=80&w=600',
            2 => 'https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&q=80&w=600',
            3 => 'https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?auto=format&fit=crop&q=80&w=600',
            4 => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&q=80&w=600',
        ];

        $sideImages = [];
        for ($i = 1; $i <= 4; $i++) {
            $key  = "hero_side_image_{$i}";
            $path = $settings[$key] ?? null;
            $sideImages[$i] = $path ? asset('storage/' . $path) : $fallbacks[$i];
        }

        $backgroundPath = $settings['hero_background_image'] ?? null;
        $background = $backgroundPath
            ? asset('storage/' . $backgroundPath)
            : 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&q=80&w=1600';

        return [
            'title'       => $settings['hero_title'] ?? 'Welcome to the North',
            'subtitle'    => $settings['hero_subtitle'] ?? 'Victorias City',
            'description' => $settings['hero_description'] ?? 'Escape into a world where the air is scented with sugar cane and the mountains hum with hidden waterfalls. A breathtaking sanctuary in Negros Occidental.',
            'background'  => $background,
            'sideImages'  => $sideImages,
            'discoverTitle'       => $settings['discover_title'] ?? 'The City of Smiles & Heritage',
            'discoverDescription' => $settings['discover_description']
                ?? 'Victorias is more than just an industrial hub; it is a blend of natural sanctuary, deep-rooted history, and warm hospitality. Experience the unique charm that makes this city a hidden gem in Western Visayas.',
        ];
    }

    /**
     * Editable content for the final CTA section.
     *
     * Every text field and the background image are stored as
     * SiteSetting rows under the `homepage_cta` cache namespace,
     * so a write through SiteSetting::setValue() invalidates the
     * whole batch in one shot — same pattern as hero().
     *
     * `background` falls back to a working Unsplash landscape if the
     * admin hasn't uploaded a custom image yet. The Blade also carries
     * an `onerror` fallback on the <img> so a broken URL degrades to
     * the gradient rather than showing a broken-image glyph.
     *
     * @return array{eyebrow: string, title: string, description: string, buttonText: string, background: string}
     */
    #[Computed]
    public function finalCta(): array
    {
        $keys = [
            'cta_eyebrow',
            'cta_title',
            'cta_description',
            'cta_button_text',
            'cta_background_image',
        ];

        $settings = SiteSetting::getBatch($keys, 'homepage_cta');

        $backgroundPath = $settings['cta_background_image'] ?? null;

        return [
            'eyebrow'     => $settings['cta_eyebrow']     ?? 'Start Your Journey',
            'title'       => $settings['cta_title']       ?? 'Plan Your Visit',
            'description' => $settings['cta_description'] ?? 'Start your journey today! Discover the best places, experiences, and adventures Victorias City has to offer.',
            'buttonText'  => $settings['cta_button_text'] ?? 'Explore Now',
            'background'  => $backgroundPath
                ? asset('storage/' . $backgroundPath)
                : 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=1920&q=80',
        ];
    }

    /**
     * Small trust-strip numbers rendered under the hero quick actions.
     * Two COUNT queries per page load, on indexed columns. No caching —
     * the numbers should reflect reality within a request, and a fresh
     * COUNT is faster than a cache round-trip for this payload size.
     *
     * Both queries bypass the tenant global scope: a signed-in tenant
     * admin browsing the public homepage must still see platform-wide
     * numbers, not their own tenant's subset.
     *
     * @return array{destinations: int, events: int}
     */
    #[Computed]
    public function heroStats(): array
    {
        $destinations = Tenant::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->count();

        $events = Event::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->count();

        return [
            'destinations' => $destinations,
            'events'       => $events,
        ];
    }

    #[Computed]
    public function popularDestinations()
    {
        // NO Cache::remember — the database cache driver corrupts
        // hydrated Eloquent models on unserialize (PHP 8.5 + Laravel 13).
        // Query is LIMIT 3, sub-10ms. Caching was a premature optimization.
        return Tenant::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->withCount([
                'bookings' => fn ($q) => $q
                    ->withoutGlobalScope(TenantScope::class)
                    ->whereNotIn('status', [Booking::STATUS_CANCELLED]),
            ])
            ->orderByDesc('bookings_count')
            ->orderBy('name')
            ->limit(3)
            ->with(['typeOfTenant:id,type'])
            ->get(['id', 'name', 'slug', 'logo', 'type_of_tenant_id', 'coordinates']);
    }

    #[Computed]
    public function carouselItems()
    {
        // NO Cache::remember — same reason as above.
        return Tenant::withoutGlobalScope(TenantScope::class)
            ->whereNotNull('logo')
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'slug', 'logo']);
    }

    #[Computed]
    public function carouselPayload(): array
    {
        if ($this->carouselItems->isNotEmpty()) {
            return $this->carouselItems->map(function ($tenant) {
                // Precompute the detail URL server-side so the Alpine
                // carousel never has to know the route pattern. Route
                // changes become a one-file edit in routes/web.php.
                return [
                    'name'  => $tenant->name,
                    'slug'  => $tenant->slug,
                    'url'   => $tenant->slug
                        ? route('business.offerings', $tenant->slug)
                        : '#',
                    'image' => $tenant->logo
                        ? asset('storage/' . $tenant->logo)
                        : 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?q=80&w=800&auto=format&fit=crop',
                ];
            })->values()->toArray();
        }

        return [
            ['name' => 'Gawahon Eco-Park',      'url' => '#', 'image' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Victorias Milling Co.', 'url' => '#', 'image' => 'https://images.unsplash.com/photo-1449034446853-66c86144b0ad?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'The Ecotrail',          'url' => '#', 'image' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Nature Reserve',        'url' => '#', 'image' => 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=800&q=80'],
            ['name' => 'Highlands',             'url' => '#', 'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=800&q=80'],
        ];
    }

    /**
     * Parent + sub-marker payload for the homepage preview map.
     *
     * Colour palette is IDENTICAL to ⚡explore-map's — a rotating
     * 8-colour ring indexed by position. Ensures visual parity with
     * the full explore map (Rule 52 — sibling consistency).
     */
    #[Computed]
    public function mapLocations()
    {
        $colors = ['#f97316','#a855f7','#3b82f6','#14b8a6','#eab308','#10b981','#8b5cf6','#f43f5e'];

        return $this->popularDestinations
            ->filter(fn ($t) => !empty($t->coordinates) && count($t->coordinates) > 0)
            ->unique('id')
            ->values()
            ->map(function ($tenant, $index) use ($colors) {
                $type  = $tenant->typeOfTenant?->type ?? 'Business';
                $color = $colors[$index % count($colors)];

                return [
                    'id'          => $tenant->id,
                    'name'        => $tenant->name,
                    'slug'        => $tenant->slug,
                    'type'        => $type,
                    'color'       => $color,
                    'logo'        => $tenant->logo ? asset('storage/' . $tenant->logo) : null,
                    'coordinates' => $tenant->coordinates,
                ];
            })
            ->toArray();
    }

    #[Computed]
    public function featuredEvents()
    {
        // NO Cache::remember — same reason as popularDestinations.
        return Event::withoutGlobalScope(TenantScope::class)
            ->where('featured', true)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->orderBy('start_date')
            ->limit(3)
            ->get([
                'id', 'name', 'type', 'description', 'image_path',
                'start_date', 'end_date', 'tenant_id',
            ]);
    }

    #[Computed]
    public function markerCategoriesByKey()
    {
        return collect($this->markerCategories)->keyBy('key');
    }

    /**
     * Auth state exposed to Blade as a computed property.
     *
     * Why a computed instead of Blade's @auth/@else/@endauth:
     * Livewire v4's ExtendBlade compiler has been observed to
     * mis-pair @auth/@else blocks when the branch body contains
     * multiple elements each carrying wire:navigate — producing
     * an orphaned endforeach in the compiled PHP (the primary
     * cause of the ParseError this file originally tripped).
     * A plain @if/@else on a boolean computed is unambiguous
     * for the compiler and produces the correct structure.
     */
    #[Computed]
    public function isAuthenticated(): bool
    {
        return auth()->check();
    }

    public function flyToLocation(int $index, int $coordIdx = 0): void
    {
        $locations = $this->mapLocations;
        $loc = $locations[$index] ?? null;
        if (!$loc) return;

        $coord = $loc['coordinates'][$coordIdx] ?? $loc['coordinates'][0] ?? null;
        if (!$coord) return;

        $this->homeHighlightedLocation = $index;
        $this->dispatch('map:fly-to', center: [(float) $coord['lng'], (float) $coord['lat']], zoom: 16);
    }

    public function search(): void
    {
        $query = trim($this->searchQuery);

        if ($query === '') {
            return;
        }

        if (mb_strlen($query) > 120) {
            $query = mb_substr($query, 0, 120);
        }

        $this->redirectRoute('explore.map', ['q' => $query], navigate: true);
    }
};
?>

{{--
    Root Alpine scope.

    Owns THREE pieces of scroll-driven UI state, all driven by ONE scroll
    listener so we don't stack multiple rAF loops:

      • progress        0–100, drives the amber progress bar at the top
      • showBackToTop   true after ~1 viewport of scroll
      • showStickySearch true once the hero search bar has scrolled out

    All three respect prefers-reduced-motion: the progress bar still updates
    (it's a status indicator, not an animation) but the back-to-top button
    and sticky search use instant visibility instead of slide transitions.

    Nothing here is a named Alpine factory — it's an inline literal, so it
    can live in the SFC and not violate Rule 119.
--}}
<div
    x-data="{
        progress: 0,
        showBackToTop: false,
        showStickySearch: false,
        _ticking: false,

        init() {
            this._onScroll = this._onScroll.bind(this);
            this._onResize = this._onResize.bind(this);
            window.addEventListener('scroll', this._onScroll, { passive: true });
            window.addEventListener('resize', this._onResize, { passive: true });

            // Observe the hero search bar — when it leaves the viewport,
            // show the sticky compact search below the header.
            this.$nextTick(() => {
                const heroSearch = this.$refs.heroSearch;
                if (!heroSearch || !('IntersectionObserver' in window)) return;
                this._heroObserver = new IntersectionObserver(([entry]) => {
                    this.showStickySearch = !entry.isIntersecting && entry.boundingClientRect.top < 0;
                }, { threshold: 0 });
                this._heroObserver.observe(heroSearch);
            });

            this._onScroll();
        },

        destroy() {
            window.removeEventListener('scroll', this._onScroll);
            window.removeEventListener('resize', this._onResize);
            if (this._heroObserver) this._heroObserver.disconnect();
        },

        _onResize() {
            this._onScroll();
        },

        _onScroll() {
            if (this._ticking) return;
            this._ticking = true;
            requestAnimationFrame(() => {
                const doc = document.documentElement;
                const scrollTop = window.scrollY || doc.scrollTop || 0;
                const docHeight = (doc.scrollHeight - doc.clientHeight) || 1;
                this.progress = Math.max(0, Math.min(100, (scrollTop / docHeight) * 100));
                this.showBackToTop = scrollTop > window.innerHeight * 0.9;
                this._ticking = false;
            });
        },

        submitStickySearch() {
            const q = this.$refs.stickyInput?.value?.trim() || '';
            if (!q) return;
            $wire.set('searchQuery', q);
            $wire.search();
        },

        get reducedMotion() {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }
    }"
    class="relative"
>

    {{-- ═══════════════ SCROLL PROGRESS BAR ═══════════════ --}}
    <div class="fixed top-0 left-0 right-0 h-0.5 z-[60] bg-transparent pointer-events-none"
         aria-hidden="true">
        <div class="h-full bg-amber-500 origin-left"
             :style="`transform: scaleX(${progress / 100}); transition: transform 100ms linear;`"></div>
    </div>

    {{-- ═══════════════ STICKY COMPACT SEARCH ═══════════════ --}}
    <div
        x-cloak
        x-show="showStickySearch"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        :class="reducedMotion && '!transition-none'"
        class="lg:hidden fixed z-30 left-3 right-3
               top-[calc(4rem+env(safe-area-inset-top)+0.5rem)]
               md:top-[calc(5rem+env(safe-area-inset-top)+0.5rem)]"
    >
        <form x-ref="stickyForm"
              @submit.prevent="submitStickySearch()"
              class="flex items-center gap-2 rounded-full bg-white/95 dark:bg-gray-900/95 backdrop-blur-md
                     border border-gray-200/80 dark:border-gray-700/80
                     shadow-lg shadow-gray-900/10
                     pl-4 pr-1.5 py-1.5">
            <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input x-ref="stickyInput"
                   type="text"
                   maxlength="120"
                   autocomplete="off"
                   enterkeyhint="search"
                   placeholder="Search destinations…"
                   aria-label="Search destinations"
                   class="flex-1 min-w-0 h-10 bg-transparent outline-none text-sm
                          text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400">
            <button type="submit"
                    class="shrink-0 h-10 w-10 rounded-full flex items-center justify-center
                           bg-primary-600 hover:bg-primary-700 text-white
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </form>
    </div>

    {{-- ═══════════════ BACK TO TOP ═══════════════ --}}
    <button
        type="button"
        x-cloak
        x-show="showBackToTop"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-3"
        :class="reducedMotion && '!transition-none'"
        @click="window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' })"
        aria-label="Back to top"
        class="fixed z-40 right-4
               bottom-[max(1rem,env(safe-area-inset-bottom))]
               sm:right-6 sm:bottom-[max(1.5rem,env(safe-area-inset-bottom))]
               w-11 h-11 rounded-full flex items-center justify-center
               bg-gray-900 dark:bg-white text-white dark:text-gray-900
               shadow-xl shadow-gray-900/25
               transition-transform active:scale-90
               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/60 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
        </svg>
    </button>

    {{-- ═══════════════ MAIN CONTENT ═══════════════ --}}
    <main class="relative z-10">

        {{-- ══════════ 1. HERO ══════════ --}}
        <section class="relative w-full min-h-[100svh] overflow-hidden bg-gray-900 flex flex-col">
            <img src="{{ $this->hero['background'] }}"
                 alt=""
                 aria-hidden="true"
                 class="absolute inset-0 w-full h-full object-cover"
                 loading="eager"
                 decoding="async"
                 fetchpriority="high"
                 width="1600"
                 height="900">
            <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/35 to-black/85"></div>

            <div class="relative z-10 flex flex-col items-center justify-center flex-1
                        px-4 sm:px-6 lg:px-12 pt-16 pb-12 sm:pt-20 sm:pb-16 text-center">

                <p class="text-white/75 font-semibold tracking-[0.35em] uppercase
                          text-[11px] sm:text-xs md:text-sm mb-4">
                    {{ $this->hero['title'] }}
                </p>

                <h1 class="text-white font-display font-bold leading-[1.05] tracking-tight
                           text-4xl sm:text-5xl md:text-6xl lg:text-7xl
                           max-w-4xl">
                    {{ $this->hero['subtitle'] }}
                </h1>

                <p class="mt-4 sm:mt-5 max-w-2xl text-sm sm:text-base md:text-lg leading-relaxed
                          text-white/85 line-clamp-3 sm:line-clamp-none">
                    {{ $this->hero['description'] }}
                </p>

                <form wire:submit="search"
                      x-ref="heroSearch"
                      class="mt-7 sm:mt-9 w-full max-w-3xl">

                    {{-- MOBILE: stacked --}}
                    <div class="sm:hidden flex flex-col gap-2.5
                                rounded-2xl bg-white/95 dark:bg-gray-800/95
                                backdrop-blur border border-white/20 dark:border-gray-700
                                shadow-2xl p-2.5
                                focus-within:ring-4 focus-within:ring-primary-500/20
                                transition-shadow duration-200">
                        <div class="flex items-center gap-2 px-3 py-2.5
                                    rounded-xl bg-gray-50 dark:bg-gray-900/60">
                            <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text"
                                   wire:model="searchQuery"
                                   maxlength="120"
                                   autocomplete="off"
                                   enterkeyhint="search"
                                   placeholder="Search destinations…"
                                   aria-label="Search destinations"
                                   class="w-full h-8 text-base text-gray-900 dark:text-white
                                          outline-none bg-transparent placeholder-gray-500 dark:placeholder-gray-400">
                        </div>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="search"
                                class="w-full h-11 rounded-xl bg-primary-600 hover:bg-primary-700
                                       text-white text-sm font-bold
                                       transition-all duration-200 active:scale-[0.98]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="search">Search</span>
                            <span wire:loading wire:target="search" class="inline-flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Searching…
                            </span>
                        </button>
                    </div>

                    {{-- TABLET & DESKTOP: single-row pill --}}
                    <div class="hidden sm:flex items-center
                                bg-white/95 dark:bg-gray-800/95 backdrop-blur
                                border-2 border-primary-600 dark:border-primary-500
                                shadow-2xl rounded-full pl-6 pr-2 h-16 md:h-20
                                transition-shadow duration-200
                                focus-within:ring-4 focus-within:ring-primary-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 text-gray-400 dark:text-gray-500 mr-3 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text"
                               wire:model="searchQuery"
                               maxlength="120"
                               autocomplete="off"
                               enterkeyhint="search"
                               placeholder="Search destinations, attractions, or activities..."
                               aria-label="Search destinations, attractions, or activities"
                               class="w-full h-full text-base md:text-lg text-gray-900 dark:text-white
                                      outline-none bg-transparent placeholder-gray-500 dark:placeholder-gray-400">
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="search"
                                class="ml-4 px-8 py-3 md:px-10 md:py-3.5 rounded-full
                                       bg-primary-600 hover:bg-primary-700 text-white
                                       text-sm md:text-base font-bold shrink-0
                                       transition-all duration-200 active:scale-95
                                       focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="search">Search</span>
                            <span wire:loading wire:target="search" class="inline-flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Searching…
                            </span>
                        </button>
                    </div>
                </form>

                {{-- ── QUICK ACTIONS ──
                     Glass pills inside the hero, right below the search.
                     No backdrop-blur (perf); neutral white/alpha only. --}}
                <div class="mt-6 sm:mt-7 flex flex-wrap items-center justify-center gap-2 sm:gap-2.5 max-w-3xl">
                    {{-- Explore Map --}}
                    <a href="{{ route('explore.map') }}" wire:navigate
                       class="group inline-flex items-center gap-2 rounded-full
                              bg-white/10 hover:bg-white/20
                              border border-white/20 hover:border-white/40
                              text-white text-xs sm:text-sm font-semibold
                              pl-3 pr-3.5 sm:pl-3.5 sm:pr-4 py-2 min-h-[38px] sm:min-h-[40px]
                              transition-all duration-200 active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        Explore Map
                    </a>

                    {{-- Tourist Spots --}}
                    <a href="{{ route('tourist-spots.index') }}" wire:navigate
                       class="group inline-flex items-center gap-2 rounded-full
                              bg-white/10 hover:bg-white/20
                              border border-white/20 hover:border-white/40
                              text-white text-xs sm:text-sm font-semibold
                              pl-3 pr-3.5 sm:pl-3.5 sm:pr-4 py-2 min-h-[38px] sm:min-h-[40px]
                              transition-all duration-200 active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                        </svg>
                        Tourist Spots
                    </a>

                    {{-- Events --}}
                    <a href="{{ route('events') }}" wire:navigate
                       class="group inline-flex items-center gap-2 rounded-full
                              bg-white/10 hover:bg-white/20
                              border border-white/20 hover:border-white/40
                              text-white text-xs sm:text-sm font-semibold
                              pl-3 pr-3.5 sm:pl-3.5 sm:pr-4 py-2 min-h-[38px] sm:min-h-[40px]
                              transition-all duration-200 active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                        </svg>
                        Events
                    </a>

                    {{-- My Bookings (auth) / Sign In (guest).
                         Uses @if($this->isAuthenticated) instead of
                         @auth / @else / @endauth — see isAuthenticated()
                         in the PHP class for why. --}}
                    @if($this->isAuthenticated)
                        <a href="{{ route('my-bookings') }}" wire:navigate
                           class="group inline-flex items-center gap-2 rounded-full
                                  bg-white/10 hover:bg-white/20
                                  border border-white/20 hover:border-white/40
                                  text-white text-xs sm:text-sm font-semibold
                                  pl-3 pr-3.5 sm:pl-3.5 sm:pr-4 py-2 min-h-[38px] sm:min-h-[40px]
                                  transition-all duration-200 active:scale-95
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/>
                            </svg>
                            My Bookings
                        </a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate
                           class="group inline-flex items-center gap-2 rounded-full
                                  bg-white/10 hover:bg-white/20
                                  border border-white/20 hover:border-white/40
                                  text-white text-xs sm:text-sm font-semibold
                                  pl-3 pr-3.5 sm:pl-3.5 sm:pr-4 py-2 min-h-[38px] sm:min-h-[40px]
                                  transition-all duration-200 active:scale-95
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                            Sign In
                        </a>
                    @endif
                </div>

                {{-- ── TRUST STRIP ──
                     @php in BLOCK form, not inline @php(...) — the inline
                     form produces malformed PHP under Livewire v4's
                     ExtendBlade compiler. --}}
                @php
                    $stats = $this->heroStats;
                @endphp
                @if($stats['destinations'] > 0 || $stats['events'] > 0)
                    <div class="mt-6 sm:mt-8 flex flex-wrap items-center justify-center
                                gap-x-3 gap-y-2 sm:gap-x-5
                                text-[11px] sm:text-xs text-white/65 font-medium">
                        @if($stats['destinations'] > 0)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-1 w-1 rounded-full bg-white/50" aria-hidden="true"></span>
                                <strong class="text-white/90 font-semibold tabular-nums">{{ $stats['destinations'] }}+</strong>
                                <span>Destinations</span>
                            </span>
                        @endif
                        @if($stats['events'] > 0)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-1 w-1 rounded-full bg-white/50" aria-hidden="true"></span>
                                <strong class="text-white/90 font-semibold tabular-nums">{{ $stats['events'] }}+</strong>
                                <span>Events</span>
                            </span>
                        @endif
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-1 w-1 rounded-full bg-white/50" aria-hidden="true"></span>
                            <strong class="text-white/90 font-semibold">100%</strong>
                            <span>Local</span>
                        </span>
                    </div>
                @endif
            </div>

            {{-- Scroll indicator — hidden on landscape phones (no room) --}}
            <div class="hidden landscape:hidden sm:flex absolute bottom-8 left-1/2 -translate-x-1/2 z-20
                        animate-bounce motion-reduce:animate-none
                        w-6 h-10 border-2 border-white/40 rounded-full items-start justify-center p-1"
                 aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-2 h-3 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
            </div>
        </section>

        {{-- ══════════ 2. POPULAR PICKS ══════════ --}}
        <section class="max-w-6xl px-4 sm:px-6 lg:px-8 mx-auto mt-16 md:mt-24 mb-12 md:mb-16">
            <div class="flex items-end justify-between mb-6 md:mb-8 gap-4">
                <div>
                    <p class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-amber-600 dark:text-amber-400">
                        <span class="h-px w-4 bg-amber-500"></span>
                        Featured
                    </p>
                    <h2 class="text-2xl md:text-3xl font-display font-semibold text-gray-900 dark:text-white">
                        Popular <em class="italic text-primary-600 dark:text-primary-400">Picks</em>
                    </h2>
                </div>
                <a href="{{ route('explore.map') }}" wire:navigate
                   class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-700
                          dark:text-primary-400 dark:hover:text-primary-300
                          transition-all duration-200
                          focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded
                          active:scale-95 shrink-0
                          min-h-[44px] px-2 -my-2">
                    View All
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6">
                @forelse($this->popularDestinations as $tenant)
                    @php
                        $cardImage = $tenant->logo
                            ? asset('storage/' . $tenant->logo)
                            : 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800&auto=format&fit=crop';
                    @endphp
                    <a href="{{ route('business.offerings', $tenant->slug) }}" wire:navigate
                       wire:key="popular-{{ $tenant->id }}"
                       class="group relative block rounded-2xl overflow-hidden
                              aspect-[4/5]
                              bg-gray-200 dark:bg-gray-800 shadow-sm
                              hover:shadow-2xl transition-all duration-300
                              transform hover:-translate-y-1
                              active:scale-[0.98]
                              focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:outline-none">
                        <img src="{{ $cardImage }}" alt="{{ $tenant->name }}"
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-700"
                             loading="lazy"
                             decoding="async"
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800&auto=format&fit=crop'">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>
                        <div class="absolute bottom-0 p-4 md:p-5 text-left">
                            <h3 class="text-white font-bold text-lg md:text-xl leading-tight">{{ $tenant->name }}</h3>
                            <p class="text-white/80 text-xs md:text-sm mt-0.5">{{ $tenant->typeOfTenant?->type ?? 'Destination' }}</p>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full flex flex-col items-center justify-center text-center py-12 gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <p class="text-gray-500 dark:text-gray-400">No destinations yet.</p>
                        <a href="{{ route('explore.map') }}" wire:navigate
                           class="inline-flex items-center gap-1 rounded-full bg-primary-600 hover:bg-primary-700
                                  text-white text-sm font-semibold px-4 py-2 min-h-[44px]
                                  transition active:scale-95
                                  focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Browse the map
                        </a>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- ══════════ 3. DISCOVER ══════════ --}}
        <section class="relative px-4 sm:px-6 lg:px-8 py-16 md:py-24 text-white overflow-hidden
                        [content-visibility:auto] [contain-intrinsic-size:auto_420px]">
            <img src="https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1600&q=80"
                 alt=""
                 aria-hidden="true"
                 class="absolute inset-0 object-cover w-full h-full opacity-30"
                 loading="lazy"
                 decoding="async">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-900/95 to-slate-800"></div>
            <div class="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-amber-900/15 to-transparent"></div>

            <div class="relative z-10 grid items-center max-w-6xl grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 mx-auto">
                <div>
                    <h2 class="mb-4 text-2xl md:text-3xl font-display font-bold leading-snug">
                        {{ $this->hero['discoverTitle'] }}
                    </h2>
                    <p class="max-w-sm text-sm md:text-base leading-relaxed text-slate-200">
                        {{ $this->hero['discoverDescription'] }}
                    </p>
                </div>

                <div>
                    {{-- Mobile scroll gallery --}}
                    <div class="md:hidden -mx-4 px-4 flex gap-3 overflow-x-auto snap-x snap-mandatory
                                scrollbar-hide pb-2">
                        @foreach($this->hero['sideImages'] as $index => $image)
                            <img src="{{ $image }}"
                                 alt=""
                                 aria-hidden="true"
                                 class="shrink-0 snap-start w-40 h-40 object-cover rounded-2xl shadow-lg"
                                 loading="lazy"
                                 decoding="async"
                                 wire:key="hero-side-m-{{ $index }}">
                        @endforeach
                    </div>

                    {{-- Tablet & desktop grid --}}
                    <div class="hidden md:grid grid-cols-2 gap-3 md:gap-4">
                        @foreach($this->hero['sideImages'] as $index => $image)
                            <img src="{{ $image }}"
                                 alt=""
                                 aria-hidden="true"
                                 class="object-cover w-full aspect-square rounded-xl shadow-lg"
                                 loading="lazy"
                                 decoding="async"
                                 wire:key="hero-side-{{ $index }}">
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ══════════ 4. MOST VISITED (CAROUSEL) ══════════ --}}
        @php
            $carouselJson = json_encode(
                $this->carouselPayload,
                JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG
            );
        @endphp
        <section wire:ignore
                 class="w-full py-16 md:py-20 bg-white dark:bg-gray-900 overflow-hidden"
                 x-data="{
                    items: JSON.parse($el.dataset.carouselItems || '[]'),
                    active: 0,
                    interval: null,
                    observer: null,
                    touchStartX: 0,
                    touchEndX: 0,
                    showSwipeHint: false,
                    prefersReduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                    init() {
                        if (this.prefersReduced) {
                            return;
                        }

                        if (window.matchMedia('(hover: none)').matches && this.items.length > 1) {
                            this.showSwipeHint = true;
                            setTimeout(() => { this.showSwipeHint = false; }, 3500);
                        }

                        if (!('IntersectionObserver' in window)) {
                            this.startAutoPlay();
                            return;
                        }

                        this.observer = new IntersectionObserver((entries) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting && !document.hidden) {
                                    this.startAutoPlay();
                                } else {
                                    this.stopAutoPlay();
                                }
                            });
                        }, { threshold: 0.15 });
                        this.observer.observe(this.$el);

                        document.addEventListener('visibilitychange', () => {
                            if (document.hidden) this.stopAutoPlay();
                        }, { passive: true });
                    },
                    destroy() {
                        this.stopAutoPlay();
                        if (this.observer) this.observer.disconnect();
                    },
                    startAutoPlay() {
                        if (this.prefersReduced) return;
                        if (this.interval) clearInterval(this.interval);
                        this.interval = setInterval(() => this.goTo(this.active + 1), 4000);
                    },
                    stopAutoPlay() {
                        if (this.interval) clearInterval(this.interval);
                        this.interval = null;
                    },
                    goTo(i) {
                        this.active = (i + this.items.length) % this.items.length;
                    },
                    handleTouchStart(e) {
                        this.touchStartX = e.changedTouches[0].screenX;
                        this.stopAutoPlay();
                        this.showSwipeHint = false;
                    },
                    handleTouchEnd(e) {
                        this.touchEndX = e.changedTouches[0].screenX;
                        const diff = this.touchStartX - this.touchEndX;
                        if (Math.abs(diff) > 50) {
                            if (diff > 0) this.goTo(this.active + 1);
                            else this.goTo(this.active - 1);
                        }
                        this.startAutoPlay();
                    },
                    getPositionStyle(index) {
                        const length = this.items.length;
                        if (length === 0) return { display: 'none' };
                        let offset = (index - this.active + length) % length;
                        if (offset > length / 2) offset -= length;
                        const absOffset = Math.abs(offset);
                        const maxSlots  = Math.min(Math.floor((length - 1) / 2), 2);
                        const base = {
                            position: 'absolute',
                            top: '50%',
                            transition: 'width 0.55s cubic-bezier(0.4,0,0.2,1), height 0.55s cubic-bezier(0.4,0,0.2,1), transform 0.55s cubic-bezier(0.4,0,0.2,1), opacity 0.55s cubic-bezier(0.4,0,0.2,1), left 0.55s cubic-bezier(0.4,0,0.2,1), right 0.55s cubic-bezier(0.4,0,0.2,1)',
                            overflow: 'hidden',
                            willChange: 'width, height, transform, opacity',
                        };
                        if (offset === 0) {
                            return { ...base, left: '50%', width: '60%', height: '100%', transform: 'translate(-50%, -50%)', zIndex: 30, opacity: 1 };
                        }
                        if (absOffset > maxSlots) {
                            return { ...base, left: '50%', width: '0%', height: '0%', transform: 'translate(-50%, -50%)', zIndex: 0, opacity: 0 };
                        }
                        const isRight = offset > 0;
                        const isInner = absOffset === 1;
                        const innerWidth  = maxSlots >= 2 ? 28 : 36;
                        const outerWidth  = 20;
                        const innerHeight = maxSlots >= 2 ? 80 : 86;
                        const outerHeight = 62;
                        const width   = isInner ? innerWidth  : outerWidth;
                        const height  = isInner ? innerHeight : outerHeight;
                        const edgePct = isInner ? (maxSlots >= 2 ? 8 : 4) : 0;
                        const pushX   = isInner ? '0%' : (isRight ? '22%' : '-22%');
                        const opacity = isInner ? 0.85 : 0.5;
                        const z       = isInner ? 20 : 10;
                        return {
                            ...base,
                            ...(isRight ? { right: edgePct + '%' } : { left: edgePct + '%' }),
                            width: width + '%',
                            height: height + '%',
                            transform: `translate(${pushX}, -50%)`,
                            zIndex: z,
                            opacity: opacity,
                        };
                    }
                 }"
                 data-carousel-items="{{ $carouselJson }}"
                 @mouseenter="stopAutoPlay()"
                 @mouseleave="startAutoPlay()"
                 @touchstart.passive="handleTouchStart($event)"
                 @touchend="handleTouchEnd($event)">

            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center mb-10 md:mb-14">
                <p class="text-primary-600 dark:text-primary-400 font-bold tracking-[0.2em] uppercase text-xs mb-2">Popular Spots</p>
                <h2 class="text-2xl md:text-3xl font-display font-bold text-gray-900 dark:text-white">Most Visited Places</h2>
                <div class="w-24 h-1 bg-primary-600 dark:bg-primary-500 mx-auto rounded-full mt-5"></div>
                <p class="max-w-xl mx-auto mt-3 text-sm md:text-base font-medium text-gray-600 dark:text-gray-300">
                    Discover the most popular destinations in Victorias City and experience the places visitors love the most.
                </p>
            </div>

            <div class="relative flex items-center justify-center max-w-6xl mx-auto
                        h-[280px] sm:h-[350px] md:h-[450px] px-4 sm:px-6 lg:px-8">

                <div x-cloak
                     x-show="showSwipeHint"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     :class="reducedMotion && '!transition-none'"
                     class="absolute inset-x-0 bottom-0 z-40 flex items-center justify-center gap-4 pointer-events-none select-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white/70 animate-pulse motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-white/70">Swipe</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white/70 animate-pulse motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>

                <template x-for="(item, index) in items" :key="index">
                    <div class="absolute rounded-3xl overflow-hidden shadow-xl"
                         :style="getPositionStyle(index)">
                        <a :href="item.url"
                           class="block h-full w-full focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-3xl">
                            <img :src="item.image" :alt="item.name" class="object-cover w-full h-full" loading="lazy" decoding="async">
                            <div :class="index === active ? 'opacity-100' : 'opacity-0'"
                                 class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end justify-center pb-4 transition-opacity duration-500 pointer-events-none">
                                <span class="text-white font-semibold text-sm md:text-lg" x-text="item.name"></span>
                            </div>
                        </a>
                    </div>
                </template>
            </div>

            <div class="flex justify-center items-center gap-4 mt-8">
                <button type="button" @click="goTo(active - 1)"
                        class="w-11 h-11 flex items-center justify-center rounded-full
                               bg-gray-100 dark:bg-gray-800 text-gray-500
                               hover:text-primary-600 dark:hover:text-primary-400
                               transition-colors
                               focus-visible:ring-2 focus-visible:ring-primary-500/50
                               active:scale-95"
                        aria-label="Previous slide">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>

                <div class="flex items-center gap-2">
                    <template x-for="(item, index) in items" :key="`dot-${index}`">
                        <button type="button" @click="goTo(index)"
                                :aria-label="'Go to slide ' + (index + 1)"
                                :aria-current="index === active ? 'true' : 'false'"
                                :class="index === active ? 'w-3 h-3 bg-primary-600' : 'w-2 h-2 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400'"
                                class="rounded-full transition-all duration-300
                                       focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       active:scale-95
                                       [touch-action:manipulation]"></button>
                    </template>
                </div>

                <button type="button" @click="goTo(active + 1)"
                        class="w-11 h-11 flex items-center justify-center rounded-full
                               bg-gray-100 dark:bg-gray-800 text-gray-500
                               hover:text-primary-600 dark:hover:text-primary-400
                               transition-colors
                               focus-visible:ring-2 focus-visible:ring-primary-500/50
                               active:scale-95"
                        aria-label="Next slide">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </section>

        {{-- ══════════ 5. EXPLORE VICTORIAS CITY (MAP) ══════════ --}}
        <section class="max-w-7xl px-4 sm:px-6 lg:px-8 mx-auto mb-16 md:mb-24"
                 x-data
                 x-init="if (typeof Alpine.store('mapZoom') === 'undefined') Alpine.store('mapZoom', 13)"
                 x-on:map:zoom-changed.window="Alpine.store('mapZoom', Number($event.detail?.zoom) || 13)">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
                <div>
                    <span class="text-sm font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400 mb-2 block">
                        Interactive Directory
                    </span>
                    <h2 class="text-3xl md:text-4xl font-display font-bold text-gray-900 dark:text-white tracking-tight">
                        Explore Victorias City
                    </h2>
                </div>
                <a href="{{ route('explore.map') }}" wire:navigate
                   class="group inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[44px]
                          text-sm font-bold text-primary-700 dark:text-primary-300
                          bg-primary-50 dark:bg-primary-900/30
                          hover:bg-primary-100 dark:hover:bg-primary-900/50
                          rounded-full transition-all duration-300
                          focus-visible:ring-2 focus-visible:ring-primary-500/50
                          active:scale-95">
                    Open Full Map
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>

            <div class="grid items-start grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
                <div class="relative bg-white dark:bg-gray-800 rounded-[2rem] p-3 md:p-4 shadow-xl
                            border border-gray-100 dark:border-gray-700
                            h-[350px] sm:h-[450px] md:h-[500px] group">

                    <div wire:key="home-map-wrapper"
                         class="h-full"
                         x-data="{
                             _configured: false,
                             setupMap(m) {
                                 if (!m || this._configured) return;
                                 try {
                                     m.setRenderWorldCopies(false);
                                     m.setMaxBounds([[122.0, 9.5], [124.0, 11.8]]);
                                     m.setMinZoom(12);
                                     this._configured = true;
                                 } catch (e) { /* noop */ }
                             }
                         }"
                         x-init="setupMap(window.__lastMapInstance)"
                         x-on:maplibre:captured.window="setupMap($event.detail?.map)">
                        <x-map
                            id="home-map"
                            :center="[123.07391289720677, 10.900736693923502]"
                            :zoom="13"
                            height="100%"
                            provider="carto-voyager"
                            theme="auto"
                            class="rounded-3xl overflow-hidden shadow-inner bg-gray-50 dark:bg-gray-900"
                        >
                            <x-map-controls
                                :zoom="true"
                                :compass="true"
                                :locate="false"
                                :fullscreen="true"
                                :scale="false"
                                position="top-right"
                            />

                            @foreach($this->mapLocations as $locIndex => $loc)
                                @php
                                    $parentCoord = $loc['coordinates'][0] ?? null;
                                    $coordCount  = count($loc['coordinates']);
                                    $nearbyCount = max(0, $coordCount - 1);
                                    $subCoords   = array_slice($loc['coordinates'], 1);
                                @endphp

                                @if($parentCoord)
                                    <x-map-marker
                                        :key="'home-t-' . $loc['id']"
                                        wire:key="home-parent-{{ $loc['id'] }}"
                                        :lat="$parentCoord['lat']"
                                        :lng="$parentCoord['lng']"
                                        :color="$loc['color']"
                                        id="home-marker-{{ $locIndex }}"
                                        anchor="bottom"
                                    >
                                        <x-marker-content>
                                            <div x-data="{
                                                    get focused() { return $wire.homeHighlightedLocation === {{ $locIndex }}; },
                                                    get zoomedIn() { return Number(Alpine.store('mapZoom')) >= 15; },
                                                    get nearbyVisible() { return this.focused || this.zoomedIn; }
                                                 }"
                                                 class="flex flex-col items-center cursor-pointer group transition-transform duration-200"
                                                 wire:click="flyToLocation({{ $locIndex }}, 0)">
                                                <div class="relative flex h-11 w-11 items-center justify-center rounded-full border-2
                                                            bg-white shadow-lg transition-transform group-hover:scale-110 dark:bg-gray-900"
                                                     :class="focused ? 'ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-gray-900' : ''"
                                                     style="border-color: {{ $loc['color'] }};">
                                                    @if($loc['logo'])
                                                        <img src="{{ $loc['logo'] }}" alt="{{ $loc['name'] }}"
                                                             class="h-full w-full rounded-full object-cover" loading="lazy" decoding="async" width="44" height="44">
                                                    @else
                                                        <span class="text-xs font-bold text-gray-900 dark:text-white">
                                                            {{ strtoupper(substr($loc['name'], 0, 2)) }}
                                                        </span>
                                                    @endif

                                                    @if($nearbyCount > 0)
                                                        <span :class="nearbyVisible ? 'opacity-0' : 'opacity-100'"
                                                              class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px]
                                                                     flex items-center justify-center rounded-full
                                                                     bg-slate-700 text-white text-[9px] font-bold font-mono
                                                                     px-1 shadow-sm ring-2 ring-white dark:ring-gray-900
                                                                     tabular-nums pointer-events-none select-none
                                                                     transition-opacity duration-150"
                                                              aria-label="{{ $nearbyCount }} nearby places">
                                                            +{{ $nearbyCount }}
                                                        </span>
                                                    @endif
                                                </div>

                                                <svg xmlns="http://www.w3.org/2000/svg" class="mt-[-1px] h-1.5 w-2"
                                                     style="color: {{ $loc['color'] }};"
                                                     viewBox="0 0 12 8" fill="currentColor" aria-hidden="true">
                                                    <path d="M0 0 L12 0 L6 8 Z"/>
                                                </svg>
                                            </div>
                                        </x-marker-content>

                                        <x-marker-popup>
                                            <div class="min-w-[220px] p-4 bg-white/95 dark:bg-gray-800/95 backdrop-blur-md rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
                                                <h3 class="font-extrabold text-gray-900 dark:text-white text-base leading-tight mb-1">{{ $loc['name'] }}</h3>
                                                <p class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                                    {{ $loc['type'] }}
                                                </p>
                                                @if($loc['logo'])
                                                    <div class="mt-3 overflow-hidden rounded-xl h-24 w-full">
                                                        <img src="{{ $loc['logo'] }}" alt="{{ $loc['name'] }}"
                                                             class="w-full h-full object-cover hover:scale-105 transition-transform duration-500"
                                                             loading="lazy" decoding="async">
                                                    </div>
                                                @endif
                                                @if($nearbyCount > 0)
                                                    <p class="mt-3 text-[11px] text-gray-500 dark:text-gray-400">
                                                        {{ $nearbyCount }} {{ $nearbyCount === 1 ? 'nearby place' : 'nearby places' }}
                                                    </p>
                                                @endif
                                                <div class="mt-4 flex gap-2">
                                                    <a href="{{ route('business.offerings', $loc['slug']) }}"
                                                       wire:navigate
                                                       class="flex-1 rounded-xl bg-gray-900 dark:bg-white px-3 py-2 text-center text-xs font-bold text-white dark:text-gray-900 shadow-sm hover:bg-gray-800 dark:hover:bg-gray-100 transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                                                        View
                                                    </a>
                                                    <a href="{{ route('explore.map', ['marker' => $loc['id']]) }}"
                                                       wire:navigate
                                                       class="flex-1 rounded-xl border-2 border-gray-200 dark:border-gray-600 px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 hover:border-gray-900 dark:hover:border-white transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                                                        Route
                                                    </a>
                                                </div>
                                            </div>
                                        </x-marker-popup>
                                    </x-map-marker>
                                @endif

                                @foreach($subCoords as $subIdx => $sub)
                                    @php
                                        $realIdx   = $subIdx + 1;
                                        $coordType = $sub['type'] ?? 'other';
                                        $mcMatch   = $this->markerCategoriesByKey->get($coordType);
                                        $subColor  = $mcMatch['color'] ?? '#94a3b8';
                                        $subIconSvg = $mcMatch['icon_svg'] ?? null;
                                    @endphp
                                    <x-map-marker
                                        :key="'home-sub-' . $loc['id'] . '-' . $realIdx"
                                        wire:key="home-sub-{{ $loc['id'] }}-{{ $realIdx }}"
                                        :lat="$sub['lat']"
                                        :lng="$sub['lng']"
                                        :color="$subColor"
                                        id="home-sub-{{ $locIndex }}-{{ $realIdx }}"
                                        anchor="bottom"
                                    >
                                        <x-marker-content>
                                            <div x-data="{
                                                    get focused() { return $wire.homeHighlightedLocation === {{ $locIndex }}; },
                                                    get zoomedIn() { return Number(Alpine.store('mapZoom')) >= 15; },
                                                    get visible() { return this.focused || this.zoomedIn; }
                                                 }"
                                                 :class="visible ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                                                 class="flex flex-col items-center cursor-pointer group transition-opacity duration-200"
                                                 wire:click="flyToLocation({{ $locIndex }}, {{ $realIdx }})">
                                                <div class="flex h-9 w-9 items-center justify-center rounded-full border-2
                                                            bg-white shadow-md transition-transform group-hover:scale-110 dark:bg-gray-900"
                                                     style="border-color: {{ $subColor }};">
                                                    @if($subIconSvg)
                                                        <div class="h-4 w-4 text-gray-800 dark:text-white">
                                                            {!! str_replace('<svg ', '<svg xmlns="http://www.w3.org/2000/svg" class="h-full w-full fill-none stroke-current stroke-2" ', $subIconSvg) !!}
                                                        </div>
                                                    @else
                                                        <span class="text-xs font-bold text-gray-800 dark:text-white">
                                                            {{ strtoupper(substr($coordType, 0, 1)) }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="mt-[-1px] h-1.5 w-2"
                                                     style="color: {{ $subColor }};"
                                                     viewBox="0 0 12 8" fill="currentColor" aria-hidden="true">
                                                    <path d="M0 0 L12 0 L6 8 Z"/>
                                                </svg>
                                            </div>
                                        </x-marker-content>
                                    </x-map-marker>
                                @endforeach
                            @endforeach
                        </x-map>

                        @if(count($this->mapLocations) === 0)
                            <div class="absolute inset-0 rounded-3xl flex items-center justify-center
                                        pointer-events-none bg-white/40 dark:bg-gray-900/40 backdrop-blur-[2px]">
                                <div class="max-w-[260px] mx-auto text-center
                                            bg-white/95 dark:bg-gray-800/95 backdrop-blur rounded-2xl
                                            px-5 py-4 shadow-lg border border-gray-100 dark:border-gray-700">
                                    <div class="mx-auto mb-2 flex h-9 w-9 items-center justify-center rounded-full
                                                bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">No pins yet</p>
                                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 leading-snug">
                                        Registered destinations will appear here.
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="absolute bottom-6 left-6 z-10">
                        <a href="{{ route('explore.map') }}"
                           wire:navigate
                           class="inline-flex items-center gap-2 rounded-2xl
                                  bg-white/90 dark:bg-gray-900/90 backdrop-blur-md
                                  px-5 py-3 min-h-[44px]
                                  text-sm font-bold text-gray-900 dark:text-white
                                  shadow-lg border border-white/20 dark:border-gray-700/50
                                  hover:scale-105 hover:bg-white dark:hover:bg-gray-900
                                  transition-all duration-300
                                  focus-visible:ring-2 focus-visible:ring-primary-500/50
                                  active:scale-95">
                            <div class="p-1.5 bg-primary-100 dark:bg-primary-900/50 rounded-lg text-primary-600 dark:text-primary-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                </svg>
                            </div>
                            Interactive Map
                        </a>
                    </div>
                </div>

                <div class="flex flex-col gap-5">
                    <p class="text-sm md:text-base font-medium leading-relaxed text-gray-700 dark:text-gray-300">
                        Find your way around and discover the places, attractions, and hidden gems that make Victorias City special.
                    </p>

                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-xs font-bold tracking-widest uppercase mb-3">
                            Nearby Destinations
                        </p>

                        <div class="hidden lg:flex flex-col gap-2">
                            @foreach($this->mapLocations as $locIndex => $loc)
                                @php
                                    $subBranches = array_slice($loc['coordinates'], 1);
                                    $hasSubBranches = count($subBranches) > 0;
                                    $isActive = $homeHighlightedLocation === $locIndex;
                                @endphp
                                <div x-data="{ expanded: false }" class="group" wire:key="nearby-{{ $loc['id'] }}">
                                    <div class="flex items-center gap-1 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700
                                                border border-gray-200 dark:border-gray-700 rounded-xl px-2 py-2
                                                transition-all duration-200
                                                {{ $isActive ? 'ring-2 ring-primary-600/50 bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                        <button type="button"
                                                wire:click="flyToLocation({{ $locIndex }}, 0)"
                                                class="flex items-center gap-3 flex-1 min-w-0 text-left rounded-lg px-2 py-1.5 min-h-[40px]
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                                       active:scale-[0.98] transition">
                                            @if($loc['logo'])
                                                <img src="{{ $loc['logo'] }}" class="w-9 h-9 rounded-full object-cover border border-gray-200 dark:border-gray-600 shrink-0" alt="{{ $loc['name'] }}" loading="lazy" decoding="async" width="36" height="36">
                                            @else
                                                <span class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 text-xs font-bold text-white" style="background: {{ $loc['color'] }};">{{ strtoupper(substr($loc['name'], 0, 1)) }}</span>
                                            @endif
                                            <span class="flex-1 min-w-0">
                                                <span class="block text-sm text-gray-900 dark:text-white font-semibold truncate">{{ $loc['name'] }}</span>
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium shrink-0">{{ $loc['type'] }}</span>
                                        </button>
                                        @if($hasSubBranches)
                                            <button type="button"
                                                    @click.stop="expanded = !expanded"
                                                    class="shrink-0 p-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition
                                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                                           active:scale-95"
                                                    :aria-expanded="expanded.toString()"
                                                    aria-label="Toggle nearby places">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                    @if($hasSubBranches)
                                        <div :class="expanded ? '' : 'hidden'" class="ml-10 mt-1 space-y-1">
                                            @foreach($subBranches as $subIndex => $sub)
                                                @php
                                                    $subType = $sub['type'] ?? 'other';
                                                    $subCategory = $this->markerCategoriesByKey->get($subType);
                                                    $subColor = $subCategory['color'] ?? '#94a3b8';
                                                    $subIconSvg = $subCategory['icon_svg'] ?? null;
                                                @endphp
                                                <button type="button"
                                                        wire:key="sub-{{ $loc['id'] }}-{{ $subIndex }}"
                                                        wire:click="flyToLocation({{ $locIndex }}, {{ $subIndex + 1 }})"
                                                        class="w-full text-left text-xs text-gray-700 dark:text-gray-300
                                                               hover:text-gray-900 dark:hover:text-white
                                                               px-3 py-2 min-h-[36px] rounded-lg
                                                               bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700
                                                               transition
                                                               focus-visible:ring-2 focus-visible:ring-primary-500/50
                                                               active:scale-[0.98]
                                                               {{ $homeHighlightedLocation === $locIndex ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                                    @if($subIconSvg)
                                                        <span class="inline-block mr-1 align-middle text-gray-800 dark:text-white">
                                                            {!! str_replace('<svg ', '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 stroke-current fill-none" ', $subIconSvg) !!}
                                                        </span>
                                                    @else
                                                        <span class="inline-block w-1.5 h-1.5 rounded-full mr-2" style="background: {{ $subColor }}"></span>
                                                    @endif
                                                    {{ $sub['name'] ?? 'Sub-location '.($subIndex + 1) }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="lg:hidden flex overflow-x-auto gap-3 pb-2 -mx-1 px-1 snap-x">
                            @foreach($this->mapLocations as $locIndex => $loc)
                                @php
                                    $subBranches = array_slice($loc['coordinates'], 1);
                                    $isActive = $homeHighlightedLocation === $locIndex;
                                @endphp
                                <button type="button"
                                        wire:key="mobile-nearby-{{ $loc['id'] }}"
                                        wire:click="flyToLocation({{ $locIndex }}, 0)"
                                        class="shrink-0 w-44 snap-start bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700
                                               rounded-xl p-3.5 text-left transition
                                               active:scale-95
                                               focus-visible:ring-2 focus-visible:ring-primary-500/50
                                               {{ $isActive ? 'ring-2 ring-primary-600/50 bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                    <div class="flex items-center gap-2.5 mb-2">
                                        @if($loc['logo'])
                                            <img src="{{ $loc['logo'] }}" class="w-8 h-8 rounded-full object-cover border border-gray-200 dark:border-gray-600 shrink-0" alt="{{ $loc['name'] }}" loading="lazy" decoding="async" width="32" height="32">
                                        @else
                                            <span class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-xs font-bold text-white" style="background: {{ $loc['color'] }};">{{ strtoupper(substr($loc['name'], 0, 1)) }}</span>
                                        @endif
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $loc['name'] }}</span>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $loc['type'] }}</span>
                                    @if(count($subBranches) > 0)
                                        <span class="block mt-1.5 text-[10px] text-primary-600 dark:text-primary-400 font-bold uppercase">
                                            {{ count($subBranches) }} sub-locations
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ══════════ 6. FEATURED EVENTS ══════════ --}}
        <section class="py-16 md:py-20 bg-gray-50 dark:bg-gray-800/50
                        [content-visibility:auto] [contain-intrinsic-size:auto_520px]">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-10 md:mb-12">
                    <p class="text-amber-600 dark:text-amber-400 font-bold tracking-[0.2em] uppercase text-xs mb-2">Don't Miss Out</p>
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Featured Events</h2>
                    <div class="w-24 h-1 bg-amber-500 mx-auto rounded-full mt-5"></div>
                </div>

                @if($this->featuredEvents->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
                        @foreach($this->featuredEvents as $event)
                            <a href="{{ route('events', ['event' => $event->id]) }}" wire:navigate
                               wire:key="event-{{ $event->id }}"
                               class="group bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700
                                      rounded-3xl overflow-hidden shadow-sm hover:shadow-xl
                                      transition-all duration-300 block
                                      focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      active:scale-[0.98]">
                                @if($event->image_path)
                                    <div class="h-52 overflow-hidden">
                                        <img src="{{ asset('storage/' . $event->image_path) }}" alt="{{ $event->name }}"
                                             class="w-full h-full object-cover group-hover:scale-105 transition duration-700"
                                             loading="lazy" decoding="async">
                                    </div>
                                @else
                                    <div class="h-52 bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                @endif
                                <div class="p-5 md:p-6">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">{{ $event->type }}</span>
                                        <time datetime="{{ $event->start_date?->toIso8601String() ?? '' }}"
                                              class="text-xs text-gray-500 dark:text-gray-400 font-mono tabular-nums">
                                            {{ $event->start_date?->format('M d, Y') ?? '—' }}
                                        </time>
                                    </div>
                                    <h3 class="font-display text-lg md:text-xl font-semibold text-gray-900 dark:text-white mb-2">{{ $event->name }}</h3>
                                    <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed">{{ Str::limit($event->description ?? '', 80) }}</p>
                                    <span class="mt-4 inline-flex items-center gap-1 text-primary-600 dark:text-primary-400 text-sm font-medium group-hover:gap-2 transition-all">
                                        Learn more
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-7-7l7 7-7 7"/>
                                        </svg>
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center text-center py-12 gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-gray-500 dark:text-gray-400">No featured events yet.</p>
                        <a href="{{ route('events') }}" wire:navigate
                           class="inline-flex items-center gap-1 rounded-full bg-primary-600 hover:bg-primary-700
                                  text-white text-sm font-semibold px-4 py-2 min-h-[44px]
                                  transition active:scale-95
                                  focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Browse all events
                        </a>
                    </div>
                @endif

                <div class="text-center mt-10">
                    <a href="{{ route('events') }}" wire:navigate
                       class="inline-flex items-center gap-2 py-3 px-6 min-h-[48px] rounded-full
                              bg-primary-600 hover:bg-primary-700 text-white font-semibold
                              shadow-lg shadow-primary-600/20
                              transition
                              focus-visible:ring-2 focus-visible:ring-primary-500/50
                              active:scale-95">
                        View All Events
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                        </svg>
                    </a>
                </div>
            </div>
        </section>

        {{-- ══════════ 7. FINAL CTA ══════════
             Background image and all copy are editable from the
             superadmin homepage editor via five SiteSetting keys:
               cta_eyebrow, cta_title, cta_description,
               cta_button_text, cta_background_image
             See finalCta() in the PHP class above.

             Overlay note: the image sits at opacity-55 with a single
             soft gradient on top — enough to keep the text legible
             without burying the photo. If the image URL 404s, the
             onerror handler hides the <img> so only the gradient
             shows (no broken-image glyph). --}}
        @php
            $cta = $this->finalCta;
        @endphp
        <section class="relative py-20 md:py-28 bg-gray-900 overflow-hidden
                        [content-visibility:auto] [contain-intrinsic-size:auto_420px]">
            <img src="{{ $cta['background'] }}"
                 alt=""
                 aria-hidden="true"
                 class="absolute inset-0 object-cover w-full h-full opacity-55"
                 loading="lazy"
                 decoding="async"
                 onerror="this.onerror=null; this.style.display='none';">
            {{-- Single soft gradient — replaced the previous triple-stack. --}}
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900/95 via-gray-900/65 to-gray-900/40"></div>
            {{-- Amber wash — kept from the original, primes the eye for the CTA. --}}
            <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-amber-900/20 to-transparent pointer-events-none"></div>

            <div class="relative z-10 flex flex-col items-center max-w-2xl px-4 sm:px-6 mx-auto text-center text-white">

                {{-- Eyebrow — matches the editorial pattern used in every
                     other section. --}}
                <p class="mb-4 inline-flex items-center gap-2
                          text-amber-400 text-xs sm:text-sm font-bold
                          tracking-[0.25em] uppercase">
                    <span class="h-px w-4 bg-amber-400" aria-hidden="true"></span>
                    {{ $cta['eyebrow'] }}
                    <span class="h-px w-4 bg-amber-400" aria-hidden="true"></span>
                </p>

                <h2 class="mb-4 text-3xl sm:text-4xl md:text-5xl font-display font-bold leading-[1.1]">
                    {{ $cta['title'] }}
                </h2>

                <p class="mb-8 text-sm md:text-base text-gray-200 leading-relaxed max-w-xl">
                    {{ $cta['description'] }}
                </p>

                {{-- Two buttons side by side on tablet+ / stacked on mobile. --}}
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3 w-full sm:w-auto">
                    <a href="{{ route('explore.map') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2
                              px-8 sm:px-10 py-3.5 min-h-[52px]
                              text-sm md:text-base font-bold text-slate-900
                              bg-amber-400 hover:bg-amber-300
                              rounded-full
                              shadow-xl shadow-amber-500/25
                              transition-all duration-200
                              focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900
                              active:scale-95">
                        {{ $cta['buttonText'] }}
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                        </svg>
                    </a>
                    <a href="{{ route('events') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2
                              px-8 sm:px-9 py-3.5 min-h-[52px]
                              text-sm md:text-base font-semibold text-white
                              bg-white/10 hover:bg-white/20
                              border border-white/25 hover:border-white/45
                              rounded-full
                              transition-all duration-200
                              focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900
                              active:scale-95">
                        Browse Events
                    </a>
                </div>

                {{-- Location meta strip --}}
                <p class="mt-8 inline-flex items-center gap-2 text-xs sm:text-sm text-white/60">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Victorias City · Negros Occidental · Philippines
                </p>
            </div>
        </section>

    </main>
</div>