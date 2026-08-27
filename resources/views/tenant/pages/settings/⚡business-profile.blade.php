{{-- resources/views/tenant/pages/settings/⚡business-profile.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TypeOfTenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

new
#[Layout('tenant.layouts.app')]
#[Title('Business Profile')]
class extends Component
{
    use WithFileUploads;

    public Tenant $tenant;

    public string $name = '';
    public string $slug = '';
    public ?int $type_of_tenant_id = null;
    public string $address = '';
    public string $barangay = '';
    public string $city = '';
    public string $province = '';
    public string $public_email = '';
    public string $contact_number = '';
    public string $description = '';
    public string $website = '';
    public string $facebook = '';
    public string $instagram = '';
    public string $opening_time = '08:00';
    public string $closing_time = '17:00';
    public ?float $latitude = null;
    public ?float $longitude = null;
    public $logo;
    public bool $is_active = true;

    public array $markers = [];
    public bool $satellite = false;
    public int $mapVersion = 0;
    public array $mapView = [
        'lat' => 10.900977766937142,
        'lng' => 123.07055771888716,
        'zoom' => 13,
    ];

    protected function rules()
    {
        return [
            'name'           => ['required', 'string', 'max:255', Rule::unique('tenants', 'name')->ignore($this->tenant->id)],
            'slug'           => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tenants', 'slug')->ignore($this->tenant->id)],
            'type_of_tenant_id' => ['required', 'integer', 'exists:type_of_tenants,id'],
            'address'        => ['nullable', 'string', 'max:255'],
            'barangay'       => ['nullable', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:255'],
            'province'       => ['nullable', 'string', 'max:255'],
            'public_email'   => ['required', 'email', 'max:255', Rule::unique('tenants', 'email')->ignore($this->tenant->id)],
            'contact_number' => ['nullable', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'website'        => ['nullable', 'url', 'max:255'],
            'facebook'       => ['nullable', 'string', 'max:255'],
            'instagram'      => ['nullable', 'string', 'max:255'],
            'opening_time'   => ['nullable', 'date_format:H:i'],
            'closing_time'   => ['nullable', 'date_format:H:i'],
            'latitude'       => ['required', 'numeric', 'min:-90', 'max:90'],
            'longitude'      => ['required', 'numeric', 'min:-180', 'max:180'],
            'logo'           => ['nullable', 'image', 'max:2048'],
            'is_active'      => ['boolean'],
        ];
    }

    public function mount(Tenant $tenant)
    {
        $this->tenant = $tenant;

        // Null-safe assignments
        $this->name = (string) ($tenant->name ?? '');
        $this->slug = (string) ($tenant->slug ?? '');
        $this->type_of_tenant_id = $tenant->type_of_tenant_id;
        $this->address = (string) ($tenant->address ?? '');
        $this->public_email = (string) ($tenant->email ?? '');
        $this->contact_number = (string) ($tenant->contact_number ?? '');
        $this->is_active = (bool) $tenant->is_active;

        $coords = $tenant->coordinates ?? [];
        $main = $coords[0] ?? null;
        $this->latitude = isset($main['lat']) ? (float) $main['lat'] : 10.900977766937142;
        $this->longitude = isset($main['lng']) ? (float) $main['lng'] : 123.07055771888716;
        $this->markers = array_slice($coords, 1);

        $info = TenantSetting::where('tenant_id', $tenant->id)
            ->where('key', 'business_info')
            ->first();

        if ($info && is_array($info->value)) {
            $v = $info->value;
            $this->description = (string) ($v['description'] ?? '');
            $this->website = (string) ($v['website'] ?? '');
            $this->facebook = (string) ($v['social_links']['facebook'] ?? '');
            $this->instagram = (string) ($v['social_links']['instagram'] ?? '');
            $this->opening_time = (string) ($v['opening_hours']['opening'] ?? '08:00');
            $this->closing_time = (string) ($v['opening_hours']['closing'] ?? '17:00');
            $this->barangay = (string) ($v['barangay'] ?? '');
            $this->city = (string) ($v['city'] ?? '');
            $this->province = (string) ($v['province'] ?? '');
        }

        $this->mapView = [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'zoom' => 15,
        ];
    }

    public function getBarangaysProperty()
    {
        return collect(config('barangays', []))->sort()->values();
    }

    public function getTenantTypesProperty()
    {
        return TypeOfTenant::orderBy('type')->get();
    }

    public function updatedName($value)
    {
        $this->name = trim($value);
        $this->slug = Str::slug($this->name);
    }

    public function updated($property)
    {
        $trimFields = ['address','barangay','city','province','description','website','facebook','instagram','public_email','contact_number'];
        if (in_array($property, $trimFields)) {
            $this->$property = trim($this->$property);
        }
    }

    #[On('map:click')]
    public function onMapClick($lat, $lng): void
    {
        $this->latitude = round((float) $lat, 6);
        $this->longitude = round((float) $lng, 6);
        $this->mapView = [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'zoom' => 16,
        ];
        $this->mapVersion++;
    }

    #[On('map:marker-drag-end')]
    public function onMarkerDragEnd($id, $lat, $lng): void
    {
        if ($id === 'main-marker') {
            $this->latitude = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView = [
                'lat' => $this->latitude,
                'lng' => $this->longitude,
                'zoom' => $this->mapView['zoom'],
            ];
            $this->mapVersion++;
        }
    }

    public function toggleSatellite(): void
    {
        $this->satellite = ! $this->satellite;
        $this->mapVersion++;
    }

    public function useMyLocation(): void
    {
        $this->dispatch('request-geolocation');
    }

    #[On('geolocation-result')]
    public function onGeolocationResult($lat, $lng): void
    {
        $this->latitude = round((float) $lat, 6);
        $this->longitude = round((float) $lng, 6);
        $this->mapView = [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'zoom' => 16,
        ];
        $this->mapVersion++;
        $this->dispatch('toast', message: 'Location updated from your device.', type: 'success');
    }

    public function save()
    {
        $this->validate();

        $logoPath = $this->tenant->logo;
        if ($this->logo) {
            $logoPath = $this->logo->store('tenant-logos', 'public');
        }

        $coordinates = [[
            'lat'  => $this->latitude,
            'lng'  => $this->longitude,
            'name' => 'Main Location',
            'type' => 'parent',
        ]];
        foreach ($this->markers as $marker) {
            unset($marker['uid']);
            $coordinates[] = $marker;
        }

        $this->tenant->update([
            'name'              => $this->name,
            'slug'              => $this->slug,
            'type_of_tenant_id' => $this->type_of_tenant_id,
            'address'           => $this->address,
            'email'             => $this->public_email,
            'contact_number'    => $this->contact_number,
            'logo'              => $logoPath,
            'coordinates'       => $coordinates,
            'is_active'         => $this->is_active,
        ]);

        TenantSetting::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'key' => 'business_info'],
            ['value' => [
                'description'   => $this->description,
                'website'       => $this->website,
                'social_links'  => ['facebook' => $this->facebook, 'instagram' => $this->instagram],
                'opening_hours' => ['opening' => $this->opening_time, 'closing' => $this->closing_time],
                'barangay'      => $this->barangay,
                'city'          => $this->city,
                'province'      => $this->province,
            ]]
        );

        session()->flash('message', 'Business profile updated successfully.');
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6">

    {{-- Toast notifications --}}
    <div
        x-data="{ toasts: [] }"
        x-on:toast.window="
            const id = Date.now() + Math.random();
            toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
            setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4000);
        "
        class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 border"
                :class="{
                    'bg-green-50 border-green-200 text-green-800 dark:bg-green-500/10 dark:border-green-500/30 dark:text-green-300': toast.type === 'success',
                    'bg-red-50 border-red-200 text-red-800 dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-300': toast.type === 'error',
                    'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                }"
            >
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Business Profile</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update your business information and location.</p>
        </div>
        <button type="button" wire:click="save" wire:loading.attr="disabled"
                class="btn-primary focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <span wire:loading.remove>Save Changes</span>
            <span wire:loading class="inline-flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                Saving…
            </span>
        </button>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Business Details --}}
        <div class="card p-6 space-y-5">
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white mb-2">Business Details</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business Name *</label>
                    <input type="text" wire:model="name" class="input" placeholder="Your business name">
                    @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL Slug</label>
                    <div class="flex rounded-xl overflow-hidden border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-900">
                        <span class="py-2.5 px-3 bg-gray-200 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 border-r border-gray-300 dark:border-gray-600">spot/</span>
                        <input type="text" wire:model="slug" class="flex-1 bg-transparent border-none py-2.5 px-4 text-sm text-gray-500 dark:text-gray-400 cursor-default outline-none" readonly>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business Type *</label>
                    <select wire:model="type_of_tenant_id" class="select">
                        <option value="">— Select Type —</option>
                        @foreach($this->tenantTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->type }}</option>
                        @endforeach
                    </select>
                    @error('type_of_tenant_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Public Email *</label>
                    <input type="email" wire:model="public_email" class="input" placeholder="business@email.com">
                    @error('public_email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Number</label>
                    <input type="text" wire:model="contact_number" class="input" placeholder="09xxxxxxxxx">
                    @error('contact_number') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barangay</label>
                    <input type="text" wire:model="barangay" list="barangays-list" class="input" placeholder="Type or select">
                    <datalist id="barangays-list">
                        @foreach($this->barangays as $b)
                            <option value="{{ $b }}">
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                    <input type="text" wire:model="city" class="input" placeholder="Victorias City">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Province</label>
                    <input type="text" wire:model="province" class="input" placeholder="Negros Occidental">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Address</label>
                <input type="text" wire:model="address" class="input" placeholder="Street, building, etc.">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                <textarea wire:model="description" rows="3" class="textarea" placeholder="Describe your business..."></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Website</label>
                    <input type="url" wire:model="website" class="input" placeholder="https://example.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Facebook</label>
                    <input type="text" wire:model="facebook" class="input" placeholder="Facebook URL">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Instagram</label>
                    <input type="text" wire:model="instagram" class="input" placeholder="Instagram URL">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Opening Time</label>
                    <input type="time" wire:model="opening_time" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Closing Time</label>
                    <input type="time" wire:model="closing_time" class="input">
                </div>
            </div>

            {{-- Logo Upload with Live Preview --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Logo</label>
                <div
                    x-data="{
                        previewUrl: null,
                        initPreview() {
                            const file = $refs.logoInput.files[0];
                            if (file) {
                                this.previewUrl = URL.createObjectURL(file);
                            } else {
                                this.previewUrl = null;
                            }
                        }
                    }"
                >
                    <input
                        type="file"
                        wire:model="logo"
                        x-ref="logoInput"
                        accept="image/*"
                        @change="initPreview()"
                        class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                    >

                    <div wire:loading wire:target="logo" class="text-xs text-blue-500 mt-1 flex items-center gap-1">
                        <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Uploading…
                    </div>

                    <div class="mt-3">
                        <template x-if="previewUrl">
                            <img :src="previewUrl" class="h-24 w-24 object-cover rounded-lg border border-gray-200 dark:border-gray-700" alt="Logo preview">
                        </template>
                        <template x-if="!previewUrl && @js($tenant->logo)">
                            <img src="{{ asset('storage/' . $tenant->logo) }}" class="h-24 w-24 object-cover rounded-lg border border-gray-200 dark:border-gray-700" alt="Current logo">
                        </template>
                    </div>
                </div>

                @error('logo') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Location Map --}}
        <div class="card p-6 space-y-5">
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white mb-2">Location</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Click on the map to set your main location, or drag the marker.</p>

            <div class="flex flex-wrap gap-2 mb-4">
                <button type="button" wire:click="useMyLocation" class="btn-secondary text-xs focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    📍 Use my location
                </button>
                <button type="button" wire:click="toggleSatellite" class="btn-secondary text-xs focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    🛰️ {{ $satellite ? 'Street View' : 'Satellite' }}
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Latitude</label>
                    <input type="text" wire:model.live.debounce.500ms="latitude" class="input font-mono" readonly>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Longitude</label>
                    <input type="text" wire:model.live.debounce.500ms="longitude" class="input font-mono" readonly>
                </div>
            </div>

            <div class="card overflow-hidden relative" style="height: 400px;"
                 x-data="{ showOverlay: true }"
                 x-init="setTimeout(() => showOverlay = false, 800)">
                <div x-show="showOverlay"
                     x-transition:leave="transition-opacity duration-500"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="absolute inset-0 z-10 flex items-center justify-center bg-gray-50 dark:bg-gray-800">
                    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Updating map…
                    </div>
                </div>

                <div wire:key="tenant-settings-map-{{ $mapVersion }}">
                    <x-map
                        id="tenant-settings-map"
                        :center="[(float)$mapView['lng'], (float)$mapView['lat']]"
                        :zoom="$mapView['zoom']"
                        height="400px"
                        :provider="$satellite ? 'custom' : 'carto-voyager'"
                        :style="$satellite ? route('map.satellite.style') : null"
                        :light-style="$satellite ? route('map.satellite.style') : null"
                        :dark-style="$satellite ? route('map.satellite.style') : null"
                        theme="auto"
                        class="h-full w-full"
                        :events="['click', 'marker-drag-end']"
                    >
                        <x-map-controls
                            :zoom="true"
                            :compass="true"
                            :locate="false"
                            :fullscreen="true"
                            :scale="true"
                            position="top-right"
                        />

                        <x-map-marker
                            wire:key="main-marker-{{ $latitude }}-{{ $longitude }}"
                            :lat="$latitude"
                            :lng="$longitude"
                            color="#ef4444"
                            id="main-marker"
                            draggable
                        >
                            <x-marker-content>
                                <div class="relative flex items-center justify-center">
                                    <svg class="h-10 w-10 drop-shadow-lg" viewBox="0 0 24 24" fill="#ef4444" stroke="white" stroke-width="1.5">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                                        <circle cx="12" cy="9" r="2.5" fill="white"/>
                                    </svg>
                                </div>
                            </x-marker-content>
                            <x-marker-popup>
                                <div class="p-2">
                                    <strong class="text-gray-900 dark:text-white">Main Location</strong>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $latitude }}, {{ $longitude }}</p>
                                </div>
                            </x-marker-popup>
                        </x-map-marker>
                    </x-map>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-3">
            <button type="submit" wire:loading.attr="disabled" class="btn-primary focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Save Changes</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    Saving…
                </span>
            </button>
        </div>
    </form>

    @script
    <script>
        function notify(message, type = 'info') {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        }

        window.addEventListener('request-geolocation', () => {
            if (!navigator.geolocation) {
                notify('Geolocation is not supported by your browser.', 'error');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    Livewire.dispatch('geolocation-result', {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                    });
                },
                () => {
                    notify('Unable to retrieve your location. Check browser permissions.', 'error');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
            );
        });
    </script>
    @endscript
</div>