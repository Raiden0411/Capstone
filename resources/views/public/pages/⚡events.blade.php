{{-- resources/views/public/pages/⚡events.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use App\Models\Event;
use App\Scopes\TenantScope;

new
#[Layout('layouts.app')]
#[Title('Local Events & Fiestas')]
class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $barangayFilter = '';
    public string $typeFilter = '';
    public string $statusFilter = 'upcoming';
    public ?int $selectedEventId = null;

    public function mount(): void
    {
        if (request()->has('event')) {
            $this->selectedEventId = (int) request()->query('event');
        }
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingBarangayFilter() { $this->resetPage(); }
    public function updatingTypeFilter() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }

    public function getEventsProperty()
    {
        $base = Event::withoutGlobalScope(TenantScope::class)
            ->when($this->search, fn($q) => $q->where(function ($sub) {
                $sub->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%')
                    ->orWhere('barangay', 'like', '%'.$this->search.'%')
                    ->orWhere('type', 'like', '%'.$this->search.'%');
            }))
            ->when($this->barangayFilter, fn($q) => $q->where('barangay', $this->barangayFilter))
            ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter));

        if ($this->statusFilter === 'upcoming') {
            $base->where('start_date', '>=', now())->where('is_active', true);
        } elseif ($this->statusFilter === 'past') {
            $base->where('start_date', '<', now())->where('is_active', true);
        } elseif ($this->statusFilter === 'featured') {
            $base->where('featured', true)->where('is_active', true);
        } else {
            $base->where('is_active', true);
        }

        return $base->orderBy('start_date')->paginate(12);
    }

    public function getFeaturedEventsProperty()
    {
        return Event::withoutGlobalScope(TenantScope::class)
            ->where('featured', true)
            ->where('is_active', true)
            ->where('start_date', '>=', now()->subDay())
            ->orderBy('start_date')
            ->limit(3)
            ->get();
    }

    public function getBarangaysProperty()
    {
        return Event::withoutGlobalScope(TenantScope::class)
            ->distinct()
            ->pluck('barangay')
            ->sort()
            ->values();
    }

    public function getTypesProperty()
    {
        return Event::withoutGlobalScope(TenantScope::class)
            ->distinct()
            ->pluck('type')
            ->sort()
            ->values();
    }

    public function getSelectedEventProperty(): ?Event
    {
        if (!$this->selectedEventId) {
            return null;
        }

        return Event::withoutGlobalScope(TenantScope::class)
            ->find($this->selectedEventId);
    }

    public function openEvent(int $eventId): void
    {
        $this->selectedEventId = $eventId;
    }

    public function closeEvent(): void
    {
        $this->selectedEventId = null;
    }
};
?>

