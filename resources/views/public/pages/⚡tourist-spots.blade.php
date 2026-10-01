{{-- resources/views/public/pages/⚡tourist-spots.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\Booking;
use App\Scopes\TenantScope;

new
#[Layout('layouts.app')]
#[Title('Tourist Spots · Victorias City')]
class extends Component
{
    #[Url(as: 'q', history: true)] public string $search = '';
    #[Url(as: 'category', history: true)] public string $categoryFilter = '';
    #[Url(as: 'view', history: true)] public string $viewMode = 'grid';
    #[Url(as: 'sort', history: true)] public string $sortBy = 'name';

    private const SORTS_NEEDING_BOOKINGS = ['popular'];
    private const SORTS_NON_DEFAULT = ['newest', 'popular', 'price_asc', 'price_desc'];

    #[Computed]
    public function heroImagesJson(): string
    {
        return json_encode($this->heroImages, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG);
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
                'bookings' => fn ($sub) => $sub->withoutGlobalScope(TenantScope::class)->whereNotIn('status', [Booking::STATUS_CANCELLED]),
            ]))
            ->when($term, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%$term%")
                ->orWhere('address', 'like', "%$term%")
                ->orWhereHas('typeOfTenant', fn ($t) => $t->where('type', 'like', "%$term%"))))
            ->when($this->categoryFilter, fn ($q) => $q->whereHas('typeOfTenant', fn ($s) => $s->where('type', $this->categoryFilter)))
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
                'bookings' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->whereNotIn('status', [Booking::STATUS_CANCELLED]),
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
        $source = $this->featured->filter(fn ($t) => $t->logo);

        if ($source->isEmpty()) {
            $source = $this->tenants->filter(fn ($t) => $t->logo);
        }

        return array_slice($source->map(fn ($t) => '/storage/' . ltrim($t->logo, '/'))->values()->toArray(), 0, 5);
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'categoryFilter']);
    }
};
?>

@php
    $tenants  = $this->tenants;
    $featured = $this->featured;
    $isList   = $viewMode === 'list';
    $target   = 'search,categoryFilter,viewMode,resetFilters,sortBy';

    $ring = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50';
    $eyebrow = 'mb-2 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400';
    $h2 = 'font-display text-2xl font-bold tracking-tight text-gray-900 dark:text-white md:text-3xl';
    $pinIcon = 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z';

    $pills = collect([['', 'All', $this->totalCount]])
        ->concat($this->categories->map(fn ($c) => [$c->type, $c->type, $c->tenants_count]));

    $stats = [
        [$this->totalCount, 'Destinations'],
        [$this->categories->count(), 'Categories'],
        [$featured->count(), 'Top picks'],
    ];
@endphp

