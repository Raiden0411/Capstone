{{-- resources/views/tenant/pages/settings/⚡business-profile.blade.php --}}
<?php

use App\Models\BusinessApplication;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TypeOfTenant;
use App\Services\ReverseGeocodeService;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('tenant.layouts.app')]
#[Title('Business Profile')]
class extends Component
{
    use WithFileUploads;
    use HandlesImageUploads;

    public ?Tenant $tenant = null;

    /** KYB record ID — resolved in mount, never trusted from client. */
    #[Locked]
    public ?int $businessApplicationId = null;

    /** Loaded from SiteSetting — never trusted from the client. */
    #[Locked]
    public array $markerCategories = [];

    // ═══ Business details ═══
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

    // ═══ Brand assets (stored on upload) ═══
    public $logo;
    public ?string $logo_path = null;

    public $cover_photo;
    public ?string $cover_photo_path = null;

    public bool $is_active = true;

    // ═══ Location & markers ═══
    public float $latitude  = 10.900977766937142;
    public float $longitude = 123.07055771888716;
    public array $markers   = [];

    public bool $satellite = false;
    public string $locationMode = 'main';
    public int $mapVersion = 0;
    public array $mapView = [
        'lat'  => 10.900977766937142,
        'lng'  => 123.07055771888716,
        'zoom' => 13,
    ];
    public ?int $selectedMarkerIndex = null;

    /** True while a reverse-geocode request is in flight — drives the "Looking up address…" chip. */
    public bool $isResolvingAddress = false;

    // ─────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────

    #[Computed]
    public function barangays(): array
    {
        return Cache::remember('config.barangays', now()->addDay(), function () {
            $list = config('barangays', [
                'Barangay I', 'Barangay II', 'Barangay III', 'Barangay IV',
                'Barangay V', 'Barangay VI', 'Barangay VII', 'Barangay VIII',
                'Barangay IX', 'Barangay X', 'Barangay XI', 'Barangay XII',
                'Barangay XIII', 'Barangay XIV', 'Barangay XV', 'Barangay XVI',
            ]);

            return collect($list)->sort()->values()->all();
        });
    }

    #[Computed]
    public function tenantTypes()
    {
        return Cache::remember('tenant_types.all', now()->addHour(), function () {
            return TypeOfTenant::query()
                ->select('id', 'type')
                ->orderBy('type')
                ->get()
                ->toArray();
        });
    }

    // ─────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────

    public function mount(): void
    {
        $user = Auth::user();
        $this->tenant = $user?->tenant;

        $this->authorizeEditProfile();

        $this->name              = $this->tenant->name ?? '';
        $this->slug              = $this->tenant->slug ?? '';
        $this->type_of_tenant_id = $this->tenant->type_of_tenant_id;
        $this->address           = $this->tenant->address ?? '';
        $this->public_email      = $this->tenant->email ?? '';
        $this->contact_number    = $this->tenant->contact_number ?? '';
        $this->is_active         = (bool) $this->tenant->is_active;
        $this->logo_path         = $this->tenant->logo;

        $coords = $this->tenant->coordinates ?? [];
        $main   = $coords[0] ?? null;

        $this->latitude  = isset($main['lat']) ? (float) $main['lat'] : 10.900977766937142;
        $this->longitude = isset($main['lng']) ? (float) $main['lng'] : 123.07055771888716;
        $this->markers   = array_slice($coords, 1);

        foreach ($this->markers as &$marker) {
            if (!isset($marker['uid'])) {
                $marker['uid'] = (string) Str::uuid();
            }
        }
        unset($marker);

        $this->mapView = [
            'lat'  => $this->latitude,
            'lng'  => $this->longitude,
            'zoom' => 13,
        ];

        $info = TenantSetting::where('tenant_id', $this->tenant->id)
            ->where('key', 'business_info')
            ->first();

        if ($info && is_array($info->value)) {
            $v = $info->value;

            $this->description  = $v['description'] ?? '';
            $this->website      = $v['website'] ?? '';
            $this->facebook     = $v['social_links']['facebook'] ?? '';
            $this->instagram    = $v['social_links']['instagram'] ?? '';
            $this->opening_time = $v['opening_hours']['opening'] ?? '08:00';
            $this->closing_time = $v['opening_hours']['closing'] ?? '17:00';
            $this->barangay     = $v['barangay'] ?? '';
            $this->city         = $v['city'] ?? '';
            $this->province     = $v['province'] ?? '';
        }

        $app = BusinessApplication::where('approved_tenant_id', $this->tenant->id)
            ->latest('id')
            ->first();

        if ($app) {
            $this->businessApplicationId = $app->id;
            $this->cover_photo_path      = $app->cover_photo_path;
        }

        $this->markerCategories = SiteSetting::getValue('marker_categories', []) ?? [];
    }

    public function hydrate(): void
    {
        $this->authorizeEditProfile();
    }

    protected function authorizeEditProfile(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_unless($user->tenant_id, 403, 'No business is linked to your account.');
        abort_unless(
            $user->hasAnyRole(['admin', 'super-admin']),
            403,
            'Only business owners can edit the business profile.'
        );
        abort_unless(
            $this->tenant?->id === $user->tenant_id,
            403,
            'You are not authorized to edit this business profile.'
        );
    }

    // ─────────────────────────────────────────────────────
    //  Mapcn bridge methods (Rule 36 / bug 6.144)
    // ─────────────────────────────────────────────────────

    public function getZoom(mixed $value = null): int
    {
        return (int) ($this->mapView['zoom'] ?? 13);
    }

    /** @return array{0: float, 1: float} */
    public function getCenter(mixed $value = null): array
    {
        return [
            (float) ($this->mapView['lng'] ?? 0.0),
            (float) ($this->mapView['lat'] ?? 0.0),
        ];
    }

