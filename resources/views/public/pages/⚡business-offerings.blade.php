<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Storage;

new
#[Layout('layouts.app')]
#[Title('What We Offer')]
class extends Component
{
    public Tenant $tenant;
    public string $activeTab = 'accommodations'; 
    public ?string $coverPhoto = null;

    public function mount($slug)
    {
        $this->tenant = Tenant::where('slug', $slug)->firstOrFail();

        $this->coverPhoto = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'spot_cover')
            ->value('value');
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
};
?>

<div class="relative z-10 min-h-screen py-8">

    {{-- ══════════ HERO ══════════ --}}
    <section class="relative py-20 md:py-28 overflow-hidden">
        @if($coverPhoto)
            <img src="{{ Storage::url($coverPhoto) }}" class="absolute inset-0 w-full h-full object-cover filter brightness-50" alt="">
        @endif
        <div class="absolute inset-0 bg-gradient-to-br from-black/60 via-black/40 to-black/80"></div>

        <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-16 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <a href="{{ route('tenant.show', $tenant->slug) }}" wire:navigate class="inline-flex items-center gap-1 text-xs tracking-widest uppercase text-white/50 hover:text-brand-400 transition-colors mb-4">
                    ← Back to {{ $tenant->name }}
                </a>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-4 h-px bg-brand-500"></span>
                    <span class="text-xs tracking-[0.22em] uppercase text-brand-500 font-semibold">Offerings</span>
                </div>
                <h1 class="font-display text-4xl md:text-6xl font-semibold text-white leading-none">
                    {{ $tenant->name }}<br>
                    <em class="italic">
                        <span class="bg-gradient-to-r from-brand-400 to-cyan-400 bg-clip-text text-transparent">What We Offer</span>
                    </em>
                </h1>
                <div class="mt-8 flex gap-8 pt-6 border-t border-white/10">
                    <div>
                        <div class="font-display text-3xl text-brand-400">{{ $this->properties->count() }}</div>
                        <div class="text-xs tracking-widest uppercase text-white/40 mt-1">Accommodations</div>
                    </div>
                    <div class="w-px h-10 bg-white/10"></div>
                    <div>
                        <div class="font-display text-3xl text-brand-400">{{ $this->services->count() }}</div>
                        <div class="text-xs tracking-widest uppercase text-white/40 mt-1">Services</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Tab Navigation --}}
    <nav class="sticky top-16 z-20 bg-black/50 backdrop-blur-lg border-b border-white/10">
        <div class="max-w-7xl mx-auto px-6 md:px-16 flex gap-0">
            <button wire:click="$set('activeTab','accommodations')"
                    class="relative px-6 py-4 text-xs font-semibold uppercase tracking-widest transition-colors whitespace-nowrap
                           {{ $activeTab === 'accommodations' ? 'text-brand-400' : 'text-white/40 hover:text-white/70' }}">
                Accommodations
                <span class="ml-2 bg-brand-500/20 text-brand-400 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $this->properties->count() }}</span>
                @if($activeTab === 'accommodations')
                    <span class="absolute bottom-0 left-0 w-full h-0.5 bg-brand-500"></span>
                @endif
            </button>
            <button wire:click="$set('activeTab','services')"
                    class="relative px-6 py-4 text-xs font-semibold uppercase tracking-widest transition-colors whitespace-nowrap
                           {{ $activeTab === 'services' ? 'text-brand-400' : 'text-white/40 hover:text-white/70' }}">
                Add‑on Services
                <span class="ml-2 bg-brand-500/20 text-brand-400 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $this->services->count() }}</span>
                @if($activeTab === 'services')
                    <span class="absolute bottom-0 left-0 w-full h-0.5 bg-brand-500"></span>
                @endif
            </button>
        </div>
    </nav>

    {{-- Body Content --}}
    <div class="max-w-7xl mx-auto px-6 md:px-16 py-10">

        @if($activeTab === 'accommodations')
            <div class="mb-10">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-4 h-px bg-brand-500"></span>
                    <span class="text-xs tracking-[0.2em] uppercase text-brand-500 font-semibold">Stay & Explore</span>
                </div>
                <h2 class="font-display text-3xl md:text-4xl font-medium text-white">Available <em class="italic text-brand-400">Accommodations</em></h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($this->properties as $property)
                    <div class="glass-card flex flex-col group" wire:key="prop-{{ $property->id }}">
                        <div class="relative aspect-[16/10] overflow-hidden rounded-t-2xl">
                            @if($property->images->isNotEmpty())
                                <img src="{{ asset('storage/'.$property->images->first()->image_path) }}" alt="{{ $property->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-700">
                            @else
                                <div class="w-full h-full bg-white/5 flex items-center justify-center text-white/30">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @endif
                            @if($property->propertyType)
                                <span class="absolute top-3 left-3 bg-black/60 backdrop-blur text-xs font-bold text-brand-400 px-2.5 py-1 rounded-full">{{ $property->propertyType->name }}</span>
                            @endif
                            <span class="absolute top-3 right-3 bg-black/60 backdrop-blur text-xs font-bold text-green-300 px-2.5 py-1 rounded-full flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-300 shadow-[0_0_6px_rgba(52,211,153,0.5)]"></span> Available
                            </span>
                        </div>
                        <div class="p-5 flex flex-col flex-1">
                            <h3 class="font-display text-xl font-semibold text-white mb-2">{{ $property->name }}</h3>
                            @if($property->description)
                                <p class="text-sm text-white/60 line-clamp-3 mb-4 flex-1">{{ $property->description }}</p>
                            @endif
                            <div class="flex items-center justify-between pt-4 border-t border-white/10 mt-auto">
                                <div>
                                    <span class="font-display text-2xl font-medium text-white">₱{{ number_format($property->price, 2) }}</span>
                                    <span class="text-xs text-white/50 ml-1">/ night</span>
                                </div>
                                @auth
                                    <a href="{{ route('booking.create', ['publicproperty' => $property->id]) }}"
                                       class="py-2 px-5 rounded-full bg-brand-600 hover:bg-brand-500 text-white text-xs font-semibold uppercase tracking-wider transition shadow-lg shadow-brand-500/20">
                                        Reserve
                                    </a>
                                @else
                                    <a href="{{ route('login', ['redirect' => url()->current()]) }}"
                                       class="py-2 px-5 rounded-full glass hover:bg-white/10 text-white text-xs font-semibold uppercase tracking-wider transition">
                                        Login to Book
                                    </a>
                                @endauth
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full glass-card p-12 text-center">
                        <svg class="w-12 h-12 mx-auto mb-4 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <h3 class="font-display text-xl italic text-white/50">No accommodations listed yet.</h3>
                        <p class="text-sm text-white/40 mt-2">Check back soon — new rooms may be added.</p>
                    </div>
                @endforelse
            </div>
        @endif

        @if($activeTab === 'services')
            <div class="mb-10">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-4 h-px bg-brand-500"></span>
                    <span class="text-xs tracking-[0.2em] uppercase text-brand-500 font-semibold">Enhance Your Stay</span>
                </div>
                <h2 class="font-display text-3xl md:text-4xl font-medium text-white">Add‑on <em class="italic text-brand-400">Services</em></h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @forelse($this->services as $service)
                    <div class="glass-card p-6 flex flex-col relative overflow-hidden group" wire:key="svc-{{ $service->id }}">
                        <div class="absolute top-0 left-0 w-full h-0.5 bg-gradient-to-r from-brand-500 to-transparent scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></div>
                        <div class="w-11 h-11 rounded-xl bg-brand-500/20 border border-brand-400/20 flex items-center justify-center text-brand-400 mb-4">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        </div>
                        <h3 class="font-display text-xl font-semibold text-white mb-2">{{ $service->name }}</h3>
                        @if($service->description)
                            <p class="text-sm text-white/60 flex-1 mb-4">{{ $service->description }}</p>
                        @endif
                        <div class="flex items-center justify-between pt-4 border-t border-white/10 mt-auto">
                            <span class="font-display text-2xl font-medium text-white">₱{{ number_format($service->price, 2) }}</span>
                            @auth
                                <span class="text-xs font-semibold uppercase tracking-wider text-white/40 bg-white/5 border border-white/10 rounded-full px-3 py-1">Add at checkout</span>
                            @else
                                <a href="{{ route('login', ['redirect' => url()->current()]) }}" class="text-xs font-semibold uppercase tracking-wider text-brand-400 hover:underline">Login to add</a>
                            @endauth
                        </div>
                    </div>
                @empty
                    <div class="col-span-full glass-card p-12 text-center">
                        <svg class="w-12 h-12 mx-auto mb-4 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        <h3 class="font-display text-xl italic text-white/50">No services available yet.</h3>
                        <p class="text-sm text-white/40 mt-2">This destination hasn't listed add‑ons yet.</p>
                    </div>
                @endforelse
            </div>
        @endif

    </div>
</div>