<div x-data="{
        heroIndex: 0,
        heroImages: JSON.parse($el.dataset.heroImages || '[]'),
        heroTimer: null,
        prefersReduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        onVisibility: null,
        init() {
            if (this.prefersReduced || this.heroImages.length <= 1) return;
            if (!document.hidden) this.startTimer();
            this.onVisibility = () => document.hidden ? this.stopTimer() : this.startTimer();
            document.addEventListener('visibilitychange', this.onVisibility, { passive: true });
        },
        destroy() {
            this.stopTimer();
            if (this.onVisibility) document.removeEventListener('visibilitychange', this.onVisibility);
        },
        startTimer() {
            if (this.prefersReduced || this.heroImages.length <= 1 || document.hidden) return;
            this.stopTimer();
            this.heroTimer = setInterval(() => { this.heroIndex = (this.heroIndex + 1) % this.heroImages.length; }, 5000);
        },
        stopTimer() {
            if (this.heroTimer) { clearInterval(this.heroTimer); this.heroTimer = null; }
        }
     }"
     data-hero-images="{{ $this->heroImagesJson }}"
     class="min-h-screen">

    <section class="relative overflow-hidden bg-gray-900 py-16 sm:py-20 md:py-24">
        <div class="absolute inset-0" x-show="heroImages.length > 0" x-cloak>
            <template x-for="(img, index) in heroImages" :key="img">
                <img :src="img" alt="" aria-hidden="true" decoding="async"
                     class="absolute inset-0 size-full object-cover transition-opacity duration-1000"
                     :class="index === heroIndex ? 'opacity-40' : 'opacity-0'">
            </template>
        </div>
        <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/50 to-black/70"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-amber-500/12 via-amber-500/4 to-transparent"></div>

        <div class="relative z-10 mx-auto flex max-w-7xl flex-col gap-8 px-4 sm:px-6 md:flex-row md:items-end md:justify-between md:gap-12 lg:px-8">
            <div class="max-w-2xl">
                <p class="mb-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-amber-400">
                    <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                    Victorias City
                </p>
                <h1 class="font-display text-4xl font-bold leading-[1.05] tracking-tight text-white sm:text-5xl md:text-6xl">Discover local wonders</h1>
                <p class="mt-4 max-w-lg text-sm leading-relaxed text-white/70 sm:text-base">
                    Find the perfect destinations, hidden gems, and must-visit attractions throughout the city and its barangays.
                </p>
            </div>

            <div class="scrollbar-hide flex shrink-0 gap-3 overflow-x-auto pb-1 md:overflow-visible md:pb-0">
                @foreach($stats as [$value, $text])
                    <div class="min-w-[120px] shrink-0 rounded-2xl border border-white/10 bg-white/[0.06] px-4 py-3.5 backdrop-blur-md md:min-w-[128px]">
                        <div class="font-display text-3xl font-semibold leading-none tabular-nums text-white">{{ $value }}</div>
                        <div class="mt-2 text-xs font-medium text-white/60">{{ $text }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="glass sticky z-20 border-x-0 border-t-0 top-[calc(4rem+env(safe-area-inset-top))] md:top-[calc(5rem+env(safe-area-inset-top))]">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 sm:px-6 md:flex-row md:items-center md:gap-4 lg:px-8">

            <div class="relative w-full md:max-w-xs md:flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" enterkeyhint="search" autocomplete="off"
                       class="input !rounded-full !py-2.5 pl-10 pr-12" placeholder="Search destinations…" aria-label="Search destinations">
                @if($search)
                    <button type="button" wire:click="$set('search','')" wire:key="clear-search-btn" aria-label="Clear search"
                            class="absolute right-0.5 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full text-gray-400 transition hover:text-gray-600 active:scale-95 {{ $ring }}">
                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>

            <div class="scrollbar-hide -mx-1 flex flex-1 gap-2 overflow-x-auto px-1 py-0.5">
                @foreach($pills as [$value, $text, $count])
                    @php $active = $categoryFilter === $value; @endphp
                    <button type="button" wire:click="$set('categoryFilter', @js($value))" wire:key="cat-pill-{{ $loop->index }}"
                            aria-pressed="{{ $active ? 'true' : 'false' }}"
                            class="inline-flex min-h-[44px] shrink-0 items-center gap-2 rounded-full border pl-4 pr-2 text-sm font-semibold transition active:scale-95 {{ $ring }}
                                   {{ $active ? 'border-primary-600 bg-primary-600 text-white shadow-sm' : 'border-gray-300 text-gray-700 hover:border-primary-400 dark:border-gray-600 dark:text-gray-300' }}">
                        <span>{{ $text }}</span>
                        <span class="grid h-6 min-w-[24px] place-items-center rounded-full px-1.5 text-xs font-bold tabular-nums {{ $active ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">{{ $count }}</span>
                    </button>
                @endforeach
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <div class="relative">
                    <select wire:model.live="sortBy" wire:key="sort-select" aria-label="Sort destinations"
                            class="select !w-auto min-h-[44px] cursor-pointer appearance-none !rounded-full !py-2 !pl-4 !pr-9 text-sm font-semibold">
                        <option value="name">Alphabetical</option>
                        <option value="newest">Newest first</option>
                        <option value="popular">Most booked</option>
                        <option value="price_asc">Price: low → high</option>
                        <option value="price_desc">Price: high → low</option>
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 size-3 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </div>

                <div class="flex shrink-0 overflow-hidden rounded-full border border-gray-200 dark:border-gray-700">
                    @foreach([['grid', 'Grid view', 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'], ['list', 'List view', 'M4 6h16M4 10h16M4 14h16M4 18h16']] as [$mode, $aria, $icon])
                        <button type="button" wire:click="$set('viewMode','{{ $mode }}')" wire:key="view-toggle-{{ $mode }}"
                                aria-pressed="{{ $viewMode === $mode ? 'true' : 'false' }}" aria-label="{{ $aria }}" title="{{ $aria }}"
                                class="grid size-11 place-items-center transition active:scale-95 {{ $ring }}
                                       {{ $viewMode === $mode ? 'bg-primary-600 text-white' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' }}">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="h-0.5 overflow-hidden" aria-hidden="true">
            <div wire:loading.delay.shortest wire:target="{{ $target }}" class="h-full w-full animate-pulse bg-primary-500 motion-reduce:animate-none"></div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8" x-data="revealOnScroll">

        @if(! $this->hasActiveFilters && $featured->isNotEmpty())
            <section class="mb-12">
                <div data-reveal class="mb-6">
                    <p class="{{ $eyebrow }}"><span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>Featured</p>
                    <h2 class="{{ $h2 }}">Popular picks</h2>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 auto-rows-fr">
                    @foreach($featured as $tenant)
                        @php $img = $tenant->logo ? '/storage/' . ltrim($tenant->logo, '/') : null; @endphp
                        <a href="{{ route('business.offerings', $tenant->slug) }}" wire:navigate wire:key="feat-{{ $tenant->id }}"
                           data-reveal style="--reveal-delay: {{ min($loop->index, 2) * 80 }}ms"
                           class="group card-hover relative flex h-full aspect-[4/5] flex-col justify-end overflow-hidden rounded-3xl bg-gray-900 shadow-sm {{ $ring }}">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $tenant->name }}" loading="lazy" decoding="async"
                                     class="absolute inset-0 size-full object-cover brightness-75 transition duration-700 group-hover:scale-105 group-hover:brightness-90">
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>
                            <div class="relative z-10 p-5">
                                <span class="glass mb-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold text-gray-900 dark:text-white">Featured</span>
                                <p class="text-sm text-white/60">{{ $tenant->typeOfTenant?->type ?? 'Destination' }}</p>
                                <h3 class="font-display text-xl font-semibold leading-tight text-white">{{ $tenant->name }}</h3>
                                @if($tenant->address)
                                    <p class="mt-1 flex items-start gap-1 text-xs text-white/60">
                                        <svg class="mt-0.5 size-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $pinIcon }}"/></svg>
                                        <span class="line-clamp-1">{{ $tenant->address }}</span>
                                    </p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <div data-reveal class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="{{ $eyebrow }}"><span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>{{ $this->hasActiveFilters ? 'Search results' : 'All destinations' }}</p>
                    <h2 class="{{ $h2 }}">
                        @if($this->hasActiveFilters)
                            <span class="tabular-nums text-primary-600 dark:text-primary-400">{{ $tenants->count() }}</span> {{ Str::plural('spot', $tenants->count()) }} found
                        @else
                            Explore everywhere
                        @endif
                    </h2>
                </div>
                @if($this->hasActiveFilters)
                    <button type="button" wire:click="resetFilters" wire:key="reset-filters-btn" class="btn-secondary min-h-[44px] px-4 py-2 text-sm">
                        <svg class="size-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        Clear filters
                    </button>
                @endif
            </div>

            <div class="grid gap-5 transition-opacity duration-300 sm:gap-6 auto-rows-fr {{ $isList ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' }}"
                 wire:loading.class="pointer-events-none opacity-40" wire:target="{{ $target }}">
                @forelse($tenants as $tenant)
                    @php
                        $img   = $tenant->logo ? '/storage/' . ltrim($tenant->logo, '/') : null;
                        $minP  = $tenant->properties_min_price;
                        $count = $tenant->properties_count + $tenant->services_count;
                    @endphp
                    <a href="{{ route('business.offerings', $tenant->slug) }}" wire:navigate wire:key="dest-{{ $tenant->id }}"
                       data-reveal style="--reveal-delay: {{ min($loop->index % 4, 3) * 60 }}ms"
                       class="group card card-hover flex h-full overflow-hidden {{ $ring }} {{ $isList ? 'flex-row' : 'flex-col' }}">
                        <div class="relative {{ $isList ? 'w-32 shrink-0 sm:w-48' : 'aspect-[4/3] w-full shrink-0' }} bg-gray-100 dark:bg-gray-800">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $tenant->name }}" loading="lazy" decoding="async"
                                     class="size-full object-cover transition duration-700 group-hover:scale-105">
                            @endif
                            @if($tenant->typeOfTenant)
                                <span class="glass absolute bottom-2 left-2 rounded-full px-3 py-1 text-xs font-semibold text-gray-900 dark:text-white">{{ $tenant->typeOfTenant->type }}</span>
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col p-4">
                            <h3 class="font-display text-lg font-semibold leading-tight text-gray-900 transition group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">{{ $tenant->name }}</h3>
                            @if($tenant->address)
                                <p class="mt-1 flex items-start gap-1 text-xs text-gray-500 dark:text-gray-400">
                                    <svg class="mt-0.5 size-3 shrink-0 text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $pinIcon }}"/></svg>
                                    <span class="line-clamp-2">{{ $tenant->address }}</span>
                                </p>
                            @endif

                            <div class="mt-auto flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-800">
                                @if($minP !== null)
                                    <div>
                                        <div class="font-display text-lg font-semibold leading-none tabular-nums text-gray-900 dark:text-white">₱{{ number_format($minP, 0) }}</div>
                                        <div class="mt-1 text-xs text-gray-400">from / unit</div>
                                    </div>
                                @else
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $count }} offering{{ $count !== 1 ? 's' : '' }}</div>
                                @endif
                                <span class="text-sm font-semibold text-primary-600 opacity-0 transition group-hover:opacity-100 dark:text-primary-400">Explore</span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full flex flex-col items-center gap-3 rounded-3xl border border-dashed border-gray-300 bg-gray-50/60 px-6 py-16 text-center dark:border-gray-700 dark:bg-gray-900/40">
                        <h3 class="font-display text-lg font-semibold text-gray-900 dark:text-white">No destinations found</h3>
                        <p class="max-w-xs text-sm text-gray-500 dark:text-gray-400">Try a different keyword or clear your filters to see everything.</p>
                        @if($this->hasActiveFilters)
                            <button type="button" wire:click="resetFilters" class="btn-primary mt-3 min-h-[44px]">Reset filters</button>
                        @endif
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>