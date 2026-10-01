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

    #[Locked] public array $markerCategories = [];

    public function mount(): void
    {
        $this->markerCategories = SiteSetting::getValue('marker_categories', []);
    }

    private function img(?string $path, ?string $fallback = null): ?string
    {
        return $path ? '/storage/' . ltrim($path, '/') : $fallback;
    }

    private function unsplash(string $id, int $w = 800): string
    {
        return "https://images.unsplash.com/{$id}?auto=format&fit=crop&q=80&w={$w}";
    }

    #[Computed]
    public function hero(): array
    {
        $s = SiteSetting::getBatch([
            'hero_title', 'hero_subtitle', 'hero_description', 'hero_background_image',
            'hero_side_image_1', 'hero_side_image_2', 'hero_side_image_3', 'hero_side_image_4',
            'discover_title', 'discover_description',
        ], 'homepage_hero');

        $fallbacks = [
            1 => 'photo-1500382017468-9049fed747ef',
            2 => 'photo-1448375240586-882707db888b',
            3 => 'photo-1542273917363-3b1817f69a2d',
            4 => 'photo-1501785888041-af3ef285b470',
        ];

        $side = [];
        foreach ($fallbacks as $i => $id) {
            $side[] = $this->img($s["hero_side_image_{$i}"] ?? null, $this->unsplash($id, 600));
        }

        return [
            'title'               => $s['hero_title'] ?? 'Welcome to the North',
            'subtitle'            => $s['hero_subtitle'] ?? 'Victorias City',
            'description'         => $s['hero_description'] ?? 'Escape into a world where the air is scented with sugar cane and the mountains hum with hidden waterfalls. A breathtaking sanctuary in Negros Occidental.',
            'background'          => $this->img($s['hero_background_image'] ?? null, $this->unsplash('photo-1441974231531-c6227db76b6e', 1600)),
            'sideImages'          => $side,
            'discoverTitle'       => $s['discover_title'] ?? 'The City of Smiles & Heritage',
            'discoverDescription' => $s['discover_description'] ?? 'Victorias is more than just an industrial hub; it is a blend of natural sanctuary, deep-rooted history, and warm hospitality. Experience the unique charm that makes this city a hidden gem in Western Visayas.',
        ];
    }

    #[Computed]
    public function finalCta(): array
    {
        $s = SiteSetting::getBatch(
            ['cta_eyebrow', 'cta_title', 'cta_description', 'cta_button_text', 'cta_background_image'],
            'homepage_cta'
        );

        return [
            'eyebrow'     => $s['cta_eyebrow'] ?? 'Start your journey',
            'title'       => $s['cta_title'] ?? 'Plan your visit',
            'description' => $s['cta_description'] ?? 'Discover the best places, experiences, and adventures Victorias City has to offer.',
            'buttonText'  => $s['cta_button_text'] ?? 'Explore now',
            'background'  => $this->img($s['cta_background_image'] ?? null, $this->unsplash('photo-1501785888041-af3ef285b470', 1920)),
        ];
    }

    #[Computed]
    public function heroStats(): array
    {
        return [
            'destinations' => Tenant::withoutGlobalScope(TenantScope::class)->where('is_active', true)->count(),
            'events'       => Event::withoutGlobalScope(TenantScope::class)
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
                ->count(),
        ];
    }

    #[Computed]
    public function ranked()
    {
        return Tenant::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->withCount([
                'bookings' => fn ($q) => $q
                    ->withoutGlobalScope(TenantScope::class)
                    ->whereNotIn('status', [Booking::STATUS_CANCELLED]),
            ])
            ->orderByDesc('bookings_count')
            ->orderBy('name')
            ->limit(4)
            ->with('typeOfTenant:id,type')
            ->get();
    }

    #[Computed]
    public function popularDestinations()
    {
        return $this->ranked->take(3)->values();
    }

    #[Computed]
    public function popularSearchTerms(): array
    {
        return $this->ranked->map(fn ($t) => [
            'id'   => $t->id,
            'name' => $t->name,
            'url'  => $t->slug
                ? route('business.offerings', $t->slug)
                : route('explore.map', ['q' => $t->name]),
        ])->all();
    }

    #[Computed]
    public function carouselPayload(): array
    {
        return Tenant::withoutGlobalScope(TenantScope::class)
            ->whereNotNull('logo')
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'slug', 'logo'])
            ->map(fn ($t) => [
                'name'  => $t->name,
                'url'   => $t->slug ? route('business.offerings', $t->slug) : route('explore.map', ['q' => $t->name]),
                'image' => $this->img($t->logo),
            ])
            ->all();
    }

    #[Computed]
    public function mapLocations(): array
    {
        $colors = ['#f97316', '#a855f7', '#3b82f6', '#14b8a6', '#eab308', '#10b981', '#8b5cf6', '#f43f5e'];

        return $this->popularDestinations
            ->filter(fn ($t) => ! empty($t->coordinates))
            ->values()
            ->map(fn ($t, $i) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'slug'        => $t->slug,
                'type'        => $t->typeOfTenant?->type ?? 'Business',
                'color'       => $colors[$i % count($colors)],
                'logo'        => $this->img($t->logo),
                'coordinates' => $t->coordinates,
            ])
            ->all();
    }

    #[Computed]
    public function featuredEvents()
    {
        return Event::withoutGlobalScope(TenantScope::class)
            ->where('featured', true)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
            ->orderBy('start_date')
            ->limit(3)
            ->get(['id', 'name', 'type', 'description', 'image_path', 'start_date', 'end_date', 'tenant_id']);
    }

    #[Computed]
    public function markerCategoriesByKey()
    {
        return collect($this->markerCategories)->keyBy('key');
    }

    public function flyToLocation(int $index, int $coordIdx = 0): void
    {
        $loc   = $this->mapLocations[$index] ?? null;
        $coord = $loc['coordinates'][$coordIdx] ?? $loc['coordinates'][0] ?? null;
        if (! $coord) return;

        $this->homeHighlightedLocation = $index;
        $this->dispatch('map:fly-to', center: [(float) $coord['lng'], (float) $coord['lat']], zoom: 16);
    }

    public function search(?string $q = null): void
    {
        $query = mb_substr(trim($q ?? $this->searchQuery), 0, 120);
        if ($query === '') return;

        $this->redirectRoute('explore.map', ['q' => $query], navigate: true);
    }
};
?>

