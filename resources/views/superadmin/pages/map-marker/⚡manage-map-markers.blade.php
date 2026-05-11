<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\Tenant;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Master Map Control')]
class extends Component {
    use WithPagination;
    
    public string $tenant_id = ''; 
    public $latitude = 10.6765;
    public $longitude = 122.9509;
    public string $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatedTenantId($value)
    {
        if ($value) {
            $tenant = Tenant::find($value);
            if ($tenant) {
                $this->latitude = $tenant->latitude ?? 10.6765;
                $this->longitude = $tenant->longitude ?? 122.9509;
                $this->dispatch('fly-to-location', lat: $this->latitude, lng: $this->longitude);
            }
        } else {
            $this->resetFields();
        }
    }

    #[Computed]
    public function availableTenants()
    {
        return Tenant::orderBy('name')->select('id', 'name')->get();
    }

    #[Computed]
    public function tenants()
    {
        return Tenant::when($this->search, function($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function allMappedTenants()
    {
        return Tenant::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'name', 'latitude', 'longitude')
            ->get();
    }

    public function store()
    {
        $this->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $tenant = Tenant::findOrFail($this->tenant_id);
        $tenant->update([
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ]);

        session()->flash('message', "Location updated successfully for {$tenant->name}.");
        $this->resetFields();
        unset($this->allMappedTenants, $this->tenants);
    }

    public function edit($id)
    {
        $tenant = Tenant::findOrFail($id);
        $this->tenant_id = (string) $tenant->id;
        $this->latitude = $tenant->latitude ?? 10.6765;
        $this->longitude = $tenant->longitude ?? 122.9509;
        $this->dispatch('fly-to-location', lat: $this->latitude, lng: $this->longitude);
    }

    public function removeLocation($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update([
            'latitude' => null,
            'longitude' => null
        ]);
        
        session()->flash('message', "Location removed for {$tenant->name}.");
        if ($this->tenant_id == $id) {
            $this->resetFields();
        }
        unset($this->allMappedTenants);
    }

    public function resetFields()
    {
        $this->reset(['tenant_id']);
        $this->latitude = 10.6765;
        $this->longitude = 122.9509;
        $this->dispatch('reset-map');
    }
};
?>

<div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" data-navigate-once />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" data-navigate-once></script>

    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet" data-navigate-once>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.dark.css" rel="stylesheet" data-navigate-once>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js" data-navigate-once></script>

    @push('styles')
    <style>
        /* Fix invisible options in glass-style selects */
        select option {
            background: #1e293b;
            color: #e2e8f0;
        }
        .leaflet-container {
            background: transparent !important;
            border-radius: 0.75rem;
        }
        .leaflet-control-zoom a {
            background: rgba(255,255,255,0.06) !important;
            backdrop-filter: blur(16px);
            color: #fff !important;
            border: 1px solid rgba(255,255,255,0.12) !important;
        }
        .leaflet-control-zoom a:hover {
            background: rgba(255,255,255,0.12) !important;
        }
        .leaflet-control-attribution {
            background: rgba(0,0,0,0.5) !important;
            color: rgba(255,255,255,0.4) !important;
            font-size: 10px !important;
        }
        .leaflet-control-attribution a {
            color: rgba(255,255,255,0.6) !important;
        }
    </style>
    @endpush

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-white/10">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-brand-600 dark:text-brand-400 flex items-center gap-2 mb-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-500 shadow-[0_0_8px_var(--color-brand-500)]"></span>
                    Super Admin · Map
                </p>
                <h1 class="font-display text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Master Map Control</h1>
                <p class="text-sm text-gray-500 dark:text-white/50 mt-1">Plot and view all business locations simultaneously.</p>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-400 font-medium">
                {{ session('message') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6"
            x-data="{
                map: null,
                activeMarker: null,
                layerGroup: null,
                tileLayer: null,
                globalTenants: @js($this->allMappedTenants),
                
                init() {
                    let checkInterval = setInterval(() => {
                        if (typeof L !== 'undefined') {
                            clearInterval(checkInterval);
                            this.initMap();
                        }
                    }, 100);

                    window.addEventListener('fly-to-location', (e) => {
                        let lat = parseFloat(e.detail.lat);
                        let lng = parseFloat(e.detail.lng);
                        if(this.activeMarker) {
                            this.activeMarker.setLatLng([lat, lng]);
                            this.activeMarker.setOpacity(1);
                        }
                        this.map.flyTo([lat, lng], 16, { animate: true, duration: 1.5 });
                    });

                    window.addEventListener('reset-map', () => {
                        if(this.activeMarker) this.activeMarker.setOpacity(0);
                    });
                },
                
                initMap() {
                    let lat = parseFloat($wire.latitude) || 10.6765;
                    let lng = parseFloat($wire.longitude) || 122.9509;

                    this.map = L.map($refs.mapContainer).setView([lat, lng], 12);

                    this.updateTileLayer();
                    new MutationObserver(() => this.updateTileLayer()).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

                    this.layerGroup = L.layerGroup().addTo(this.map);
                    this.plotGlobalPins();

                    let activeIcon = L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    });

                    this.activeMarker = L.marker([lat, lng], { 
                        draggable: true, 
                        icon: activeIcon,
                        opacity: $wire.tenant_id ? 1 : 0 
                    }).addTo(this.map);

                    this.activeMarker.on('dragend', (e) => {
                        if(!$wire.tenant_id) return;
                        let pos = e.target.getLatLng();
                        $wire.latitude = pos.lat.toFixed(6);
                        $wire.longitude = pos.lng.toFixed(6);
                    });

                    this.map.on('click', (e) => {
                        if(!$wire.tenant_id) return;
                        this.activeMarker.setLatLng(e.latlng);
                        $wire.latitude = e.latlng.lat.toFixed(6);
                        $wire.longitude = e.latlng.lng.toFixed(6);
                        this.activeMarker.setOpacity(1);
                    });

                    setTimeout(() => { this.map.invalidateSize(); }, 300);
                },

                updateTileLayer() {
                    const isDark = document.documentElement.classList.contains('dark');
                    const url = isDark
                        ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
                        : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png';
                    const attribution = '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a> contributors &copy; <a href=&quot;https://carto.com/&quot;>CARTO</a>';
                    if (this.tileLayer) {
                        this.map.removeLayer(this.tileLayer);
                    }
                    this.tileLayer = L.tileLayer(url, { maxZoom: 19, attribution: attribution }).addTo(this.map);
                },

                plotGlobalPins() {
                    this.layerGroup.clearLayers();
                    let staticIcon = L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-grey.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    });

