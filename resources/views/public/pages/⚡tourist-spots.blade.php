{{-- resources/views/public/pages/⚡tourist-spots.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\TypeOfTenant;

new
#[Layout('layouts.app')]
#[Title('Tourist Spots · Victorias City')]
class extends Component
{
    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'category', history: true)]
    public string $categoryFilter = '';

    #[Computed]
    public function tenants()
    {
        $term = trim($this->search);

        return Tenant::query()
            ->where('is_active', true)
            ->with(['typeOfTenant:id,type'])
            ->when($term !== '', function ($q) use ($term) {
                $like = '%' . $term . '%';
                $q->where(function ($sub) use ($like) {
                    $sub->where('name', 'like', $like)
                       ->orWhere('address', 'like', $like)
                       ->orWhereHas('typeOfTenant', fn($t) => $t->where('type', 'like', $like));
                });
            })
            ->when($this->categoryFilter, fn($q) => $q->whereHas(
                'typeOfTenant',
                fn($sub) => $sub->where('type', $this->categoryFilter)
            ))
            ->orderBy('name')
            ->get([
                'id', 'name', 'slug', 'logo', 'address', 'is_recommended', 'type_of_tenant_id'
            ]);
    }

    #[Computed]
    public function popularDestinations()
    {
        return Tenant::query()
            ->where('is_active', true)
            ->where('is_recommended', true)
            ->with(['typeOfTenant:id,type'])
            ->orderBy('name')
            ->limit(6)
            ->get([
                'id', 'name', 'slug', 'logo', 'address', 'is_recommended', 'type_of_tenant_id'
            ]);
    }

    #[Computed]
    public function categories()
    {
        return TypeOfTenant::query()
            ->withCount(['tenants' => fn($q) => $q->where('is_active', true)])
            ->whereHas('tenants', fn($q) => $q->where('is_active', true))
            ->orderBy('type')
            ->get(['id', 'type']);
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->categoryFilter !== '';
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'categoryFilter']);
    }
};
?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-20">
    {{-- Hero Section --}}
    <div class="relative bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 text-center">
            <p class="text-sm font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400 mb-3">
                Explore Victorias City
            </p>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-6">
                Discover Local Wonders
            </h1>
            <p class="max-w-2xl mx-auto text-lg text-gray-600 dark:text-gray-300">
                Find the perfect destinations, hidden gems, and must-visit attractions in and around the city.
            </p>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-10">
        
        {{-- Unified Control Panel --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-4 sm:p-6 mb-12">
            <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                
                {{-- Search Bar --}}
                <div class="relative w-full md:w-1/2">
                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search spots, addresses, or categories..."
                        class="w-full bg-gray-50 dark:bg-gray-900 border-0 rounded-xl pl-12 pr-10 py-3.5 text-gray-900 dark:text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500 transition-shadow"
                    >
                    @if($search)
                        <button type="button" wire:click="$set('search', '')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Categories --}}
            <div class="mt-6 flex gap-2 overflow-x-auto pb-2 hide-scrollbar items-center">
                <span class="text-sm text-gray-500 dark:text-gray-400 font-medium mr-2 shrink-0">Filters:</span>
                <button
                    type="button"
                    wire:click="$set('categoryFilter', '')"
                    class="shrink-0 rounded-full px-5 py-2 text-sm font-medium transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                        {{ $categoryFilter === '' ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900 shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}"
                >
                    All
                </button>
                @foreach($this->categories as $cat)
                    <button
                        type="button"
                        wire:click="$set('categoryFilter', '{{ $cat->type }}')"
                        class="shrink-0 rounded-full px-5 py-2 text-sm font-medium transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                            {{ $categoryFilter === $cat->type ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}"
                    >
                        {{ $cat->type }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Popular Destinations Section --}}
        @if($this->popularDestinations->isNotEmpty())
            <div class="mb-14">
                <div class="flex items-center gap-2 mb-6">
                    <div class="p-2 bg-amber-100 dark:bg-amber-500/20 rounded-lg">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Popular Destinations</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
                    @foreach($this->popularDestinations as $tenant)
                        @php
                            $cardImage = $tenant->logo
                                ? asset('storage/' . $tenant->logo)
                                : 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800&auto=format&fit=crop';
                        @endphp
                        <a href="{{ route('business.offerings', $tenant->slug) }}" wire:navigate
                           class="group flex flex-col bg-white dark:bg-gray-800 rounded-3xl overflow-hidden border border-amber-200/60 dark:border-amber-500/30 shadow-sm hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50">
                            <div class="relative h-52 w-full overflow-hidden">
                                <img src="{{ $cardImage }}" alt="{{ $tenant->name }}" loading="lazy"
                                     onerror="this.src='https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800&auto=format&fit=crop'"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <div class="absolute inset-0 bg-gradient-to-t from-gray-900/70 via-gray-900/20 to-transparent opacity-80"></div>
                                <div class="absolute top-4 right-4 bg-white/95 backdrop-blur-sm px-3 py-1.5 rounded-full shadow-lg flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <span class="text-xs font-bold text-gray-900 tracking-wide">Top Pick</span>
                                </div>
                                <div class="absolute bottom-4 left-4 right-4">
                                    <h3 class="text-xl font-bold text-white mb-1 line-clamp-1">{{ $tenant->name }}</h3>
                                    <p class="text-gray-300 text-sm flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        {{ $tenant->typeOfTenant->type ?? 'Destination' }}
                                    </p>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Results Header --}}
        <div class="flex items-center justify-between mb-6 px-2">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Found <span class="text-primary-600">{{ $this->tenants->count() }}</span> {{ Str::plural('spot', $this->tenants->count()) }}
            </h2>
            @if($this->hasActiveFilters)
                <button type="button" wire:click="resetFilters" class="text-sm font-medium text-red-500 hover:text-red-600 transition flex items-center gap-1 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 rounded-md px-2 py-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset Filters
                </button>
            @endif
        </div>

        {{-- Main Grid --}}
        @if($this->tenants->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 transition-opacity duration-200" wire:loading.class="opacity-50">
                @foreach($this->tenants as $tenant)
                    @php
                        $cardImage = $tenant->logo
                            ? asset('storage/' . $tenant->logo)
                            : 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800&auto=format&fit=crop';
                    @endphp
                    <a
                        href="{{ route('business.offerings', $tenant->slug) }}"
                        wire:navigate
                        class="group flex flex-col bg-white dark:bg-gray-800 rounded-3xl overflow-hidden border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                    >
                        <div class="relative h-56 w-full overflow-hidden">
                            <img
                                src="{{ $cardImage }}"
                                alt="{{ $tenant->name }}"
                                loading="lazy"
                                onerror="this.src='https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800&auto=format&fit=crop'"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            >
                            <div class="absolute inset-0 bg-gradient-to-t from-gray-900/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            @if($tenant->is_recommended)
                                <div class="absolute top-4 right-4 bg-white/95 backdrop-blur-sm px-3 py-1.5 rounded-full shadow-lg flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <span class="text-xs font-bold text-gray-900 tracking-wide">Top Pick</span>
                                </div>
                            @endif
                        </div>
                        <div class="p-6 flex flex-col flex-1">
                            <span class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-2">
                                {{ $tenant->typeOfTenant->type ?? 'Destination' }}
                            </span>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 line-clamp-1 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                {{ $tenant->name }}
                            </h3>
                            <div class="mt-auto pt-4 border-t border-gray-100 dark:border-gray-700">
                                @if($tenant->address)
                                    <p class="text-sm text-gray-500 dark:text-gray-400 flex items-start gap-2 line-clamp-2">
                                        <svg class="w-4 h-4 mt-0.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        {{ $tenant->address }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div wire:loading.delay class="flex justify-center mt-8">
                <svg class="animate-spin h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 p-12 text-center shadow-sm">
                <div class="mx-auto h-20 w-20 rounded-full bg-gray-50 dark:bg-gray-900 flex items-center justify-center mb-6">
                    <svg class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No destinations found</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">We couldn't find any tourist spots matching your current filters. Try adjusting your search criteria.</p>
                @if($this->hasActiveFilters)
                    <button wire:click="resetFilters" class="inline-flex items-center gap-2 px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Clear all filters
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>

<style>
    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>