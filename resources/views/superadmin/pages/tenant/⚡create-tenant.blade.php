{{-- resources/views/superadmin/pages/tenant/⚡create-tenant.blade.php --}}
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
use App\Models\PropertyType;
use App\Models\Service;
use App\Models\User;
use App\Models\SiteSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

new
#[Layout('superadmin.layouts.app')]
#[Title('Add Tenant')]
class extends Component {
    use WithFileUploads;

    public int $step = 1;
    public int $furthestStep = 1;
    public bool $showSuccessModal = false;
    public bool $isSubmitting = false;

    public const MAX_MARKERS = 20;
    public const DEFAULT_LAT = 10.900977766937142;
    public const DEFAULT_LNG = 123.07055771888716;

    protected const STEP_LABELS = [
        1 => 'Business Details',
        2 => 'Map Location',
        3 => 'Sub-Establishments',
        4 => 'Admin Account',
    ];

    // Step 1: Business Details
    public string $name = '';
    public string $slug = '';
    public bool $slugEditable = false;
    public string $type_of_tenant_id = '';
    public string $address = '';
    public string $barangay = '';
    public string $city = '';
    public string $province = '';
    public string $public_email = '';
    public string $contact_number = '';

    public string $description = '';
    public bool $open_24_hours = false;
    public string $opening_time = '08:00';
    public string $closing_time = '17:00';
    public $logo;

    public bool $is_active = true;
    public bool $is_recommended = false;

    // Step 2: Map Location
    public float $latitude = self::DEFAULT_LAT;
    public float $longitude = self::DEFAULT_LNG;
    public bool $locationConfirmed = false;
    public bool $satellite = false;

    // Step 3: Sub-Establishments
    public bool $hasSubBranches = false;
    public array $markers = [];
    public ?int $selectedMarkerIndex = null;
    public string $markerSearch = '';

    // Map state
    public int $mapVersion = 0;
    public array $mapView = [
        'lat' => self::DEFAULT_LAT,
        'lng' => self::DEFAULT_LNG,
        'zoom' => 13,
    ];

    public array $markerCategories = [];

    // Add category modal
    public bool $showAddCategoryModal = false;
    public string $newCategoryKey = '';
    public string $newCategoryLabel = '';
    public string $newCategoryColor = '#3b82f6';
    public $newCategoryIcon;

    // Step 4: Admin Account
    public string $admin_name = '';
    public string $admin_email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public ?string $createdAdminEmail = null;
    public ?string $createdAdminPassword = null;
    public ?string $createdTenantName = null;
    public ?int $createdTenantId = null;

    public function mount(): void
    {
        $this->markerCategories = SiteSetting::getValue('marker_categories', []);
    }

    #[Computed]
    public function tenantTypes()
    {
        return TypeOfTenant::query()
            ->select('id', 'type')
            ->orderBy('type')
            ->get();
    }

    #[Computed]
    public function stepLabels(): array
    {
        return self::STEP_LABELS;
    }

    #[Computed]
    public function maxMarkers(): int
    {
        return self::MAX_MARKERS;
    }