@php
    $hero     = $this->hero;
    $stats    = $this->heroStats;
    $cta      = $this->finalCta;
    $terms    = $this->popularSearchTerms;
    $slides   = $this->carouselPayload;
    $locs     = $this->mapLocations;
    $fallback = 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800&auto=format&fit=crop';

    $eyebrow     = 'text-xs font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400';
    $h2          = 'font-display text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight text-gray-900 dark:text-white';
    $chipPrimary = 'inline-flex items-center justify-center gap-2 min-h-[44px] px-4 rounded-full bg-white/12 hover:bg-white/20 backdrop-blur-md border border-white/25 text-white text-sm font-medium transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60';
    $chipGhost   = 'inline-flex items-center justify-center gap-2 min-h-[44px] px-3.5 rounded-full bg-white/5 hover:bg-white/12 border border-white/10 text-white/85 text-sm font-medium transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40';
    $chev        = 'M9 5l7 7-7 7';

    $links = [
        ['Explore map', route('explore.map'), 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7'],
        ['Tourist spots', route('tourist-spots.index'), 'm2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z'],
        ['Events', route('events'), 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
        auth()->check()
            ? ['My bookings', route('my-bookings'), 'M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z']
            : ['Sign in', route('login'), 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
    ];

    $steps = [
        ['Discover', 'Browse destinations, spots, and events across Victorias City.', 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'],
        ['Plan', 'Check availability, pick a date, and reserve your visit in minutes.', 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
        ['Explore', 'Navigate with the interactive map and uncover hidden local gems.', 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7'],
    ];
@endphp

<div x-data="{
        past: false,
        submit() {
            const v = this.$refs.q.value.trim();
            if (v) this.$wire.search(v);
        },
        init() {
            const hero = document.getElementById('home-hero');
            if (!hero || !('IntersectionObserver' in window)) return;
            new IntersectionObserver(([e]) => {
                this.past = !e.isIntersecting && e.boundingClientRect.top < 0;
            }).observe(hero);
        }
    }">

    <form x-cloak x-show="past" x-transition.opacity.duration.200ms
          @submit.prevent="submit"
          class="glass lg:hidden fixed z-30 inset-x-3 flex items-center gap-2 rounded-full pl-4 pr-1.5 py-1.5 shadow-lg
                 top-[calc(4rem+env(safe-area-inset-top)+0.5rem)] md:top-[calc(5rem+env(safe-area-inset-top)+0.5rem)]">
        <svg class="size-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input x-ref="q" type="text" maxlength="120" autocomplete="off" enterkeyhint="search"
               placeholder="Search destinations…" aria-label="Search destinations"
               class="min-w-0 flex-1 h-11 bg-transparent outline-none text-base text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400">
        <button type="submit" aria-label="Search"
                class="btn-primary size-11 shrink-0 !p-0">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $chev }}"/></svg>
        </button>
    </form>

    <button type="button" x-cloak x-show="past" x-transition.opacity
            @click.stop="window.scrollTo({ top: 0, behavior: 'smooth' })" aria-label="Back to top"
            class="fixed z-40 right-4 bottom-[max(1rem,env(safe-area-inset-bottom))] size-11 grid place-items-center rounded-full
                   bg-gray-900 text-white dark:bg-white dark:text-gray-900 shadow-xl
                   transition-transform active:scale-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
    </button>

    <main class="relative z-10" x-data="revealOnScroll">

        <section id="home-hero" class="relative min-h-[100svh] overflow-hidden bg-gray-900 flex flex-col">
            <img src="{{ $hero['background'] }}" alt="" aria-hidden="true" width="1600" height="900"
                 class="absolute inset-0 size-full object-cover" loading="eager" decoding="async" fetchpriority="high">
            <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/30 to-black/85"></div>

            <div class="relative z-10 flex flex-1 flex-col items-center justify-center px-4 sm:px-6 pt-24 pb-16 sm:pt-28 md:pt-32 md:pb-20 text-center">
                <p data-reveal class="mb-4 text-sm sm:text-base font-medium text-white/75 tracking-wide">{{ $hero['title'] }}</p>

                <h1 data-reveal style="--reveal-delay: 60ms"
                    class="max-w-5xl font-display font-bold text-white leading-[0.95] tracking-tighter text-5xl sm:text-7xl md:text-8xl">
                    {{ $hero['subtitle'] }}
                </h1>

                <p data-reveal style="--reveal-delay: 120ms"
                   class="mt-6 max-w-2xl text-base md:text-lg leading-relaxed text-white/85 line-clamp-3 sm:line-clamp-none">
                    {{ $hero['description'] }}
                </p>

                <form wire:submit="search" data-reveal style="--reveal-delay: 180ms"
                      class="glass mt-9 flex w-full max-w-2xl items-center gap-2 rounded-full pl-5 pr-1.5 h-14 sm:h-16 shadow-2xl
                             transition-shadow focus-within:ring-4 focus-within:ring-primary-500/30">
                    <svg class="size-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" wire:model="searchQuery" maxlength="120" autocomplete="off" enterkeyhint="search"
                           placeholder="Search destinations or activities" aria-label="Search destinations or activities"
                           class="min-w-0 flex-1 h-full bg-transparent outline-none text-base sm:text-lg text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400">
                    <button type="submit" wire:loading.attr="disabled" wire:target="search"
                            class="btn-primary shrink-0 h-11 sm:h-12 px-5 sm:px-8 disabled:opacity-60">
                        <span wire:loading.remove wire:target="search">Search</span>
                        <span wire:loading wire:target="search">Searching…</span>
                    </button>
                </form>

                @if($terms)
                    <div data-reveal style="--reveal-delay: 240ms" class="mt-6 flex flex-wrap items-center justify-center gap-2 max-w-2xl">
                        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-white/50 mr-1">Popular</span>
                        @foreach($terms as $term)
                            <a href="{{ $term['url'] }}" wire:navigate wire:key="term-{{ $term['id'] }}" class="{{ $chipPrimary }} max-w-[10rem]">
                                <span class="truncate">{{ $term['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div data-reveal style="--reveal-delay: 300ms" class="mt-4 flex flex-wrap items-center justify-center gap-2 max-w-3xl">
                    @foreach($links as [$label, $url, $icon])
                        <a href="{{ $url }}" wire:navigate class="{{ $chipGhost }}">
                            <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}"/></svg>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                @if($stats['destinations'] || $stats['events'])
                    <p data-reveal style="--reveal-delay: 360ms" class="mt-10 flex flex-wrap items-center justify-center gap-x-5 gap-y-1 text-sm text-white/65">
                        @if($stats['destinations'])<span><strong class="font-semibold text-white tabular-nums">{{ $stats['destinations'] }}+</strong> destinations</span>@endif
                        @if($stats['destinations'] && $stats['events'])<span class="text-white/30" aria-hidden="true">·</span>@endif
                        @if($stats['events'])<span><strong class="font-semibold text-white tabular-nums">{{ $stats['events'] }}+</strong> events</span>@endif
                    </p>
                @endif
            </div>
        </section>

        <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-24 md:py-32">
            <div data-reveal class="flex items-end justify-between gap-4 mb-10 md:mb-14">
                <div>
                    <p class="{{ $eyebrow }}">Featured</p>
                    <h2 class="{{ $h2 }} mt-1.5">Popular picks</h2>
                </div>
                <a href="{{ route('explore.map') }}" wire:navigate class="btn-ghost shrink-0 min-h-[44px] text-primary-600 dark:text-primary-400">View all</a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6">
                @forelse($this->popularDestinations as $t)
                    @php
                        $cardImage = $t->logo ? '/storage/' . ltrim($t->logo, '/') : $fallback;
                        $type      = $t->typeOfTenant?->type ?? 'Destination';
                        $count     = (int) $t->bookings_count;
                    @endphp
                    <article wire:key="popular-{{ $t->id }}"
                             class="group card-hover relative aspect-[4/5] overflow-hidden rounded-3xl bg-gray-200 dark:bg-gray-800 shadow-sm">
                        <img src="{{ $cardImage }}" alt="{{ $t->name }}" loading="lazy" decoding="async"
                             class="absolute inset-0 size-full object-cover transition duration-700 ease-out group-hover:scale-105"
                             onerror="this.onerror=null;this.src='{{ $fallback }}'">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent"></div>

                        <a href="{{ route('business.offerings', $t->slug) }}" wire:navigate aria-label="{{ $t->name }}"
                           class="absolute inset-0 z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"></a>

                        <span class="glass absolute top-3 left-3 z-20 rounded-full px-3 py-1 text-xs font-semibold text-gray-900 dark:text-white pointer-events-none">{{ $type }}</span>

                        <div class="absolute inset-x-0 bottom-0 z-20 p-5 pointer-events-none">
                            <h3 class="text-xl font-bold leading-tight text-white">{{ $t->name }}</h3>
                            @if($count > 0)
                                <p class="mt-1 text-sm text-white/75 tabular-nums">{{ number_format($count) }} {{ Str::plural('booking', $count) }}</p>
                            @endif
                            <div class="mt-4 flex gap-2 pointer-events-auto">
                                <a href="{{ route('business.offerings', $t->slug) }}" wire:navigate @click.stop
                                   class="btn-primary flex-1 min-h-[44px] px-4 py-2.5">View</a>
                                <a href="{{ route('explore.map', ['marker' => $t->id]) }}" wire:navigate @click.stop
                                   class="btn-secondary flex-1 min-h-[44px] px-4 py-2.5 backdrop-blur-md border-white/30 text-white hover:bg-white/15">Route</a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full flex flex-col items-center gap-4 py-12 text-center">
                        <p class="text-gray-500 dark:text-gray-400">No destinations yet.</p>
                        <a href="{{ route('explore.map') }}" wire:navigate class="btn-primary min-h-[44px]">Browse the map</a>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-24 md:pb-32">
            <div data-reveal class="mb-12 md:mb-16 text-center">
                <p class="{{ $eyebrow }}">Planning a trip</p>
                <h2 class="{{ $h2 }} mt-1.5">How it works</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 md:gap-6">
                @foreach($steps as [$title, $copy, $icon])
                    <div data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms" class="card p-7">
                        <div class="mb-5 grid size-12 place-items-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
                        </div>
                        <h3 class="mb-1.5 text-lg font-bold text-gray-900 dark:text-white">{{ $title }}</h3>
                        <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ $copy }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="relative overflow-hidden px-4 sm:px-6 lg:px-8 py-24 md:py-32 text-white bg-gray-900">
            <img src="https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1600&q=80"
                 alt="" aria-hidden="true" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover opacity-25">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-gray-900/95 to-gray-900/80"></div>

            <div class="relative z-10 mx-auto grid max-w-6xl grid-cols-1 items-center gap-10 md:grid-cols-2 md:gap-16">
                <div data-reveal>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-primary-300">The city</p>
                    <h2 class="mb-5 font-display text-3xl sm:text-4xl md:text-5xl font-bold leading-[1.1] tracking-tight">{{ $hero['discoverTitle'] }}</h2>
                    <p class="max-w-md text-base leading-relaxed text-gray-300">{{ $hero['discoverDescription'] }}</p>
                    <a href="{{ route('tourist-spots.index') }}" wire:navigate class="btn-primary mt-8 min-h-[44px]">Explore tourist spots</a>
                </div>

                <div data-reveal style="--reveal-delay: 140ms"
                     class="scrollbar-hide -mx-4 px-4 flex snap-x snap-mandatory gap-3 overflow-x-auto md:mx-0 md:grid md:grid-cols-2 md:gap-4 md:overflow-visible md:px-0">
                    @foreach($hero['sideImages'] as $i => $image)
                        <img src="{{ $image }}" alt="" aria-hidden="true" loading="lazy" decoding="async" wire:key="side-{{ $i }}"
                             class="aspect-square w-40 shrink-0 snap-start rounded-2xl object-cover shadow-lg ring-1 ring-white/10 md:w-full">
                    @endforeach
                </div>
            </div>
        </section>

        @if($slides)
            <section x-data="{ go(d) { const t = $refs.track; t.scrollBy({ left: d * t.clientWidth * 0.8, behavior: 'smooth' }); } }"
                     aria-label="Most visited places" class="bg-white py-24 md:py-32 dark:bg-gray-900">
                <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div data-reveal class="mb-10 flex items-end justify-between gap-4 md:mb-14">
                        <div>
                            <p class="{{ $eyebrow }}">Most visited</p>
                            <h2 class="{{ $h2 }} mt-1.5">Places worth the journey</h2>
                        </div>
                        <div class="hidden gap-2 sm:flex">
                            <button type="button" @click.stop="go(-1)" aria-label="Previous" class="btn-secondary size-11 !p-0">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button type="button" @click.stop="go(1)" aria-label="Next" class="btn-secondary size-11 !p-0">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $chev }}"/></svg>
                            </button>
                        </div>
                    </div>

                    <div x-ref="track"
                         class="scrollbar-hide scroll-smooth -mx-4 -my-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 py-4 scroll-px-4 sm:mx-0 sm:px-0 sm:scroll-px-0">
                        @foreach($slides as $slide)
                            <a href="{{ $slide['url'] }}" wire:navigate wire:key="slide-{{ $loop->index }}"
                               class="group card-hover relative aspect-[4/5] w-[72%] shrink-0 snap-start overflow-hidden rounded-3xl bg-gray-200 dark:bg-gray-800 sm:w-[calc(50%-0.5rem)] lg:w-[calc(33.333%-0.667rem)]">
                                <img src="{{ $slide['image'] }}" alt="{{ $slide['name'] }}" loading="lazy" decoding="async"
                                     class="size-full object-cover transition duration-700 ease-out group-hover:scale-105">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-transparent to-transparent"></div>
                                <span class="absolute inset-x-0 bottom-0 p-5 text-lg font-semibold text-white">{{ $slide['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24 md:py-32"
                 x-data
                 x-init="if (typeof Alpine.store('mapZoom') === 'undefined') Alpine.store('mapZoom', 13)"
                 x-on:map:zoom-changed.window="Alpine.store('mapZoom', Number($event.detail?.zoom) || 13)">
            <div data-reveal class="mb-10 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p class="{{ $eyebrow }}">Interactive directory</p>
                    <h2 class="{{ $h2 }} mt-1.5">Explore Victorias City</h2>
                </div>
                <a href="{{ route('explore.map') }}" wire:navigate class="btn-secondary min-h-[44px] self-start sm:self-auto">Open full map</a>
            </div>

            <div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-2 lg:gap-12">
                <div data-reveal class="card relative h-[360px] p-2 shadow-xl sm:h-[460px] md:p-3 lg:h-[560px] !rounded-[2rem]">
                    <div wire:key="home-map-wrapper" class="relative h-full"
                         x-data="{
                             done: false,
                             setup(m) {
                                 if (!m || this.done) return;
                                 try {
                                     m.setRenderWorldCopies(false);
                                     m.setMaxBounds([[122.0, 9.5], [124.0, 11.8]]);
                                     m.setMinZoom(12);
                                     this.done = true;
                                 } catch (e) {}
                             }
                         }"
                         x-init="setup(window.__lastMapInstance)"
                         x-on:maplibre:captured.window="setup($event.detail?.map)">
                        <x-map id="home-map" :center="[123.07391289720677, 10.900736693923502]" :zoom="13" height="100%"
                               provider="carto-voyager" theme="auto"
                               class="overflow-hidden rounded-3xl bg-gray-50 shadow-inner dark:bg-gray-900">
                            <x-map-controls :zoom="true" :compass="true" :locate="false" :fullscreen="true" :scale="false" position="top-right" />

                            @foreach($locs as $i => $loc)
                                @php
                                    $parent = $loc['coordinates'][0] ?? null;
                                    $subs   = array_slice($loc['coordinates'], 1);
                                    $nearby = count($subs);
                                @endphp

                                @if($parent)
                                    <x-map-marker :key="'home-t-' . $loc['id']" wire:key="home-parent-{{ $loc['id'] }}"
                                                  :lat="$parent['lat']" :lng="$parent['lng']" :color="$loc['color']"
                                                  id="home-marker-{{ $i }}" anchor="bottom">
                                        <x-marker-content>
                                            <div x-data="{
                                                    get focused() { return $wire.homeHighlightedLocation === {{ $i }}; },
                                                    get zoomed() { return Number(Alpine.store('mapZoom')) >= 15; }
                                                 }"
                                                 wire:click="flyToLocation({{ $i }}, 0)"
                                                 class="group flex cursor-pointer flex-col items-center">
                                                <div class="relative grid size-11 place-items-center rounded-full border-2 bg-white shadow-lg transition-transform group-hover:scale-110 dark:bg-gray-900"
                                                     :class="focused && 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900'"
                                                     style="border-color: {{ $loc['color'] }};">
                                                    @if($loc['logo'])
                                                        <img src="{{ $loc['logo'] }}" alt="{{ $loc['name'] }}" width="44" height="44" loading="lazy" decoding="async"
                                                             class="size-full rounded-full object-cover">
                                                    @else
                                                        <span class="text-xs font-bold text-gray-900 dark:text-white">{{ strtoupper(substr($loc['name'], 0, 2)) }}</span>
                                                    @endif
                                                    @if($nearby > 0)
                                                        <span :class="(focused || zoomed) ? 'opacity-0' : 'opacity-100'"
                                                              class="pointer-events-none absolute -right-1.5 -top-1.5 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-gray-700 px-1 text-[9px] font-bold tabular-nums text-white ring-2 ring-white transition-opacity dark:ring-gray-900"
                                                              aria-label="{{ $nearby }} nearby places">+{{ $nearby }}</span>
                                                    @endif
                                                </div>
                                                <svg class="-mt-px h-1.5 w-2" style="color: {{ $loc['color'] }};" viewBox="0 0 12 8" fill="currentColor" aria-hidden="true"><path d="M0 0 L12 0 L6 8 Z"/></svg>
                                            </div>
                                        </x-marker-content>

                                        <x-marker-popup>
                                            <div class="w-56 p-4">
                                                <h3 class="text-base font-extrabold leading-tight text-gray-900 dark:text-white">{{ $loc['name'] }}</h3>
                                                <p class="mt-0.5 text-xs font-semibold text-primary-600 dark:text-primary-400">{{ $loc['type'] }}</p>
                                                @if($loc['logo'])
                                                    <img src="{{ $loc['logo'] }}" alt="{{ $loc['name'] }}" loading="lazy" decoding="async"
                                                         class="mt-3 h-24 w-full rounded-xl object-cover">
                                                @endif
                                                @if($nearby > 0)
                                                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ $nearby }} {{ $nearby === 1 ? 'nearby place' : 'nearby places' }}</p>
                                                @endif
                                                <div class="mt-4 flex gap-2">
                                                    <a href="{{ route('business.offerings', $loc['slug']) }}" wire:navigate @click.stop
                                                       class="btn-primary min-h-[44px] flex-1 px-3 py-2 text-xs">View</a>
                                                    <a href="{{ route('explore.map', ['marker' => $loc['id']]) }}" wire:navigate @click.stop
                                                       class="btn-secondary min-h-[44px] flex-1 px-3 py-2 text-xs">Route</a>
                                                </div>
                                            </div>
                                        </x-marker-popup>
                                    </x-map-marker>
                                @endif

                                @foreach($subs as $j => $sub)
                                    @php
                                        $real  = $j + 1;
                                        $type  = $sub['type'] ?? 'other';
                                        $cat   = $this->markerCategoriesByKey->get($type);
                                        $color = $cat['color'] ?? '#94a3b8';
                                        $svg   = $cat['icon_svg'] ?? null;
                                    @endphp
                                    <x-map-marker :key="'home-sub-' . $loc['id'] . '-' . $real" wire:key="home-sub-{{ $loc['id'] }}-{{ $real }}"
                                                  :lat="$sub['lat']" :lng="$sub['lng']" :color="$color"
                                                  id="home-sub-{{ $i }}-{{ $real }}" anchor="bottom">
                                        <x-marker-content>
                                            <div x-data="{
                                                    get visible() { return $wire.homeHighlightedLocation === {{ $i }} || Number(Alpine.store('mapZoom')) >= 15; }
                                                 }"
                                                 :class="visible ? 'opacity-100' : 'pointer-events-none opacity-0'"
                                                 wire:click="flyToLocation({{ $i }}, {{ $real }})"
                                                 class="group flex cursor-pointer flex-col items-center transition-opacity duration-200">
                                                <div class="grid size-11 place-items-center rounded-full border-2 bg-white shadow-md transition-transform group-hover:scale-110 dark:bg-gray-900"
                                                     style="border-color: {{ $color }};">
                                                    @if($svg)
                                                        <div class="size-5 text-gray-800 dark:text-white">
                                                            <x-safe-svg :svg="$svg" class="size-full fill-none stroke-current stroke-2" />
                                                        </div>
                                                    @else
                                                        <span class="text-xs font-bold text-gray-800 dark:text-white">{{ strtoupper(substr($type, 0, 1)) }}</span>
                                                    @endif
                                                </div>
                                                <svg class="-mt-px h-1.5 w-2" style="color: {{ $color }};" viewBox="0 0 12 8" fill="currentColor" aria-hidden="true"><path d="M0 0 L12 0 L6 8 Z"/></svg>
                                            </div>
                                        </x-marker-content>
                                    </x-map-marker>
                                @endforeach
                            @endforeach
                        </x-map>

                        @if(! $locs)
                            <div class="glass pointer-events-none absolute inset-x-6 top-1/2 mx-auto max-w-[260px] -translate-y-1/2 rounded-2xl px-5 py-4 text-center shadow-lg">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">No pins yet</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Registered destinations will appear here.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div data-reveal style="--reveal-delay: 140ms" class="flex flex-col gap-6">
                    <p class="text-base leading-relaxed text-gray-700 dark:text-gray-300">
                        Find your way around and discover the places, attractions, and hidden gems that make Victorias City special.
                    </p>

                    <div>
                        <p class="{{ $eyebrow }} mb-3">Nearby destinations</p>

                        <div class="scrollbar-hide -mx-1 flex snap-x gap-2 overflow-x-auto px-1 pb-2 lg:flex-col lg:overflow-visible">
                            @foreach($locs as $i => $loc)
                                @php
                                    $subs   = array_slice($loc['coordinates'], 1);
                                    $active = $homeHighlightedLocation === $i;
                                @endphp
                                <div x-data="{ open: false }" wire:key="nearby-{{ $loc['id'] }}" class="w-64 shrink-0 snap-start lg:w-full">
                                    <div class="card flex items-center gap-1 p-1.5 transition {{ $active ? 'ring-2 ring-primary-600/50' : '' }}">
                                        <button type="button" wire:click="flyToLocation({{ $i }}, 0)"
                                                class="flex min-h-[44px] min-w-0 flex-1 items-center gap-3 rounded-xl px-2 text-left transition active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                            @if($loc['logo'])
                                                <img src="{{ $loc['logo'] }}" alt="" width="36" height="36" loading="lazy" decoding="async"
                                                     class="size-9 shrink-0 rounded-full border border-gray-200 object-cover dark:border-gray-600">
                                            @else
                                                <span class="grid size-9 shrink-0 place-items-center rounded-full text-xs font-bold text-white" style="background: {{ $loc['color'] }};">{{ strtoupper(substr($loc['name'], 0, 1)) }}</span>
                                            @endif
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $loc['name'] }}</span>
                                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $loc['type'] }}</span>
                                            </span>
                                        </button>
                                        @if($subs)
                                            <button type="button" @click.stop="open = !open" :aria-expanded="open" aria-label="Toggle nearby places"
                                                    class="hidden size-11 shrink-0 place-items-center rounded-xl text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 active:scale-95 lg:grid dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                                                <svg class="size-4 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                            </button>
                                        @endif
                                    </div>

                                    @if($subs)
                                        <div x-show="open" x-cloak class="ml-12 mt-1 hidden space-y-1 lg:block">
                                            @foreach($subs as $j => $sub)
                                                @php
                                                    $cat   = $this->markerCategoriesByKey->get($sub['type'] ?? 'other');
                                                    $color = $cat['color'] ?? '#94a3b8';
                                                    $svg   = $cat['icon_svg'] ?? null;
                                                @endphp
                                                <button type="button" wire:key="sub-{{ $loc['id'] }}-{{ $j }}"
                                                        wire:click="flyToLocation({{ $i }}, {{ $j + 1 }})"
                                                        class="flex min-h-[44px] w-full items-center gap-2 rounded-xl bg-gray-50 px-3 text-left text-xs text-gray-700 transition hover:bg-gray-100 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                                                    @if($svg)
                                                        <x-safe-svg :svg="$svg" class="size-4 shrink-0 fill-none stroke-current" />
                                                    @else
                                                        <span class="size-1.5 shrink-0 rounded-full" style="background: {{ $color }}"></span>
                                                    @endif
                                                    <span class="truncate">{{ $sub['name'] ?? 'Sub-location ' . ($j + 1) }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-24 md:py-32 [content-visibility:auto] [contain-intrinsic-size:auto_600px]">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div data-reveal class="mb-12 text-center md:mb-16">
                    <p class="{{ $eyebrow }}">Don't miss out</p>
                    <h2 class="{{ $h2 }} mt-1.5">Featured events</h2>
                </div>

                @forelse($this->featuredEvents as $event)
                    @if($loop->first)<div class="grid grid-cols-1 gap-6 md:grid-cols-3 md:gap-8">@endif
                    <a href="{{ route('events', ['event' => $event->id]) }}" wire:navigate wire:key="event-{{ $event->id }}"
                       class="group card card-hover block overflow-hidden !rounded-3xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <div class="relative h-52 overflow-hidden bg-gray-100 dark:bg-gray-700">
                            @if($event->image_path)
                                <img src="/storage/{{ ltrim($event->image_path, '/') }}" alt="{{ $event->name }}" loading="lazy" decoding="async"
                                     class="size-full object-cover transition duration-700 ease-out group-hover:scale-105">
                            @endif
                            <span class="glass absolute left-3 top-3 rounded-full px-3 py-1 text-xs font-semibold text-gray-900 dark:text-white">{{ $event->type }}</span>
                        </div>
                        <div class="p-6">
                            <time datetime="{{ $event->start_date?->toIso8601String() }}" class="text-xs font-medium tabular-nums text-gray-500 dark:text-gray-400">
                                {{ $event->start_date?->format('M d, Y') ?? '—' }}
                            </time>
                            <h3 class="mt-1 mb-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $event->name }}</h3>
                            <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ Str::limit($event->description ?? '', 80) }}</p>
                            <span class="mt-5 inline-flex min-h-[44px] items-center text-sm font-semibold text-primary-600 dark:text-primary-400">Learn more</span>
                        </div>
                    </a>
                    @if($loop->last)</div>@endif
                @empty
                    <div class="flex flex-col items-center gap-4 py-12 text-center">
                        <p class="text-gray-500 dark:text-gray-400">No featured events yet.</p>
                        <a href="{{ route('events') }}" wire:navigate class="btn-primary min-h-[44px]">Browse all events</a>
                    </div>
                @endforelse

                <div data-reveal class="mt-12 text-center">
                    <a href="{{ route('events') }}" wire:navigate class="btn-primary min-h-[48px] px-8">View all events</a>
                </div>
            </div>
        </section>

        <section class="relative overflow-hidden bg-gray-900 py-28 md:py-36 [content-visibility:auto] [contain-intrinsic-size:auto_420px]">
            <img src="{{ $cta['background'] }}" alt="" aria-hidden="true" loading="lazy" decoding="async"
                 class="absolute inset-0 size-full object-cover opacity-50" onerror="this.style.display='none'">
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900/95 via-gray-900/65 to-gray-900/40"></div>

            <div data-reveal class="relative z-10 mx-auto flex max-w-2xl flex-col items-center px-4 text-center text-white sm:px-6">
                <p class="mb-4 text-xs font-semibold uppercase tracking-[0.18em] text-primary-300">{{ $cta['eyebrow'] }}</p>
                <h2 class="mb-5 font-display text-4xl font-bold leading-[1.05] tracking-tighter sm:text-5xl md:text-6xl">{{ $cta['title'] }}</h2>
                <p class="mb-9 max-w-xl text-base leading-relaxed text-gray-200">{{ $cta['description'] }}</p>

                <div class="flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row">
                    <a href="{{ route('explore.map') }}" wire:navigate class="btn-primary min-h-[52px] px-10 text-base">{{ $cta['buttonText'] }}</a>
                    <a href="{{ route('events') }}" wire:navigate
                       class="btn-secondary min-h-[52px] px-9 text-base border-white/30 bg-white/10 text-white backdrop-blur-md hover:bg-white/20">Browse events</a>
                </div>

                <p class="mt-9 text-sm text-white/60">Victorias City, Negros Occidental, Philippines</p>
            </div>
        </section>

    </main>
</div>