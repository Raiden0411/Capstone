{{-- resources/views/tenant/pages/settings/⚡business-profile.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TypeOfTenant;
use App\Models\SiteSetting;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

new
#[Layout('tenant.layouts.app')]
#[Title('Business Profile')]
class extends Component
{
    use WithFileUploads;

    public ?Tenant $tenant = null;

    // Business details
    public string $name = '';
    public string $slug = '';
    public ?int $type_of_tenant_id = null;
    public string $address = '';
    public string $barangay = '';
    public string $city = '';
    public string $province = '';
    public string $public_email = '';
    public string $contact_number = '';

    // Extra business info
    public string $description = '';
    public string $website = '';
    public string $facebook = '';
    public string $instagram = '';
    public string $opening_time = '08:00';
    public string $closing_time = '17:00';
    public $logo;
    public bool $is_active = true;

    // Location & markers
    public float $latitude;
    public float $longitude;
    public array $markers = [];

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

    // Dynamic marker categories
    public array $markerCategories = [];

    #[Computed]
    public function barangays()
    {
        $list = config('barangays', [
            'Barangay I', 'Barangay II', 'Barangay III', 'Barangay IV',
            'Barangay V', 'Barangay VI', 'Barangay VII', 'Barangay VIII',
            'Barangay IX', 'Barangay X', 'Barangay XI', 'Barangay XII',
            'Barangay XIII', 'Barangay XIV', 'Barangay XV', 'Barangay XVI',
        ]);
        return collect($list)->sort()->values();
    }

    #[Computed]
    public function tenantTypes()
    {
        return TypeOfTenant::query()->select('id', 'type')->orderBy('type')->get();
    }

    public function mount()
    {
        $user = Auth::user();
        $this->tenant = $user?->tenant;

        if (!$this->tenant) {
            abort(403, 'No business is linked to your account.');
        }

        $this->name = $this->tenant->name ?? '';
        $this->slug = $this->tenant->slug ?? '';
        $this->type_of_tenant_id = $this->tenant->type_of_tenant_id;
        $this->address = $this->tenant->address ?? '';
        $this->public_email = $this->tenant->email ?? '';
        $this->contact_number = $this->tenant->contact_number ?? '';
        $this->is_active = (bool) $this->tenant->is_active;

        $coords = $this->tenant->coordinates ?? [];
        $main = $coords[0] ?? null;
        $this->latitude = isset($main['lat']) ? (float) $main['lat'] : 10.900977766937142;
        $this->longitude = isset($main['lng']) ? (float) $main['lng'] : 123.07055771888716;
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

        $info = TenantSetting::where('tenant_id', $this->tenant->id)
            ->where('key', 'business_info')
            ->first();

        if ($info && is_array($info->value)) {
            $v = $info->value;
            $this->description = $v['description'] ?? '';
            $this->website = $v['website'] ?? '';
            $this->facebook = $v['social_links']['facebook'] ?? '';
            $this->instagram = $v['social_links']['instagram'] ?? '';
            $this->opening_time = $v['opening_hours']['opening'] ?? '08:00';
            $this->closing_time = $v['opening_hours']['closing'] ?? '17:00';
            $this->barangay = $v['barangay'] ?? '';
            $this->city = $v['city'] ?? '';
            $this->province = $v['province'] ?? '';
        }

        $this->markerCategories = SiteSetting::getValue('marker_categories', []);
    }

    public function updatedName($value)
    {
        $this->name = trim($value);
        $this->slug = Str::slug($this->name);
    }

    public function updatedLatitude($value)
    {
        $this->latitude = round((float) $value, 6);
        $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => 16];
        $this->mapVersion++;
        $this->dispatch('map:fly-to', center: [(float)$this->longitude, (float)$this->latitude], zoom: 16);
    }

    public function updatedLongitude($value)
    {
        $this->longitude = round((float) $value, 6);
        $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => 16];
        $this->mapVersion++;
        $this->dispatch('map:fly-to', center: [(float)$this->longitude, (float)$this->latitude], zoom: 16);
    }

    public function updated($property)
    {
        $trimFields = ['name','address','barangay','city','province','description','website','facebook','instagram','public_email','contact_number'];
        if (in_array($property, $trimFields)) {
            $this->$property = trim($this->$property);
        }

        if ($property === 'contact_number') {
            $this->contact_number = preg_replace('/[^0-9]/', '', $this->contact_number);
            $this->contact_number = substr($this->contact_number, 0, 11);
        }

        if (preg_match('/^markers\.\d+\.type$/', $property)) {
            $this->mapVersion++;
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
        $this->addMarkerAt($this->latitude, $this->longitude);
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
        if ($id === 'main-marker' && $this->locationMode === 'main') {
            $this->latitude = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => $this->mapView['zoom']];
            $this->mapVersion++;
        }

        if (str_starts_with($id, 'sub-marker-') && $this->locationMode === 'nearby') {
            $index = (int) substr($id, strlen('sub-marker-'));
            if (isset($this->markers[$index])) {
                $this->markers[$index]['lat'] = round((float) $lat, 6);
                $this->markers[$index]['lng'] = round((float) $lng, 6);
                $this->mapVersion++;
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
        if ($this->locationMode === 'main') {
            $this->latitude = $this->mapView['lat'];
            $this->longitude = $this->mapView['lng'];
        }
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
            'type' => '',
        ];
        $this->selectedMarkerIndex = count($this->markers) - 1;
        $this->mapVersion++;
        $this->mapView = ['lat' => round((float) $lat, 6), 'lng' => round((float) $lng, 6), 'zoom' => 15];
        $this->dispatch('toast', message: 'Nearby place added. Please set its category.', type: 'info');
    }

    public function save()
    {
        $this->validate([
            'name' => ['required','min:3','max:255', Rule::unique('tenants','name')->ignore($this->tenant->id)],
            'slug' => ['required','string','max:255','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tenants','slug')->ignore($this->tenant->id)],
            'type_of_tenant_id' => 'required|integer|exists:type_of_tenants,id',
            'address' => 'nullable|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'public_email' => ['required','email','max:255', Rule::unique('tenants','email')->ignore($this->tenant->id)],
            'contact_number' => ['nullable', 'string', 'max:11', 'regex:/^[0-9]{10,11}$/'],
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180',
            'markers' => 'array',
            'markers.*.name' => 'required|string|max:100',
            'markers.*.lat' => 'required|numeric|min:-90|max:90',
            'markers.*.lng' => 'required|numeric|min:-180|max:180',
            'markers.*.type' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'website' => 'nullable|url|max:255',
            'facebook' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i',
            'logo' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ], [
            'slug.regex' => 'Slug may only contain lowercase letters, numbers, and hyphens.',
            'contact_number.regex' => 'Contact number must be 10-11 digits only.',
            'contact_number.max' => 'Contact number cannot exceed 11 digits.',
        ]);

        if ($this->logo) {
            $logoPath = $this->logo->store('tenant-logos', 'public');
            if ($this->tenant->logo && Storage::disk('public')->exists($this->tenant->logo)) {
                Storage::disk('public')->delete($this->tenant->logo);
            }
        } else {
            $logoPath = $this->tenant->logo;
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
            ['value' => $businessInfo]
        );

        session()->flash('message', 'Business profile updated successfully.');
        $this->dispatch('profile-saved');
    }
};
?>

