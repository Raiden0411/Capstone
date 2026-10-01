{{-- resources/views/public/pages/⚡events.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use App\Models\Event;
use App\Models\SiteSetting;
use App\Scopes\TenantScope;

new
#[Layout('layouts.app')]
#[Title('Local Events & Fiestas')]
class extends Component
{
    use WithPagination;

    #[Url] public string $search         = '';
    #[Url] public string $barangayFilter = '';
    #[Url] public string $typeFilter     = '';
    #[Url] public string $statusFilter   = 'upcoming';
    #[Url(as: 'event')] public ?int $selectedEventId = null;

    public function updatingSearch(): void         { $this->resetPage(); }
    public function updatingBarangayFilter(): void { $this->resetPage(); }
    public function updatingTypeFilter(): void     { $this->resetPage(); }
    public function updatingStatusFilter(): void   { $this->resetPage(); }

    #[Computed]
    public function events()
    {
        $query = $this->publicEventQuery();

        match ($this->statusFilter) {
            'upcoming' => $query->where('start_date', '>=', now())->where('is_active', true),
            'past'     => $query->where('start_date', '<',  now())->where('is_active', true),
            'featured' => $query->where('featured', true)->where('is_active', true),
            default    => $query->where('is_active', true),
        };

        return $query->orderBy('start_date')->paginate(12);
    }

    #[Computed]
    public function featuredEvents()
    {
        return $this->applyPublicFilters(
            Event::withoutGlobalScope(TenantScope::class)->select([
                'id', 'name', 'barangay', 'description', 'type',
                'start_date', 'end_date', 'coordinates', 'image_path',
                'is_active', 'featured', 'tenant_id',
            ])
        )
            ->where('featured', true)
            ->where('is_active', true)
            ->where('start_date', '>=', now()->subDay())
            ->orderBy('start_date')
            ->limit(3)
            ->get();
    }

    #[Computed]
    public function barangays()
    {
        return Event::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');
    }

    #[Computed]
    public function types()
    {
        return Event::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');
    }

    #[Computed]
    public function stats(): array
    {
        $now = now();

        $row = Event::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN start_date >= ? THEN 1 ELSE 0 END) as upcoming', [$now])
            ->selectRaw(
                'SUM(CASE WHEN featured = 1 AND start_date >= ? THEN 1 ELSE 0 END) as featured',
                [$now],
            )
            ->first();

        return [
            'total'    => (int) ($row->total    ?? 0),
            'upcoming' => (int) ($row->upcoming ?? 0),
            'featured' => (int) ($row->featured ?? 0),
        ];
    }

    #[Computed]
    public function heroImage(): string
    {
        $siteHero = SiteSetting::getValue('hero_background_image');

        if (is_string($siteHero) && $siteHero !== '') {
            return '/storage/' . ltrim($siteHero, '/');
        }

        $featuredImage = Event::withoutGlobalScope(TenantScope::class)
            ->where('featured', true)
            ->where('is_active', true)
            ->whereNotNull('image_path')
            ->orderByDesc('start_date')
            ->value('image_path');

        if (is_string($featuredImage) && $featuredImage !== '') {
            return '/storage/' . ltrim($featuredImage, '/');
        }

        return 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?auto=format&fit=crop&w=1600&q=80';
    }

    #[Computed]
    public function selectedEvent(): ?Event
    {
        if (! $this->selectedEventId) {
            return null;
        }

        return Event::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->with('tenant:id,name,slug,logo')
            ->find($this->selectedEventId);
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->barangayFilter !== ''
            || $this->typeFilter !== '';
    }

    private function applyPublicFilters($query)
    {
        return $query
            ->when($this->search !== '', function ($q) {
                $search = '%' . $this->search . '%';
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhere('barangay', 'like', $search)
                        ->orWhere('type', 'like', $search);
                });
            })
            ->when($this->barangayFilter !== '', fn ($q) => $q->where('barangay', $this->barangayFilter))
            ->when($this->typeFilter     !== '', fn ($q) => $q->where('type',     $this->typeFilter));
    }

    private function publicEventQuery()
    {
        return $this->applyPublicFilters(
            Event::withoutGlobalScope(TenantScope::class)->select([
                'id', 'name', 'barangay', 'description', 'type',
                'start_date', 'end_date', 'coordinates', 'image_path',
                'is_active', 'featured', 'tenant_id',
            ])
        );
    }

    public function openEvent(int $eventId): void
    {
        $this->selectedEventId = $eventId;
    }

    public function closeEvent(): void
    {
        $this->selectedEventId = null;
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'barangayFilter', 'typeFilter']);
        $this->statusFilter = 'upcoming';
        $this->resetPage();
    }
};
?>