    public function getBearing(mixed $value = null): float
    {
        return 0.0;
    }

    public function getPitch(mixed $value = null): float
    {
        return 0.0;
    }

    // ─────────────────────────────────────────────────────
    //  Asset preview URLs — Rule J: relative /storage paths only.
    //  asset() prefixes APP_URL, which may not match the current host.
    // ─────────────────────────────────────────────────────

    public function logoPreviewUrl(): ?string
    {
        return $this->logo_path
            ? '/storage/' . ltrim($this->logo_path, '/')
            : null;
    }

    public function coverPreviewUrl(): ?string
    {
        return $this->cover_photo_path
            ? '/storage/' . ltrim($this->cover_photo_path, '/')
            : null;
    }

    // ─────────────────────────────────────────────────────
    //  Field hooks
    // ─────────────────────────────────────────────────────

    public function updatedName(): void
    {
        $this->name = trim((string) $this->name);

        if (!$this->tenant) {
            return;
        }

        $base = Str::slug($this->name) ?: 'business';

        if ($base === $this->slug) {
            return;
        }

        $slug = $base;
        $i    = 2;
        while (
            Tenant::where('slug', $slug)
                ->where('id', '!=', $this->tenant->id)
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        $this->slug = $slug;
    }

    public function updatedLatitude($value): void
    {
        $this->latitude = round(max(-90, min(90, (float) $value)), 6);
        $this->mapView['lat']  = $this->latitude;
        $this->mapView['zoom'] = 16;
        $this->mapVersion++;
    }

    public function updatedLongitude($value): void
    {
        $this->longitude = round(max(-180, min(180, (float) $value)), 6);
        $this->mapView['lng']  = $this->longitude;
        $this->mapView['zoom'] = 16;
        $this->mapVersion++;
    }

    public function updated(string $property): void
    {
        $trimFields = [
            'address', 'barangay', 'city', 'province',
            'description', 'website', 'facebook', 'instagram',
            'public_email', 'contact_number',
        ];

        if (in_array($property, $trimFields, true)) {
            $this->$property = trim((string) $this->$property);
        }

        if ($property === 'contact_number') {
            $this->contact_number = substr(
                (string) preg_replace('/[^0-9]/', '', $this->contact_number),
                0,
                11
            );
        }

        if (preg_match('/^markers\.\d+\.type$/', $property)) {
            $this->mapVersion++;
        }
    }

    // ─────────────────────────────────────────────────────
    //  Reverse geocoding (Rule 86 — always overwrite)
    // ─────────────────────────────────────────────────────

    /**
     * Convert coordinates into the four address fields via Nominatim.
     *
     * Called from the map-interaction hooks when the user is working with
     * the MAIN location (not a sub-marker). Semantics per Rule 86:
     * every field the geocoder returns a non-empty value for is
     * overwritten. Manual edits the user made before the pin moved are
     * lost on the next pin move — that's the intended behaviour, because
     * the four fields are derived values from the pin.
     */
    public function resolveAddress(float $lat, float $lng): void
    {
        if (! is_finite($lat) || ! is_finite($lng)) {
            return;
        }
        if (abs($lat) > 90 || abs($lng) > 180) {
            return;
        }

        $this->isResolvingAddress = true;

        try {
            $result = app(ReverseGeocodeService::class)->reverse($lat, $lng);
        } catch (\Throwable $e) {
            Log::warning('Business profile reverse geocode failed', [
                'tenant_id' => $this->tenant?->id,
                'lat'       => $lat,
                'lng'       => $lng,
                'error'     => $e->getMessage(),
            ]);
            $this->isResolvingAddress = false;
            return;
        }

        if ($result === null) {
            $this->isResolvingAddress = false;
            return;
        }

        // Rule 86 — always overwrite when the geocoder returned a value.
        if ($result['address']  !== '') { $this->address  = $result['address'];  }
        if ($result['barangay'] !== '') { $this->barangay = $result['barangay']; }
        if ($result['city']     !== '') { $this->city     = $result['city'];     }
        if ($result['province'] !== '') { $this->province = $result['province']; }

        $this->isResolvingAddress = false;
    }

    // ─────────────────────────────────────────────────────
    //  Asset uploads
    // ─────────────────────────────────────────────────────

    public function updatedLogo(): void
    {
        $this->storeAsset(
            uploadedFile: $this->logo,
            pathProperty: 'logo_path',
            folder: 'tenant-logos',
            mimeRules: ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            errorKey: 'logo',
            successMessage: 'Logo uploaded.',
            context: 'tenant-logo',
        );
        $this->logo = null;
    }

    public function removeLogo(): void
    {
        $this->removeStoredAsset('logo_path', 'Logo removed.');
    }

    public function updatedCoverPhoto(): void
    {
        $this->storeAsset(
            uploadedFile: $this->cover_photo,
            pathProperty: 'cover_photo_path',
            folder: 'tenant-covers',
            mimeRules: ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            errorKey: 'cover_photo',
            successMessage: 'Cover photo uploaded.',
            context: 'tenant-cover',
        );
        $this->cover_photo = null;
    }

    public function removeCoverPhoto(): void
    {
        $this->removeStoredAsset('cover_photo_path', 'Cover photo removed.');
    }

    protected function storeAsset(
        $uploadedFile,
        string $pathProperty,
        string $folder,
        array $mimeRules,
        string $errorKey,
        string $successMessage,
        string $context,
    ): void {
        if (!$uploadedFile) {
            return;
        }

        try {
            $this->validate([$errorKey => $mimeRules]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: $first ?: 'Invalid file.', type: 'error');
            return;
        }

        try {
            $newPath = $this->storeImage($uploadedFile, $folder, 'public', $context);

            if (! $newPath) {
                throw new \RuntimeException('Failed to store the uploaded file.');
            }

            $this->{$pathProperty} = $newPath;

            $this->dispatch('toast', message: $successMessage, type: 'success');
        } catch (\Throwable $e) {
            Log::error('Business profile asset upload failed', [
                'tenant_id' => $this->tenant?->id,
                'folder'    => $folder,
                'context'   => $context,
                'error'     => $e->getMessage(),
            ]);
            $this->dispatch('toast', message: 'Upload failed. Please try again.', type: 'error');
        }
    }

    protected function removeStoredAsset(string $pathProperty, string $successMessage): void
    {
        $this->{$pathProperty} = null;
        $this->dispatch('toast', message: $successMessage, type: 'info');
    }

    // ─────────────────────────────────────────────────────
    //  Map interactions
    // ─────────────────────────────────────────────────────

    public function setLocationMode(string $mode): void
    {
        if (!in_array($mode, ['main', 'nearby'], true)) {
            return;
        }

        $this->locationMode = $mode;

        if ($mode === 'main') {
            $this->selectedMarkerIndex = null;
        }

        $this->mapVersion++;
    }

    public function addMarker(): void
    {
        $this->addMarkerAt($this->latitude, $this->longitude);
    }

    public function removeMarker(int $index): void
    {
        if (!isset($this->markers[$index])) {
            return;
        }

        unset($this->markers[$index]);
        $this->markers = array_values($this->markers);

        if ($this->selectedMarkerIndex === $index) {
            $this->selectedMarkerIndex = null;
        } elseif ($this->selectedMarkerIndex !== null && $this->selectedMarkerIndex > $index) {
            $this->selectedMarkerIndex--;
        }

        $this->mapVersion++;
        $this->dispatch('toast', message: 'Nearby place removed.', type: 'info');
    }

    #[On('map:click')]
    public function onMapClick($lat, $lng): void
    {
        if ($this->locationMode === 'main') {
            $this->latitude  = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView   = [
                'lat'  => $this->latitude,
                'lng'  => $this->longitude,
                'zoom' => $this->mapView['zoom'],
            ];
            $this->mapVersion++;

            // Auto-fill the four address fields from the tapped coordinates.
            $this->resolveAddress($this->latitude, $this->longitude);
        } else {
            $this->addMarkerAt($lat, $lng);
        }
    }

    #[On('map:marker-drag-end')]
    public function onMarkerDragEnd($id, $lat, $lng): void
    {
        if ($id === 'main-marker' && $this->locationMode === 'main') {
            $this->latitude  = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView   = [
                'lat'  => $this->latitude,
                'lng'  => $this->longitude,
                'zoom' => $this->mapView['zoom'],
            ];
            $this->mapVersion++;

            $this->resolveAddress($this->latitude, $this->longitude);
            return;
        }

        if (str_starts_with((string) $id, 'sub-marker-') && $this->locationMode === 'nearby') {
            $index = (int) substr((string) $id, strlen('sub-marker-'));
            if (isset($this->markers[$index])) {
                $this->markers[$index]['lat'] = round((float) $lat, 6);
                $this->markers[$index]['lng'] = round((float) $lng, 6);
                $this->mapVersion++;
            }
        }
    }

    #[On('map:marker-clicked')]
    public function onMarkerClicked($id, $lat, $lng): void
    {
        if (str_starts_with((string) $id, 'sub-marker-')) {
            $index = (int) substr((string) $id, strlen('sub-marker-'));
            if (isset($this->markers[$index])) {
                $this->selectedMarkerIndex = $index;
                $this->locationMode        = 'nearby';
                $this->mapVersion++;
            }
        } elseif ($id === 'main-marker') {
            $this->locationMode        = 'main';
            $this->selectedMarkerIndex = null;
            $this->mapVersion++;
        }
    }

    #[On('map:center-changed')]
    public function onMapCenterChanged($lat, $lng): void
    {
        $this->mapView['lat'] = round((float) $lat, 6);
        $this->mapView['lng'] = round((float) $lng, 6);

        if ($this->locationMode === 'main') {
            $this->latitude  = $this->mapView['lat'];
            $this->longitude = $this->mapView['lng'];
        }
    }

    #[On('map:zoom-changed')]
    public function onMapZoomChanged($zoom): void
    {
        $this->mapView['zoom'] = (int) $zoom;
    }

    public function toggleSatellite(): void
    {
        $this->satellite = !$this->satellite;
        $this->mapVersion++;
    }

    public function useMyLocation(): void
    {
        $this->dispatch('request-geolocation');
    }

    #[On('geolocation-result')]
    public function onGeolocationResult($lat, $lng): void
    {
        if ($this->locationMode === 'main') {
            $this->latitude  = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView   = [
                'lat'  => $this->latitude,
                'lng'  => $this->longitude,
                'zoom' => 16,
            ];
            $this->mapVersion++;

            $this->resolveAddress($this->latitude, $this->longitude);
        } else {
            $this->addMarkerAt($lat, $lng);
        }
    }

    protected function addMarkerAt($lat, $lng): void
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
        $this->mapView = [
            'lat'  => round((float) $lat, 6),
            'lng'  => round((float) $lng, 6),
            'zoom' => 15,
        ];

        $this->dispatch('toast', message: 'Nearby place added. Please set its category.', type: 'info');
    }

