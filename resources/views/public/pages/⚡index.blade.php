<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use Illuminate\Support\Str;

new 
#[Layout('layouts.app')] 
class extends Component {
    
    #[Computed]
    public function tenants()
    {
        return Tenant::with('typeOfTenant')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function mapLocations()
    {
        return $this->tenants
            ->filter(fn($t) => $t->latitude && $t->longitude)
            ->map(function ($tenant, $index) {
                return [
                    'name'  => $tenant->name,
                    'slug'  => $tenant->slug,
                    'lat'   => (float) $tenant->latitude,
                    'lng'   => (float) $tenant->longitude,
                    'type'  => $tenant->typeOfTenant->type ?? 'Business',
                    'color' => 'hsl(' . (($index * 137) % 360) . ', 65%, 55%)',
                ];
            })->values()->toArray();
    }
};
?>
<div>
    <!-- ========== HERO CAROUSEL ========== -->
    <div class="w-full">
        <div data-hs-carousel='{"loadingClasses":"opacity-0","isAutoPlay":true}' class="relative">
            <div class="hs-carousel relative overflow-hidden w-full min-h-[70vh] md:min-h-screen bg-gray-900">
                <div class="hs-carousel-body absolute top-0 bottom-0 start-0 flex flex-nowrap transition-transform duration-700 opacity-0">
                    <div class="hs-carousel-slide">
                        <div class="min-h-[70vh] md:min-h-screen flex flex-col items-center justify-end pb-20
                            bg-[linear-gradient(to_top,rgba(0,0,0,0.75)_0%,rgba(0,0,0,0.2)_50%,rgba(0,0,0,0.1)_100%),url('https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=1200')]
                            bg-cover bg-center">
                            <p class="text-white/60 text-xs md:text-sm uppercase tracking-[0.25em] font-light mb-3">Explore the Philippines</p>
                            <span class="text-white text-7xl md:text-9xl font-black leading-none drop-shadow-2xl" style="font-family: 'Georgia', serif;">Capstone</span>
                            <p class="text-white/60 text-sm md:text-base font-light tracking-widest mt-4">Discover eco‑parks, resorts, and hidden gems</p>
                        </div>
                    </div>
                    <div class="hs-carousel-slide">
                        <div class="min-h-[70vh] md:min-h-screen flex flex-col items-center justify-end pb-20
                            bg-[linear-gradient(to_top,rgba(0,0,0,0.75)_0%,rgba(0,0,0,0.2)_50%,rgba(0,0,0,0.1)_100%),url('https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?q=80&w=1200')]
                            bg-cover bg-center">
                            <p class="text-white/60 text-xs md:text-sm uppercase tracking-[0.25em] font-light mb-3">Your next adventure awaits</p>
                            <span class="text-white text-7xl md:text-9xl font-black leading-none drop-shadow-2xl" style="font-family: 'Georgia', serif;">Wander</span>
                            <p class="text-white/60 text-sm md:text-base font-light tracking-widest mt-4">Curated stays &amp; unforgettable experiences</p>
                        </div>
                    </div>
                    <div class="hs-carousel-slide">
                        <div class="min-h-[70vh] md:min-h-screen flex flex-col items-center justify-end pb-20
                            bg-[linear-gradient(to_top,rgba(0,0,0,0.75)_0%,rgba(0,0,0,0.2)_50%,rgba(0,0,0,0.1)_100%),url('https://images.unsplash.com/photo-1544731612-de7f96ffe55f?q=80&w=1200')]
                            bg-cover bg-center">
                            <p class="text-white/60 text-xs md:text-sm uppercase tracking-[0.25em] font-light mb-3">Travel with ease</p>
                            <span class="text-white text-7xl md:text-9xl font-black leading-none drop-shadow-2xl" style="font-family: 'Georgia', serif;">Local</span>
                            <p class="text-white/60 text-sm md:text-base font-light tracking-widest mt-4">Hotels, inns, and resorts – all in one place</p>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="hs-carousel-prev hs-carousel-disabled:opacity-50 disabled:pointer-events-none absolute inset-y-0 start-0 inline-flex justify-center items-center w-16 h-full text-white hover:bg-white/10 focus:outline-none transition-colors">
                <svg class="shrink-0 size-8" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                <span class="sr-only">Previous</span>
            </button>
            <button type="button" class="hs-carousel-next hs-carousel-disabled:opacity-50 disabled:pointer-events-none absolute inset-y-0 end-0 inline-flex justify-center items-center w-16 h-full text-white hover:bg-white/10 focus:outline-none transition-colors">
                <span class="sr-only">Next</span>
                <svg class="shrink-0 size-8" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </button>
            <div class="hs-carousel-pagination flex justify-center absolute bottom-6 start-0 end-0 space-x-3">
                <span class="hs-carousel-active:bg-white hs-carousel-active:border-white size-3 border-2 border-white/50 rounded-full cursor-pointer transition-all"></span>
                <span class="hs-carousel-active:bg-white hs-carousel-active:border-white size-3 border-2 border-white/50 rounded-full cursor-pointer transition-all"></span>
                <span class="hs-carousel-active:bg-white hs-carousel-active:border-white size-3 border-2 border-white/50 rounded-full cursor-pointer transition-all"></span>
            </div>
        </div>
    </div>

