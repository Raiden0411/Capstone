<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Scopes\TenantScope;

new 
#[Layout('layouts.app')]
#[Title('What We Offer')]
class extends Component {
    public Tenant $tenant;

    public function mount($slug)
    {
        $this->tenant = Tenant::where('slug', $slug)->firstOrFail();
    }

    #[Computed]
    public function properties()
    {
        return $this->tenant->properties()
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->where('status', 'available')
            ->with([
                'propertyType' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'images'       => fn($q) => $q->withoutGlobalScope(TenantScope::class),
            ])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function services()
    {
        return $this->tenant->services()
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
?>

<div class="min-h-screen bg-white dark:bg-black py-12 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Page header with back link and title --}}
        <div class="mb-10">
            <a href="{{ route('tenant.show', $tenant->slug) }}" 
               wire:navigate 
               class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-red-500 transition-colors mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to {{ $tenant->name }}
            </a>
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">{{ $tenant->name }} – What We Offer</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Choose your stay or add extra services</p>
        </div>

        {{-- Accommodations section --}}
        <div class="mb-16">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Accommodations</h2>
                @if($this->properties->isNotEmpty())
                    <span class="bg-blue-50 dark:bg-red-900/30 text-blue-700 dark:text-red-400 text-sm font-semibold px-3 py-1 rounded-full border border-blue-200 dark:border-red-800/50">
                        {{ $this->properties->count() }} available
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @forelse($this->properties as $property)
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col overflow-hidden">
                        {{-- Image --}}
                        <div class="aspect-[4/3] bg-gray-100 dark:bg-gray-800 overflow-hidden">
                            @if($property->images->isNotEmpty())
                                <img src="{{ asset('storage/' . $property->images->first()->image_path) }}"
                                     alt="{{ $property->name }}"
                                     class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400 dark:text-gray-600">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @endif
                        </div>

                        {{-- Details --}}
                        <div class="p-5 flex-1 flex flex-col">
                            <p class="text-xs font-bold text-blue-600 dark:text-red-500 uppercase mb-1 tracking-wider">
                                {{ $property->propertyType->name ?? 'Property' }}
                            </p>
                            <h3 class="font-bold text-xl text-gray-900 dark:text-white mb-2">{{ $property->name }}</h3>
                            @if($property->description)
                                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4 flex-1 line-clamp-3">{{ $property->description }}</p>
                            @endif

                            <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-2xl font-extrabold text-gray-900 dark:text-white">₱{{ number_format($property->price, 2) }}</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">/ night</span>
                                </div>

                                @auth
                                    <a href="{{ route('booking.create', ['publicproperty' => $property->id]) }}"
                                       class="inline-flex items-center gap-1.5 bg-blue-600 dark:bg-red-600 hover:bg-blue-700 dark:hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        Book
                                    </a>
                                @else
                                    @php
                                        $returnUrl = url()->current();
                                        $loginUrl = route('login', ['redirect' => $returnUrl]);
                                    @endphp
                                    <a href="{{ $loginUrl }}"
                                       class="inline-flex items-center gap-1.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-semibold py-2 px-4 rounded-lg text-sm transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                                        Login to Book
                                    </a>
                                @endauth
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full bg-white dark:bg-gray-900 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700 p-16 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No accommodations yet</h3>
                        <p class="text-gray-500 dark:text-gray-400">This business hasn't listed any available properties. Check back later!</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Services section --}}
        <div>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Additional Services</h2>
                @if($this->services->isNotEmpty())
                    <span class="bg-indigo-50 dark:bg-purple-900/30 text-indigo-700 dark:text-purple-400 text-sm font-semibold px-3 py-1 rounded-full border border-indigo-200 dark:border-purple-800/50">
                        {{ $this->services->count() }} available
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @forelse($this->services as $service)
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm hover:shadow-lg transition-all duration-300 p-6 flex flex-col">
                        <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-3">{{ $service->name }}</h3>
                        @if($service->description)
                            <p class="text-sm text-gray-600 dark:text-gray-300 mb-4 flex-1">{{ $service->description }}</p>
                        @endif
                        <div class="border-t border-gray-100 dark:border-gray-800 pt-4 mt-auto">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-2xl font-extrabold text-gray-900 dark:text-white">₱{{ number_format($service->price, 2) }}</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">/ service</span>
                                </div>
                                @auth
                                    @php
                                        // For services, we currently don't have a direct booking endpoint; this is informative only.
                                        // But if you want to add to cart or booking, you can link later.
                                    @endphp
                                    <span class="text-xs text-gray-400 dark:text-gray-500 italic">Add on checkout</span>
                                @else
                                    @php
                                        $returnUrl = url()->current();
                                        $loginUrl = route('login', ['redirect' => $returnUrl]);
                                    @endphp
                                    <a href="{{ $loginUrl }}" class="text-sm text-blue-600 dark:text-red-500 hover:underline">Login to add</a>
                                @endauth
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full bg-white dark:bg-gray-900 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700 p-12 text-center">
                        <p class="text-gray-500 dark:text-gray-400">No additional services are currently offered.</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>