<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use Illuminate\Support\Str;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Edit Tenant')]
class extends Component {
    
    public Tenant $tenantRecord;
    
    public $name = '';
    public $slug = '';
    public $type_of_tenant_id = '';
    public $address = '';
    public $email = '';
    public $contact_number = '';
    public $latitude;
    public $longitude;

    #[Computed]
    public function tenantTypes()
    {
        return TypeOfTenant::all();
    }

    public function mount(Tenant $tenant)
    {
        $this->tenantRecord = $tenant;
        
        // Mapped to the CORRECT database columns
        $this->name = $tenant->name;
        $this->slug = $tenant->slug;
        $this->type_of_tenant_id = $tenant->type_of_tenant_id;
        $this->address = $tenant->address;
        $this->email = $tenant->email;
        $this->contact_number = $tenant->contact_number;
        $this->latitude = $tenant->latitude ?? 10.6765;
        $this->longitude = $tenant->longitude ?? 122.9509;
    }

    public function updatedName($value)
    {
        // Auto-update the slug when the name changes, just like in Create
        $this->slug = Str::slug($value);
    }

    public function update()
    {
        $validated = $this->validate([
            'name' => 'required|min:3|max:255|unique:tenants,name,' . $this->tenantRecord->id,
            'slug' => 'required|string|max:255|unique:tenants,slug,' . $this->tenantRecord->id,
            'type_of_tenant_id' => 'required|integer',
            'email' => 'required|email|unique:tenants,email,' . $this->tenantRecord->id,
            'address' => 'required|string|max:255',
            'contact_number' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $this->tenantRecord->update($validated);

        session()->flash('message', 'Business Location successfully updated!');
        return $this->redirectRoute('superadmin.tenants.index', navigate: true);
    }
};
?>

<div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" data-navigate-once />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" data-navigate-once></script>

    <div class="p-6 sm:p-10 max-w-7xl mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Edit Business</h1>
                <p class="text-slate-500">Update business details for {{ $name }}</p>
            </div>
            <a href="{{ route('superadmin.tenants.index') }}" wire:navigate class="text-slate-500 hover:text-slate-700 font-medium">
                &larr; Back to Tenants
            </a>
        </div>

        <form wire:submit="update" class="grid grid-cols-1 lg:grid-cols-2 gap-8" 
            x-data="{
                map: null,
                marker: null,
                init() {
                    let checkInterval = setInterval(() => {
                        if (typeof L !== 'undefined') {
                            clearInterval(checkInterval);
                            this.initMap();
                        }
                    }, 100);
                },
                initMap() {
                    let lat = parseFloat($wire.latitude) || 10.6765;
                    let lng = parseFloat($wire.longitude) || 122.9509;

                    this.map = L.map($refs.mapContainer).setView([lat, lng], 13);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(this.map);

                    this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);

                    setTimeout(() => { this.map.invalidateSize(); }, 300);

                    this.marker.on('dragend', (e) => {
                        let pos = e.target.getLatLng();
                        $wire.latitude = pos.lat;
                        $wire.longitude = pos.lng;
                    });

                    this.map.on('click', (e) => {
                        this.marker.setLatLng(e.latlng);
                        $wire.latitude = e.latlng.lat;
                        $wire.longitude = e.latlng.lng;
                    });

                    $watch('$wire.latitude', val => this.updateMap());
                    $watch('$wire.longitude', val => this.updateMap());
                },
                updateMap() {
                    let lat = parseFloat($wire.latitude);
                    let lng = parseFloat($wire.longitude);
                    if (!isNaN(lat) && !isNaN(lng) && this.marker) {
                        this.marker.setLatLng([lat, lng]);
                        this.map.setView([lat, lng]);
                    }
                },
                getLocation() {
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                let lat = position.coords.latitude;
                                let lng = position.coords.longitude;
                                
                                this.marker.setLatLng([lat, lng]);
                                this.map.flyTo([lat, lng], 16);
                                
                                $wire.latitude = lat;
                                $wire.longitude = lng;
                            },
                            (error) => alert('Could not get GPS location. Please check your browser permissions.')
                        );
                    } else {
                        alert('Geolocation is not supported by your browser.');
                    }
                }
            }"
        >
            <div class="space-y-6">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Business Details</h2>
                    
                    <div class="grid grid-cols-1 gap-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Business Name</label>
                                <input type="text" wire:model.live.debounce.300ms="name" class="w-full rounded-lg border-slate-300 focus:ring-blue-500">
                                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">URL Slug</label>
                                <input type="text" wire:model="slug" class="w-full rounded-lg border-slate-300 bg-slate-50 text-slate-600 focus:ring-blue-500">
                                @error('slug') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Tenant Type</label>
                                <select wire:model="type_of_tenant_id" class="w-full rounded-lg border-slate-300 focus:ring-blue-500 bg-white">
                                    <option value="">-- Select Type --</option>
                                    @foreach($this->tenantTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->type }}</option>
                                    @endforeach
                                </select>
                                @error('type_of_tenant_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Contact Number</label>
                                <input type="text" wire:model="contact_number" class="w-full rounded-lg border-slate-300 focus:ring-blue-500">
                                @error('contact_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Business Email</label>
                            <input type="email" wire:model="email" class="w-full rounded-lg border-slate-300 focus:ring-blue-500">
                            @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Headquarters Address</label>
                            <input type="text" wire:model="address" class="w-full rounded-lg border-slate-300 focus:ring-blue-500">
                            @error('address') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Latitude</label>
                                <input type="text" wire:model.live="latitude" class="w-full bg-slate-50 rounded-lg border-slate-300 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Longitude</label>
                                <input type="text" wire:model.live="longitude" class="w-full bg-slate-50 rounded-lg border-slate-300 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <button type="button" @click="getLocation()" class="text-sm text-blue-600 hover:text-blue-800 flex items-center mt-1 w-fit">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Use My Current GPS Location
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl shadow-md transition-all flex items-center justify-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
                    <span class="in-data-loading:hidden">Update Business Details</span>
                    <span class="not-in-data-loading:hidden flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving Changes...
                    </span>
                </button>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-2 relative z-10 h-fit sticky top-6">
                <div class="p-2 text-sm text-slate-500">Drag the marker, click the map, or use the GPS button to update the location.</div>
                <div wire:ignore>
                    <div x-ref="mapContainer" style="height: 500px; width: 100%;" class="rounded-lg border border-slate-200"></div>
                </div>
            </div>
        </form>
    </div>
</div>