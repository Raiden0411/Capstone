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

<div>
    <div class="relative z-10">

        {{-- ========== 1. HERO ========== --}}
        <section class="relative w-full min-h-[100svh] overflow-hidden bg-gray-900 flex flex-col">
            <img src="{{ $this->hero['background'] }}"
                 alt="{{ $this->hero['subtitle'] }}"
                 class="absolute inset-0 w-full h-full object-cover"
                 loading="eager"
                 decoding="async"
                 fetchpriority="high"
                 width="1600"
                 height="900">
            <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/40 to-black/80"></div>

            <div class="relative z-10 flex flex-col items-center justify-center flex-1 px-4 sm:px-6 lg:px-12 text-center">
                <p class="text-yellow-400 font-semibold tracking-[0.35em] uppercase text-sm md:text-base mb-4">
                    {{ $this->hero['title'] }}
                </p>
                <h1 class="text-white font-display text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold leading-tight max-w-4xl">
                    {{ $this->hero['subtitle'] }}
                </h1>

                <form wire:submit="search"
                      class="mt-10 w-full max-w-3xl bg-white/95 dark:bg-gray-800/95 backdrop-blur border-2 border-primary-600 dark:border-primary-500 shadow-2xl rounded-full pl-6 pr-2 h-16 md:h-20 flex items-center
                             transition-shadow duration-200
                             focus-within:ring-4 focus-within:ring-primary-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 text-gray-400 dark:text-gray-500 mr-3 w-5 h-5"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           wire:model="searchQuery"
                           maxlength="120"
                           autocomplete="off"
                           enterkeyhint="search"
                           placeholder="Search destinations, attractions, or activities..."
                           aria-label="Search destinations, attractions, or activities"
                           class="w-full h-full text-base md:text-lg text-gray-900 dark:text-white outline-none bg-transparent placeholder-gray-500 dark:placeholder-gray-400">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="search"
                            class="ml-4 px-8 py-3 md:px-10 md:py-3.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm md:text-base font-bold shrink-0 transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed active:scale-95">
                        <span wire:loading.remove wire:target="search">Search</span>
                        <span wire:loading wire:target="search" class="inline-flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Searching…
                        </span>
                    </button>
                </form>
            </div>

            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 z-20 animate-bounce motion-reduce:animate-none w-6 h-10 border-2 border-white/40 rounded-full flex items-start justify-center p-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-2 h-3 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
            </div>
        </section>

        {{-- ========== 2. POPULAR PICKS (TOP 3) ========== --}}
        <section class="max-w-6xl px-4 sm:px-6 lg:px-8 mx-auto mt-16 md:mt-24 mb-12 md:mb-16">
            <div class="flex items-end justify-between mb-6 md:mb-8 gap-4">
                <div>
                    <p class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">
                        <span class="h-px w-4 bg-primary-600 dark:bg-primary-400"></span>
                        Featured
                    </p>
                    <h2 class="text-2xl md:text-3xl font-display font-semibold text-gray-900 dark:text-white">
                        Popular <em class="italic text-primary-600 dark:text-primary-400">Picks</em>
                    </h2>
                </div>
                <a href="{{ route('explore.map') }}" wire:navigate
                   class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 shrink-0">
                    View All
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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
                       class="group relative block rounded-2xl overflow-hidden aspect-[4/5] bg-gray-200 dark:bg-gray-800 shadow-sm hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:outline-none">
                        <img src="{{ $cardImage }}" alt="{{ $tenant->name }}"
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-700"
                             loading="lazy"
                             decoding="async"
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800&auto=format&fit=crop'">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                        <div class="absolute bottom-0 p-4 md:p-5 text-left">
                            <h3 class="text-white font-bold text-lg md:text-xl">{{ $tenant->name }}</h3>
                            <p class="text-white/80 text-xs md:text-sm">{{ $tenant->typeOfTenant?->type ?? 'Destination' }}</p>
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
                           class="inline-flex items-center gap-1 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-4 py-2 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Browse the map
                        </a>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- ========== 3. DISCOVER VICTORIAS CITY ========== --}}
        <section class="relative px-4 sm:px-6 lg:px-8 py-16 md:py-24 text-white overflow-hidden [content-visibility:auto] [contain-intrinsic-size:auto_420px]">
            <img src="https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1600&q=80"
                 alt=""
                 aria-hidden="true"
                 class="absolute inset-0 object-cover w-full h-full opacity-20"
                 loading="lazy"
                 decoding="async">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-800 to-blue-950"></div>
            <div class="relative z-10 grid items-center max-w-6xl grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 mx-auto">
                <div>
                    <h2 class="mb-4 text-2xl md:text-3xl font-display font-bold leading-snug">{{ $this->hero['discoverTitle'] }}</h2>
                    <p class="max-w-sm text-sm md:text-base leading-relaxed text-blue-100">{{ $this->hero['discoverDescription'] }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3 md:gap-4">
                    @foreach($this->hero['sideImages'] as $index => $image)
                        <img src="{{ $image }}"
                             alt="Discover Victorias"
                             class="object-cover w-full h-28 md:h-36 rounded-xl shadow-lg"
                             loading="lazy"
                             decoding="async"
                             wire:key="hero-side-{{ $index }}">
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ========== 4. MOST VISITED PLACES (OVERLAPPING CAROUSEL) ==========
             `wire:ignore` isolates this section from Livewire morphs. The
             carousel owns its own Alpine state (active index, interval,
             IntersectionObserver) and never re-reads the server during a
             session. --}}
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
                    prefersReduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                    init() {
                        if (this.prefersReduced) {
                            return;
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
                            if (document.hidden) {
                                this.stopAutoPlay();
                            }
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
                 @touchstart="handleTouchStart($event)"
                 @touchend="handleTouchEnd($event)">

            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center mb-10 md:mb-14">
                <p class="text-primary-600 dark:text-primary-400 font-bold tracking-[0.2em] uppercase text-xs mb-2">Popular Spots</p>
                <h2 class="text-2xl md:text-3xl font-display font-bold text-gray-900 dark:text-white">Most Visited Places</h2>
                <div class="w-24 h-1 bg-primary-600 dark:bg-primary-500 mx-auto rounded-full mt-5"></div>
                <p class="max-w-xl mx-auto mt-3 text-sm md:text-base font-medium text-gray-600 dark:text-gray-300">
                    Discover the most popular destinations in Victorias City and experience the places visitors love the most.
                </p>
            </div>

            <div class="relative flex items-center justify-center max-w-6xl mx-auto h-[280px] sm:h-[350px] md:h-[450px] px-4 sm:px-6 lg:px-8">
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
                        class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95"
                        aria-label="Previous slide">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>

                <div class="flex items-center gap-2">
                    <template x-for="(item, index) in items" :key="`dot-${index}`">
                        <button type="button" @click="goTo(index)"
                                :aria-label="'Go to slide ' + (index + 1)"
                                :class="index === active ? 'w-3 h-3 bg-primary-600' : 'w-2 h-2 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400'"
                                class="rounded-full transition-all duration-300 focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95"></button>
                    </template>
                </div>

                <button type="button" @click="goTo(active + 1)"
                        class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95"
                        aria-label="Next slide">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </section>

        {{-- ========== 5. EXPLORE VICTORIAS CITY (MAP) ========== --}}
        <section class="max-w-7xl px-4 sm:px-6 lg:px-8 mx-auto mb-16 md:mb-24"
                 x-data
                 x-init="if (typeof Alpine.store('mapZoom') === 'undefined') Alpine.store('mapZoom', 13)"
                 x-on:map:zoom-changed.window="Alpine.store('mapZoom', Number($event.detail?.zoom) || 13)">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
                <div>
                    <span class="text-sm font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400 mb-2 block">
                        Interactive Directory
                    </span>
                    <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                        Explore Victorias City
                    </h2>
                </div>
                <a href="{{ route('explore.map') }}" wire:navigate
                   class="group inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-bold text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/30 hover:bg-primary-100 dark:hover:bg-primary-900/50 rounded-full transition-all duration-300 focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                    Open Full Map
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>

            <div class="grid items-start grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
                <div class="relative bg-white dark:bg-gray-800 rounded-[2rem] p-3 md:p-4 shadow-xl border border-gray-100 dark:border-gray-700 h-[350px] sm:h-[450px] md:h-[500px] group">

                    {{-- The wrapper div carries `wire:key` (Rule 21 — mapcn
                         does not forward attributes on <x-map>). It MUST also
                         carry `h-full` so that <x-map height="100%"> resolves
                         against the parent's real content height.

                         Map configuration (maxBounds, minZoom, renderWorldCopies)
                         is applied via the AppServiceProvider MapLibre
                         constructor interceptor's `maplibre:captured` event —
                         NOT via @map:load on <x-map>, which never worked
                         because (a) mapcn doesn't forward the attribute and
                         (b) the correct event name is `map:loaded`. The
                         interceptor provides a guaranteed map handle. --}}
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

                                {{-- ── Parent marker ─────────────────────────── --}}
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
                                                                     bg-amber-500 text-white text-[9px] font-bold font-mono
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

                                {{-- ── Sub-markers (progressive disclosure) ───── --}}
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
                    </div>

                    <div class="absolute bottom-6 left-6 z-10">
                        <a href="{{ route('explore.map') }}"
                           wire:navigate
                           class="inline-flex items-center gap-2 rounded-2xl bg-white/90 dark:bg-gray-900/90 backdrop-blur-md px-5 py-3 text-sm font-bold text-gray-900 dark:text-white shadow-lg border border-white/20 dark:border-gray-700/50 hover:scale-105 hover:bg-white dark:hover:bg-gray-900 transition-all duration-300 focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                            <div class="p-1.5 bg-primary-100 dark:bg-primary-900/50 rounded-lg text-primary-600 dark:text-primary-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            </div>
                            Interactive Map
                        </a>
                    </div>
                </div>

                {{-- Right column --}}
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
                                    <div class="flex items-center gap-1 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 rounded-xl px-2 py-2 transition-all duration-200 {{ $isActive ? 'ring-2 ring-primary-600/50 bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                        <button type="button"
                                                wire:click="flyToLocation({{ $locIndex }}, 0)"
                                                class="flex items-center gap-3 flex-1 min-w-0 text-left rounded-lg px-2 py-1.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-[0.98] transition">
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
                                                    class="shrink-0 p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95"
                                                    :aria-expanded="expanded.toString()"
                                                    aria-label="Toggle nearby places">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
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
                                                        class="w-full text-left text-xs text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-[0.98] {{ $homeHighlightedLocation === $locIndex ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
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
                                        class="shrink-0 w-44 snap-start bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-3.5 text-left transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 {{ $isActive ? 'ring-2 ring-primary-600/50 bg-blue-50 dark:bg-blue-900/20' : '' }}">
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

        {{-- ========== FEATURED EVENTS ========== --}}
        <section class="py-16 md:py-20 bg-gray-50 dark:bg-gray-800/50 [content-visibility:auto] [contain-intrinsic-size:auto_520px]">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-10 md:mb-12">
                    <p class="text-primary-600 dark:text-primary-400 font-bold tracking-[0.2em] uppercase text-xs mb-2">Don't Miss Out</p>
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Featured Events</h2>
                    <div class="w-24 h-1 bg-primary-600 dark:bg-primary-500 mx-auto rounded-full mt-5"></div>
                </div>

                @if($this->featuredEvents->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
                        @foreach($this->featuredEvents as $event)
                            <a href="{{ route('events', ['event' => $event->id]) }}" wire:navigate
                               wire:key="event-{{ $event->id }}"
                               class="group bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-3xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 block focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-[0.98]">
                                @if($event->image_path)
                                    <div class="h-52 overflow-hidden">
                                        <img src="{{ asset('storage/' . $event->image_path) }}" alt="{{ $event->name }}"
                                             class="w-full h-full object-cover group-hover:scale-105 transition duration-700"
                                             loading="lazy" decoding="async">
                                    </div>
                                @else
                                    <div class="h-52 bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                                <div class="p-5 md:p-6">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">{{ $event->type }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $event->start_date?->format('M d, Y') ?? '—' }}</span>
                                    </div>
                                    <h3 class="font-display text-lg md:text-xl font-semibold text-gray-900 dark:text-white mb-2">{{ $event->name }}</h3>
                                    <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed">{{ Str::limit($event->description ?? '', 80) }}</p>
                                    <span class="mt-4 inline-flex items-center gap-1 text-primary-600 dark:text-primary-400 text-sm font-medium group-hover:gap-2 transition-all">
                                        Learn more
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-7-7l7 7-7 7"/></svg>
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
                           class="inline-flex items-center gap-1 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-4 py-2 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Browse all events
                        </a>
                    </div>
                @endif

                <div class="text-center mt-10">
                    <a href="{{ route('events') }}" wire:navigate
                       class="inline-flex items-center gap-2 py-3 px-6 rounded-full bg-primary-600 hover:bg-primary-700 text-white font-semibold shadow-lg shadow-blue-500/20 transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                        View All Events
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </a>
                </div>
            </div>
        </section>

        {{-- ========== PLAN YOUR VISIT CTA ========== --}}
        <section class="relative py-16 md:py-24 bg-gray-900 [content-visibility:auto] [contain-intrinsic-size:auto_380px]">
            <img src="https://images.unsplash.com/photo-1542314831-c6a4d14db54d?auto=format&fit=crop&w=1920&q=80"
                 alt=""
                 aria-hidden="true"
                 class="absolute inset-0 object-cover w-full h-full opacity-40"
                 loading="lazy"
                 decoding="async">
            <div class="relative z-10 flex flex-col items-center max-w-2xl px-4 sm:px-6 mx-auto text-center text-white">
                <h2 class="mb-4 text-3xl md:text-5xl font-display font-bold">Plan Your Visit</h2>
                <p class="mb-8 text-sm md:text-base text-gray-200">Start your journey today! Discover the best places, experiences, and adventures Victorias City has to offer.</p>
                <a href="{{ route('explore.map') }}" wire:navigate
                   class="w-full sm:w-auto px-10 py-3.5 text-sm md:text-base font-bold text-white transition bg-primary-600 rounded-full hover:bg-primary-700 text-center shadow-lg shadow-blue-500/20 focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                    Explore Now
                </a>
            </div>
        </section>
    </div>
</div>