    // ─────────────────────────────────────────────────────
    //  Save
    // ─────────────────────────────────────────────────────

    public function save(): void
    {
        $this->authorizeEditProfile();

        $this->validate([
            'name'              => ['required', 'string', 'min:3', 'max:255', Rule::unique('tenants', 'name')->ignore($this->tenant->id)],
            'slug'              => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tenants', 'slug')->ignore($this->tenant->id)],
            'type_of_tenant_id' => ['required', 'integer', Rule::exists('type_of_tenants', 'id')],
            'address'           => ['nullable', 'string', 'max:255'],
            'barangay'          => ['nullable', 'string', 'max:255'],
            'city'              => ['nullable', 'string', 'max:255'],
            'province'          => ['nullable', 'string', 'max:255'],
            'public_email'      => ['required', 'email:rfc', 'max:255', Rule::unique('tenants', 'email')->ignore($this->tenant->id)],
            'contact_number'    => ['nullable', 'string', 'regex:/^[0-9]{10,11}$/'],
            'latitude'          => ['required', 'numeric', 'min:-90', 'max:90'],
            'longitude'         => ['required', 'numeric', 'min:-180', 'max:180'],
            'markers'           => ['array', 'max:20'],
            'markers.*.name'    => ['required', 'string', 'max:100'],
            'markers.*.lat'     => ['required', 'numeric', 'min:-90', 'max:90'],
            'markers.*.lng'     => ['required', 'numeric', 'min:-180', 'max:180'],
            'markers.*.type'    => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:500'],
            'website'           => ['nullable', 'url', 'max:255'],
            'facebook'          => ['nullable', 'string', 'max:255'],
            'instagram'         => ['nullable', 'string', 'max:255'],
            'opening_time'      => ['nullable', 'date_format:H:i'],
            'closing_time'      => ['nullable', 'date_format:H:i'],
            'logo'              => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_photo'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active'         => ['boolean'],
        ], [
            'slug.regex'              => 'Slug may only contain lowercase letters, numbers, and hyphens.',
            'contact_number.regex'    => 'Contact number must be 10-11 digits only.',
            'markers.max'             => 'You can have at most 20 nearby places.',
            'markers.*.type.required' => 'Please select a category for each nearby place.',
            'logo.mimes'              => 'Logo must be a valid image (JPEG, PNG, or WebP).',
            'cover_photo.mimes'       => 'Cover photo must be a valid image (JPEG, PNG, or WebP).',
        ]);

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
            'social_links'  => [
                'facebook'  => $this->facebook,
                'instagram' => $this->instagram,
            ],
            'opening_hours' => [
                'opening' => $this->opening_time,
                'closing' => $this->closing_time,
                'is_24hr' => false,
            ],
            'barangay' => $this->barangay,
            'city'     => $this->city,
            'province' => $this->province,
        ];