@push('styles')
    @once
        <style>
            @keyframes eventModalBackdropIn {
                from { opacity: 0 }
                to   { opacity: 1 }
            }
            @keyframes eventModalPanelIn {
                from { opacity: 0; transform: translateY(24px) scale(.96); }
                to   { opacity: 1; transform: translateY(0) scale(1); }
            }
            .event-modal-backdrop { animation: eventModalBackdropIn .25s ease-out; }
            .event-modal-panel    { animation: eventModalPanelIn .3s cubic-bezier(.16,1,.3,1); }
            @media (prefers-reduced-motion: reduce) {
                .event-modal-backdrop, .event-modal-panel { animation: none; }
            }
        </style>
    @endonce
@endpush

<div x-data="revealOnScroll">
    <div x-data="{ filtersOpen: false, isDesktop: window.innerWidth >= 768 }"
         @resize.window="isDesktop = window.innerWidth >= 768"
         @keydown.escape.window="if ($wire.selectedEventId) $wire.closeEvent()"
         class="min-h-screen pb-20">

        <section class="relative overflow-hidden bg-gray-900 py-16 sm:py-20 md:py-24">

            <img src="{{ $this->heroImage }}"
                 alt=""
                 aria-hidden="true"
                 loading="eager"
                 fetchpriority="high"
                 decoding="async"
                 class="absolute inset-0 w-full h-full object-cover opacity-40">

            <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/50 to-black/70"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-amber-500/12 via-amber-500/4 to-transparent pointer-events-none" aria-hidden="true"></div>

            <div class="relative z-10 mx-auto flex max-w-7xl flex-col gap-8 px-4 sm:px-6 md:flex-row md:items-end md:justify-between md:gap-12 lg:px-8">

                <div class="max-w-2xl">
                    <p class="mb-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-amber-400">
                        <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                        Victorias City Festivities
                    </p>
                    <h1 class="font-display text-4xl font-bold leading-[1.05] tracking-tight text-white sm:text-5xl md:text-6xl">
                        Events <em class="italic text-amber-300">&amp;</em> Fiestas
                    </h1>
                    <p class="mt-4 max-w-lg text-sm leading-relaxed text-white/70 sm:text-base">
                        Discover upcoming local festivities, cultural events, and community activities near your destination.
                    </p>
                </div>

                @php $s = $this->stats; @endphp
                @if($s['total'] > 0)
                    <div class="scrollbar-hide flex shrink-0 gap-3 overflow-x-auto pb-1 md:overflow-visible md:pb-0">

                        <div class="min-w-[120px] shrink-0 rounded-2xl border border-white/10 bg-white/[0.06] px-4 py-3.5 backdrop-blur-md md:min-w-[128px]">
                            <div class="font-display text-3xl font-semibold leading-none tabular-nums text-white">
                                {{ $s['total'] }}
                            </div>
                            <div class="mt-2 text-xs font-medium text-white/60">
                                Total Events
                            </div>
                        </div>

                        <div class="min-w-[120px] shrink-0 rounded-2xl border border-white/10 bg-white/[0.06] px-4 py-3.5 backdrop-blur-md md:min-w-[128px]">
                            <div class="font-display text-3xl font-semibold leading-none tabular-nums text-white">
                                {{ $s['upcoming'] }}
                            </div>
                            <div class="mt-2 text-xs font-medium text-white/60">
                                Upcoming
                            </div>
                        </div>

                        @if($s['featured'] > 0)
                            <div class="min-w-[120px] shrink-0 rounded-2xl border border-amber-400/30 bg-white/[0.06] px-4 py-3.5 backdrop-blur-md md:min-w-[128px]">
                                <div class="font-display text-3xl font-semibold leading-none tabular-nums text-amber-300">
                                    {{ $s['featured'] }}
                                </div>
                                <div class="mt-2 text-xs font-medium text-amber-300/90">
                                    Featured
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </section>

        <div class="glass sticky z-20 border-x-0 border-t-0 top-[calc(4rem+env(safe-area-inset-top))] md:top-[calc(5rem+env(safe-area-inset-top))]">
            <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">

                <div class="md:hidden" :class="filtersOpen ? 'mb-4' : ''">
                    <button type="button" @click="filtersOpen = !filtersOpen"
                            :aria-expanded="filtersOpen.toString()"
                            aria-controls="events-filters-panel"
                            class="w-full flex items-center justify-between px-4 min-h-[44px]
                                   bg-gray-100 dark:bg-gray-800 rounded-xl
                                   text-sm font-semibold text-gray-700 dark:text-gray-200
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <span>Search &amp; Filters</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 transition-transform duration-200"
                             :class="filtersOpen ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                <div id="events-filters-panel"
                     :class="(filtersOpen || isDesktop) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                     class="grid transition-[grid-template-rows] duration-300 ease-out">
                    <div class="overflow-hidden">
                        <div class="space-y-3">

                            <div class="flex flex-col md:flex-row gap-3 items-center justify-between">

                                <div class="relative w-full md:max-w-xs md:flex-1">
                                    <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <input type="text"
                                           wire:model.live.debounce.300ms="search"
                                           enterkeyhint="search"
                                           autocomplete="off"
                                           placeholder="Search events…"
                                           aria-label="Search events"
                                           class="input !rounded-full !py-2.5 pl-10 pr-4">
                                </div>

                                <div class="flex flex-col sm:flex-row w-full md:w-auto gap-2">
                                    <select wire:model.live="barangayFilter"
                                            aria-label="Filter by location"
                                            class="select w-full sm:w-auto min-h-[44px] cursor-pointer appearance-none !rounded-full !py-2 !pl-4 !pr-9 text-sm font-semibold">
                                        <option value="">All Locations</option>
                                        @foreach($this->barangays as $b)
                                            <option value="{{ $b }}">{{ $b }}</option>
                                        @endforeach
                                    </select>

                                    <select wire:model.live="typeFilter"
                                            aria-label="Filter by event type"
                                            class="select w-full sm:w-auto min-h-[44px] cursor-pointer appearance-none !rounded-full !py-2 !pl-4 !pr-9 text-sm font-semibold">
                                        <option value="">All Types</option>
                                        @foreach($this->types as $type)
                                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="scrollbar-hide -mx-1 flex flex-1 gap-2 overflow-x-auto px-1 py-0.5">
                                @foreach(['upcoming' => 'Upcoming', 'past' => 'Past', 'featured' => 'Featured', 'all' => 'All'] as $val => $label)
                                    @php $isActive = $statusFilter === $val; @endphp
                                    <button type="button"
                                            wire:key="status-pill-{{ $val }}"
                                            wire:click="$set('statusFilter', '{{ $val }}')"
                                            aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                                            class="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-full border px-4 text-sm font-semibold transition active:scale-95
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                                   {{ $isActive
                                                      ? 'border-primary-600 bg-primary-600 text-white shadow-sm'
                                                      : 'border-gray-300 text-gray-700 hover:border-primary-400 dark:border-gray-600 dark:text-gray-300' }}">
                                        {{ $label }}
                                    </button>
                                @endforeach

                                @if($this->hasActiveFilters)
                                    <button type="button"
                                            wire:click="clearFilters"
                                            wire:loading.attr="disabled"
                                            wire:target="clearFilters"
                                            class="btn-secondary ml-auto min-h-[44px] shrink-0 px-4 py-2 text-sm">
                                        <svg class="size-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Clear
                                    </button>
                                @endif
                            </div>

                            <div class="h-0.5 overflow-hidden" aria-hidden="true">
                                <div wire:loading.delay.shortest wire:target="search,barangayFilter,typeFilter,statusFilter,gotoPage,nextPage,previousPage,clearFilters"
                                     class="h-full w-full animate-pulse bg-primary-500 motion-reduce:animate-none"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">

            @if($this->featuredEvents->isNotEmpty() && in_array($statusFilter, ['upcoming', 'all'], true))
                <section class="mb-12">
                    <div data-reveal class="mb-6 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="mb-2 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400">
                                <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                                Don't Miss Out
                            </p>
                            <h2 class="font-display text-2xl font-bold tracking-tight text-gray-900 dark:text-white md:text-3xl">
                                Featured events
                            </h2>
                        </div>
                        <div class="hidden sm:block font-display text-5xl font-light text-gray-200 dark:text-gray-800 tabular-nums">
                            {{ str_pad($this->featuredEvents->count(), 2, '0', STR_PAD_LEFT) }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:gap-6 md:grid-cols-3 auto-rows-fr">
                        @foreach($this->featuredEvents as $event)
                            @php $img = $event->image_path ? '/storage/' . ltrim($event->image_path, '/') : null; @endphp
                            <button type="button"
                                    wire:key="featured-{{ $event->id }}"
                                    wire:click="openEvent({{ $event->id }})"
                                    data-reveal
                                    style="--reveal-delay: {{ min($loop->index, 2) * 80 }}ms"
                                    class="group flex flex-col h-full bg-white dark:bg-gray-800 rounded-3xl overflow-hidden
                                           border border-amber-200/60 dark:border-amber-500/30 shadow-sm
                                           hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1
                                           text-left w-full active:scale-[0.99]
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50">
                                <div class="relative aspect-[4/3] w-full overflow-hidden shrink-0">
                                    @if($img)
                                        <img src="{{ $img }}"
                                             alt="{{ $event->name }}"
                                             loading="lazy" decoding="async"
                                             class="size-full object-cover transition duration-700 group-hover:scale-105">
                                    @else
                                        <div class="w-full h-full bg-gradient-to-br from-amber-50 to-orange-50 dark:from-gray-800 dark:to-gray-700 flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>

                                    <div class="absolute top-4 left-4 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full
                                                bg-amber-500 text-white shadow-lg">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                        <span class="text-[10px] font-bold uppercase tracking-wider">Featured</span>
                                    </div>

                                    <div class="glass absolute top-4 right-4 rounded-full px-3 py-1">
                                        <span class="text-[10px] font-semibold text-gray-900 dark:text-white tracking-wider uppercase">{{ $event->type }}</span>
                                    </div>

                                    <div class="absolute bottom-4 left-4 right-4">
                                        <h3 class="text-xl font-display font-bold text-white mb-1 line-clamp-2">{{ $event->name }}</h3>
                                        <p class="text-amber-300 text-sm font-medium flex items-center gap-1.5 tabular-nums">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            {{ $event->start_date?->format('M d, Y') ?? '—' }}
                                        </p>
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </section>
            @endif

            <section>
                <div data-reveal class="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="mb-2 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400">
                            <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                            {{ $this->hasActiveFilters ? 'Search results' : 'All events' }}
                        </p>
                        <h2 class="font-display text-2xl font-bold tracking-tight text-gray-900 dark:text-white md:text-3xl">
                            @if($this->hasActiveFilters)
                                <span class="tabular-nums text-primary-600 dark:text-primary-400">{{ $this->events->total() }}</span> {{ \Illuminate\Support\Str::plural('event', $this->events->total()) }} found
                            @else
                                Explore events
                            @endif
                        </h2>
                    </div>
                    <div class="hidden sm:block font-display text-5xl font-light text-gray-200 dark:text-gray-800 tabular-nums">
                        {{ str_pad($this->events->total(), 2, '0', STR_PAD_LEFT) }}
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4 auto-rows-fr transition-opacity duration-300"
                     wire:loading.class="pointer-events-none opacity-40"
                     wire:target="search,barangayFilter,typeFilter,statusFilter,gotoPage,nextPage,previousPage,clearFilters">
                    @forelse($this->events as $event)
                        @php $img = $event->image_path ? '/storage/' . ltrim($event->image_path, '/') : null; @endphp
                        <button type="button"
                                wire:key="event-{{ $event->id }}"
                                wire:click="openEvent({{ $event->id }})"
                                data-reveal
                                style="--reveal-delay: {{ min($loop->index % 4, 3) * 60 }}ms"
                                class="group card card-hover flex h-full flex-col text-left
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">

                            <div class="relative aspect-[16/10] w-full overflow-hidden shrink-0">
                                @if($img)
                                    <img src="{{ $img }}"
                                         alt="{{ $event->name }}"
                                         loading="lazy" decoding="async"
                                         class="size-full object-cover transition duration-700 group-hover:scale-105">
                                @else
                                    <div class="w-full h-full bg-gray-50 dark:bg-gray-700 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                @endif

                                <div class="absolute top-3 left-3 glass rounded-xl shadow-md">
                                    <div class="px-3 py-1.5 text-center leading-none">
                                        <span class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-0.5">{{ $event->start_date?->format('M') ?? '—' }}</span>
                                        <span class="block text-lg font-extrabold text-primary-600 dark:text-primary-400 tabular-nums leading-none">{{ $event->start_date?->format('d') ?? '—' }}</span>
                                    </div>
                                </div>

                                @if($event->featured)
                                    <div class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-1 rounded-full
                                                bg-amber-500/95 backdrop-blur-sm text-white shadow-md">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                        <span class="text-[9px] font-bold uppercase tracking-wider">Featured</span>
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-1 flex-col p-4">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-2">
                                    {{ $event->type }}
                                </span>
                                <h3 class="font-display text-lg font-semibold leading-tight text-gray-900 transition group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400 line-clamp-2">
                                    {{ $event->name }}
                                </h3>

                                <div class="mt-auto flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5 truncate">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 shrink-0 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span class="truncate">{{ $event->barangay }}</span>
                                    </p>
                                    <span class="text-sm font-semibold text-primary-600 opacity-0 transition group-hover:opacity-100 dark:text-primary-400 shrink-0">Open</span>
                                </div>
                            </div>
                        </button>
                    @empty
                        <div data-reveal class="col-span-full flex flex-col items-center gap-3 rounded-3xl border border-dashed border-gray-300 bg-gray-50/60 px-6 py-16 text-center dark:border-gray-700 dark:bg-gray-900/40">
                            <h3 class="font-display text-lg font-semibold text-gray-900 dark:text-white">
                                @if($statusFilter === 'featured')
                                    No featured events
                                @elseif($this->hasActiveFilters)
                                    No events found
                                @else
                                    No events scheduled
                                @endif
                            </h3>
                            <p class="max-w-xs text-sm text-gray-500 dark:text-gray-400">
                                @if($statusFilter === 'featured')
                                    There are no featured events at the moment. Check back later or browse all events.
                                @elseif($this->hasActiveFilters)
                                    We couldn't find any events matching your current filters. Try adjusting your search criteria.
                                @else
                                    No events have been scheduled yet. Check back soon.
                                @endif
                            </p>
                            <div class="flex flex-wrap gap-2 justify-center">
                                @if($this->hasActiveFilters)
                                    <button type="button"
                                            wire:click="clearFilters"
                                            wire:loading.attr="disabled"
                                            wire:target="clearFilters"
                                            class="btn-secondary min-h-[44px] px-5">
                                        Clear filters
                                    </button>
                                @endif

                                @if($statusFilter === 'featured')
                                    <button type="button"
                                            wire:click="$set('statusFilter', 'upcoming')"
                                            class="btn-primary min-h-[44px] px-5">
                                        Browse upcoming
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforelse
                </div>

                <div class="mt-10">
                    {{ $this->events->links() }}
                </div>
            </section>
        </div>

        <div x-cloak
             x-data
             x-init="$watch('$wire.selectedEventId', v => { if (v) $nextTick(() => $refs.modalClose?.focus()); })"
             x-effect="document.body.style.overflow = $wire.selectedEventId !== null ? 'hidden' : ''"
             :class="$wire.selectedEventId !== null ? 'event-modal-backdrop flex' : 'hidden'"
             class="fixed inset-0 z-[1000] items-center justify-center p-4 sm:p-6 bg-gray-900/60"
             role="dialog"
             aria-modal="true"
             aria-labelledby="event-modal-title"
             @click="$wire.closeEvent()">

            <div :class="$wire.selectedEventId !== null ? 'event-modal-panel flex flex-col' : 'hidden'"
                 class="bg-white dark:bg-gray-800 rounded-3xl overflow-hidden w-full max-w-2xl max-h-[90vh]
                        relative shadow-2xl border border-gray-100 dark:border-gray-700"
                 @click.stop>

                @if($this->selectedEvent)
                    @php
                        $event   = $this->selectedEvent;
                        $coords  = $event->coordinates;
                        $hasMap  = is_array($coords) && isset($coords['lat'], $coords['lng']);
                        $tenant  = $event->tenant;
                        $eventImg = $event->image_path ? '/storage/' . ltrim($event->image_path, '/') : null;
                        $tenantLogo = $tenant?->logo ? '/storage/' . ltrim($tenant->logo, '/') : null;
                    @endphp

                    <button type="button" wire:click="closeEvent"
                            x-ref="modalClose"
                            aria-label="Close event details"
                            class="absolute top-4 right-4 size-11 rounded-full bg-black/50 hover:bg-black/75 backdrop-blur-md
                                   text-white flex items-center justify-center transition-colors z-10
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    <div class="relative h-64 md:h-80 w-full shrink-0">
                        @if($eventImg)
                            <img src="{{ $eventImg }}"
                                 alt="{{ $event->name }}"
                                 loading="lazy" decoding="async"
                                 class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/90 via-gray-900/30 to-transparent"></div>
                        <div class="absolute bottom-6 left-6 right-6">
                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-primary-500 text-white shadow-sm">
                                    {{ $event->type }}
                                </span>
                                @if($event->featured)
                                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-500 text-white shadow-sm flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                        Featured
                                    </span>
                                @endif
                            </div>
                            <h2 id="event-modal-title" class="text-3xl md:text-4xl font-display font-bold text-white leading-tight tracking-tight">
                                {{ $event->name }}
                            </h2>
                        </div>
                    </div>

                    <div class="p-6 md:p-8 space-y-7 overflow-y-auto custom-scrollbar">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div class="flex items-start gap-4">
                                <div class="size-12 rounded-2xl bg-primary-50 dark:bg-primary-900/30 border border-primary-100 dark:border-primary-800 flex items-center justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">When</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                                        {{ $event->start_date?->format('F d, Y') ?? '—' }}
                                        @if($event->start_date)
                                            <span class="block text-gray-500 dark:text-gray-400 font-normal mt-0.5">
                                                {{ $event->start_date->format('h:i A') }}
                                            </span>
                                        @endif
                                    </p>
                                    @if($event->end_date)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 tabular-nums">
                                            Until {{ $event->end_date->format('M d, Y h:i A') }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-start gap-4">
                                <div class="size-12 rounded-2xl bg-primary-50 dark:bg-primary-900/30 border border-primary-100 dark:border-primary-800 flex items-center justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Where</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $event->barangay ?: 'Victorias City' }}
                                        <span class="block text-gray-500 dark:text-gray-400 font-normal mt-0.5">Victorias City</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-display font-bold tracking-tight text-gray-900 dark:text-white mb-3">About this event</h3>
                            <p class="text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">
                                {{ $event->description ?: 'No additional details provided.' }}
                            </p>
                        </div>

                        @if($hasMap || $tenant)
                            <div class="pt-6 border-t border-gray-100 dark:border-gray-700
                                        flex flex-col sm:flex-row items-center justify-between gap-4">
                                @if($tenant)
                                    <div class="flex items-center gap-3 w-full sm:w-auto">
                                        <div class="size-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center shrink-0 overflow-hidden">
                                            @if($tenantLogo)
                                                <img src="{{ $tenantLogo }}"
                                                     alt="{{ $tenant->name }}"
                                                     loading="lazy" decoding="async"
                                                     class="w-full h-full object-cover">
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Organized by</p>
                                            <a href="{{ route('tenant.show', $tenant->slug) }}"
                                               wire:navigate
                                               class="text-sm font-bold text-primary-600 dark:text-primary-400 hover:underline
                                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                                {{ $tenant->name }}
                                            </a>
                                        </div>
                                    </div>
                                @endif

                                @if($hasMap)
                                    <a href="{{ route('explore.map', ['event' => $event->id]) }}"
                                       wire:navigate
                                       class="btn-primary min-h-[44px] w-full sm:w-auto px-5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        View on Map
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>  