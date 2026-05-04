<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        
        $this->name = $tenant->name;
        $this->slug = $tenant->slug;
        $this->type_of_tenant_id = $tenant->type_of_tenant_id;
        $this->address = $tenant->address;
        $this->email = $tenant->email;
        $this->contact_number = $tenant->contact_number;
        $this->latitude = $tenant->latitude ?? 10.6765;
        $this->longitude = $tenant->longitude ?? 122.9509;
    }

    /**
     * Auto‑update slug when name changes.
     */
    public function updatedName($value)
    {
        $this->slug = Str::slug(trim($value));
    }

    /**
     * Trim string inputs on update.
     */
    public function updated($property)
    {
        if (in_array($property, ['name', 'address', 'contact_number', 'email'])) {
            $this->$property = trim($this->$property);
        }
    }

    /**
     * Sanitize all string inputs before validation: trim and strip HTML tags.
     */
    protected function sanitize(): void
    {
        $fields = ['name', 'address', 'contact_number', 'email'];
        foreach ($fields as $field) {
            if (is_string($this->$field)) {
                $this->$field = trim(strip_tags($this->$field));
            }
        }
        // Slug is re‑generated if name changed, otherwise keep as is
        $this->slug = Str::slug($this->name);
    }

    public function update()
    {
        $this->sanitize();

        $validated = $this->validate([
            'name' => [
                'required', 'min:3', 'max:255',
                Rule::unique('tenants', 'name')->ignore($this->tenantRecord->id),
            ],
            'slug' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('tenants', 'slug')->ignore($this->tenantRecord->id),
            ],
            'type_of_tenant_id' => 'required|integer|exists:type_of_tenants,id',
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('tenants', 'email')->ignore($this->tenantRecord->id),
                function ($attribute, $value, $fail) {
                    $adminUser = User::where('tenant_id', $this->tenantRecord->id)
                                    ->where('email', $value)
                                    ->first();
                    
                    $exists = User::where('email', $value)
                        ->when($adminUser, fn($q) => $q->where('id', '!=', $adminUser->id))
                        ->exists();
                    
                    if ($exists) {
                        $fail('This email is already in use by another user account.');
                    }
                },
            ],
            'address' => 'required|string|max:255',
            'contact_number' => 'nullable|string|max:20|regex:/^[0-9\+\-\s\(\)]+$/',
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180',
        ], [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and hyphens.',
        ]);

        $this->tenantRecord->update($validated);

        // If email changed, update the associated admin user's email
        if ($this->tenantRecord->wasChanged('email')) {
            $adminUser = User::where('tenant_id', $this->tenantRecord->id)
                            ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
                            ->first();
            if ($adminUser) {
                $adminUser->update(['email' => $this->email]);
            }
        }

        session()->flash('message', 'Business Location successfully updated!');
        return $this->redirectRoute('superadmin.tenants.index', navigate: true);
    }
};
?>

<div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" data-navigate-once />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" data-navigate-once></script>

    <div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white">

        {{-- Flash Message --}}
        @if (session()->has('message'))
            <div class="mb-6 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 p-4 rounded-md shadow-sm">
                <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit Business</h1>
                <p class="text-gray-500 dark:text-slate-400 mt-1">Update business details for {{ $name }}</p>
            </div>
            <a href="{{ route('superadmin.tenants.index') }}" wire:navigate class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white font-medium transition-colors">
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
            {{-- Left Column: Form Fields --}}
            <div class="space-y-6">
                {{-- Business Details Card --}}
                <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-200 dark:border-slate-700/50 pb-2">Business Details</h2>
                    
                    <div class="grid grid-cols-1 gap-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Business Name</label>
                                <input type="text" wire:model.live.debounce.300ms="name" 
                                       class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
                                @error('name') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">URL Slug</label>
                                <input type="text" wire:model="slug" 
                                       class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 focus:ring-blue-500">
                                @error('slug') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Tenant Type</label>
                                <select wire:model="type_of_tenant_id" 
                                        class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">-- Select Type --</option>
                                    @foreach($this->tenantTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->type }}</option>
                                    @endforeach
                                </select>
                                @error('type_of_tenant_id') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Contact Number</label>
                                <input type="text" wire:model="contact_number" 
                                       class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
                                @error('contact_number') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Business Email</label>
                            <input type="email" wire:model="email" 
                                   class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
                            @error('email') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Headquarters Address</label>
                            <input type="text" wire:model="address" 
                                   class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
                            @error('address') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Latitude</label>
                                <input type="text" wire:model.live="latitude" 
                                       class="w-full bg-gray-50 dark:bg-slate-700 rounded-lg border-gray-300 dark:border-slate-700 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Longitude</label>
                                <input type="text" wire:model.live="longitude" 
                                       class="w-full bg-gray-50 dark:bg-slate-700 rounded-lg border-gray-300 dark:border-slate-700 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <button type="button" @click="getLocation()" 
                                class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 flex items-center mt-1 w-fit transition-colors">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Use My Current GPS Location
                        </button>
                    </div>
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl shadow-md transition-all flex items-center justify-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
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

            {{-- Right Column: Map --}}
            <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-2 relative z-10 h-fit sticky top-6">
                <div class="p-2 text-sm text-gray-500 dark:text-slate-400">Drag the marker, click the map, or use the GPS button to update the location.</div>
                <div wire:ignore>
                    <div x-ref="mapContainer" style="height: 500px; width: 100%;" class="rounded-lg border border-gray-200 dark:border-slate-700/50"></div>
                </div>
            </div>
        </form>
    </div>
</div>