    <!-- ========== DESTINATIONS GRID ========== -->
    <section class="py-20 bg-[#1B261D]">
        <div class="max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:justify-between md:items-end mb-10 gap-8">
                <div>
                    <p class="text-[#C9A84C] font-bold tracking-[0.2em] uppercase text-xs mb-2">Curated Experiences</p>
                    <h2 class="text-4xl md:text-5xl font-extrabold text-white tracking-tight leading-tight">Popular Destinations</h2>
                </div>
                <a href="{{ route('explore.map') }}" wire:navigate class="py-3 px-7 inline-flex items-center gap-x-2 text-sm font-semibold rounded-full bg-[#C9A84C] text-[#1B261D] hover:bg-[#b8963e] transition-colors duration-200 focus:outline-none">
                    View Map
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </a>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($this->tenants as $tenant)
                    <div class="group flex flex-col bg-white rounded-3xl overflow-hidden shadow-md hover:shadow-2xl transition-all duration-500 hover:-translate-y-1.5">
                        <div class="relative h-56 overflow-hidden">
                            <img src="{{ $tenant->logo ? Storage::url($tenant->logo) : 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800' }}"
                                 alt="{{ $tenant->name }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                                 loading="lazy">
                            <span class="absolute top-4 left-4 bg-[#2d7a52]/90 text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                {{ $tenant->typeOfTenant->type ?? 'Stay' }}
                            </span>
                        </div>
                        <div class="flex flex-col flex-1 p-6 bg-white">
                            <h3 class="text-lg font-extrabold text-[#1B261D] leading-snug mb-2">{{ $tenant->name }}</h3>
                            <p class="text-sm text-[#4A554E] leading-relaxed mb-6 flex-1">
                                {{ Str::limit($tenant->address, 100) }}
                            </p>
                            <div class="flex justify-between items-center gap-2 pt-4 border-t border-[#f0efe8]">
                                <span class="text-[11px] text-[#7E8A74] font-semibold">{{ $tenant->contact_number }}</span>
                                <a href="{{ route('tenant.show', $tenant->slug) }}" wire:navigate class="shrink-0 py-2 px-5 text-xs font-bold rounded-full border-2 border-[#C9A84C] text-[#C9A84C] hover:bg-[#C9A84C] hover:text-[#1B261D] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#C9A84C]/30">
                                    Explore
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ========== INTERACTIVE MAP ========== -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <section class="py-12 lg:py-20 bg-[#1B261D]">
        <div class="max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-5 gap-10 items-start">
                <div class="lg:col-span-3 order-2 lg:order-1">
                    <p class="text-[#8fc99a] font-bold tracking-[0.2em] uppercase text-xs mb-2 flex items-center gap-2">
                        <span class="inline-block w-5 h-px bg-[#8fc99a]"></span>
                        Explore the Region
                    </p>
                    <h3 class="text-3xl font-extrabold text-white mb-5 leading-snug">Interactive Map</h3>
                    <div id="map" style="width:100%; height:450px;" class="rounded-2xl border border-white/10 overflow-hidden shadow-2xl"></div>
                </div>

                <div class="lg:col-span-2 pt-2 order-1 lg:order-2">
                    <p class="text-white/40 text-[10px] font-bold tracking-widest uppercase mb-4">Nearby Destinations</p>
                    <div id="location-list" class="flex flex-col gap-2 mb-8">
                        @foreach ($this->mapLocations() as $index => $loc)
                            <button onclick="focusLocation({{ $index }})" class="group flex items-center gap-3 bg-white/5 hover:bg-white/10 border border-white/8 rounded-xl px-4 py-3 cursor-pointer transition-all duration-200 text-left w-full focus:outline-none focus:ring-1 focus:ring-opacity-50">
                                <span class="size-2.5 rounded-full shrink-0" style="background: {{ $loc['color'] }}"></span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] text-white/80 font-semibold truncate">{{ $loc['name'] }}</p>
                                </div>
                                <span class="text-[11px] text-white/35 font-medium shrink-0">{{ $loc['type'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <a href="{{ route('explore.map') }}" wire:navigate
                       class="inline-flex items-center gap-x-2 py-3 px-6 text-sm font-semibold rounded-full bg-white text-[#1B261D] hover:bg-[#e8f5ec] transition-colors duration-200 focus:outline-none">
                        Explore Full Map
                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <script>
        (function() {
            function initMap() {
                var mapEl = document.getElementById('map');
                if (!mapEl) return;
                mapEl.innerHTML = '';

                const locations = @json($this->mapLocations());

                const defaultCenter = locations.length > 0
                    ? [locations[0].lat, locations[0].lng]
                    : [12.8797, 121.7740];

                const map = L.map('map', {
                    center: defaultCenter,
                    zoom: locations.length === 0 ? 6 : (locations.length === 1 ? 15 : 10),
                    zoomControl: true
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                window.addEventListener('load', function() { map.invalidateSize(); });
                setTimeout(function() { map.invalidateSize(); }, 500);

                const markers = [];
                locations.forEach((loc, index) => {
                    const color = loc.color || `hsl(${(index * 137) % 360}, 65%, 55%)`;
                    const marker = L.circleMarker([loc.lat, loc.lng], {
                        radius: 8,
                        fillColor: color,
                        color: '#ffffff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.9
                    }).addTo(map);
                    marker.bindPopup(`<strong>${loc.name}</strong><br/>${loc.type}<br/><a href="/business/${loc.slug}" class="text-blue-500 underline" wire:navigate>Visit business</a>`);
                    markers.push(marker);
                });

                window.focusLocation = function(index) {
                    if (index < 0 || index >= locations.length) return;
                    const loc = locations[index];
                    map.flyTo([loc.lat, loc.lng], 15, { duration: 1.5 });
                    setTimeout(() => markers[index].openPopup(), 1200);
                };
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initMap);
            } else {
                initMap();
            }
        })();
    </script>
</div>