<div x-data="{ filtersOpen: false }" @keydown.escape.window="if ($wire.selectedEventId) $wire.closeEvent()">
    <div class="max-w-7xl mx-auto py-8 md:py-12 px-4 sm:px-6 lg:px-8 space-y-8">

        {{-- Header --}}
        <div class="text-center">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Events & Fiestas</h1>
            <p class="text-gray-600 dark:text-gray-300 max-w-2xl mx-auto mt-2 text-sm md:text-base">
                Discover upcoming local festivities, cultural events, and community activities near your destination.
            </p>
        </div>

        {{-- Featured Events --}}
        @if($this->featuredEvents->isNotEmpty())
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="text-amber-500">★</span> Featured Events
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5 md:gap-6">
                    @foreach($this->featuredEvents as $event)
                        <button wire:click="openEvent({{ $event->id }})"
                                class="bg-white dark:bg-gray-800 border border-amber-200 dark:border-amber-500/30 rounded-2xl overflow-hidden group text-left hover:shadow-xl hover:border-amber-300 dark:hover:border-amber-500/60 transition-all cursor-pointer shadow-sm focus-visible:ring-2 focus-visible:ring-primary-600/50">
                            @if($event->image_path)
                                <img src="{{ asset('storage/' . $event->image_path) }}" alt="{{ $event->name }}"
                                     class="w-full h-44 object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                            @else
                                <div class="w-full h-44 bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-5xl">🎉</div>
                            @endif
                            <div class="p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-blue-400">{{ $event->type }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">Featured</span>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $event->name }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ Str::limit($event->description, 70) }}</p>
                                <span class="inline-block mt-3 text-xs font-medium text-primary-600 dark:text-blue-400">View Details →</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Filters Toggle (Mobile) --}}
        <div class="md:hidden">
            <button @click="filtersOpen = !filtersOpen"
                    class="w-full flex items-center justify-between px-4 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-semibold text-gray-700 dark:text-gray-200">
                <span>Filters</span>
                <svg class="w-4 h-4 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>

        {{-- Filters Container --}}
        <div x-show="filtersOpen || window.innerWidth >= 768"
             x-cloak
             class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 shadow-sm space-y-4 md:block">

            <div class="flex flex-wrap gap-2">
                @foreach([
                    'upcoming' => 'Upcoming',
                    'past' => 'Past',
                    'featured' => 'Featured',
                    'all' => 'All',
                ] as $val => $label)
                    <button wire:click="$set('statusFilter', '{{ $val }}')"
                            class="px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition
                                   {{ $statusFilter === $val ? 'bg-primary-600 text-white shadow-md shadow-primary-600/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 focus-visible:ring-2 focus-visible:ring-primary-600/50' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search events..."
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-600/50 transition">
                </div>

                <select wire:model.live="barangayFilter"
                        class="w-full md:w-48 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-600/50 transition">
                    <option value="">All Barangays</option>
                    @foreach($this->barangays as $b)
                        <option value="{{ $b }}">{{ $b }}</option>
                    @endforeach
                </select>

                <select wire:model.live="typeFilter"
                        class="w-full md:w-48 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-600/50 transition">
                    <option value="">All Types</option>
                    @foreach($this->types as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Events Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6">
            @forelse($this->events as $event)
                <button wire:click="openEvent({{ $event->id }})"
                        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden hover:shadow-xl hover:border-primary-600/40 dark:hover:border-blue-500/40 transition-all group text-left cursor-pointer shadow-sm focus-visible:ring-2 focus-visible:ring-primary-600/50">
                    @if($event->image_path)
                        <img src="{{ asset('storage/' . $event->image_path) }}" alt="{{ $event->name }}"
                             class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                    @else
                        <div class="w-full h-48 bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-6xl">🎉</div>
                    @endif

                    <div class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-blue-400">{{ $event->type }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $event->start_date->format('M d, Y') }}</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $event->name }}</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ Str::limit($event->description, 80) }}</p>
                        <p class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 0111.314 0z"/></svg>
                            {{ $event->barangay }}
                        </p>
                        <span class="inline-block text-xs font-medium text-primary-600 dark:text-blue-400">View Details →</span>
                    </div>
                </button>
            @empty
                <div class="col-span-full py-12 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                        <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No events found.</p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Try adjusting your filters or search.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $this->events->links() }}
        </div>
    </div>

    {{-- Event Detail Modal --}}
    <div x-data="{ show: @entangle('selectedEventId') }"
         x-show="show !== null"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         @click="$wire.closeEvent()">

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-3xl overflow-hidden w-full max-w-2xl max-h-[90vh] flex flex-col relative shadow-2xl"
             @click.stop>
            @if($this->selectedEvent)
                @php
                    $event = $this->selectedEvent;
                    $coords = is_array($event->coordinates) ? $event->coordinates : json_decode($event->coordinates, true);
                @endphp

                {{-- Image --}}
                @if($event->image_path)
                    <img src="{{ asset('storage/' . $event->image_path) }}" alt="{{ $event->name }}"
                         class="w-full h-56 md:h-72 object-cover">
                @else
                    <div class="w-full h-56 md:h-72 bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-8xl">🎉</div>
                @endif

                <div class="p-6 md:p-8 space-y-5 overflow-y-auto">
                    {{-- Type & Featured --}}
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-blue-300">
                            {{ $event->type }}
                        </span>
                        @if($event->featured)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">★ Featured</span>
                        @endif
                    </div>

                    {{-- Name --}}
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">{{ $event->name }}</h2>

                    {{-- Date & Location --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex items-center gap-3">
                            <div class="size-10 rounded-xl bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-primary-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $event->start_date->format('F d, Y h:i A') }}
                                    @if($event->end_date && $event->end_date != $event->start_date)
                                        - {{ $event->end_date->format('F d, Y h:i A') }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="size-10 rounded-xl bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-primary-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path d="M15 11a3 3 0 11-6 0 3 3 0 0111.314 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Location</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $event->barangay }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Tenant --}}
                    @if($event->tenant)
                        <div class="flex items-center gap-3">
                            <div class="size-10 rounded-xl bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-primary-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Organized by</p>
                                <a href="{{ route('tenant.show', $event->tenant->slug) }}" wire:navigate
                                   class="text-sm font-medium text-primary-600 dark:text-blue-400 hover:underline">
                                    {{ $event->tenant->name }}
                                </a>
                            </div>
                        </div>
                    @endif

                    {{-- Description --}}
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">About this event</h3>
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed">{{ $event->description ?: 'No description available.' }}</p>
                    </div>

                    {{-- Map link --}}
                    @if($coords && isset($coords['lat'], $coords['lng']))
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                            <a href="{{ route('explore.map', ['lat' => $coords['lat'], 'lng' => $coords['lng']]) }}"
                               wire:navigate
                               class="inline-flex items-center gap-2 py-3 px-6 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary-600/50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path d="M15 11a3 3 0 11-6 0 3 3 0 0111.314 0z"/>
                                </svg>
                                View on Map
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Close button --}}
                <button type="button" wire:click="closeEvent"
                        class="absolute top-4 right-4 size-8 rounded-full bg-black/50 text-white/70 hover:text-white hover:bg-black/70 flex items-center justify-center transition z-10 focus-visible:ring-2 focus-visible:ring-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            @endif
        </div>
    </div>
</div>