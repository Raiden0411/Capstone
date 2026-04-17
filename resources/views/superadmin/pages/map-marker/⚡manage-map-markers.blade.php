<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\Tenant;

new class extends Component {
    use WithPagination;
    
    public string $tenant_id = ''; 
    public $latitude = 10.6765; // Defaulted to Bacolod
    public $longitude = 122.9509;
    
    public string $search = '';

    public function rendering($view)
    {
        $view->layout('superadmin.layouts.app')->title('Manage Master Map');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    // NEW: This hook automatically runs when the dropdown changes $tenant_id
    public function updatedTenantId($value)
    {
        if ($value) {
            $tenant = Tenant::find($value);
            if ($tenant) {
                $this->latitude = $tenant->latitude ?? 10.6765;
                $this->longitude = $tenant->longitude ?? 122.9509;
                
                // Tell the map to fly to the new coordinates
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
        
        unset($this->allMappedTenants); 
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
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js" data-navigate-once></script>

    <div class="p-6 sm:p-10 max-w-7xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-800">Master Map Control</h1>
            <p class="text-slate-500">Plot and view all business locations simultaneously.</p>
        </div>

        @if (session()->has('message'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                {{ session('message') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8"
            x-data="{
                map: null,
                activeMarker: null,
                layerGroup: null,
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

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(this.map);

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
                        this.activeMarker.setOpacity(1); // Ensures marker becomes visible if clicked directly
                    });

                    setTimeout(() => { this.map.invalidateSize(); }, 300);
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
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-2 h-full min-h-[600px] relative">
                    <div class="absolute top-4 right-4 z-[400] bg-white px-3 py-2 rounded shadow text-xs text-slate-600 border border-slate-200">
                        <div class="flex items-center gap-2 mb-1"><div class="w-3 h-3 bg-red-500 rounded-full"></div> Active Pin</div>
                        <div class="flex items-center gap-2"><div class="w-3 h-3 bg-gray-400 rounded-full"></div> Existing Spots</div>
                    </div>
                    <div wire:ignore class="h-full">
                        <div x-ref="mapContainer" class="h-full rounded-lg border border-slate-200" style="min-height: 600px; z-index: 10;"></div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4 order-1 lg:order-2 space-y-6">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-slate-800 mb-4">Set Location</h2>
                    
                    <form wire:submit="store" class="space-y-4">
                        
                        <div x-data="{
                                ts: null,
                                init() {
                                    this.ts = new TomSelect(this.$refs.select, {
                                        create: false,
                                        placeholder: 'Search establishments...',
                                        sortField: { field: 'text', direction: 'asc' },
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
                            <label class="block text-sm font-medium text-slate-700 mb-1">Establishment / Tenant</label>
                            <div wire:ignore>
                                <select x-ref="select" class="w-full text-sm">
                                    <option value="">Search establishments...</option>
                                    @foreach($this->availableTenants as $tenantOption)
                                        <option value="{{ $tenantOption->id }}">{{ $tenantOption->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('tenant_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Lat</label>
                                <input type="text" wire:model.live="latitude" class="w-full bg-slate-50 rounded-lg border-slate-300 text-xs" {{ !$tenant_id ? 'disabled' : '' }}>
                                @error('latitude') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Lng</label>
                                <input type="text" wire:model.live="longitude" class="w-full bg-slate-50 rounded-lg border-slate-300 text-xs" {{ !$tenant_id ? 'disabled' : '' }}>
                                @error('longitude') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <button type="button" @click="getLocation()" class="text-xs text-blue-600 hover:text-blue-800 flex items-center disabled:opacity-50" {{ !$tenant_id ? 'disabled' : '' }}>
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                            Sync to My GPS
                        </button>

                        <div class="pt-4 flex gap-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-4 rounded-lg shadow-sm transition-all flex-1 disabled:bg-slate-300 disabled:cursor-not-allowed" {{ !$tenant_id ? 'disabled' : '' }}>
                                Save Location
                            </button>
                            <button type="button" wire:click="resetFields" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium py-2.5 px-4 rounded-lg shadow-sm transition-all disabled:opacity-50" {{ !$tenant_id ? 'disabled' : '' }}>
                                Clear
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col h-[400px]">
                    <div class="p-4 border-b border-slate-100">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Filter list..." class="w-full rounded-lg border-slate-300 focus:ring-blue-500 text-sm">
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        @forelse ($this->tenants as $tenant)
                            <div class="p-3 border border-slate-100 rounded-lg hover:border-blue-200 hover:bg-blue-50 transition-colors {{ $tenant_id == $tenant->id ? 'border-blue-300 bg-blue-50' : '' }}">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-medium text-sm text-slate-800">{{ $tenant->name }}</div>
                                        @if($tenant->latitude && $tenant->longitude)
                                            <span class="text-[10px] font-medium text-green-600 flex items-center mt-1">
                                                <div class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1"></div> Mapped
                                            </span>
                                        @else
                                            <span class="text-[10px] font-medium text-slate-400 flex items-center mt-1">
                                                <div class="w-1.5 h-1.5 rounded-full bg-slate-300 mr-1"></div> Unmapped
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <button wire:click="edit({{ $tenant->id }})" class="text-xs bg-white border border-slate-200 px-2 py-1 rounded shadow-sm hover:bg-slate-50 text-blue-600">Set Pin</button>
                                        @if($tenant->latitude)
                                            <button wire:click="removeLocation({{ $tenant->id }})" wire:confirm="Remove this pin?" class="text-[10px] text-red-500 hover:underline text-right">Remove</button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-slate-500 text-sm py-4">No businesses found.</div>
                        @endforelse
                    </div>
                    
                    <div class="p-3 border-t border-slate-100 bg-slate-50 rounded-b-xl">
                        {{ $this->tenants->links(data: ['scrollTo' => false]) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>