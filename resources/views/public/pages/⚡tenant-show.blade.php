<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Scopes\TenantScope;

new 
#[Layout('layouts.app')]
#[Title('Business Details')]
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

    #[Computed]
    public function galleryImages()
    {
        $setting = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'business_gallery')
            ->first();
        return $setting ? $setting->value : [];
    }
}
?>

<div class="min-h-screen bg-white" x-data="{ previewImage: null }">
    {{-- Image Preview Modal --}}
    <div x-show="previewImage" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80" @click.self="previewImage = null" @keydown.escape.window="previewImage = null">
        <div class="relative max-w-5xl max-h-[90vh]">
            <button @click="previewImage = null" class="absolute -top-10 right-0 text-white hover:text-gray-300 text-sm font-medium">Close</button>
            <img :src="previewImage" class="max-w-full max-h-[85vh] rounded-xl shadow-2xl border-4 border-white">
        </div>
    </div>

    {{-- Hero / Cover Section --}}
    <div class="bg-white border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
            <div class="flex flex-col lg:flex-row gap-10 items-start">
                
                {{-- Business Info --}}
                <div class="flex-1 w-full">
                    <div class="flex items-center gap-6 mb-6">
                        <div class="shrink-0">
                            @if($tenant->logo)
                                <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}" class="h-28 w-28 object-cover rounded-2xl border border-slate-200 shadow-sm">
                            @else
                                <div class="h-28 w-28 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold text-4xl shadow-sm">
                                    {{ substr($tenant->name, 0, 1) }}
                                </div>
                            @endif
                        </div>
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <h1 class="text-4xl font-bold text-slate-900 tracking-tight">{{ $tenant->name }}</h1>
                                <span class="px-3 py-1 text-xs font-semibold uppercase tracking-wider rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                                    {{ $tenant->typeOfTenant->type ?? 'Business' }}
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-slate-600">
                                @if($tenant->address)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span>{{ $tenant->address }}</span>
                                </div>
                                @endif
                                @if($tenant->contact_number)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    <span>{{ $tenant->contact_number }}</span>
                                </div>
                                @endif
                                @if($tenant->email)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <a href="mailto:{{ $tenant->email }}" class="hover:text-blue-600 transition-colors">{{ $tenant->email }}</a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-wrap gap-3 mt-8">
                        @if($this->properties->isNotEmpty())
                            @auth
                                <a href="{{ route('business.offerings', $tenant->slug) }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-sm hover:shadow transition-all duration-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Book Now
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-sm transition-all duration-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Login to Book
                                </a>
                            @endauth
                        @endif
                        @if($tenant->latitude && $tenant->longitude)
                        <a href="https://www.google.com/maps/search/?api=1&query={{ $tenant->latitude }},{{ $tenant->longitude }}" target="_blank" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-700 font-medium py-2.5 px-5 rounded-xl shadow-sm transition-all duration-200">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            Get Directions
                        </a>
                        @endif
                    </div>
                </div>

                {{-- Map (if coordinates) --}}
                @if($tenant->latitude && $tenant->longitude)
                <div class="w-full lg:w-80 shrink-0">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/80">
                            <h3 class="font-semibold text-slate-800 text-sm flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Location
                            </h3>
                        </div>
                        <div class="h-48 w-full relative z-0"
                             x-data="{ 
                                init() { 
                                    let check = setInterval(() => {
                                        if (typeof L !== 'undefined') {
                                            clearInterval(check);
                                            delete L.Icon.Default.prototype._getIconUrl;
                                            L.Icon.Default.mergeOptions({
                                                iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
                                                iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                                                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                                            });
                                            let map = L.map($refs.miniMap).setView([{{ $tenant->latitude }}, {{ $tenant->longitude }}], 14);
                                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
                                                maxZoom: 19,
                                                attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a>'
                                            }).addTo(map);
                                            L.marker([{{ $tenant->latitude }}, {{ $tenant->longitude }}]).addTo(map);
                                            setTimeout(() => map.invalidateSize(), 100);
                                        }
                                    }, 100);
                                }
                             }">
                            <div wire:ignore x-ref="miniMap" class="h-full w-full"></div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- PHOTO GALLERY --}}
    @if(!empty($this->galleryImages()))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-slate-900">Photo Gallery</h2>
            <p class="text-slate-500 text-sm mt-1">Take a look around before you visit</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($this->galleryImages() as $imagePath)
                <div class="aspect-square overflow-hidden rounded-xl cursor-pointer relative group shadow-sm"
                     @click="previewImage = '{{ Storage::url($imagePath) }}'">
                    <img src="{{ Storage::url($imagePath) }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m4-6v6"/></svg>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>