                    this.globalTenants.forEach(tenant => {
                        if($wire.tenant_id == tenant.id) return; 

                        L.marker([tenant.latitude, tenant.longitude], { icon: staticIcon })
                            .bindPopup(`<b>${tenant.name}</b>`)
                            .addTo(this.layerGroup);
                    });
                },

                getLocation() {
                    if (!$wire.tenant_id) return alert('Please select a tenant first!');
                    
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                let lat = position.coords.latitude;
                                let lng = position.coords.longitude;
                                this.activeMarker.setLatLng([lat, lng]);
                                this.activeMarker.setOpacity(1);
                                this.map.flyTo([lat, lng], 17);
                                $wire.latitude = lat.toFixed(6);
                                $wire.longitude = lng.toFixed(6);
                            },
                            (error) => alert('GPS failed. Please check permissions.')
                        );
                    }
                }
            }"
        >
            <div class="lg:col-span-8 order-2 lg:order-1">
                <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl shadow-sm dark:shadow-none p-2 h-full min-h-[600px] relative overflow-hidden">
                    <div class="absolute top-4 right-4 z-[400] glass-card !rounded-lg !px-3 !py-2 text-xs text-white/80 flex flex-col gap-1">
                        <div class="flex items-center gap-2"><div class="w-3 h-3 bg-red-500 rounded-full"></div> Active Pin</div>
                        <div class="flex items-center gap-2"><div class="w-3 h-3 bg-gray-400 rounded-full"></div> Existing Spots</div>
                    </div>
                    <div wire:ignore class="h-full">
                        <div x-ref="mapContainer" class="h-full rounded-lg" style="min-height: 600px;"></div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4 order-1 lg:order-2 space-y-6">
                {{-- Location Setter --}}
                <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl shadow-sm dark:shadow-none p-4 sm:p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Set Location</h2>
                    
                    <form wire:submit="store" class="space-y-4">
                        <div x-data="{
                                ts: null,
                                init() {
                                    const isDark = document.documentElement.classList.contains('dark');
                                    this.ts = new TomSelect(this.$refs.select, {
                                        create: false,
                                        placeholder: 'Search establishments...',
                                        sortField: { field: 'text', direction: 'asc' },
                                        className: isDark ? 'dark' : '',
                                        onChange: (value) => {
                                            $wire.tenant_id = value;
                                        }
                                    });

                                    $wire.$watch('tenant_id', (value) => {
                                        this.ts.setValue(value, true); 
                                    });
                                }
                            }"
                        >
                            <label class="block text-sm font-medium text-gray-700 dark:text-white/70 mb-1">Establishment / Tenant</label>
                            <div wire:ignore>
                                <select x-ref="select" class="w-full text-sm">
                                    <option value="">Search establishments...</option>
                                    @foreach($this->availableTenants as $tenantOption)
                                        <option value="{{ $tenantOption->id }}">{{ $tenantOption->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('tenant_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-white/70 mb-1">Lat</label>
                                <input type="text" wire:model.live="latitude"
                                       class="w-full rounded-xl bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition {{ !$tenant_id ? 'opacity-50 cursor-not-allowed' : '' }}"
                                       {{ !$tenant_id ? 'disabled' : '' }}>
                                @error('latitude') <span class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-white/70 mb-1">Lng</label>
                                <input type="text" wire:model.live="longitude"
                                       class="w-full rounded-xl bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition {{ !$tenant_id ? 'opacity-50 cursor-not-allowed' : '' }}"
                                       {{ !$tenant_id ? 'disabled' : '' }}>
                                @error('longitude') <span class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <button type="button" @click="getLocation()"
                                class="text-sm text-brand-600 dark:text-brand-400 hover:text-brand-500 dark:hover:text-brand-300 flex items-center disabled:opacity-50"
                                {{ !$tenant_id ? 'disabled' : '' }}>
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Sync to My GPS
                        </button>

                        <div class="pt-4 flex gap-2">
                            <button type="submit"
                                    class="bg-brand-600 hover:bg-brand-500 text-white font-medium py-2.5 px-4 rounded-xl shadow-lg shadow-brand-500/20 transition flex-1 disabled:opacity-50 disabled:cursor-not-allowed"
                                    {{ !$tenant_id ? 'disabled' : '' }}>
                                Save Location
                            </button>
                            <button type="button" wire:click="resetFields"
                                    class="bg-gray-100 dark:bg-white/10 hover:bg-gray-200 dark:hover:bg-white/20 text-gray-700 dark:text-white/70 font-medium py-2.5 px-4 rounded-xl transition disabled:opacity-50"
                                    {{ !$tenant_id ? 'disabled' : '' }}>
                                Clear
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Tenant List --}}
                <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl shadow-sm dark:shadow-none flex flex-col h-[400px]">
                    <div class="p-4 border-b border-gray-200 dark:border-white/10">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Filter list..." 
                               class="w-full rounded-xl bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        @forelse ($this->tenants as $tenant)
                            <div class="p-3 border border-gray-200 dark:border-white/10 rounded-xl hover:border-brand-400/50 dark:hover:border-brand-400/50 transition-colors {{ $tenant_id == $tenant->id ? 'border-brand-500/50 bg-brand-50 dark:bg-brand-500/10' : '' }}">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-medium text-sm text-gray-900 dark:text-white">{{ $tenant->name }}</div>
                                        @if($tenant->latitude && $tenant->longitude)
                                            <span class="text-[10px] font-medium text-green-600 dark:text-green-400 flex items-center mt-1">
                                                <div class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1"></div> Mapped
                                            </span>
                                        @else
                                            <span class="text-[10px] font-medium text-gray-500 dark:text-white/40 flex items-center mt-1">
                                                <div class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-white/20 mr-1"></div> Unmapped
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <button wire:click="edit({{ $tenant->id }})"
                                                class="text-xs bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 px-2 py-1 rounded-lg shadow-sm hover:bg-gray-50 dark:hover:bg-white/20 text-brand-600 dark:text-brand-400 transition">
                                            Set Pin
                                        </button>
                                        @if($tenant->latitude)
                                            <button wire:click="removeLocation({{ $tenant->id }})" wire:confirm="Remove this pin?"
                                                    class="text-[10px] text-red-500 dark:text-red-400 hover:underline text-right">
                                                Remove
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-gray-500 dark:text-white/40 text-sm py-4">No businesses found.</div>
                        @endforelse
                    </div>
                    
                    <div class="p-3 border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 rounded-b-2xl">
                        {{ $this->tenants->links(data: ['scrollTo' => false]) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>