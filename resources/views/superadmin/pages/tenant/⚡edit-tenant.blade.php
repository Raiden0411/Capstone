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
use App\Models\SiteSetting;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

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

    // Dynamic marker categories (loaded from site_settings)
    public array $markerCategories = [];

    // Add category modal
    public bool $showAddCategoryModal = false;
    public string $newCategoryKey = '';
    public string $newCategoryLabel = '';
    public string $newCategoryColor = '#3b82f6';
    public $newCategoryIcon;

    #[Computed]
    public function tenantTypes()
    {
        return TypeOfTenant::query()->select('id', 'type')->get();
    }

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
            $this->opening_time = $info['opening_hours']['opening'] ?? '08:00';
            $this->closing_time = $info['opening_hours']['closing'] ?? '17:00';
            $this->barangay = $info['barangay'] ?? '';
            $this->city = $info['city'] ?? '';
            $this->province = $info['province'] ?? '';
        }

        $this->adminUserRecord = User::where('tenant_id', $tenant->id)
                                     ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
                                     ->select('id', 'name', 'email', 'tenant_id')
                                     ->first();

        if ($this->adminUserRecord) {
            $this->admin_name = $this->adminUserRecord->name;
            $this->admin_email = $this->adminUserRecord->email;
        }

        // Load marker categories from site settings
        $this->markerCategories = SiteSetting::getValue('marker_categories', []);
    }

    public function updatedName($value)
    {
        $this->slug = Str::slug(trim($value));
    }

    public function updated($property)
    {
        $trimFields = ['name','address','barangay','city','province','description','admin_name','public_email','admin_email','contact_number'];
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

    public function setLocationMode($mode)
    {
        if (in_array($mode, ['main', 'nearby'])) {
            $this->locationMode = $mode;
            $this->selectedMarkerIndex = null;
            $this->mapVersion++;
        }
    }

    public function addMarker()
    {
        $this->addMarkerAt($this->mapView['lat'], $this->mapView['lng']);
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
            'contact_number' => ['nullable', 'string', 'max:11', 'regex:/^[0-9]{10,11}$/'],
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180',
            'markers' => 'array',
            'markers.*.name' => 'required|string|max:100',
            'markers.*.lat' => 'required|numeric|min:-90|max:90',
            'markers.*.lng' => 'required|numeric|min:-180|max:180',
            'markers.*.type' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i',
            'logo' => ['nullable','image','mimes:jpeg,png,jpg,gif,webp','max:10240'], // ★ 10MB
            'is_active' => 'boolean',
            'is_recommended' => 'boolean',
            'admin_name' => 'required|string|min:3|max:255',
            'admin_email' => ['required','email','max:255',
                Rule::unique('users','email')->ignore($this->adminUserRecord->id ?? null),
            ],
            'admin_password' => 'nullable|min:8|confirmed',
        ], [
            'slug.regex' => 'Slug may only contain lowercase letters, numbers, and hyphens.',
            'contact_number.regex' => 'Contact number must be 10-11 digits only.',
            'contact_number.max' => 'Contact number cannot exceed 11 digits.',
            'logo.max' => 'Logo must not exceed 10MB.', // ★ added
        ]);

        DB::transaction(function () {
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
        });

        session()->flash('message', 'Business details successfully updated!');
        return $this->redirectRoute('superadmin.tenants.index', navigate: true);
    }

    // ─── Marker Category CRUD (inline) ──────────────────

    public function openAddCategoryModal(): void
    {
        $this->reset(['newCategoryKey', 'newCategoryLabel', 'newCategoryColor', 'newCategoryIcon']);
        $this->newCategoryColor = '#3b82f6';
        $this->showAddCategoryModal = true;
    }

    public function closeAddCategoryModal(): void
    {
        $this->showAddCategoryModal = false;
        $this->reset(['newCategoryKey', 'newCategoryLabel', 'newCategoryColor', 'newCategoryIcon']);
    }

    public function saveNewCategory(): void
    {
        $this->validate([
            'newCategoryKey'   => 'required|alpha_dash|max:50',
            'newCategoryLabel' => 'required|string|max:100',
            'newCategoryColor' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'newCategoryIcon'  => 'nullable|file|mimes:svg|max:1024',
        ]);

        if (collect($this->markerCategories)->contains('key', $this->newCategoryKey)) {
            $this->addError('newCategoryKey', 'This key already exists.');
            return;
        }

        $iconPath = null;
        $iconSvg = null;
        if ($this->newCategoryIcon) {
            $iconPath = $this->newCategoryIcon->store('marker-icons', 'public');
            $iconSvg = file_get_contents($this->newCategoryIcon->getRealPath());
        }

        $this->markerCategories[] = [
            'key'       => $this->newCategoryKey,
            'label'     => $this->newCategoryLabel,
            'color'     => $this->newCategoryColor,
            'icon_path' => $iconPath,
            'icon_svg'  => $iconSvg,
        ];

        SiteSetting::setValue('marker_categories', $this->markerCategories);

        $this->closeAddCategoryModal();
        $this->dispatch('toast', message: 'Category added successfully.', type: 'success');
    }

    /**
     * Generate a temporary preview URL for the uploaded logo.
     * Used in the Blade template to show a preview before saving.
     */
    public function logoPreviewUrl(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        try {
            return $this->logo->temporaryUrl();
        } catch (\Exception $e) {
            return null;
        }
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
        <a href="{{ route('superadmin.tenants.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to tenants
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

            {{-- Business logo with drag & drop and 10MB limit --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="field-logo">Business logo</label>
                <div
                    x-data="{ dragging: false, previewUrl: null }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; $refs.logoInput.files = $event.dataTransfer.files; $refs.logoInput.dispatchEvent(new Event('change'))"
                    :class="dragging ? 'border-primary-600 bg-blue-50 dark:bg-blue-500/10' : 'border-gray-300 dark:border-gray-600'"
                    class="relative flex items-center gap-4 rounded-xl border-2 border-dashed p-4 transition-colors"
                >
                    @php $logoPreview = $this->logoPreviewUrl(); @endphp
                    <template x-if="previewUrl">
                        <img :src="previewUrl" class="h-16 w-16 object-cover rounded-lg border border-gray-200 dark:border-gray-700 shrink-0">
                    </template>
                    <template x-if="!previewUrl && @js($tenantRecord->logo)">
                        <img src="{{ asset('storage/' . $tenantRecord->logo) }}" class="h-16 w-16 object-cover rounded-lg border border-gray-200 dark:border-gray-700 shrink-0">
                    </template>
                    <template x-if="!previewUrl && !@js($tenantRecord->logo)">
                        <div class="h-16 w-16 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 8h.01M4 4h16a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                        </div>
                    </template>
                    <div class="flex-1 min-w-0">
                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600">
                            {{ $logo ? 'Change logo' : 'Upload a logo' }}
                        </span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Drag & drop, or click to browse. PNG/JPG up to 10MB.</p>
                        <div wire:loading wire:target="logo" class="text-xs text-blue-500 mt-1 flex items-center gap-1">
                            <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Uploading…
                        </div>
                    </div>
                    @if ($logo)
                        <button type="button" wire:click="$set('logo', null)" @click="previewUrl = null" class="relative z-10 shrink-0 text-xs font-semibold text-red-500 hover:text-red-700 active:scale-95 transition-transform">Remove</button>
                    @endif
                    <input x-ref="logoInput" id="field-logo" type="file" wire:model="logo" accept="image/*"
                           @change="previewUrl = URL.createObjectURL($refs.logoInput.files[0])"
                           class="absolute inset-0 opacity-0 cursor-pointer">
                </div>
                @error('logo') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active / Pending</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="is_active" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
            </div>

            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    Recognized Tourist Attraction / Recommended Destination
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
                <button type="button" wire:click="openAddCategoryModal"
                        class="ml-auto inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary-50 dark:bg-blue-500/10 text-primary-600 dark:text-blue-400 text-xs font-semibold border border-primary-200 dark:border-blue-500/30 hover:bg-primary-100 dark:hover:bg-blue-500/20 transition active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Category
                </button>
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
                <button type="button" wire:click="useMyLocation" class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Use my location
                </button>
                <button type="button" wire:click="toggleSatellite" class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
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
                                    <div class="relative flex h-10 w-10 items-center justify-center
                                                transform-gpu will-change-transform transition-transform duration-200
                                                group-hover:scale-110 active:scale-95"
                                         style="cursor: pointer;">
                                        <svg class="absolute inset-0 size-10 drop-shadow-md
                                                    fill-white dark:fill-gray-900
                                                    stroke-slate-400 dark:stroke-slate-600 stroke-1"
                                             viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                        </svg>
                                        @if($iconSvg)
                                            <div class="absolute mb-1 size-[18px] text-gray-800 dark:text-white">
                                                {!! str_replace('<svg ', '<svg class="size-full stroke-current fill-none" ', $iconSvg) !!}
                                            </div>
                                        @else
                                            <span class="absolute mb-1 text-[10px] font-bold text-gray-800 dark:text-white">
                                                {{ strtoupper(substr($type, 0, 1)) }}
                                            </span>
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
                        <button type="button" wire:click="addMarker" class="text-xs font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
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
                                @php
                                    $type = $marker['type'] ?? '';
                                    $category = collect($this->markerCategories)->firstWhere('key', $type);
                                @endphp
                                <div wire:key="marker-row-{{ $marker['uid'] }}"
                                     class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700
                                            {{ $selectedMarkerIndex === $index ? 'ring-2 ring-primary-600/40 border-primary-600/30' : '' }}">
                                    <input type="text" wire:model.debounce.500ms="markers.{{ $index }}.name" placeholder="Place name" class="input !py-2 flex-1 min-w-[140px]">
                                    <input type="number" step="any" min="-90" max="90" wire:model.debounce.500ms="markers.{{ $index }}.lat" placeholder="Lat" class="input !py-2 !w-28 font-mono">
                                    <input type="number" step="any" min="-180" max="180" wire:model.debounce.500ms="markers.{{ $index }}.lng" placeholder="Lng" class="input !py-2 !w-28 font-mono">
                                    <select wire:model.live="markers.{{ $index }}.type" class="select !py-2 !w-40 {{ empty($marker['type']) ? 'border-red-300 dark:border-red-500' : '' }}">
                                        <option value="">Select category *</option>
                                        @foreach($this->markerCategories as $cat)
                                            <option value="{{ $cat['key'] }}">{{ $cat['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="removeMarker({{ $index }})" class="text-red-500 hover:text-red-700 active:scale-95 transition-transform" aria-label="Remove nearby place">
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
            <button type="submit" wire:loading.attr="disabled" class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Save Changes</span>
                <span wire:loading>Saving…</span>
            </button>
        </div>
    </form>

    {{-- Add Category Modal --}}
    @if($showAddCategoryModal)
        <div class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 p-4"
             x-on:keydown.escape.window="$wire.closeAddCategoryModal()"
             @click.self="$wire.closeAddCategoryModal()">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add Marker Category</h3>
                    <button type="button" wire:click="closeAddCategoryModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Key (slug)</label>
                        <input type="text" wire:model="newCategoryKey" class="input" placeholder="e.g. restaurant">
                        @error('newCategoryKey') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Label</label>
                        <input type="text" wire:model="newCategoryLabel" class="input" placeholder="Restaurant">
                        @error('newCategoryLabel') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                        <input type="color" wire:model="newCategoryColor" class="h-10 w-full rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer">
                        @error('newCategoryColor') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Icon (SVG)</label>
                        <input type="file" wire:model="newCategoryIcon" accept=".svg" class="input">
                        @error('newCategoryIcon') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" wire:click="closeAddCategoryModal" class="btn-secondary">Cancel</button>
                    <button type="button" wire:click="saveNewCategory" class="btn-primary">Add Category</button>
                </div>
            </div>
        </div>
    @endif

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