        $oldLogo      = $this->tenant->logo;
        $oldCoverPath = $this->businessApplicationId
            ? BusinessApplication::whereKey($this->businessApplicationId)->value('cover_photo_path')
            : null;

        try {
            DB::transaction(function () use ($coordinates, $businessInfo): void {
                $tenant = Tenant::whereKey($this->tenant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $tenant->update([
                    'name'              => $this->name,
                    'slug'              => $this->slug,
                    'type_of_tenant_id' => $this->type_of_tenant_id,
                    'address'           => $this->address,
                    'email'             => $this->public_email,
                    'contact_number'    => $this->contact_number,
                    'logo'              => $this->logo_path,
                    'coordinates'       => $coordinates,
                    'is_active'         => $this->is_active,
                ]);

                TenantSetting::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'key' => 'business_info'],
                    ['value'     => $businessInfo]
                );

                if ($this->businessApplicationId) {
                    BusinessApplication::whereKey($this->businessApplicationId)->update([
                        'business_name'     => $this->name,
                        'type_of_tenant_id' => $this->type_of_tenant_id,
                        'logo_path'         => $this->logo_path,
                        'cover_photo_path'  => $this->cover_photo_path,
                        'contact_email'     => $this->public_email,
                        'contact_phone'     => $this->contact_number,
                        'address'           => $this->address,
                        'barangay'          => $this->barangay,
                        'city'              => $this->city,
                        'province'          => $this->province,
                        'coordinates'       => $coordinates,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Business profile save error', [
                'tenant_id' => $this->tenant->id,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            $this->dispatch('toast', message: 'An error occurred while saving. Please try again.', type: 'error');
            return;
        }

        if ($oldLogo && $oldLogo !== $this->logo_path && Storage::disk('public')->exists($oldLogo)) {
            Storage::disk('public')->delete($oldLogo);
        }
        if ($oldCoverPath && $oldCoverPath !== $this->cover_photo_path && Storage::disk('public')->exists($oldCoverPath)) {
            Storage::disk('public')->delete($oldCoverPath);
        }

        $this->tenant->refresh();

        session()->flash('message', 'Business profile updated successfully.');
        $this->dispatch('profile-saved');
        $this->dispatch('toast', message: 'Business profile saved.', type: 'success');
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
    class="p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto space-y-6"
>
    {{-- ═══ Toasts ═══ --}}
    <div class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none no-print">
        <template x-for="toast in toasts" :key="toast.id">
            <div
                class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 border"
                :class="{
                    'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300': toast.type === 'success',
                    'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300': toast.type === 'error',
                    'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                }"
            >
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    {{-- ═══ Save overlay ═══ --}}
    <div wire:loading.delay.longer wire:target="save"
         class="fixed inset-0 z-40 bg-white/60 dark:bg-gray-900/60 backdrop-blur-sm flex items-center justify-center pointer-events-none">
        <div class="flex items-center gap-3 bg-white dark:bg-gray-800 rounded-2xl shadow-xl px-6 py-4 pointer-events-auto">
            <svg class="animate-spin h-5 w-5 text-primary-600 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Saving your business profile…</span>
        </div>
    </div>

    {{-- ═══ Page header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Settings · Business</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Business Profile
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Update your public business information, photos, and location.
            </p>
        </div>
        <a href="{{ route('tenant.account.index') }}" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <span>My Account</span>
        </a>
    </div>

    {{-- ═══ Flash + error bag ═══ --}}
    @if(session()->has('message'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10 p-4 text-sm text-rose-700 dark:text-rose-300">
            <p class="font-semibold mb-1">Please fix {{ $errors->count() }} field{{ $errors->count() === 1 ? '' : 's' }} before saving:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li wire:key="err-{{ $loop->index }}">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">

        {{-- ═══════════════ BRAND ASSETS ═══════════════ --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Brand Assets
                </h2>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                {{-- ─── Cover photo (2/3 wide) ─── --}}
                <div class="lg:col-span-2 space-y-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Cover Photo <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                    </label>

                    <div
                        x-data="{
                            ...imageCropper({
                                wireProperty: 'cover_photo',
                                aspect: 3,
                                title: 'Crop cover photo',
                                description: 'Wide 3:1 landscape banner',
                                previewEvent: 'cover-photo-preview',
                            }),
                            ...avatarPreview(),
                            dragging: false,
                            serverUrl: '{{ $this->coverPreviewUrl() ?? '' }}',
                            get showPreview() { return !!this.previewUrl || !!this.serverUrl; },
                        }"
                        x-init="init()"
                        x-on:cover-photo-preview.window="setUrl($event.detail.url)"
                        x-on:cover-photo-cleared.window="clear()"
                    >
                        <div
                            x-on:dragover.prevent="dragging = true"
                            x-on:dragleave.prevent="dragging = false"
                            x-on:drop.prevent="
                                dragging = false;
                                const dt = new DataTransfer();
                                for (const f of $event.dataTransfer.files) dt.items.add(f);
                                $refs.input.files = dt.files;
                                $refs.input.dispatchEvent(new Event('change'));
                            "
                            :class="dragging
                                ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10'
                                : 'border-gray-300 dark:border-gray-600 hover:border-primary-500/50'"
                            class="relative w-full aspect-[3/1] border-2 border-dashed rounded-xl overflow-hidden transition-colors"
                        >
                            <input
                                x-ref="input"
                                id="cover-image-input"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                x-on:change="pick($event)"
                            >

                            <img
                                :src="previewUrl || serverUrl"
                                :class="showPreview ? 'block' : 'hidden'"
                                alt="Cover preview"
                                class="absolute inset-0 w-full h-full object-cover"
                                loading="lazy"
                                decoding="async"
                            >

                            <label
                                for="cover-image-input"
                                :class="showPreview ? 'hidden' : 'flex'"
                                class="absolute inset-0 flex-col items-center justify-center p-4 text-center cursor-pointer
                                       bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700 text-white"
                            >
                                <svg class="h-8 w-8 mb-2 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-xs font-semibold uppercase tracking-wider">No cover photo yet</p>
                                <p class="mt-1 text-[11px] opacity-80">Wide 3:1 · click or drop a photo</p>
                            </label>

                            <div
                                wire:loading.flex
                                wire:target="cover_photo"
                                class="absolute inset-0 bg-black/45 backdrop-blur-[2px] items-center justify-center pointer-events-none"
                                aria-hidden="true"
                            >
                                <svg class="animate-spin h-5 w-5 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </div>
                        </div>

                        <div :class="showPreview ? 'flex' : 'hidden'" class="flex-wrap items-center gap-2 pt-3">
                            <label for="cover-image-input"
                                   class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                                          border border-gray-300 dark:border-gray-600
                                          bg-white dark:bg-gray-800
                                          text-gray-700 dark:text-gray-200
                                          text-xs font-semibold cursor-pointer
                                          transition-all duration-200 active:scale-95
                                          hover:bg-gray-50 dark:hover:bg-gray-700
                                          focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-500/50">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span>Replace</span>
                            </label>

                            <button type="button"
                                    x-on:click="clear(); $wire.removeCoverPhoto()"
                                    class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                                           border border-rose-300 dark:border-rose-500/40
                                           bg-white dark:bg-gray-800
                                           text-rose-700 dark:text-rose-300
                                           text-xs font-semibold
                                           transition-all duration-200 active:scale-95
                                           hover:bg-rose-50 dark:hover:bg-rose-500/10
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <span>Remove</span>
                            </button>
                        </div>

                        @error('cover_photo') <span class="text-rose-500 dark:text-rose-400 text-xs block" role="alert">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                            PNG, JPG, or WebP · max 5 MB · auto-cropped to 3:1 · banner on your public listing
                        </p>
                    </div>
                </div>

                {{-- ─── Logo (1/3 wide) ─── --}}
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Business Logo <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                    </label>

                    <div
                        x-data="{
                            ...imageCropper({
                                wireProperty: 'logo',
                                aspect: 1,
                                title: 'Crop logo',
                                description: 'Square crop works best',
                                previewEvent: 'logo-preview',
                            }),
                            ...avatarPreview(),
                            dragging: false,
                            serverUrl: '{{ $this->logoPreviewUrl() ?? '' }}',
                            get showPreview() { return !!this.previewUrl || !!this.serverUrl; },
                        }"
                        x-init="init()"
                        x-on:logo-preview.window="setUrl($event.detail.url)"
                        x-on:logo-cleared.window="clear()"
                    >
                        <div
                            x-on:dragover.prevent="dragging = true"
                            x-on:dragleave.prevent="dragging = false"
                            x-on:drop.prevent="
                                dragging = false;
                                const dt = new DataTransfer();
                                for (const f of $event.dataTransfer.files) dt.items.add(f);
                                $refs.input.files = dt.files;
                                $refs.input.dispatchEvent(new Event('change'));
                            "
                            :class="dragging
                                ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10'
                                : 'border-gray-300 dark:border-gray-600 hover:border-primary-500/50'"
                            class="relative w-32 h-32 aspect-square border-2 border-dashed rounded-xl overflow-hidden transition-colors mx-auto"
                        >
                            <input
                                x-ref="input"
                                id="logo-image-input"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                x-on:change="pick($event)"
                            >

                            <img
                                :src="previewUrl || serverUrl"
                                :class="showPreview ? 'block' : 'hidden'"
                                alt="Logo preview"
                                class="absolute inset-0 w-full h-full object-cover"
                                loading="lazy"
                                decoding="async"
                            >

                            <label
                                for="logo-image-input"
                                :class="showPreview ? 'hidden' : 'flex'"
                                class="absolute inset-0 flex-col items-center justify-center p-2 text-center cursor-pointer"
                            >
                                <svg class="h-7 w-7 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="mt-1 text-[10px] font-medium text-gray-700 dark:text-gray-300 leading-tight">
                                    Click or drop
                                </p>
                            </label>

                            <div
                                wire:loading.flex
                                wire:target="logo"
                                class="absolute inset-0 bg-black/45 backdrop-blur-[2px] items-center justify-center pointer-events-none"
                                aria-hidden="true"
                            >
                                <svg class="animate-spin h-5 w-5 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </div>
                        </div>

                        <div :class="showPreview ? 'flex' : 'hidden'" class="flex-wrap items-center justify-center gap-2 pt-3">
                            <label for="logo-image-input"
                                   class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg
                                          border border-gray-300 dark:border-gray-600
                                          bg-white dark:bg-gray-800
                                          text-gray-700 dark:text-gray-200
                                          text-[11px] font-semibold cursor-pointer
                                          transition-all duration-200 active:scale-95
                                          hover:bg-gray-50 dark:hover:bg-gray-700
                                          focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-500/50">
                                <span>Replace</span>
                            </label>

                            <button type="button"
                                    x-on:click="clear(); $wire.removeLogo()"
                                    class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg
                                           border border-rose-300 dark:border-rose-500/40
                                           bg-white dark:bg-gray-800
                                           text-rose-700 dark:text-rose-300
                                           text-[11px] font-semibold
                                           transition-all duration-200 active:scale-95
                                           hover:bg-rose-50 dark:hover:bg-rose-500/10
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                <span>Remove</span>
                            </button>
                        </div>

                        @error('logo') <span class="text-rose-500 dark:text-rose-400 text-xs block text-center" role="alert">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 text-center">
                            Square · compressed to ≤512 KB
                        </p>
                    </div>
                </div>

            </div>
        </div>

        {{-- ═══════════════ BUSINESS INFORMATION ═══════════════ --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Business Information
                </h2>
            </div>

            {{-- Name + Slug --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-business-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Business Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="field-business-name" wire:model.live.debounce.300ms="name" class="input" maxlength="255">
                    @error('name') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">How customers see your business on the public site.</p>
                </div>
                <div>
                    <label for="field-business-slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        URL Slug
                    </label>
                    <div class="flex rounded-xl overflow-hidden border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-900">
                        <span class="py-2.5 px-3 bg-gray-200 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 border-r border-gray-300 dark:border-gray-600">spot/</span>
                        <input type="text" id="field-business-slug" wire:model="slug" readonly
                               class="flex-1 bg-transparent border-none py-2.5 px-4 text-sm text-gray-500 dark:text-gray-400 cursor-default outline-none font-mono">
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Auto-generated from the name. Collisions auto-suffixed.</p>
                    @error('slug') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Type + Email + Contact --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="field-business-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Business Type <span class="text-rose-500">*</span>
                    </label>
                    <select id="field-business-type" wire:model="type_of_tenant_id" class="select">
                        <option value="">— Select type —</option>
                        @foreach($this->tenantTypes as $type)
                            <option value="{{ $type['id'] }}" wire:key="type-{{ $type['id'] }}">{{ $type['type'] }}</option>
                        @endforeach
                    </select>
                    @error('type_of_tenant_id') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="field-business-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Public Email <span class="text-rose-500">*</span>
                    </label>
                    <input type="email" id="field-business-email" wire:model="public_email" class="input" maxlength="255">
                    @error('public_email') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="field-contact" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Contact Number
                    </label>
                    <input type="tel" id="field-contact"
                           inputmode="numeric" pattern="[0-9]*" maxlength="11"
                           wire:model="contact_number"
                           x-on:input="event.target.value = event.target.value.replace(/[^0-9]/g, '').slice(0, 11)"
                           class="input" placeholder="09xxxxxxxxx">
                    @error('contact_number') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">10–11 digits, numbers only.</p>
                </div>
            </div>

            {{-- Address block — auto-filled from the map tap below --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4 space-y-4">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Address</span>
                    <span class="text-[10px] text-gray-400 dark:text-gray-500 font-normal normal-case tracking-normal">— auto-fills when you move the pin</span>

                    <div wire:loading wire:target="resolveAddress" class="ml-auto inline-flex items-center gap-1.5 text-[10px] font-medium text-primary-600 dark:text-primary-400">
                        <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Looking up address…
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-4">
                        <label for="field-address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Street Address
                        </label>
                        <input type="text" id="field-address" wire:model="address" class="input" maxlength="255">
                    </div>
                    <div class="md:col-span-2">
                        <label for="field-barangay" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Barangay
                        </label>
                        <input type="text" id="field-barangay" wire:model="barangay" list="barangays-list" class="input" maxlength="255">
                        <datalist id="barangays-list">
                            @foreach($this->barangays as $b)
                                <option value="{{ $b }}" wire:key="brgy-{{ Str::slug($b) }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div>
                        <label for="field-city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            City / Municipality
                        </label>
                        <input type="text" id="field-city" wire:model="city" class="input" maxlength="255">
                    </div>
                    <div>
                        <label for="field-province" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Province
                        </label>
                        <input type="text" id="field-province" wire:model="province" class="input" maxlength="255">
                    </div>
                </div>
            </div>

            {{-- Description --}}
            <div>
                <div class="flex justify-between items-baseline mb-1">
                    <label for="field-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Short Description
                    </label>
                    <span class="text-[11px] text-gray-400 dark:text-gray-500 tabular-nums">{{ strlen($description) }}/500</span>
                </div>
                <textarea id="field-description" wire:model="description" rows="3" class="textarea" maxlength="500"
                          placeholder="A short introduction to your business — what makes it special?"></textarea>
                @error('description') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
            </div>

            {{-- Web + social --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="field-website" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Website <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                    </label>
                    <input type="url" id="field-website" wire:model="website" class="input" placeholder="https://" maxlength="255">
                    @error('website') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="field-facebook" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Facebook
                    </label>
                    <input type="text" id="field-facebook" wire:model="facebook" class="input" maxlength="255" placeholder="page or URL">
                </div>
                <div>
                    <label for="field-instagram" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Instagram
                    </label>
                    <input type="text" id="field-instagram" wire:model="instagram" class="input" maxlength="255" placeholder="@handle or URL">
                </div>
            </div>

            {{-- Hours --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-opening" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Opening Time
                    </label>
                    <input type="time" id="field-opening" wire:model="opening_time" class="input">
                    @error('opening_time') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="field-closing" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Closing Time
                    </label>
                    <input type="time" id="field-closing" wire:model="closing_time" class="input">
                    @error('closing_time') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Active toggle --}}
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700/60">
                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <span class="relative inline-flex items-center shrink-0">
                        <input type="checkbox" wire:model="is_active" class="sr-only peer">
                        <span class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full
                                     peer peer-checked:bg-primary-600
                                     after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                     after:bg-white after:rounded-full after:h-5 after:w-5
                                     after:transition-all peer-checked:after:translate-x-full"></span>
                    </span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Active
                        <span class="text-gray-400 dark:text-gray-500">— visible to customers on the public site</span>
                    </span>
                </label>
            </div>
        </div>

        {{-- ═══════════════ LOCATION & NEARBY PLACES ═══════════════ --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Location &amp; Nearby Places
                </h2>
            </div>

            {{-- Mode toggle --}}
            <div class="flex flex-wrap gap-2 items-center">
                <button type="button"
                        wire:click="setLocationMode('main')"
                        class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                               transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $locationMode === 'main'
                                  ? 'bg-primary-600 border-primary-600 text-white shadow-sm shadow-primary-600/20'
                                  : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>Main Location</span>
                </button>
                <button type="button"
                        wire:click="setLocationMode('nearby')"
                        class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                               transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $locationMode === 'nearby'
                                  ? 'bg-primary-600 border-primary-600 text-white shadow-sm shadow-primary-600/20'
                                  : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    <span>Nearby Places</span>
                </button>
                <span class="text-xs text-gray-400 dark:text-gray-500">
                    @if($locationMode === 'main')
                        Click the map to move your main location — the address auto-fills.
                    @else
                        Click the map to add a new nearby place.
                    @endif
                </span>
            </div>

            {{-- Coords + actions --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-latitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Latitude <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" id="field-latitude" step="any" min="-90" max="90"
                           wire:model.live.debounce.500ms="latitude" onfocus="this.select()"
                           class="input font-mono"
                           @if($locationMode !== 'main') readonly @endif>
                    @error('latitude') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="field-longitude" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Longitude <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" id="field-longitude" step="any" min="-180" max="180"
                           wire:model.live.debounce.500ms="longitude" onfocus="this.select()"
                           class="input font-mono"
                           @if($locationMode !== 'main') readonly @endif>
                    @error('longitude') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="useMyLocation"
                        class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                               border border-gray-300 dark:border-gray-600
                               bg-white dark:bg-gray-800
                               text-gray-700 dark:text-gray-200
                               text-xs font-semibold
                               transition-all duration-200 active:scale-95
                               hover:bg-gray-50 dark:hover:bg-gray-700
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>Use my location</span>
                </button>
                <button type="button" wire:click="toggleSatellite"
                        class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                               border border-gray-300 dark:border-gray-600
                               bg-white dark:bg-gray-800
                               text-gray-700 dark:text-gray-200
                               text-xs font-semibold
                               transition-all duration-200 active:scale-95
                               hover:bg-gray-50 dark:hover:bg-gray-700
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                    </svg>
                    <span>{{ $satellite ? 'Street View' : 'Satellite' }}</span>
                </button>
            </div>

            {{-- Map --}}
            <div class="rounded-2xl overflow-hidden relative border border-gray-200/80 dark:border-gray-700/80"
                 style="height: 400px;">
                <div wire:key="business-profile-map-{{ $mapVersion }}">
                    <x-map
                        id="business-profile-map"
                        :center="[(float) $mapView['lng'], (float) $mapView['lat']]"
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
                                $type     = $marker['type'] ?? '';
                                $category = collect($markerCategories)->firstWhere('key', $type);
                                $color    = $category['color'] ?? '#94a3b8';
                                $iconSvg  = $category['icon_svg'] ?? null;
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
                                    <div class="relative flex h-10 w-10 items-center justify-center transform-gpu will-change-transform" style="cursor: pointer;">
                                        <svg class="absolute inset-0 size-10 drop-shadow-md fill-white dark:fill-gray-900 stroke-slate-400 dark:stroke-slate-600 stroke-1" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                        </svg>
                                        @if($iconSvg)
                                            <div class="absolute mb-1 size-[18px] text-gray-800 dark:text-white">
                                                <x-safe-svg :svg="$iconSvg" class="size-full stroke-current fill-none" />
                                            </div>
                                        @else
                                            <span class="absolute mb-1 text-[10px] font-bold text-gray-800 dark:text-white">{{ strtoupper(substr($type ?: '?', 0, 1)) }}</span>
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
                            wire:key="main-marker"
                            :lat="$latitude"
                            :lng="$longitude"
                            color="#ef4444"
                            id="main-marker"
                            :draggable="$locationMode === 'main'"
                        >
                            <x-marker-content>
                                <div class="relative flex items-center justify-center transform-gpu will-change-transform">
                                    <svg class="h-10 w-10 drop-shadow-lg" viewBox="0 0 24 24" fill="#ef4444" stroke="white" stroke-width="1.5" aria-hidden="true">
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

            {{-- Nearby places list --}}
            @if($locationMode === 'nearby')
                <div>
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Nearby Places <span class="text-gray-400 dark:text-gray-500 font-normal">({{ count($markers) }}/20)</span>
                        </span>
                        <button type="button" wire:click="addMarker"
                                class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                                       border border-gray-300 dark:border-gray-600
                                       bg-white dark:bg-gray-800
                                       text-gray-700 dark:text-gray-200
                                       text-xs font-semibold
                                       transition-all duration-200 active:scale-95
                                       hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Add nearby place</span>
                        </button>
                    </div>

                    @if(count($markers) > 0)
                        <div class="flex flex-wrap items-center gap-3 mb-3 text-[11px] text-gray-500 dark:text-gray-400">
                            @foreach($markerCategories as $cat)
                                <span class="inline-flex items-center gap-1" wire:key="cat-legend-{{ $cat['key'] }}">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $cat['color'] }}"></span>
                                    {{ $cat['label'] }}
                                </span>
                            @endforeach
                        </div>

                        <div class="space-y-2">
                            @foreach($markers as $index => $marker)
                                <div wire:key="marker-row-{{ $marker['uid'] }}"
                                     class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/30 rounded-xl border transition
                                            {{ $selectedMarkerIndex === $index ? 'ring-2 ring-primary-600/40 border-primary-600/30' : 'border-gray-200 dark:border-gray-700' }}">
                                    <input type="text" wire:model="markers.{{ $index }}.name" placeholder="Place name" class="input !py-2 flex-1 min-w-[140px]">
                                    <input type="number" step="any" min="-90" max="90"
                                           wire:model="markers.{{ $index }}.lat" placeholder="Lat"
                                           class="input !py-2 !w-28 font-mono">
                                    <input type="number" step="any" min="-180" max="180"
                                           wire:model="markers.{{ $index }}.lng" placeholder="Lng"
                                           class="input !py-2 !w-28 font-mono">
                                    <select wire:model.live="markers.{{ $index }}.type" class="select !py-2 !w-40 {{ empty($marker['type']) ? 'border-rose-300 dark:border-rose-500' : '' }}">
                                        <option value="">Select category *</option>
                                        @foreach($markerCategories as $cat)
                                            <option value="{{ $cat['key'] }}" wire:key="opt-{{ $index }}-{{ $cat['key'] }}">{{ $cat['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="removeMarker({{ $index }})"
                                            class="inline-flex items-center justify-center h-9 w-9 rounded-lg
                                                   text-rose-500 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300
                                                   hover:bg-rose-50 dark:hover:bg-rose-500/20
                                                   transition-all duration-200 active:scale-95
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                                            aria-label="Remove nearby place">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                                @error("markers.{$index}.name") <p class="text-rose-500 dark:text-rose-400 text-[10px] mt-1" role="alert">{{ $message }}</p> @enderror
                                @error("markers.{$index}.type") <p class="text-rose-500 dark:text-rose-400 text-[10px] mt-1" role="alert">{{ $message }}</p> @enderror
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            No nearby places yet. Click the map or use the button above to add one.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        {{-- ═══════════════ DANGER ZONE ═══════════════ --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-rose-200/80 dark:border-rose-500/30 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-rose-500"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">
                    Danger Zone
                </h2>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                        Delete this business account
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed">
                        Permanently remove the business and all of its data. A superadmin will review the request first. This cannot be undone.
                    </p>
                </div>

                <a href="{{ route('account.delete') }}" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl shrink-0
                          border border-rose-300 dark:border-rose-500/40
                          bg-white dark:bg-gray-800
                          text-rose-700 dark:text-rose-300
                          text-sm font-semibold
                          transition-all duration-200 active:scale-95
                          hover:bg-rose-50 dark:hover:bg-rose-500/10
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span>Delete account</span>
                </a>
            </div>
        </div>

        {{-- ═══════════════ ACTIONS ═══════════════ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save">Save Changes</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
        </div>
    </form>

    {{-- Image crop modal — singleton for this page (Rule 87) --}}
    <x-image-crop-modal />
</div>

@script
<script>
    if (! window.__businessProfileGeoRegistered) {
        window.__businessProfileGeoRegistered = true;

        window.__businessProfileNotify = function (message, type = 'info') {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        };

        window.addEventListener('request-geolocation', () => {
            if (! navigator.geolocation) {
                window.__businessProfileNotify('Geolocation is not supported by your browser.', 'error');
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
                    window.__businessProfileNotify('Unable to retrieve your location. Check browser permissions.', 'error');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
            );
        });
    }
</script>
@endscript