{{-- resources/views/public/pages/⚡events.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use App\Models\Event;
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

    // ─────────────────────────────────────────────────────────
    //  Queries
    // ─────────────────────────────────────────────────────────

    #[Computed]
    public function events()
    {
        $query = $this->publicEventQuery();

        match ($this->statusFilter) {
            'upcoming' => $query->where('start_date', '>=', now())
                                ->where('is_active', true),
            'past'     => $query->where('start_date', '<',  now())
                                ->where('is_active', true),
            'featured' => $query->where('featured', true)
                                ->where('is_active', true),
            default    => $query->where('is_active', true),
        };

        return $query->orderBy('start_date')->paginate(12);
    }

    #[Computed]
    public function featuredEvents()
    {
        return Event::withoutGlobalScope(TenantScope::class)
            ->select([
                'id', 'name', 'barangay', 'description', 'type',
                'start_date', 'end_date', 'coordinates', 'image_path',
                'is_active', 'featured', 'tenant_id',
            ])
            ->where('featured', true)
            ->where('is_active', true)
            ->where('start_date', '>=', now()->subDay())
            ->orderBy('start_date')
            ->limit(3)
            ->get();
    }

    /**
     * Distinct barangays for the filter dropdown.
     *
     * Restricted to active events so options never yield zero results.
     */
    #[Computed]
    public function barangays()
    {
        return Event::withoutGlobalScope(TenantScope::class)
            ->select('barangay')
            ->where('is_active', true)
            ->whereNotNull('barangay')
            ->where('barangay', '!=', '')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');
    }

    /**
     * Distinct types for the filter dropdown.
     *
     * Restricted to active events so options never yield zero results.
     */
    #[Computed]
    public function types()
    {
        return Event::withoutGlobalScope(TenantScope::class)
            ->select('type')
            ->where('is_active', true)
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');
    }

    /**
     * The currently opened event (from `?event=`).
     *
     * Public visibility: active only. This prevents enumeration of
     * inactive events by guessing IDs in the query string.
     */
    #[Computed]
    public function selectedEvent(): ?Event
    {
        if (!$this->selectedEventId) {
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

    // ─────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────

    private function publicEventQuery()
    {
        return Event::withoutGlobalScope(TenantScope::class)
            ->select([
                'id', 'name', 'barangay', 'description', 'type',
                'start_date', 'end_date', 'coordinates', 'image_path',
                'is_active', 'featured', 'tenant_id',
            ])
            ->when($this->search !== '', function ($q) {
                // Whole OR-group must live in its own closure so it can't
                // leak past the outer filters.
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

    // ─────────────────────────────────────────────────────────
    //  Actions
    // ─────────────────────────────────────────────────────────

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

<div x-data="{ filtersOpen: false, isDesktop: window.innerWidth >= 768 }"
     @resize.window="isDesktop = window.innerWidth >= 768"
     @keydown.escape.window="if ($wire.selectedEventId) $wire.closeEvent()"
     class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-20">

    {{-- ═══════════════ HERO ═══════════════ --}}
    <div class="relative bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 text-center">
            <p class="text-sm font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400 mb-3">
                Victorias City Festivities
            </p>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-6">
                Events &amp; Fiestas
            </h1>
            <p class="max-w-2xl mx-auto text-lg text-gray-600 dark:text-gray-300">
                Discover upcoming local festivities, cultural events, and community activities near your destination.
            </p>
        </div>
    </div>

    {{-- ═══════════════ MAIN CONTENT ═══════════════ --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-10">

        {{-- Unified control panel --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-4 sm:p-6 mb-10">

            {{-- Mobile filter toggle --}}
            <div class="md:hidden mb-4">
                <button type="button" @click="filtersOpen = !filtersOpen"
                        class="w-full flex items-center justify-between px-4 py-3
                               bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl
                               text-sm font-semibold text-gray-700 dark:text-gray-200 transition active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <span>Search &amp; Filters</span>
                    <svg class="w-4 h-4 transition-transform duration-200"
                         :class="filtersOpen ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
            </div>

            <div x-show="filtersOpen || isDesktop" x-cloak x-collapse class="space-y-6">

                {{-- Search + selects --}}
                <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                    <div class="relative w-full md:flex-1">
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" wire:model.live.debounce.300ms="search"
                               placeholder="Search events or descriptions..."
                               class="w-full bg-gray-50 dark:bg-gray-900 border-0 rounded-xl pl-12 pr-4 py-3.5
                                      text-gray-900 dark:text-white placeholder-gray-500
                                      focus:ring-2 focus:ring-primary-500 transition-shadow">
                    </div>

                    <div class="flex flex-col sm:flex-row w-full md:w-auto gap-4">
                        <select wire:model.live="barangayFilter"
                                class="w-full sm:w-48 bg-gray-50 dark:bg-gray-900 border-0 rounded-xl py-3.5 px-4
                                       text-sm text-gray-900 dark:text-white
                                       focus:ring-2 focus:ring-primary-500 transition-shadow">
                            <option value="">All Locations</option>
                            @foreach($this->barangays as $b)
                                <option value="{{ $b }}" wire:key="barangay-opt-{{ \Illuminate\Support\Str::slug($b) }}">{{ $b }}</option>
                            @endforeach
                        </select>

                        <select wire:model.live="typeFilter"
                                class="w-full sm:w-48 bg-gray-50 dark:bg-gray-900 border-0 rounded-xl py-3.5 px-4
                                       text-sm text-gray-900 dark:text-white
                                       focus:ring-2 focus:ring-primary-500 transition-shadow">
                            <option value="">All Types</option>
                            @foreach($this->types as $type)
                                <option value="{{ $type }}" wire:key="type-opt-{{ \Illuminate\Support\Str::slug($type) }}">{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Status pills + Clear --}}
                <div class="flex gap-2 overflow-x-auto pb-2 hide-scrollbar items-center
                            border-t border-gray-100 dark:border-gray-700 pt-5 mt-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400 font-medium mr-2 shrink-0">Show:</span>
                    @foreach(['upcoming' => 'Upcoming', 'past' => 'Past', 'featured' => 'Featured', 'all' => 'All'] as $val => $label)
                        <button type="button"
                                wire:key="status-pill-{{ $val }}"
                                wire:click="$set('statusFilter', '{{ $val }}')"
                                class="shrink-0 rounded-full px-5 py-2 text-sm font-medium transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       {{ $statusFilter === $val
                                          ? 'bg-primary-600 text-white shadow-md'
                                          : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                            {{ $label }}
                        </button>
                    @endforeach

                    @if($this->hasActiveFilters)
                        <button type="button" wire:click="clearFilters"
                                class="shrink-0 inline-flex items-center gap-1 rounded-full px-4 py-2 text-sm font-medium
                                       border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400
                                       hover:border-rose-400 hover:text-rose-600 dark:hover:text-rose-400
                                       transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Clear filters
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══════════════ FEATURED SECTION ═══════════════ --}}
        @if($this->featuredEvents->isNotEmpty() && in_array($statusFilter, ['upcoming', 'all'], true))
            <div class="mb-12">
                <div class="flex items-center gap-2 mb-6">
                    <div class="p-2 bg-amber-100 dark:bg-amber-500/20 rounded-lg">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Featured Events</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
                    @foreach($this->featuredEvents as $event)
                        <button type="button"
                                wire:key="featured-{{ $event->id }}"
                                wire:click="openEvent({{ $event->id }})"
                                class="group flex flex-col bg-white dark:bg-gray-800 rounded-3xl overflow-hidden
                                       border border-amber-200/60 dark:border-amber-500/30 shadow-sm
                                       hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2
                                       text-left w-full active:scale-[0.98]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50">
                            <div class="relative h-52 w-full overflow-hidden">
                                @if($event->image_path)
                                    <img src="{{ asset('storage/' . $event->image_path) }}"
                                         alt="{{ $event->name }}" loading="lazy"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-amber-50 to-orange-50 dark:from-gray-800 dark:to-gray-700 flex items-center justify-center">
                                        <svg class="w-12 h-12 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 via-gray-900/20 to-transparent opacity-80"></div>
                                <div class="absolute top-4 right-4 bg-white/95 backdrop-blur-sm px-3 py-1.5 rounded-full shadow-lg flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-gray-900 tracking-wide uppercase">{{ $event->type }}</span>
                                </div>
                                <div class="absolute bottom-4 left-4 right-4">
                                    <h3 class="text-xl font-bold text-white mb-1 line-clamp-1">{{ $event->name }}</h3>
                                    <p class="text-amber-300 text-sm font-medium flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        {{ $event->start_date?->format('M d, Y') ?? '—' }}
                                    </p>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ═══════════════ MAIN GRID ═══════════════ --}}
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Explore Events</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 lg:gap-8 transition-opacity duration-200"
             wire:loading.class="opacity-50">
            @forelse($this->events as $event)
                <button type="button"
                        wire:key="event-{{ $event->id }}"
                        wire:click="openEvent({{ $event->id }})"
                        class="group flex flex-col bg-white dark:bg-gray-800 rounded-3xl overflow-hidden
                               border border-gray-100 dark:border-gray-700 shadow-sm
                               hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2
                               text-left w-full active:scale-[0.98]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">

                    <div class="relative h-48 w-full overflow-hidden">
                        @if($event->image_path)
                            <img src="{{ asset('storage/' . $event->image_path) }}"
                                 alt="{{ $event->name }}" loading="lazy"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        @else
                            <div class="w-full h-full bg-gray-50 dark:bg-gray-700 flex items-center justify-center">
                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        <div class="absolute top-4 left-4 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm px-3 py-1.5 rounded-lg shadow-sm">
                            <div class="text-center">
                                <span class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase leading-none">{{ $event->start_date?->format('M') ?? '—' }}</span>
                                <span class="block text-lg font-extrabold text-primary-600 dark:text-primary-400 leading-tight">{{ $event->start_date?->format('d') ?? '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 flex flex-col flex-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-2">
                            {{ $event->type }}
                        </span>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 line-clamp-2
                                   group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                            {{ $event->name }}
                        </h3>

                        <div class="mt-auto pt-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <p class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1.5 truncate pr-2">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="truncate">{{ $event->barangay }}</span>
                            </p>
                            <span class="text-primary-600 dark:text-primary-400">
                                <svg class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                </button>
            @empty
                <div class="col-span-full bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 p-12 text-center shadow-sm">
                    <div class="mx-auto h-20 w-20 rounded-full bg-gray-50 dark:bg-gray-900 flex items-center justify-center mb-6">
                        <svg class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                        @if($statusFilter === 'featured')
                            No featured events
                        @elseif($this->hasActiveFilters)
                            No events found
                        @else
                            No events scheduled
                        @endif
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">
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
                            <button type="button" wire:click="clearFilters"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full
                                           border border-gray-300 dark:border-gray-600
                                           text-gray-700 dark:text-gray-200 text-sm font-semibold
                                           hover:bg-gray-50 dark:hover:bg-gray-700
                                           transition active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                Clear Filters
                            </button>
                        @endif

                        @if($statusFilter === 'featured')
                            <button type="button" wire:click="$set('statusFilter', 'upcoming')"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full
                                           bg-primary-600 hover:bg-primary-700 text-white
                                           text-sm font-semibold shadow-md shadow-primary-600/20
                                           transition active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                Browse Upcoming Events
                            </button>
                        @endif
                    </div>
                </div>
            @endforelse
        </div>

        <div class="mt-10">
            {{ $this->events->links() }}
        </div>
    </div>

    {{-- ═══════════════ EVENT DETAIL MODAL ═══════════════ --}}
    <div x-show="$wire.selectedEventId !== null"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-trap.inert.noscroll="$wire.selectedEventId !== null"
         class="fixed inset-0 z-1000 flex items-center justify-center p-4 sm:p-6 bg-gray-900/60"
         role="dialog"
         aria-modal="true"
         aria-labelledby="event-modal-title"
         @click="$wire.closeEvent()">

        <div x-show="$wire.selectedEventId !== null"
             x-transition:enter="transition ease-out duration-300 delay-75"
             x-transition:enter-start="opacity-0 translate-y-8 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-8 scale-95"
             class="bg-white dark:bg-gray-800 rounded-3xl overflow-hidden w-full max-w-2xl max-h-[90vh]
                    flex flex-col relative shadow-2xl border border-gray-100 dark:border-gray-700"
             @click.stop>

            @if($this->selectedEvent)
                @php
                    $event   = $this->selectedEvent;
                    $coords  = $event->coordinates;
                    $hasMap  = is_array($coords) && isset($coords['lat'], $coords['lng']);
                    $tenant  = $event->tenant;
                @endphp

                <button type="button" wire:click="closeEvent"
                        aria-label="Close event details"
                        class="absolute top-4 right-4 size-10 rounded-full bg-black/40 hover:bg-black/70 backdrop-blur-md
                               text-white flex items-center justify-center transition-colors z-10
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <div class="relative h-64 md:h-80 w-full shrink-0">
                    @if($event->image_path)
                        <img src="{{ asset('storage/' . $event->image_path) }}"
                             alt="{{ $event->name }}"
                             class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                            <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                    Featured
                                </span>
                            @endif
                        </div>
                        <h2 id="event-modal-title" class="text-3xl md:text-4xl font-extrabold text-white leading-tight">
                            {{ $event->name }}
                        </h2>
                    </div>
                </div>

                <div class="p-6 md:p-8 space-y-8 overflow-y-auto custom-scrollbar">
                    {{-- Date & location --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="flex items-start gap-4">
                            <div class="size-12 rounded-2xl bg-primary-50 dark:bg-primary-900/30 border border-primary-100 dark:border-primary-800 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">When</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $event->start_date?->format('F d, Y') ?? '—' }}
                                    @if($event->start_date)
                                        <span class="block text-gray-500 dark:text-gray-400 font-normal mt-0.5">
                                            {{ $event->start_date->format('h:i A') }}
                                        </span>
                                    @endif
                                </p>
                                @if($event->end_date)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Until {{ $event->end_date->format('M d, Y h:i A') }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="size-12 rounded-2xl bg-primary-50 dark:bg-primary-900/30 border border-primary-100 dark:border-primary-800 flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
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

                    {{-- Description --}}
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3">About this event</h3>
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">
                            {{ $event->description ?: 'No additional details provided.' }}
                        </p>
                    </div>

                    {{-- Footer actions --}}
                    @if($hasMap || $tenant)
                        <div class="pt-6 border-t border-gray-100 dark:border-gray-700
                                    flex flex-col sm:flex-row items-center justify-between gap-4">
                            @if($tenant)
                                <div class="flex items-center gap-3 w-full sm:w-auto">
                                    <div class="size-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center shrink-0 overflow-hidden">
                                        @if($tenant->logo)
                                            <img src="{{ asset('storage/' . $tenant->logo) }}"
                                                 class="w-full h-full object-cover"
                                                 alt="{{ $tenant->name }}">
                                        @else
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Organized by</p>
                                        <a href="{{ route('tenant.show', $tenant->slug) }}"
                                           wire:navigate
                                           class="text-sm font-bold text-primary-600 dark:text-primary-400 hover:underline
                                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                            {{ $tenant->name }}
                                        </a>
                                    </div>
                                </div>
                            @endif

                            @if($hasMap)
                                <a href="{{ route('explore.map', ['lat' => $coords['lat'], 'lng' => $coords['lng']]) }}"
                                   wire:navigate
                                   class="w-full sm:w-auto inline-flex items-center justify-center gap-2
                                          py-3 px-6 rounded-xl bg-gray-900 dark:bg-white
                                          hover:bg-gray-800 dark:hover:bg-gray-100
                                          text-white dark:text-gray-900 text-sm font-bold
                                          transition shadow-sm active:scale-95
                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
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

<style>
    .hide-scrollbar::-webkit-scrollbar { display: none; }
    .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 20px;
    }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #475569;
    }
</style>