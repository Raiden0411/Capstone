{{-- resources/views/superadmin/pages/tenant/⚡edit-tenant.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TypeOfTenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

new
#[Layout('superadmin.layouts.app')]
#[Title('Edit Tenant')]
class extends Component {

    use WithFileUploads;

    public Tenant $tenantRecord;

    // Business details
    public $name = '';
    public $slug = '';
    public $type_of_tenant_id = '';
    public $address = '';
    public $barangay = '';
    public $city = '';
    public $province = '';
    public $public_email = '';
    public $contact_number = '';

    // Location
    public $latitude;
    public $longitude;
    public $markers = [];

    // Extra business info
    public $description = '';
    public $website = '';
    public $facebook = '';
    public $instagram = '';
    public $opening_time = '08:00';
    public $closing_time = '17:00';
    public $logo;
    public $is_active = true;
    public $is_recommended = false;

    // Admin account
    public $admin_name = '';
    public $admin_email = '';
    public $admin_password = '';
    public $admin_password_confirmation = '';

    public $adminUserRecord = null;

    // Map & editing mode
    public bool $satellite = false;
    public string $locationMode = 'main';
    public int $mapVersion = 0;
    public array $mapView = [
        'lat' => 10.900977766937142,
        'lng' => 123.07055771888716,
        'zoom' => 13,
    ];
    public ?int $selectedMarkerIndex = null;

    // Marker categories
    public array $markerTypes = [
        'restaurant' => 'Restaurant',
        'cafe'       => 'Café',
        'inn'        => 'Inn / Hotel',
        'shop'       => 'Shop',
        'viewpoint'  => 'Viewpoint',
        'parking'    => 'Parking',
        'entrance'   => 'Entrance',
        'other'      => 'Other',
    ];

    public array $markerColors = [
        'restaurant' => '#f97316',
        'cafe'       => '#a855f7',
        'inn'        => '#3b82f6',
        'shop'       => '#14b8a6',
        'viewpoint'  => '#eab308',
        'parking'    => '#6b7280',
        'entrance'   => '#22c55e',
        'other'      => '#94a3b8',
    ];

    public array $markerEmojis = [
        'restaurant' => '🍽️',
        'cafe'       => '☕',
        'inn'        => '🏨',
        'shop'       => '🛍️',
        'viewpoint'  => '🌄',
        'parking'    => '🅿️',
        'entrance'   => '🚪',
        'other'      => '📍',
    ];

    #[Computed]
    public function tenantTypes() { return TypeOfTenant::all(); }

    public function mount(Tenant $tenant)
    {
        $this->tenantRecord = $tenant;
        $this->name = $tenant->name;
        $this->slug = $tenant->slug;
        $this->type_of_tenant_id = $tenant->type_of_tenant_id;
        $this->address = $tenant->address;
        $this->public_email = $tenant->email;
        $this->contact_number = $tenant->contact_number;
        $this->is_active = (bool) $tenant->is_active;
        $this->is_recommended = (bool) $tenant->is_recommended;

        $coords = $tenant->coordinates ?? [];
        $this->latitude = $coords[0]['lat'] ?? 10.900977766937142;
        $this->longitude = $coords[0]['lng'] ?? 123.07055771888716;
        $this->markers = array_slice($coords, 1);
        foreach ($this->markers as &$marker) {
            if (!isset($marker['uid'])) {
                $marker['uid'] = (string) Str::uuid();
            }
        }
        unset($marker);

        $this->mapView = [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'zoom' => 13,
        ];

        $businessInfo = TenantSetting::where('tenant_id', $tenant->id)
                                     ->where('key', 'business_info')
                                     ->first();

        if ($businessInfo && is_array($businessInfo->value)) {
            $info = $businessInfo->value;
            $this->description = $info['description'] ?? '';
            $this->website = $info['website'] ?? '';
            $this->facebook = $info['social_links']['facebook'] ?? '';
            $this->instagram = $info['social_links']['instagram'] ?? '';
            $this->opening_time = $info['opening_hours']['opening'] ?? '08:00';
            $this->closing_time = $info['opening_hours']['closing'] ?? '17:00';
            $this->barangay = $info['barangay'] ?? '';
            $this->city = $info['city'] ?? '';
            $this->province = $info['province'] ?? '';
        }

        $this->adminUserRecord = User::where('tenant_id', $tenant->id)
                                     ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
                                     ->first();

        if ($this->adminUserRecord) {
            $this->admin_name = $this->adminUserRecord->name;
            $this->admin_email = $this->adminUserRecord->email;
        }
    }

    public function updatedName($value)
    {
        $this->slug = Str::slug(trim($value));
    }

    public function updated($property)
    {
        $trimFields = ['name','address','barangay','city','province','description','website','facebook','instagram','admin_name','public_email','admin_email'];
        if (in_array($property, $trimFields)) {
            $this->$property = trim($this->$property);
        }
    }

    public function setLocationMode($mode)
    {
        if (in_array($mode, ['main', 'nearby'])) {
            $this->locationMode = $mode;
            if ($mode === 'main') {
                $this->selectedMarkerIndex = null;
            }
            $this->mapVersion++;
        }
    }

    public function addMarker()
    {
        if (count($this->markers) >= 20) {
            $this->dispatch('toast', message: 'You can add up to 20 nearby places.', type: 'error');
            return;
        }

        $this->markers[] = [
            'uid'  => (string) Str::uuid(),
            'name' => 'Nearby place ' . (count($this->markers) + 1),
            'lat'  => $this->latitude,
            'lng'  => $this->longitude,
            'type' => 'other',
        ];
        $this->selectedMarkerIndex = count($this->markers) - 1;
        $this->mapVersion++;
        $this->dispatch('toast', message: 'Nearby place added. You can now edit its details below.', type: 'info');
    }

    public function removeMarker($index)
    {
        unset($this->markers[$index]);
        $this->markers = array_values($this->markers);
        if ($this->selectedMarkerIndex === $index) {
            $this->selectedMarkerIndex = null;
        }
        $this->mapVersion++;
        $this->dispatch('toast', message: 'Nearby place removed.', type: 'info');
    }

    public function refreshMapAfterTypeChange()
    {
        $this->mapVersion++;
    }

    #[On('map:click')]
    public function onMapClick($lat, $lng)
    {
        if ($this->locationMode === 'main') {
            $this->latitude = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => $this->mapView['zoom']];
            $this->mapVersion++;
        } else {
            $this->addMarkerAt($lat, $lng);
        }
    }

    #[On('map:marker-drag-end')]
    public function onMarkerDragEnd($id, $lat, $lng)
    {
        if ($id === 'main-marker') {
            if ($this->locationMode === 'main') {
                $this->latitude = round((float) $lat, 6);
                $this->longitude = round((float) $lng, 6);
                $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => $this->mapView['zoom']];
                $this->mapVersion++;
            }
        }

        if (str_starts_with($id, 'sub-marker-')) {
            if ($this->locationMode === 'nearby') {
                $index = (int) substr($id, strlen('sub-marker-'));
                if (isset($this->markers[$index])) {
                    $this->markers[$index]['lat'] = round((float) $lat, 6);
                    $this->markers[$index]['lng'] = round((float) $lng, 6);
                    $this->mapVersion++;
                }
            }
        }
    }

    #[On('map:marker-clicked')]
    public function onMarkerClicked($id, $lat, $lng)
    {
        if (str_starts_with($id, 'sub-marker-')) {
            $index = (int) substr($id, strlen('sub-marker-'));
            $this->selectedMarkerIndex = $index;
            $this->locationMode = 'nearby';
            $this->mapVersion++;
        } elseif ($id === 'main-marker') {
            $this->locationMode = 'main';
            $this->selectedMarkerIndex = null;
            $this->mapVersion++;
        }
    }

    #[On('map:center-changed')]
    public function onMapCenterChanged($lat, $lng)
    {
        $this->mapView['lat'] = round((float) $lat, 6);
        $this->mapView['lng'] = round((float) $lng, 6);
    }

    #[On('map:zoom-changed')]
    public function onMapZoomChanged($zoom)
    {
        $this->mapView['zoom'] = (int) $zoom;
    }

    public function toggleSatellite()
    {
        $this->satellite = !$this->satellite;
        $this->mapVersion++;
    }

    public function useMyLocation()
    {
        $this->dispatch('request-geolocation');
    }

    #[On('geolocation-result')]
    public function onGeolocationResult($lat, $lng)
    {
        if ($this->locationMode === 'main') {
            $this->latitude = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => 16];
            $this->mapVersion++;
        } else {
            $this->addMarkerAt($lat, $lng);
        }
    }

    protected function addMarkerAt($lat, $lng)
    {
        if (count($this->markers) >= 20) {
            $this->dispatch('toast', message: 'You can add up to 20 nearby places.', type: 'error');
            return;
        }

        $this->markers[] = [
            'uid'  => (string) Str::uuid(),
            'name' => 'Nearby place ' . (count($this->markers) + 1),
            'lat'  => round((float) $lat, 6),
            'lng'  => round((float) $lng, 6),
            'type' => 'other',
        ];
        $this->selectedMarkerIndex = count($this->markers) - 1;
        $this->mapVersion++;
        $this->mapView = ['lat' => round((float) $lat, 6), 'lng' => round((float) $lng, 6), 'zoom' => 15];
        $this->dispatch('toast', message: 'Nearby place added. You can now edit its details below.', type: 'info');
    }

    public function update()
    {
        $this->validate([
            'name' => ['required','min:3','max:255', Rule::unique('tenants','name')->ignore($this->tenantRecord->id)],
            'slug' => ['required','string','max:255','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tenants','slug')->ignore($this->tenantRecord->id)],
            'type_of_tenant_id' => 'required|integer|exists:type_of_tenants,id',
            'address' => 'nullable|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'public_email' => ['required','email','max:255', Rule::unique('tenants','email')->ignore($this->tenantRecord->id)],
            'contact_number' => ['nullable', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180',
            'markers' => 'array',
            'markers.*.name' => 'required|string|max:100',
            'markers.*.lat' => 'required|numeric|min:-90|max:90',
            'markers.*.lng' => 'required|numeric|min:-180|max:180',
            'markers.*.type' => ['required', Rule::in(array_keys($this->markerTypes))],
            'description' => 'nullable|string|max:500',
            'website' => 'nullable|url|max:255',
            'facebook' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i',
            'logo' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
            'is_recommended' => 'boolean',
            'admin_name' => 'required|string|min:3|max:255',
            'admin_email' => ['required','email','max:255',
                Rule::unique('users','email')->ignore($this->adminUserRecord->id ?? null),
            ],
            'admin_password' => 'nullable|min:8|confirmed',
        ], [
            'slug.regex' => 'Slug may only contain lowercase letters, numbers, and hyphens.',
            'contact_number.regex' => 'Invalid Philippine phone number. Use 09xxxxxxxxx or +639xxxxxxxxx.',
        ]);

        if ($this->logo) {
            $logoPath = $this->logo->store('tenant-logos', 'public');
            if ($this->tenantRecord->logo && Storage::disk('public')->exists($this->tenantRecord->logo)) {
                Storage::disk('public')->delete($this->tenantRecord->logo);
            }
        } else {
            $logoPath = $this->tenantRecord->logo;
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

        $businessInfo = [
            'description'   => $this->description,
            'website'       => $this->website,
            'social_links'  => ['facebook' => $this->facebook, 'instagram' => $this->instagram],
            'opening_hours' => ['opening' => $this->opening_time, 'closing' => $this->closing_time],
            'barangay'      => $this->barangay,
            'city'          => $this->city,
            'province'      => $this->province,
        ];

        $this->tenantRecord->update([
            'name'              => $this->name,
            'slug'              => $this->slug,
            'type_of_tenant_id' => $this->type_of_tenant_id,
            'address'           => $this->address,
            'email'             => $this->public_email,
            'contact_number'    => $this->contact_number,
            'logo'              => $logoPath,
            'coordinates'       => $coordinates,
            'is_active'         => $this->is_active,
            'is_recommended'    => $this->is_recommended,
        ]);

        TenantSetting::updateOrCreate(
            ['tenant_id' => $this->tenantRecord->id, 'key' => 'business_info'],
            ['value' => $businessInfo]
        );

        if ($this->adminUserRecord) {
            $this->adminUserRecord->update([
                'name'  => $this->admin_name,
                'email' => $this->admin_email,
            ]);
            if ($this->admin_password) {
                $this->adminUserRecord->update(['password' => Hash::make($this->admin_password)]);
            }
        } else {
            $user = User::create([
                'name'      => $this->admin_name,
                'email'     => $this->admin_email,
                'password'  => Hash::make($this->admin_password ?: Str::password(16)),
                'tenant_id' => $this->tenantRecord->id,
                'is_active' => true,
            ]);
            $user->assignRole('admin');
        }

        session()->flash('message', 'Business details successfully updated!');
        return $this->redirectRoute('superadmin.tenants.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

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

    @if(session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit Tenant</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update business information, location, and admin account.</p>
        </div>
        <a href="{{ route('superadmin.tenants.index') }}" wire:navigate class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
            &larr; Back to tenants
        </a>
    </div>

    <form wire:submit="update" class="space-y-8">

        {{-- Business Details --}}
        <div class="card p-6 space-y-6">
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white mb-4">Business Details</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business name <span class="text-red-500">*</span></label>
                    <input type="text" wire:model.live.debounce.300ms="name" class="input">
                    @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL slug</label>
                    <div class="flex rounded-xl overflow-hidden border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-900">
                        <span class="py-2.5 px-3 bg-gray-200 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 border-r border-gray-300 dark:border-gray-600">spot/</span>
                        <input type="text" wire:model="slug" readonly class="flex-1 bg-transparent border-none py-2.5 px-4 text-sm text-gray-500 dark:text-gray-400 cursor-default outline-none">
                    </div>
                    @error('slug') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business type <span class="text-red-500">*</span></label>
                    <select wire:model="type_of_tenant_id" class="select">
                        <option value="">— Select type —</option>
                        @foreach($this->tenantTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->type }}</option>
                        @endforeach
                    </select>
                    @error('type_of_tenant_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Public email <span class="text-red-500">*</span></label>
                    <input type="email" wire:model="public_email" class="input">
                    @error('public_email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact number</label>
                    <input type="text" wire:model="contact_number" class="input" placeholder="09xxxxxxxxx">
                    @error('contact_number') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barangay</label>
                    <input type="text" wire:model="barangay" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                    <input type="text" wire:model="city" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Province</label>
                    <input type="text" wire:model="province" class="input">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full address (optional)</label>
                <input type="text" wire:model="address" class="input">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                <textarea wire:model="description" rows="3" class="textarea"></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Website URL</label>
                    <input type="url" wire:model="website" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Facebook</label>
                    <input type="text" wire:model="facebook" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Instagram</label>
                    <input type="text" wire:model="instagram" class="input">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Opening time</label>
                    <input type="time" wire:model="opening_time" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Closing time</label>
                    <input type="time" wire:model="closing_time" class="input">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business logo</label>
                <input type="file" wire:model="logo" accept="image/*" class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition">
                @error('logo') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                @if ($logo)
                    <img src="{{ $logo->temporaryUrl() }}" class="mt-2 h-24 w-24 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                @elseif ($tenantRecord->logo)
                    <img src="{{ asset('storage/' . $tenantRecord->logo) }}" class="mt-2 h-24 w-24 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                @endif
            </div>

            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active / Pending</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="is_active" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
            </div>

            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    ⭐ Recognized Tourist Attraction / Recommended Destination
                </span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="is_recommended" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
            </div>
        </div>

        {{-- Location & Nearby Places --}}
        <div class="card p-6 space-y-6">
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white mb-4">Location & Nearby Places</h2>

            {{-- Mode selector --}}
            <div class="flex flex-wrap gap-2 mb-4">
                <button type="button"
                        wire:click="setLocationMode('main')"
                        class="px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $locationMode === 'main' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    📍 Edit Main Location
                </button>
                <button type="button"
                        wire:click="setLocationMode('nearby')"
                        class="px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $locationMode === 'nearby' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    🗺️ Add / Edit Nearby Places
                </button>
                <span class="text-xs text-gray-400 dark:text-gray-500 self-center">
                    @if($locationMode === 'main')
                        Click on map to move the main tourist spot.
                    @else
                        Click on map to add a new nearby place.
                    @endif
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Latitude <span class="text-red-500">*</span></label>
                    <input type="text" wire:model.live.debounce.500ms="latitude" onfocus="this.select()" class="input font-mono"
                           @if($locationMode !== 'main') readonly @endif>
                    @error('latitude') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Longitude <span class="text-red-500">*</span></label>
                    <input type="text" wire:model.live.debounce.500ms="longitude" onfocus="this.select()" class="input font-mono"
                           @if($locationMode !== 'main') readonly @endif>
                    @error('longitude') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mb-4">
                <button type="button" wire:click="useMyLocation" class="btn-secondary text-xs focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    📍 Use my location
                </button>
                <button type="button" wire:click="toggleSatellite" class="btn-secondary text-xs focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    🛰️ {{ $satellite ? 'Street View' : 'Satellite' }}
                </button>
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

                <div wire:key="tenant-edit-map-{{ $mapVersion }}">
                    <x-map
                        id="tenant-edit-map"
                        :center="[(float)$mapView['lng'], (float)$mapView['lat']]"
                        :zoom="$mapView['zoom']"
                        height="400px"
                        :provider="$satellite ? 'custom' : 'carto-voyager'"
                        :style="$satellite ? route('map.satellite.style') : null"
                        :light-style="$satellite ? route('map.satellite.style') : null"
                        :dark-style="$satellite ? route('map.satellite.style') : null"
                        theme="auto"
                        class="h-full w-full"
                        :events="['click', 'marker-clicked']"
                    >
                        <x-map-controls
                            :zoom="true"
                            :compass="true"
                            :locate="true"
                            :fullscreen="true"
                            :scale="true"
                            position="top-right"
                        />

                        @foreach($markers as $index => $marker)
                            @php
                                $type = $marker['type'] ?? 'other';
                                $color = $this->markerColors[$type] ?? '#94a3b8';
                                $emoji = $this->markerEmojis[$type] ?? '📍';
                            @endphp
                            <x-map-marker
                                wire:key="sub-marker-{{ $marker['uid'] }}"
                                :lat="$marker['lat']"
                                :lng="$marker['lng']"
                                :color="$color"
                                id="sub-marker-{{ $index }}"
                                :draggable="$locationMode === 'nearby'"
                            >
                                <x-marker-content>
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 bg-white shadow-lg text-xl"
                                         style="border-color: {{ $color }};">
                                        <span class="leading-none">{{ $emoji }}</span>
                                    </div>
                                </x-marker-content>
                                <x-marker-popup>
                                    <div class="p-2">
                                        <strong class="text-gray-900 dark:text-white">{{ $marker['name'] }}</strong>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $this->markerTypes[$type] ?? 'Other' }}</p>
                                    </div>
                                </x-marker-popup>
                            </x-map-marker>
                        @endforeach

                        <x-map-marker
                            wire:key="main-marker-{{ $latitude }}-{{ $longitude }}"
                            :lat="$latitude"
                            :lng="$longitude"
                            color="#ef4444"
                            id="main-marker"
                            :draggable="$locationMode === 'main'"
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

            @if($locationMode === 'nearby')
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Nearby places <span class="text-gray-400 font-normal">({{ count($markers) }}/20)</span>
                        </span>
                        <button type="button" wire:click="addMarker" class="text-xs font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                            + Add nearby place
                        </button>
                    </div>

                    @if(count($markers) > 0)
                        <div class="flex flex-wrap items-center gap-3 mb-3 text-[11px] text-gray-500 dark:text-gray-400">
                            @foreach($this->markerTypes as $typeKey => $typeLabel)
                                <span class="inline-flex items-center gap-1">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $this->markerColors[$typeKey] }}"></span>
                                    {{ $this->markerEmojis[$typeKey] }} {{ $typeLabel }}
                                </span>
                            @endforeach
                        </div>
                        <div class="space-y-2">
                            @foreach($markers as $index => $marker)
                                <div wire:key="marker-row-{{ $marker['uid'] }}"
                                     class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700
                                            {{ $selectedMarkerIndex === $index ? 'ring-2 ring-primary-600/40 border-primary-600/30' : '' }}">
                                    <input type="text" wire:model.debounce.500ms="markers.{{ $index }}.name" placeholder="Place name" class="input !py-2 flex-1 min-w-[140px]">
                                    <input type="number" step="0.0001" wire:model.debounce.500ms="markers.{{ $index }}.lat" placeholder="Lat" class="input !py-2 !w-28 font-mono">
                                    <input type="number" step="0.0001" wire:model.debounce.500ms="markers.{{ $index }}.lng" placeholder="Lng" class="input !py-2 !w-28 font-mono">
                                    <select wire:model="markers.{{ $index }}.type" wire:change="refreshMapAfterTypeChange" class="select !py-2 !w-40">
                                        @foreach($this->markerTypes as $typeKey => $typeLabel)
                                            <option value="{{ $typeKey }}">{{ $this->markerEmojis[$typeKey] }} {{ $typeLabel }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="removeMarker({{ $index }})" class="text-red-500 hover:text-red-700">✕</button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">No nearby places yet. Click the map or use the button to add.</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Admin Account --}}
        <div class="card p-6 space-y-6">
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white mb-4">Admin Account</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin full name <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="admin_name" class="input">
                    @error('admin_name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin login email <span class="text-red-500">*</span></label>
                    <input type="email" wire:model="admin_email" class="input">
                    @error('admin_email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New password (optional)</label>
                    <input type="password" wire:model="admin_password" class="input">
                    @error('admin_password') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm new password</label>
                    <input type="password" wire:model="admin_password_confirmation" class="input">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" class="btn-primary focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Save Changes</span>
                <span wire:loading>Saving…</span>
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