<div
    x-data="{ toasts: [] }"
    x-on:toast.window="
        const id = Date.now() + Math.random();
        toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
        setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4000);
    "
    x-on:profile-saved.window="window.scrollTo({ top: 0, behavior: 'smooth' });"
    class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6"
>

    {{-- Toast notifications --}}
    <div class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none">
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
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Business Profile</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update your business information and location.</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-8">

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
                    <input type="tel" id="field-contact"
                           inputmode="numeric" pattern="[0-9]*" maxlength="11"
                           wire:model.live.debounce.400ms="contact_number"
                           x-on:input="event.target.value = event.target.value.replace(/[^0-9]/g, '').slice(0, 11)"
                           class="input" placeholder="09xxxxxxxxx">
                    @error('contact_number') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barangay</label>
                    <input type="text" wire:model="barangay" list="barangays-list" class="input">
                    <datalist id="barangays-list">
                        @foreach($this->barangays as $b)
                            <option value="{{ $b }}">
                        @endforeach
                    </datalist>
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
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Website</label>
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
                <div x-data="{ previewUrl: null }">
                    <input type="file"
                           wire:model="logo"
                           x-ref="logoInput"
                           accept="image/*"
                           @change="previewUrl = URL.createObjectURL($refs.logoInput.files[0])"
                           class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition">
                    @error('logo') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror

                    <div class="mt-3">
                        <template x-if="previewUrl">
                            <img :src="previewUrl" class="h-24 w-24 object-cover rounded-lg border border-gray-200 dark:border-gray-700" alt="New logo preview">
                        </template>
                        <template x-if="!previewUrl && @js($tenant->logo)">
                            <img src="{{ asset('storage/' . $tenant->logo) }}" class="h-24 w-24 object-cover rounded-lg border border-gray-200 dark:border-gray-700" alt="Current logo">
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="is_active" class="sr-only peer">
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
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $locationMode === 'main' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Edit Main Location
                </button>
                <button type="button"
                        wire:click="setLocationMode('nearby')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $locationMode === 'nearby' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    Add / Edit Nearby Places
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
                    <input type="number" step="any" min="-90" max="90"
                           wire:model.live.debounce.500ms="latitude" onfocus="this.select()" class="input font-mono"
                           @if($locationMode !== 'main') readonly @endif>
                    @error('latitude') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Longitude <span class="text-red-500">*</span></label>
                    <input type="number" step="any" min="-180" max="180"
                           wire:model.live.debounce.500ms="longitude" onfocus="this.select()" class="input font-mono"
                           @if($locationMode !== 'main') readonly @endif>
                    @error('longitude') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mb-4">
                <button type="button" wire:click="useMyLocation"
                        class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Use my location
                </button>
                <button type="button" wire:click="toggleSatellite"
                        class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                    {{ $satellite ? 'Street View' : 'Satellite' }}
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
                        :events="['click', 'marker-clicked', 'marker-drag-end']"
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
                                $type = $marker['type'] ?? '';
                                $category = collect($this->markerCategories)->firstWhere('key', $type);
                                $color = $category['color'] ?? '#94a3b8';
                                $iconSvg = $category['icon_svg'] ?? null;
                            @endphp
                            <x-map-marker
                                wire:key="sub-marker-{{ $marker['uid'] }}-{{ $marker['type'] }}"
                                :lat="$marker['lat']"
                                :lng="$marker['lng']"
                                :color="$color"
                                id="sub-marker-{{ $index }}"
                                :draggable="$locationMode === 'nearby'"
                            >
                                <x-marker-content>
                                    <div class="relative flex h-10 w-10 items-center justify-center transform-gpu will-change-transform transition-transform duration-200 group-hover:scale-110 active:scale-95"
                                         style="cursor: pointer;">
                                        <svg class="absolute inset-0 size-10 drop-shadow-md fill-white dark:fill-gray-900 stroke-slate-400 dark:stroke-slate-600 stroke-1" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                        </svg>
                                        @if($iconSvg)
                                            <div class="absolute mb-1 size-[18px] text-gray-800 dark:text-white">
                                                {!! str_replace('<svg ', '<svg class="size-full stroke-current fill-none" ', $iconSvg) !!}
                                            </div>
                                        @else
                                            <span class="absolute mb-1 text-[10px] font-bold text-gray-800 dark:text-white">{{ strtoupper(substr($type, 0, 1)) }}</span>
                                        @endif
                                    </div>
                                </x-marker-content>
                                <x-marker-popup>
                                    <div class="p-2">
                                        <strong class="text-gray-900 dark:text-white">{{ $marker['name'] }}</strong>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $category['label'] ?? 'Uncategorized' }}</p>
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
                                <div class="relative flex items-center justify-center transform-gpu will-change-transform transition-transform duration-200 group-hover:scale-110 active:scale-95">
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
                        <button type="button" wire:click="addMarker"
                                class="text-xs font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                            + Add nearby place
                        </button>
                    </div>

                    @if(count($markers) > 0)
                        <div class="flex flex-wrap items-center gap-3 mb-3 text-[11px] text-gray-500 dark:text-gray-400">
                            @foreach($this->markerCategories as $cat)
                                <span class="inline-flex items-center gap-1">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $cat['color'] }}"></span>
                                    {{ $cat['label'] }}
                                </span>
                            @endforeach
                        </div>
                        <div class="space-y-2">
                            @foreach($markers as $index => $marker)
                                <div wire:key="marker-row-{{ $marker['uid'] }}"
                                     class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700
                                            {{ $selectedMarkerIndex === $index ? 'ring-2 ring-primary-600/40 border-primary-600/30' : '' }}">
                                    <input type="text" wire:model.debounce.500ms="markers.{{ $index }}.name" placeholder="Place name" class="input !py-2 flex-1 min-w-[140px]">
                                    <input type="number" step="any" min="-90" max="90" wire:model.debounce.500ms="markers.{{ $index }}.lat" placeholder="Lat" class="input !py-2 !w-28 font-mono">
                                    <input type="number" step="any" min="-180" max="180" wire:model.debounce.500ms="markers.{{ $index }}.lng" placeholder="Lng" class="input !py-2 !w-28 font-mono">
                                    <select wire:model.live="markers.{{ $index }}.type" class="select !py-2 !w-40">
                                        <option value="">Select category *</option>
                                        @foreach($this->markerCategories as $cat)
                                            <option value="{{ $cat['key'] }}">{{ $cat['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="removeMarker({{ $index }})"
                                            class="p-1.5 text-red-500 hover:text-red-700 rounded-full transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50"
                                            title="Remove">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">No nearby places yet. Click the map or use the button to add.</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
            <button type="submit" wire:loading.attr="disabled"
                    class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
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