    #[Computed]
    public function filteredMarkers(): array
    {
        if ($this->markerSearch === '') {
            return $this->markers;
        }

        $search = Str::lower($this->markerSearch);
        return array_values(array_filter($this->markers, function ($marker) use ($search) {
            return Str::contains(Str::lower($marker['name']), $search)
                || Str::contains(Str::lower($marker['type']), $search);
        }));
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255', Rule::unique('tenants', 'name')],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tenants', 'slug'), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'type_of_tenant_id' => ['required', 'integer', Rule::exists('type_of_tenants', 'id')],
            'address' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'public_email' => ['required', 'email', 'max:255', Rule::unique('tenants', 'email')],
            'contact_number' => ['nullable', 'string', 'max:11', 'regex:/^[0-9]{10,11}$/'],
            'latitude' => ['required', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['required', 'numeric', 'min:-180', 'max:180'],
            'markers' => ['array', 'max:' . self::MAX_MARKERS],
            'markers.*.name' => ['required', 'string', 'max:100'],
            'markers.*.lat' => ['required', 'numeric', 'min:-90', 'max:90'],
            'markers.*.lng' => ['required', 'numeric', 'min:-180', 'max:180'],
            'markers.*.type' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => array_filter([
                'nullable',
                'date_format:H:i',
                $this->open_24_hours ? null : 'after:opening_time',
            ]),
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'], // ★ updated to 10MB
            'is_active' => ['boolean'],
            'is_recommended' => ['boolean'],
            'admin_name' => ['required', 'string', 'min:3', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email'), 'different:public_email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function stepFields(int $step): array
    {
        return match ($step) {
            1 => [
                'name', 'slug', 'type_of_tenant_id', 'address', 'barangay', 'city', 'province',
                'public_email', 'contact_number', 'description',
                'opening_time', 'closing_time', 'logo', 'is_active', 'is_recommended',
            ],
            2 => ['latitude', 'longitude'],
            3 => [
                'markers', 'markers.*.name', 'markers.*.lat', 'markers.*.lng', 'markers.*.type',
            ],
            4 => ['admin_name', 'admin_email', 'password'],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'public_email.unique'   => 'This public email is already registered to another business.',
            'admin_email.unique'    => 'This admin login email is already taken.',
            'admin_email.different' => 'Use a different email than the public business email.',
            'contact_number.regex'  => 'Contact number must be 10-11 digits only.',
            'contact_number.max'    => 'Contact number cannot exceed 11 digits.',
            'password.confirmed'    => 'Password confirmation does not match.',
            'closing_time.after'    => 'Closing time must be later than the opening time.',
            'slug.regex'            => 'Slug may only contain lowercase letters, numbers and single hyphens.',
            'slug.unique'           => 'This slug is already taken — try another.',
            'markers.max'           => 'You can add up to ' . self::MAX_MARKERS . ' additional markers.',
            'markers.*.type.required' => 'Please select a category for this nearby place.',
            'logo.mimes'            => 'Logo must be a valid image file (JPEG, PNG, GIF, or WebP).',
            'logo.max'              => 'Logo must not exceed 10MB.', // ★ added max message
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'type_of_tenant_id' => 'business type',
            'public_email' => 'public email',
            'admin_email' => 'admin login email',
            'newCategoryKey' => 'category key',
            'newCategoryLabel' => 'category label',
            'newCategoryColor' => 'category color',
            'newCategoryIcon' => 'category icon',
        ];
    }

    public function updatedName(string $value): void
    {
        $this->name = trim($value);
        if (! $this->slugEditable) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function updated(string $property): void
    {
        $trimmable = [
            'address', 'barangay', 'city', 'province', 'description',
            'admin_name', 'public_email', 'admin_email', 'contact_number',
        ];

        if (in_array($property, $trimmable, true)) {
            $this->$property = trim($this->$property);
        }

        if ($property === 'contact_number') {
            $this->contact_number = substr(preg_replace('/[^0-9]/', '', $this->contact_number), 0, 11);
        }

        $liveValidated = ['slug', 'public_email', 'admin_email', 'contact_number'];
        if (in_array($property, $liveValidated, true) && $this->$property !== '') {
            $this->validateOnly($property);
        }

        if (preg_match('/^markers\.\d+\.type$/', $property)) {
            $this->mapVersion++;
        }
    }

    public function toggleSlugEdit(): void
    {
        $this->slugEditable = ! $this->slugEditable;
        if (! $this->slugEditable) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function generatePassword(): void
    {
        $this->password = Str::password(16, true, true, false);
        $this->password_confirmation = $this->password;
        $this->dispatch('password-generated', password: $this->password);
        $this->dispatch('toast', message: 'Strong password generated.', type: 'success');
    }

    public function confirmLocation(): void
    {
        $this->validate([
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180',
        ]);

        $this->locationConfirmed = true;
        $this->mapVersion++;
        $this->dispatch('toast', message: 'Main location confirmed.', type: 'success');
    }

    public function addMarkerAt(float|string $lat, float|string $lng): void
    {
        if (count($this->markers) >= self::MAX_MARKERS) {
            $this->dispatch('toast', message: 'You can add up to ' . self::MAX_MARKERS . ' nearby places.', type: 'error');
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
        $this->mapView = [
            'lat' => round((float) $lat, 6),
            'lng' => round((float) $lng, 6),
            'zoom' => 16,
        ];

        $this->dispatch('toast', message: 'Nearby place added. Please set its category.', type: 'info');
    }

    public function removeMarker(int $index): void
    {
        unset($this->markers[$index]);
        $this->markers = array_values($this->markers);

        if ($this->selectedMarkerIndex === $index) {
            $this->selectedMarkerIndex = null;
        }

        $this->mapVersion++;
        $this->dispatch('toast', message: 'Nearby place removed.', type: 'info');
    }

    public function focusMarker(int $index): void
    {
        if (isset($this->markers[$index])) {
            $this->selectedMarkerIndex = $index;
            $this->mapView = [
                'lat' => $this->markers[$index]['lat'],
                'lng' => $this->markers[$index]['lng'],
                'zoom' => 16,
            ];
            $this->mapVersion++;
        }
    }

    public function toggleSatellite(): void
    {
        $this->satellite = ! $this->satellite;
        $this->mapVersion++;
    }

    #[On('map:click')]
    public function onMapClick(float|string $lat, float|string $lng): void
    {
        if ($this->step === 2 && !$this->locationConfirmed) {
            $this->latitude = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => $this->mapView['zoom']];
            $this->mapVersion++;
        } elseif ($this->step === 3) {
            $this->addMarkerAt($lat, $lng);
        }
    }

    #[On('map:marker-clicked')]
    public function onMarkerClicked(string $id, float|string $lat, float|string $lng): void
    {
        if (str_starts_with($id, 'sub-marker-')) {
            $this->selectedMarkerIndex = (int) substr($id, strlen('sub-marker-'));
        }
    }

    #[On('map:marker-drag-end')]
    public function onMarkerDragEnd(string $id, float|string $lat, float|string $lng): void
    {
        if ($id === 'main-marker' && !$this->locationConfirmed) {
            $this->latitude = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => $this->mapView['zoom']];
            $this->mapVersion++;
        }

        if (str_starts_with($id, 'sub-marker-')) {
            $index = (int) substr($id, strlen('sub-marker-'));
            if (isset($this->markers[$index])) {
                $this->markers[$index]['lat'] = round((float) $lat, 6);
                $this->markers[$index]['lng'] = round((float) $lng, 6);
                $this->mapVersion++;
            }
        }
    }

    #[On('map:center-changed')]
    public function onMapCenterChanged(float|string $lat, float|string $lng): void
    {
        $this->mapView['lat'] = round((float) $lat, 6);
        $this->mapView['lng'] = round((float) $lng, 6);
    }

    #[On('map:zoom-changed')]
    public function onMapZoomChanged(int|string $zoom): void
    {
        $this->mapView['zoom'] = (int) $zoom;
    }

    public function useMyLocation(): void
    {
        $this->dispatch('request-geolocation');
    }

    #[On('geolocation-result')]
    public function onGeolocationResult(float|string $lat, float|string $lng): void
    {
        if ($this->step === 2 && !$this->locationConfirmed) {
            $this->latitude = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => 16];
            $this->mapVersion++;
            $this->dispatch('toast', message: 'Location updated from your device.', type: 'success');
        }
    }

    public function goToStep(int $target): void
    {
        if ($target >= 1 && $target <= $this->furthestStep) {
            $this->step = $target;
        }
    }

    public function nextStep(): void
    {
        $this->validate(
            Arr::only($this->rules(), $this->stepFields($this->step)),
            $this->messages(),
            $this->validationAttributes(),
        );

        if ($this->step === 2 && !$this->locationConfirmed) {
            $this->dispatch('toast', message: 'Please confirm the location before continuing.', type: 'error');
            return;
        }

        if ($this->step === 3 && $this->hasSubBranches) {
            foreach ($this->markers as $marker) {
                if (empty($marker['type'])) {
                    $this->dispatch('toast', message: 'All sub-branches must have a category selected.', type: 'error');
                    return;
                }
            }
        }

        $this->step = min(4, $this->step + 1);
        $this->furthestStep = max($this->furthestStep, $this->step);
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function goToTenantList(): void
    {
        $this->redirect(route('superadmin.tenants.index'), navigate: true);
    }

    public function save(): void
    {
        if ($this->isSubmitting) return;
        if ($this->step < 4) { $this->nextStep(); return; }

        $this->validate($this->rules(), $this->messages(), $this->validationAttributes());
        $this->isSubmitting = true;

        $logoPath = null;

        try {
            $tenant = DB::transaction(function () use (&$logoPath) {

                if ($this->logo) {
                    $logoPath = $this->logo->store('tenant-logos', 'public');
                }

                $coordinates = array_merge(
                    [[
                        'lat'  => $this->latitude,
                        'lng'  => $this->longitude,
                        'name' => 'Main Location',
                        'type' => 'parent',
                    ]],
                    $this->hasSubBranches ? array_map(fn (array $m) => Arr::except($m, ['uid']), $this->markers) : []
                );

                $businessInfo = [
                    'description'   => $this->description,
                    'opening_hours' => [
                        'opening' => $this->open_24_hours ? null : $this->opening_time,
                        'closing' => $this->open_24_hours ? null : $this->closing_time,
                        'is_24hr' => $this->open_24_hours,
                    ],
                    'barangay' => $this->barangay,
                    'city'     => $this->city,
                    'province' => $this->province,
                ];

                $tenant = Tenant::create([
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

                TenantSetting::create([
                    'tenant_id' => $tenant->id,
                    'key'       => 'business_info',
                    'value'     => $businessInfo,
                ]);

                $user = User::create([
                    'name'      => $this->admin_name,
                    'email'     => $this->admin_email,
                    'password'  => Hash::make($this->password),
                    'tenant_id' => $tenant->id,
                    'is_active' => true,
                ]);
                $user->assignRole('admin');

                $tenantType = $this->tenantTypes->firstWhere('id', (int) $this->type_of_tenant_id);
                foreach ($this->getDefaultPropertyTypes($tenantType) as $ptName) {
                    PropertyType::create(['tenant_id' => $tenant->id, 'name' => $ptName]);
                }

                $this->createDefaultServices($tenant);

                return $tenant;
            });

            $this->createdAdminEmail = $this->admin_email;
            $this->createdAdminPassword = $this->password;
            $this->createdTenantName = $tenant->name;
            $this->createdTenantId = $tenant->id;
            $this->showSuccessModal = true;

        } catch (\Throwable $e) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            Log::error('Tenant creation failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->dispatch('toast', message: 'Something went wrong while creating the tenant. Please try again.', type: 'error');
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function createAnother(): void
    {
        $this->reset([
            'step', 'furthestStep', 'showSuccessModal',
            'name', 'slug', 'slugEditable', 'type_of_tenant_id',
            'address', 'barangay', 'city', 'province', 'public_email', 'contact_number',
            'latitude', 'longitude', 'locationConfirmed', 'markers', 'satellite',
            'hasSubBranches', 'description', 'open_24_hours', 'opening_time', 'closing_time',
            'logo', 'is_active', 'is_recommended',
            'admin_name', 'admin_email', 'password', 'password_confirmation',
            'createdAdminEmail', 'createdAdminPassword', 'createdTenantName', 'createdTenantId',
            'selectedMarkerIndex', 'mapVersion', 'markerSearch',
        ]);
        $this->resetValidation();
        $this->mapView = ['lat' => self::DEFAULT_LAT, 'lng' => self::DEFAULT_LNG, 'zoom' => 13];
        $this->dispatch('toast', message: 'Ready to add another business.', type: 'info');
    }

    public function goToAddSubBranches(): void
    {
        $this->redirect(route('superadmin.map-markers.index', ['tenant' => $this->createdTenantId]), navigate: true);
    }

    protected function getDefaultPropertyTypes(?TypeOfTenant $type): array
    {
        if (!$type) {
            return ['Standard Room'];
        }

        $name = strtolower($type->type);

        return match ($name) {
            'resort'   => ['Standard Room', 'Deluxe Room', 'Cottage', 'Villa'],
            'eco park' => ['Entrance', 'Cottage', 'Pavilion', 'Picnic Hut'],
            'mangrove' => ['Entrance', 'Boat', 'Cottage', 'Viewing Deck'],
            'inn'      => ['Standard Room', 'Family Room'],
            default    => ['Standard Room'],
        };
    }

    protected function createDefaultServices(Tenant $tenant): void
    {
        foreach (['Entrance Fee', 'Parking', 'Guided Tour'] as $serviceName) {
            Service::create([
                'tenant_id' => $tenant->id,
                'name'      => $serviceName,
                'price'     => 0,
                'is_active' => true,
            ]);
        }
    }

    public function logoPreviewUrl(): ?string
    {
        if (!$this->logo) return null;
        try {
            return $this->logo->temporaryUrl();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function updateMarkerOrder(string $orderedUids): void
    {
        $uids = explode(',', $orderedUids);
        $markers = collect($this->markers);

        $this->markers = collect($uids)
            ->map(fn($uid) => $markers->firstWhere('uid', $uid))
            ->filter()
            ->merge($markers->whereNotIn('uid', $uids))
            ->values()
            ->toArray();

        $this->mapVersion++;
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
};
?>
<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Toast notifications --}}
    <div x-data="{ toasts: [] }"
         x-on:toast.window="
             const id = Date.now() + Math.random();
             toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
             setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4000);
         "
         class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none">
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
                }">
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    {{-- Loading overlay --}}
    <div wire:loading.delay.longer wire:target="save"
         class="fixed inset-0 z-40 bg-white/60 dark:bg-gray-900/60 backdrop-blur-sm flex items-center justify-center pointer-events-none">
        <div class="flex items-center gap-3 bg-white dark:bg-gray-800 rounded-2xl shadow-xl px-6 py-4 pointer-events-auto">
            <svg class="animate-spin h-5 w-5 text-primary-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Creating the business & admin account…</span>
        </div>
    </div>

    @if(session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Add Tenant</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create a new business and its admin account.</p>
        </div>
        <button type="button" wire:click="goToTenantList"
                @if($step > 1 || $name !== '') wire:confirm="Leave without saving? Your progress on this tenant will be lost." @endif
                class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to tenants
        </button>
    </div>

    {{-- Sticky Step Indicator --}}
    <div class="sticky top-0 z-30 bg-[#F8F7F3] dark:bg-gray-900/95 backdrop-blur-sm py-3 mb-2">
        <nav aria-label="Progress" class="max-w-5xl mx-auto">
            <ol class="flex items-center gap-2">
                @foreach($this->stepLabels as $stepNum => $label)
                    <li class="flex items-center gap-2 min-w-0 {{ $stepNum < 4 ? 'flex-1' : '' }}">
                        <button type="button" wire:click="goToStep({{ $stepNum }})"
                                @if($stepNum > $furthestStep) disabled @endif
                                aria-current="{{ $step === $stepNum ? 'step' : 'false' }}"
                                class="flex items-center gap-2 shrink-0 group {{ $stepNum > $furthestStep ? 'cursor-not-allowed opacity-60' : 'cursor-pointer' }} focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-colors
                                {{ $step > $stepNum ? 'bg-green-500 text-white' : ($step === $stepNum ? 'bg-primary-600 text-white ring-4 ring-blue-100 dark:ring-blue-500/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400') }}">
                                @if($step > $stepNum)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    {{ $stepNum }}
                                @endif
                            </span>
                            <span class="hidden sm:inline text-sm font-medium {{ $step >= $stepNum ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400' }} {{ $stepNum <= $furthestStep && $stepNum !== $step ? 'group-hover:underline' : '' }}">
                                {{ $label }}
                            </span>
                        </button>
                        @if($stepNum < 4)
                            <div class="flex-1 h-1 rounded bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div class="h-full bg-primary-600 transition-all duration-500" style="width: {{ $step > $stepNum ? '100%' : '0%' }}"></div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    </div>

    {{-- Error summary --}}
    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10 p-4 text-sm text-red-700 dark:text-red-300">
            <p class="font-semibold mb-1">Please fix {{ $errors->count() }} field{{ $errors->count() === 1 ? '' : 's' }} before continuing:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ========== STEP 1: BUSINESS DETAILS ========== --}}
    @if($step === 1)
        <div class="card p-6 space-y-6">
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white">Business Details</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500 -mt-4">Fields marked <span class="text-red-500">*</span> are required.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business name <span class="text-red-500">*</span></label>
                    <input type="text" id="field-name" wire:model.live.debounce.300ms="name" class="input" placeholder="e.g. Islas Beach Resort">
                    @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="field-slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300">URL slug</label>
                        <button type="button" wire:click="toggleSlugEdit" class="text-xs font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                            {{ $slugEditable ? 'Auto-generate' : 'Edit manually' }}
                        </button>
                    </div>
                    <div class="flex rounded-xl overflow-hidden border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-900">
                        <span class="py-2.5 px-3 bg-gray-200 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 border-r border-gray-300 dark:border-gray-600">spot/</span>
                        <input type="text" id="field-slug" wire:model.live.debounce.400ms="slug" @if(!$slugEditable) readonly @endif class="flex-1 bg-transparent border-none py-2.5 px-4 text-sm outline-none {{ $slugEditable ? 'text-gray-900 dark:text-white cursor-text' : 'text-gray-500 dark:text-gray-400 cursor-default' }}">
                    </div>
                    @error('slug') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="field-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business type <span class="text-red-500">*</span></label>
                    <select id="field-type" wire:model="type_of_tenant_id" class="select">
                        <option value="">— Select type —</option>
                        @foreach($this->tenantTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->type }}</option>
                        @endforeach
                    </select>
                    @error('type_of_tenant_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="field-public-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Public email <span class="text-red-500">*</span></label>
                    <input type="email" id="field-public-email" wire:model.live.debounce.400ms="public_email" class="input" placeholder="business@email.com">
                    @error('public_email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="field-contact" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact number</label>
                    <input type="tel" id="field-contact"
                           inputmode="numeric" pattern="[0-9]*" maxlength="11"
                           wire:model.live.debounce.400ms="contact_number"
                           x-on:input="event.target.value = event.target.value.replace(/[^0-9]/g, '').slice(0, 11)"
                           class="input" placeholder="09xxxxxxxxx">
                    @error('contact_number') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="field-barangay" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barangay</label>
                    <input type="text" id="field-barangay" wire:model="barangay" class="input">
                    @error('barangay') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="field-city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                    <input type="text" id="field-city" wire:model="city" class="input">
                    @error('city') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="field-province" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Province</label>
                    <input type="text" id="field-province" wire:model="province" class="input">
                    @error('province') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label for="field-address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full address (optional)</label>
                <input type="text" id="field-address" wire:model="address" class="input" placeholder="Street, Building, etc.">
                @error('address') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="field-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                <textarea id="field-description" wire:model="description" rows="3" class="textarea" placeholder="Short description about the business"></textarea>
                @error('description') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
            </div>

            {{-- Business Hours --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Business hours</label>
                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-600 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="open_24_hours" class="rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                        Open 24 hours
                    </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 {{ $open_24_hours ? 'opacity-50 pointer-events-none' : '' }}">
                    <div>
                        <label for="field-opening" class="text-xs text-gray-500 dark:text-gray-400">Opening time</label>
                        <input type="time" id="field-opening" wire:model="opening_time" class="input" @if($open_24_hours) disabled @endif>
                        @error('opening_time') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="field-closing" class="text-xs text-gray-500 dark:text-gray-400">Closing time</label>
                        <input type="time" id="field-closing" wire:model="closing_time" class="input" @if($open_24_hours) disabled @endif>
                        @error('closing_time') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- Logo --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="field-logo">Business logo</label>
                <div
                    x-data="{ dragging: false }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; $refs.logoInput.files = $event.dataTransfer.files; $refs.logoInput.dispatchEvent(new Event('change'))"
                    :class="dragging ? 'border-primary-600 bg-blue-50 dark:bg-blue-500/10' : 'border-gray-300 dark:border-gray-600'"
                    class="relative flex items-center gap-4 rounded-xl border-2 border-dashed p-4 transition-colors"
                >
                    @php $logoPreview = $this->logoPreviewUrl(); @endphp
                    @if ($logoPreview)
                        <img src="{{ $logoPreview }}" class="h-16 w-16 object-cover rounded-lg border border-gray-200 dark:border-gray-700 shrink-0">
                    @else
                        <div class="h-16 w-16 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 8h.01M4 4h16a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                        </div>
                    @endif

                    <div class="flex-1 min-w-0">
                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600">
                            {{ $logo ? 'Change logo' : 'Upload a logo' }}
                        </span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Drag & drop, or click to browse. PNG/JPG up to 10MB.</p> {{-- ★ updated text --}}
                        <div wire:loading wire:target="logo" class="text-xs text-blue-500 mt-1 flex items-center gap-1">
                            <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Uploading…
                        </div>
                    </div>

                    @if ($logo)
                        <button type="button" wire:click="$set('logo', null)" class="relative z-10 shrink-0 text-xs font-semibold text-red-500 hover:text-red-700 active:scale-95 transition-transform">Remove</button>
                    @endif

                    <input x-ref="logoInput" id="field-logo" type="file" wire:model="logo" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                </div>
                @error('logo') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
            </div>

            {{-- Active & Recommended Toggles --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Activate this business immediately?</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_active" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        Recommended Destination
                    </span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_recommended" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep" class="btn-primary active:scale-95 transition-transform">
                    Next: Map Location →
                </button>
            </div>
        </div>
    @endif

    {{-- ========== STEP 2: MAP LOCATION ========== --}}
    @if($step === 2)
        <div class="card p-6 space-y-6">
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white">Map Location Setup</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500 -mt-4">Drag the pin or click on the map to set the main location.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-lat" class="text-xs text-gray-500 dark:text-gray-400">Latitude</label>
                    <input type="number" id="field-lat" step="any" min="-90" max="90"
                           wire:model.live.debounce.500ms="latitude" onfocus="this.select()"
                           class="input font-mono {{ $locationConfirmed ? 'bg-gray-100 dark:bg-gray-700 cursor-not-allowed' : '' }}"
                           @if($locationConfirmed) readonly @endif>
                    @error('latitude') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="field-lng" class="text-xs text-gray-500 dark:text-gray-400">Longitude</label>
                    <input type="number" id="field-lng" step="any" min="-180" max="180"
                           wire:model.live.debounce.500ms="longitude" onfocus="this.select()"
                           class="input font-mono {{ $locationConfirmed ? 'bg-gray-100 dark:bg-gray-700 cursor-not-allowed' : '' }}"
                           @if($locationConfirmed) readonly @endif>
                    @error('longitude') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 mb-4">
                <button type="button"
                        wire:click="useMyLocation"
                        @if($locationConfirmed) disabled @endif
                        class="inline-flex items-center gap-1.5 rounded-full border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-50 disabled:cursor-not-allowed active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Use my location
                </button>
                <button type="button"
                        wire:click="toggleSatellite"
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium shadow-sm transition
                               {{ $satellite ? 'bg-primary-600 text-white border-transparent' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700' }} focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                    Satellite
                </button>
            </div>

            <div class="card overflow-hidden relative" style="height: 400px;">
                <div wire:key="tenant-create-map-{{ $mapVersion }}">
                    <x-map
                        id="tenant-create-map"
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
                        <x-map-controls :zoom="true" :compass="true" :locate="true" :fullscreen="true" :scale="true" position="top-right" />

                        <x-map-marker
                            wire:key="main-marker-{{ $latitude }}-{{ $longitude }}-{{ $locationConfirmed ? 'confirmed' : 'editable' }}"
                            :lat="$latitude"
                            :lng="$longitude"
                            color="#ef4444"
                            id="main-marker"
                            :draggable="!$locationConfirmed"
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

            @if(!$locationConfirmed)
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="confirmLocation" class="btn-primary active:scale-95 transition-transform">Confirm Location</button>
                    <span class="text-xs text-gray-400">After confirming, the pin will be locked.</span>
                </div>
            @else
                <div class="flex items-center gap-2 text-sm font-medium text-green-600 dark:text-green-400">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Location confirmed and locked.
                </div>
            @endif

            <div class="flex justify-between">
                <button type="button" wire:click="prevStep" class="btn-secondary active:scale-95 transition-transform">← Back</button>
                <button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep"
                        @if(!$locationConfirmed) disabled @endif
                        class="btn-primary active:scale-95 transition-transform">
                    Next: Sub-Establishments →
                </button>
            </div>
        </div>
    @endif

    {{-- ========== STEP 3: SUB-ESTABLISHMENTS ========== --}}
    @if($step === 3)
        <div class="card p-6 space-y-6">
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white">Sub-Establishments</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500 -mt-4">Optional: Add restaurants, cafes, parking, etc. linked to this main spot.</p>

            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Does this spot have sub-branches?</span>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model.live="hasSubBranches" value="1" class="text-primary-600 focus:ring-primary-600">
                    <span class="text-sm">Yes</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model.live="hasSubBranches" value="0" class="text-primary-600 focus:ring-primary-600">
                    <span class="text-sm">No</span>
                </label>
            </div>

            @if($hasSubBranches)
                <div class="border-t border-gray-200 dark:border-gray-700 pt-5 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Nearby places <span class="text-gray-400 font-normal">({{ count($markers) }}/{{ $this->maxMarkers }})</span>
                        </span>
                        <div class="flex items-center gap-2">
                            <input type="text" wire:model.live.debounce.300ms="markerSearch"
                                   placeholder="Search places…"
                                   class="input !py-1.5 !w-48 text-sm">
                            <button type="button" wire:click="openAddCategoryModal"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary-50 dark:bg-blue-500/10 text-primary-600 dark:text-blue-400 text-xs font-semibold border border-primary-200 dark:border-blue-500/30 hover:bg-primary-100 dark:hover:bg-blue-500/20 transition active:scale-95">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Add Category
                            </button>
                        </div>
                    </div>

                    {{-- Mini map for adding sub-branches --}}
                    <div class="card overflow-hidden relative" style="height: 400px;">
                        <div wire:key="tenant-sub-map-{{ $mapVersion }}">
                            <x-map
                                id="tenant-sub-map"
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
                                <x-map-controls :zoom="true" :compass="true" :locate="true" :fullscreen="true" :scale="true" position="top-right" />

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
                                        draggable
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
                            </x-map>
                        </div>
                    </div>

                    {{-- Marker list --}}
                    @if(count($this->filteredMarkers) > 0)
                        <div class="flex flex-wrap items-center gap-3 mb-3 text-[11px] text-gray-500 dark:text-gray-400">
                            @foreach($this->markerCategories as $cat)
                                <span class="inline-flex items-center gap-1">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $cat['color'] }}"></span>
                                    {{ $cat['label'] }}
                                </span>
                            @endforeach
                        </div>
                        <ul wire:sort="updateMarkerOrder" class="space-y-2">
                            @foreach($this->filteredMarkers as $index => $marker)
                                @php
                                    $originalIndex = array_search($marker['uid'], array_column($this->markers, 'uid'));
                                    $type = $marker['type'] ?? '';
                                    $category = collect($this->markerCategories)->firstWhere('key', $type);
                                @endphp
                                <li wire:key="marker-row-{{ $marker['uid'] }}"
                                    wire:sort:item="{{ $marker['uid'] }}"
                                    class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700 cursor-grab active:cursor-grabbing
                                            {{ $selectedMarkerIndex === $originalIndex ? 'ring-2 ring-primary-600/40 border-primary-600/30' : '' }}"
                                    @click="$wire.selectedMarkerIndex = {{ $originalIndex }}"
                                    title="Drag to reorder"
                                >
                                    <span class="shrink-0 text-gray-400 dark:text-gray-500 cursor-grab">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 100 4 2 2 0 000-4zM7 8a2 2 0 100 4 2 2 0 000-4zM7 14a2 2 0 100 4 2 2 0 000-4zM13 2a2 2 0 100 4 2 2 0 000-4zM13 8a2 2 0 100 4 2 2 0 000-4zM13 14a2 2 0 100 4 2 2 0 000-4z"/></svg>
                                    </span>
                                    <input type="text" wire:model.debounce.500ms="markers.{{ $originalIndex }}.name" placeholder="Place name" aria-label="Place name" class="input !py-2 flex-1 min-w-[140px]">
                                    <input type="number" step="any" min="-90" max="90" wire:model.debounce.500ms="markers.{{ $originalIndex }}.lat" placeholder="Lat" aria-label="Latitude" class="input !py-2 !w-28 font-mono">
                                    <input type="number" step="any" min="-180" max="180" wire:model.debounce.500ms="markers.{{ $originalIndex }}.lng" placeholder="Lng" aria-label="Longitude" class="input !py-2 !w-28 font-mono">
                                    <select wire:model.live="markers.{{ $originalIndex }}.type"
                                            class="select !py-2 !w-48 {{ empty($marker['type']) ? 'border-red-300 dark:border-red-500' : '' }}">
                                        <option value="">Select category *</option>
                                        @foreach($this->markerCategories as $cat)
                                            <option value="{{ $cat['key'] }}">{{ $cat['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button"
                                            wire:click="focusMarker({{ $originalIndex }})"
                                            aria-label="Locate on map"
                                            class="text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 shrink-0 active:scale-95 transition-transform">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    </button>
                                    <button type="button" wire:click="removeMarker({{ $originalIndex }})" aria-label="Remove nearby place" class="text-red-500 hover:text-red-700 shrink-0 active:scale-95 transition-transform">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $markerSearch !== '' ? 'No places match your search.' : 'Click anywhere on the map to add a nearby place.' }}
                        </p>
                    @endif
                </div>
            @endif

            <div class="flex justify-between">
                <button type="button" wire:click="prevStep" class="btn-secondary active:scale-95 transition-transform">← Back</button>
                <button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep" class="btn-primary active:scale-95 transition-transform">
                    {{ $hasSubBranches ? 'Next: Admin Account →' : 'Skip / Next: Admin Account →' }}
                </button>
            </div>
        </div>
    @endif

    {{-- ========== STEP 4: ADMIN ACCOUNT ========== --}}
    @if($step === 4)
        <div class="card p-6 space-y-6">
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white mb-4">Admin Account Assignment</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-admin-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin full name <span class="text-red-500">*</span></label>
                    <input type="text" id="field-admin-name" wire:model="admin_name" class="input">
                    @error('admin_name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="field-admin-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin login email <span class="text-red-500">*</span></label>
                    <input type="email" id="field-admin-email" wire:model.live.debounce.400ms="admin_email" class="input" placeholder="admin@business.com">
                    @error('admin_email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
            </div>

            <div x-data="{ show: false, score: 0, pwd: '' }"
                 x-on:password-generated.window="pwd = $event.detail.password; score = window.pwStrength(pwd)">
                <label for="field-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <input
                            :type="show ? 'text' : 'password'"
                            id="field-password"
                            wire:model="password"
                            @input="pwd = $event.target.value; score = window.pwStrength(pwd)"
                            class="input pr-10"
                            placeholder="Min. 8 characters"
                        >
                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 active:scale-95 transition-transform" :aria-label="show ? 'Hide password' : 'Show password'">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.025 10.025 0 012.132-3.592m3.16-2.11A9.958 9.958 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.958 9.958 0 01-4.132 5.411M15 12a3 3 0 11-6 0 3 3 0 016 0z M3 3l18 18"/></svg>
                        </button>
                    </div>
                    <button type="button" wire:click="generatePassword" class="px-3 py-2 rounded-xl bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition active:scale-95 whitespace-nowrap">
                        Generate
                    </button>
                </div>

                <div class="mt-1.5 flex items-center gap-2" x-show="pwd.length > 0" x-cloak>
                    <div class="flex-1 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-300"
                             :class="{ 'bg-red-500': score <= 1, 'bg-orange-500': score === 2, 'bg-yellow-500': score === 3, 'bg-green-500': score >= 4 }"
                             :style="`width: ${(score / 5) * 100}%`"></div>
                    </div>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 w-16 text-right"
                          x-text="['Too weak','Weak','Fair','Good','Strong','Excellent'][score]"></span>
                </div>

                @error('password') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="field-password-confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm password <span class="text-red-500">*</span></label>
                <input type="password" id="field-password-confirmation" wire:model="password_confirmation" class="input">
                @error('password_confirmation') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
            </div>

            <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                <button type="button" wire:click="prevStep" class="btn-secondary active:scale-95 transition-transform">← Back</button>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="relative px-8 py-3 rounded-full bg-green-600 hover:bg-green-500 disabled:opacity-70 text-white text-sm font-semibold shadow-lg shadow-green-500/20 transition focus-visible:ring-2 focus-visible:ring-green-500/50 active:scale-95">
                    <span wire:loading.remove wire:target="save">Complete Registration & Save</span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Creating…
                    </span>
                </button>
            </div>
        </div>
    @endif

    {{-- Success Modal --}}
    <div x-cloak x-show="$wire.showSuccessModal"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
         role="dialog" aria-modal="true" aria-labelledby="success-modal-title">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-8 max-w-md w-full shadow-2xl"
             x-data="{ copiedAll: false, email: @js($createdAdminEmail), password: @js($createdAdminPassword) }"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <div class="text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-green-100 dark:bg-green-500/20 flex items-center justify-center mb-4 animate-[bounce_0.8s_ease-in-out_1]">
                    <svg class="w-7 h-7 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h3 id="success-modal-title" class="text-xl font-bold text-gray-900 dark:text-white mb-1">{{ $createdTenantName ?? 'Business' }} is live!</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">Keep these credentials safe. The admin can log in with the email and password below.</p>

                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 text-left mb-4 space-y-3">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Admin email</p>
                        <p class="text-sm font-mono text-gray-900 dark:text-white break-all">{{ $createdAdminEmail }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Password</p>
                        <p class="text-sm font-mono text-gray-900 dark:text-white break-all">{{ $createdAdminPassword }}</p>
                    </div>
                </div>

                <button type="button"
                        @click="navigator.clipboard.writeText(`Email: ${email}\nPassword: ${password}`); copiedAll = true; setTimeout(() => copiedAll = false, 2000)"
                        class="w-full mb-3 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                    <span x-show="!copiedAll">Copy credentials</span>
                    <span x-show="copiedAll" x-cloak class="inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Copied to clipboard
                    </span>
                </button>

                <div class="flex flex-col sm:flex-row gap-2">
                    <button type="button" wire:click="createAnother" class="flex-1 px-6 py-3 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                        Add another
                    </button>
                    <button type="button" wire:click="goToTenantList" class="flex-1 px-6 py-3 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                        Go to Tenants List
                    </button>
                </div>
            </div>
        </div>
    </div>

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
        window.pwStrength = function (pwd) {
            if (!pwd) return 0;
            let score = 0;
            if (pwd.length >= 8) score++;
            if (pwd.length >= 12) score++;
            if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) score++;
            if (/\d/.test(pwd)) score++;
            if (/[^A-Za-z0-9]/.test(pwd)) score++;
            return Math.min(score, 5);
        };

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