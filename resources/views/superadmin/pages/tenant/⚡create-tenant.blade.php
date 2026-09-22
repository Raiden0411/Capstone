{{-- resources/views/superadmin/pages/tenant/⚡create-tenant.blade.php --}}
<?php

use App\Mail\TenantAdminWelcome;
use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\PropertyType;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TypeOfTenant;
use App\Models\User;
use App\Services\BusinessApplicationService;
use App\Services\ReverseGeocodeService;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('superadmin.layouts.app')]
#[Title('Add Tenant')]
class extends Component {
    use WithFileUploads;
    use HandlesImageUploads;

    public int $step = 1;
    public int $furthestStep = 1;
    public bool $showSuccessModal = false;
    public bool $isSubmitting = false;
    public bool $welcomeEmailSent = false;

    public const MAX_MARKERS = 20;
    public const DEFAULT_LAT = 10.900977766937142;
    public const DEFAULT_LNG = 123.07055771888716;
    public const TOTAL_STEPS = 4;

    protected const STEP_LABELS = [
        1 => 'Business Details',
        2 => 'Map Location',
        3 => 'Sub-Establishments',
        4 => 'Admin Account',
    ];

    public const OWNER_ID_PATTERNS = [
        'national_id'     => '/^[0-9]{4}[-\s]?[0-9]{4}[-\s]?[0-9]{4}[-\s]?[0-9]{4}$/',
        'passport'        => '/^[A-Z]{1,2}[0-9]{6,7}[A-Z]?$/',
        'drivers_license' => '/^[A-Z][0-9]{2}[-\s]?[0-9]{2}[-\s]?[0-9]{6}$/',
        'umid'            => '/^[0-9]{4}[-\s]?[0-9]{7}[-\s]?[0-9]{1}$/',
        'sss_id'          => '/^[0-9]{2}[-\s]?[0-9]{7}[-\s]?[0-9]{1}$/',
        'prc_id'          => '/^[0-9]{7}$/',
        'postal_id'       => '/^[A-Z0-9][A-Z0-9\-\s]{5,19}$/',
        'pagibig_id'      => '/^[0-9]{4}[-\s]?[0-9]{4}[-\s]?[0-9]{4}$/',
    ];

    public const OWNER_ID_HINTS = [
        'national_id'     => '16 digits printed on your PhilSys ID or ePhilID — e.g. 1234-5678-9012-3456.',
        'passport'        => '9 characters starting with 1–2 letters (e.g. P1234567A or EC1234567).',
        'drivers_license' => 'Format: N01-23-456789 — letter, 2 digits, dash, 2 digits, dash, 6 digits.',
        'umid'            => '12 digits in the format 1234-5678901-2.',
        'sss_id'          => '10 digits in the format 12-3456789-0.',
        'prc_id'          => '7 digits as printed on your PRC ID.',
        'postal_id'       => 'Alphanumeric as printed on your Philippine Postal ID.',
        'pagibig_id'      => '12 digits in the format 1234-5678-9012.',
    ];

    public const OWNER_ID_PLACEHOLDERS = [
        'national_id'     => '1234-5678-9012-3456',
        'passport'        => 'P1234567A',
        'drivers_license' => 'N01-23-456789',
        'umid'            => '1234-5678901-2',
        'sss_id'          => '12-3456789-0',
        'prc_id'          => '0123456',
        'postal_id'       => 'PCA-1234567',
        'pagibig_id'      => '1234-5678-9012',
    ];

    public const OWNER_ID_MAX_LENGTHS = [
        'national_id'     => 19,
        'passport'        => 9,
        'drivers_license' => 13,
        'umid'            => 14,
        'sss_id'          => 12,
        'prc_id'          => 7,
        'postal_id'       => 20,
        'pagibig_id'      => 14,
    ];

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
    public ?string $logo_path = null;

    public $cover_photo;
    public ?string $cover_photo_path = null;

    public string $business_type = '';
    public string $business_registration_number = '';
    public string $tin_number = '';
    public string $owner_id_type = '';
    public string $owner_id_number = '';
    public string $owner_birthdate = '';

    /** @var array<string, mixed> */
    public array $document_uploads = [];

    public bool $is_active = true;
    public bool $is_recommended = false;

    public float $latitude = self::DEFAULT_LAT;
    public float $longitude = self::DEFAULT_LNG;
    public bool $locationConfirmed = false;
    public bool $satellite = false;

    public bool $hasSubBranches = false;
    public array $markers = [];
    public ?int $selectedMarkerIndex = null;
    public string $markerSearch = '';

    public int $mapVersion = 0;
    public array $mapView = [
        'lat' => self::DEFAULT_LAT,
        'lng' => self::DEFAULT_LNG,
        'zoom' => 13,
    ];

    public array $markerCategories = [];

    public string $admin_name = '';
    public string $admin_email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public $admin_avatar;
    public ?string $admin_avatar_path = null;

    public bool $showAddCategoryModal = false;
    public string $newCategoryKey = '';
    public string $newCategoryLabel = '';
    public string $newCategoryColor = '#3b82f6';
    public $newCategoryIcon;

    public bool $showNewTenantTypeModal = false;
    public string $newTenantTypeName = '';
    public string $newTenantTypeDescription = '';

    public ?string $createdAdminEmail = null;
    public ?string $createdAdminPassword = null;
    public ?string $createdTenantName = null;
    public ?int $createdTenantId = null;

    public function mount(): void
    {
        $this->markerCategories = SiteSetting::getValue('marker_categories', []);
    }

    // ─────────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────────

    #[Computed]
    public function tenantTypes()
    {
        return TypeOfTenant::query()->select('id', 'type')->orderBy('type')->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function businessTypeLabels(): array
    {
        return BusinessApplication::BUSINESS_TYPE_LABELS;
    }

    /** @return array<string, string> */
    #[Computed]
    public function ownerIdTypes(): array
    {
        return BusinessApplication::OWNER_ID_TYPES;
    }

    /** @return array<string, string> */
    #[Computed]
    public function documentLabels(): array
    {
        return BusinessApplication::DOCUMENT_LABELS;
    }

    /** @return array<int, string> */
    #[Computed]
    public function requiredDocuments(): array
    {
        return BusinessApplication::REQUIRED_DOCUMENTS;
    }

    /** @return array<int, string> */
    #[Computed]
    public function optionalDocuments(): array
    {
        return BusinessApplication::OPTIONAL_DOCUMENTS;
    }

    /** @return array<int, string> */
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

    #[Computed]
    public function registrationNumberPlaceholder(): string
    {
        if ($this->business_type === '') {
            return 'e.g. DTI-2024-123456';
        }

        return BusinessApplication::REGISTRATION_NUMBER_PLACEHOLDERS[$this->business_type]
            ?? 'Enter your registration number';
    }

    #[Computed]
    public function registrationNumberHint(): string
    {
        if ($this->business_type === '') {
            return 'Found on your government registration certificate.';
        }

        return BusinessApplication::REGISTRATION_NUMBER_HINTS[$this->business_type]
            ?? 'Found on your government registration certificate.';
    }

    #[Computed]
    public function selectedOwnerIdLabel(): ?string
    {
        if ($this->owner_id_type === '') {
            return null;
        }

        return BusinessApplication::OWNER_ID_TYPES[$this->owner_id_type] ?? null;
    }

    #[Computed]
    public function ownerIdHint(): string
    {
        return self::OWNER_ID_HINTS[$this->owner_id_type]
            ?? 'Enter the number exactly as printed on the card.';
    }

    #[Computed]
    public function ownerIdPlaceholder(): string
    {
        return self::OWNER_ID_PLACEHOLDERS[$this->owner_id_type]
            ?? 'Number as printed on the ID';
    }

    #[Computed]
    public function ownerIdMaxLength(): int
    {
        return self::OWNER_ID_MAX_LENGTHS[$this->owner_id_type] ?? 100;
    }

    #[Computed]
    public function uploadedRequiredCount(): int
    {
        return collect($this->requiredDocuments)
            ->filter(fn (string $t) => isset($this->document_uploads[$t]))
            ->count();
    }

    /** @return array<int, string> */
    #[Computed]
    public function missingRequiredDocuments(): array
    {
        $labels = BusinessApplication::DOCUMENT_LABELS;

        return array_values(array_map(
            fn (string $type) => $labels[$type] ?? $type,
            array_diff(
                $this->requiredDocuments,
                array_keys($this->document_uploads),
            ),
        ));
    }

    public function logoPreviewUrl(): ?string
    {
        return $this->logo_path ? asset('storage/' . $this->logo_path) : null;
    }

    public function coverPreviewUrl(): ?string
    {
        return $this->cover_photo_path ? asset('storage/' . $this->cover_photo_path) : null;
    }

    public function avatarPreviewUrl(): ?string
    {
        return $this->admin_avatar_path ? asset('storage/' . $this->admin_avatar_path) : null;
    }

    // ─────────────────────────────────────────────────────────────
    //  Map — fast + slow paths (called by the Alpine locationPicker)
    // ─────────────────────────────────────────────────────────────

    public function setMainLocation(float|string $lat, float|string $lng): void
    {
        if ($this->locationConfirmed) {
            return;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if (! is_finite($lat) || ! is_finite($lng)) {
            return;
        }
        if (abs($lat) > 90 || abs($lng) > 180) {
            return;
        }

        $this->latitude  = round($lat, 6);
        $this->longitude = round($lng, 6);
        $this->mapView   = [
            'lat'  => $this->latitude,
            'lng'  => $this->longitude,
            'zoom' => $this->mapView['zoom'],
        ];
    }

    public function resolveAddress(float|string $lat, float|string $lng): void
    {
        $lat = (float) $lat;
        $lng = (float) $lng;

        if (! is_finite($lat) || ! is_finite($lng)) {
            return;
        }
        if (abs($lat) > 90 || abs($lng) > 180) {
            return;
        }

        try {
            $result = app(ReverseGeocodeService::class)->reverse($lat, $lng);
        } catch (\Throwable $e) {
            Log::warning('Reverse geocode failed (tenant wizard)', [
                'lat'   => $lat,
                'lng'   => $lng,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        if ($result === null) {
            return;
        }

        if ($result['address'] !== '')  { $this->address  = $result['address'];  }
        if ($result['barangay'] !== '') { $this->barangay = $result['barangay']; }
        if ($result['city'] !== '')     { $this->city     = $result['city'];     }
        if ($result['province'] !== '') { $this->province = $result['province']; }
    }

    public function useMyLocation(): void
    {
        if ($this->locationConfirmed) {
            return;
        }

        $this->dispatch('request-geolocation');
    }

    // ─────────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────────

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
            'public_email' => ['required', 'email:rfc', 'max:255', Rule::unique('tenants', 'email')],
            'contact_number' => ['nullable', 'string', 'regex:/^[0-9]{10,11}$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => array_filter([
                'nullable',
                'date_format:H:i',
                $this->open_24_hours ? null : 'after:opening_time',
            ]),
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active' => ['boolean'],
            'is_recommended' => ['boolean'],

            'business_type' => ['required', Rule::in(BusinessApplication::BUSINESS_TYPES)],
            'business_registration_number' => ['required', 'string', 'min:4', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/'],
            'tin_number' => ['required', 'string', 'regex:/^\d{3}[-\s]?\d{3}[-\s]?\d{3}(?:[-\s]?\d{3})?$/'],

            'owner_id_type' => ['required', Rule::in(array_keys(BusinessApplication::OWNER_ID_TYPES))],
            'owner_id_number' => [
                'required', 'string', 'min:4', 'max:100',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $type = $this->owner_id_type;
                    if ($type === '' || ! isset(self::OWNER_ID_PATTERNS[$type])) {
                        return;
                    }
                    if (! preg_match(self::OWNER_ID_PATTERNS[$type], (string) $value)) {
                        $label = BusinessApplication::OWNER_ID_TYPES[$type] ?? 'ID';
                        $fail("That doesn't look like a valid {$label} number — check the format hint below the field.");
                    }
                },
            ],
            'owner_birthdate' => ['nullable', 'date', 'before:18 years ago', 'after:1900-01-01'],

            'document_uploads.dti_sec_cda' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'document_uploads.bir_2303' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'document_uploads.mayors_permit' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'document_uploads.owner_id' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],

            'latitude' => ['required', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['required', 'numeric', 'min:-180', 'max:180'],

            'markers' => ['array', 'max:' . self::MAX_MARKERS],
            'markers.*.name' => ['required', 'string', 'max:100'],
            'markers.*.lat' => ['required', 'numeric', 'min:-90', 'max:90'],
            'markers.*.lng' => ['required', 'numeric', 'min:-180', 'max:180'],
            'markers.*.type' => ['required', 'string', 'max:255'],

            'admin_name' => ['required', 'string', 'min:3', 'max:255'],
            'admin_email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email'), 'different:public_email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'admin_avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    protected function stepFields(int $step): array
    {
        return match ($step) {
            1 => [
                'name', 'slug', 'type_of_tenant_id', 'public_email', 'contact_number', 'description',
                'opening_time', 'closing_time', 'logo', 'cover_photo', 'is_active', 'is_recommended',
                'business_type', 'business_registration_number', 'tin_number',
                'owner_id_type', 'owner_id_number', 'owner_birthdate',
                'document_uploads.dti_sec_cda',
                'document_uploads.bir_2303',
                'document_uploads.mayors_permit',
                'document_uploads.owner_id',
            ],
            2 => ['latitude', 'longitude', 'address', 'barangay', 'city', 'province'],
            3 => ['markers', 'markers.*.name', 'markers.*.lat', 'markers.*.lng', 'markers.*.type'],
            4 => ['admin_name', 'admin_email', 'password', 'admin_avatar'],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'public_email.unique'   => 'This public email is already registered to another business.',
            'public_email.email'    => 'Enter a valid public email address.',
            'admin_email.unique'    => 'This admin login email is already taken.',
            'admin_email.different' => 'Use a different email than the public business email.',
            'admin_email.email'     => 'Enter a valid admin email address.',
            'contact_number.regex'  => 'Contact number must be 10 or 11 digits, numbers only.',
            'password.confirmed'    => 'Password confirmation does not match.',
            'closing_time.after'    => 'Closing time must be later than the opening time.',
            'slug.regex'            => 'Slug may only contain lowercase letters, numbers and single hyphens.',
            'slug.unique'           => 'This slug is already taken — try another.',
            'markers.max'           => 'You can add up to ' . self::MAX_MARKERS . ' additional markers.',
            'markers.*.type.required' => 'Please select a category for this nearby place.',
            'logo.mimes'            => 'Logo must be a valid image file (JPEG, PNG, or WebP).',
            'logo.max'              => 'Logo must not exceed 5MB.',
            'cover_photo.mimes'     => 'Cover photo must be a valid image (JPEG, PNG, or WebP).',
            'cover_photo.max'       => 'Cover photo must not exceed 5MB.',
            'admin_avatar.mimes'    => 'Photo must be a valid image (JPEG, PNG, or WebP).',
            'admin_avatar.max'      => 'Photo must not exceed 5MB.',
            'business_registration_number.required' => 'Enter the registration number printed on the certificate.',
            'business_registration_number.regex'    => 'Registration numbers can only contain letters, digits, and dashes.',
            'tin_number.required'   => 'Enter the TIN shown on the BIR Form 2303.',
            'tin_number.regex'      => 'Enter a valid TIN: 123-456-789 or 123-456-789-000.',
            'owner_id_type.required'   => 'Select the type of government ID you uploaded.',
            'owner_id_type.in'         => 'That ID type is not accepted. Choose one from the list.',
            'owner_id_number.required' => 'Enter the ID number exactly as printed on the card.',
            'owner_id_number.min'      => 'That ID number looks too short. Please double-check it.',
            'owner_birthdate.date'     => 'Enter a valid date of birth.',
            'owner_birthdate.before'   => 'The owner must be at least 18 years old.',
            'owner_birthdate.after'    => 'Please enter a date after 1900.',
            'document_uploads.dti_sec_cda.required'   => 'Upload the DTI / SEC / CDA registration certificate.',
            'document_uploads.bir_2303.required'      => 'Upload BIR Form 2303.',
            'document_uploads.mayors_permit.required' => "Upload the Mayor's Permit.",
            'document_uploads.owner_id.required'      => "Upload the owner's government ID.",
            'document_uploads.*.mimes' => 'Documents must be PDF, JPG, PNG, or WEBP.',
            'document_uploads.*.max'   => 'Each document must be 10MB or smaller.',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'type_of_tenant_id' => 'business category',
            'public_email' => 'public email',
            'admin_email' => 'admin login email',
            'business_type' => 'registration type',
            'business_registration_number' => 'registration number',
            'tin_number' => 'TIN',
            'owner_id_type' => 'owner ID type',
            'owner_id_number' => 'owner ID number',
            'owner_birthdate' => 'owner date of birth',
            'document_uploads.dti_sec_cda' => 'DTI / SEC / CDA certificate',
            'document_uploads.bir_2303' => 'BIR Form 2303',
            'document_uploads.mayors_permit' => "Mayor's Permit",
            'document_uploads.owner_id' => 'owner ID',
            'newCategoryKey' => 'category key',
            'newCategoryLabel' => 'category label',
            'newCategoryColor' => 'category color',
            'newCategoryIcon' => 'category icon',
            'newTenantTypeName' => 'type name',
            'newTenantTypeDescription' => 'description',
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Field hooks
    // ─────────────────────────────────────────────────────────────

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
            'business_registration_number', 'tin_number', 'owner_id_number',
            'newTenantTypeName', 'newTenantTypeDescription',
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

    public function updatedOwnerIdType(): void
    {
        if ($this->owner_id_number !== '') {
            $this->owner_id_number = '';
        }
        $this->resetValidation('owner_id_number');
    }

    public function updatedOwnerIdNumber(): void
    {
        if ($this->owner_id_number === '') {
            return;
        }

        if (in_array($this->owner_id_type, ['passport', 'postal_id'], true)) {
            $this->owner_id_number = strtoupper($this->owner_id_number);
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
        $this->password = Str::password(16, letters: true, numbers: true, symbols: true);
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
        $this->dispatch('toast', message: 'Main location confirmed.', type: 'success');
    }

    public function clearDocumentUpload(string $documentType): void
    {
        if (isset($this->document_uploads[$documentType])) {
            unset($this->document_uploads[$documentType]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Asset uploads — routed through HandlesImageUploads,
    //  which calls ImageCompressionService with the context below.
    //    logo          → 'tenant-logo'  (512 KB / 1024×1024)
    //    cover_photo   → 'tenant-cover' (2 MB  / 2560×1440)
    //    admin_avatar  → 'avatars'      (512 KB / 800×800)
    // ─────────────────────────────────────────────────────────────

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

    public function updatedAdminAvatar(): void
    {
        $this->storeAsset(
            uploadedFile: $this->admin_avatar,
            pathProperty: 'admin_avatar_path',
            folder: 'tenant-avatars',
            mimeRules: ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            errorKey: 'admin_avatar',
            successMessage: 'Photo uploaded.',
            context: 'avatars',
        );
        $this->admin_avatar = null;
    }

    public function removeAdminAvatar(): void
    {
        $this->removeStoredAsset('admin_avatar_path', 'Photo removed.');
    }

    protected function storeAsset(
        $uploadedFile,
        string $pathProperty,
        string $folder,
        array $mimeRules,
        string $errorKey,
        string $successMessage,
        ?string $context = null,
    ): void {
        if (! $uploadedFile) {
            return;
        }

        try {
            $this->validate([$errorKey => $mimeRules]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: $first ?: 'Invalid file.', type: 'error');
            return;
        }

        $oldPath = $this->{$pathProperty};

        try {
            $newPath = $this->storeImage($uploadedFile, $folder, 'public', $context);

            if (! $newPath) {
                throw new \RuntimeException('Storage returned no path.');
            }

            $this->{$pathProperty} = $newPath;

            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $this->dispatch('toast', message: $successMessage, type: 'success');
        } catch (\Throwable $e) {
            Log::error('Tenant asset upload failed', [
                'folder' => $folder,
                'error'  => $e->getMessage(),
            ]);
            $this->dispatch('toast', message: 'Upload failed. Please try again.', type: 'error');
        }
    }

    protected function removeStoredAsset(string $pathProperty, string $successMessage): void
    {
        $old = $this->{$pathProperty};

        if ($old && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        $this->{$pathProperty} = null;
        $this->dispatch('toast', message: $successMessage, type: 'info');
    }

    // ─────────────────────────────────────────────────────────────
    //  Sub-markers (step 3) — unchanged from the previous version
    // ─────────────────────────────────────────────────────────────

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
            'lat'  => round((float) $lat, 6),
            'lng'  => round((float) $lng, 6),
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
                'lat'  => $this->markers[$index]['lat'],
                'lng'  => $this->markers[$index]['lng'],
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
        // Step 2's map forwards nothing now (the module owns it), so this
        // handler only ever fires from Step 3's map (sub-markers).
        if ($this->step === 3) {
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

    // ─────────────────────────────────────────────────────────────
    //  Step navigation + save (unchanged)
    // ─────────────────────────────────────────────────────────────

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

        if ($this->step === 2 && ! $this->locationConfirmed) {
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

        $this->step = min(self::TOTAL_STEPS, $this->step + 1);
        $this->furthestStep = max($this->furthestStep, $this->step);
        $this->dispatch('scroll-to-top');
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->dispatch('scroll-to-top');
    }

    public function goToTenantList(): void
    {
        $this->redirect(route('superadmin.tenants.index'), navigate: true);
    }

    public function save(): void
    {
        if ($this->isSubmitting) return;

        if ($this->step < self::TOTAL_STEPS) {
            $this->nextStep();
            return;
        }

        $this->validate($this->rules(), $this->messages(), $this->validationAttributes());
        $this->isSubmitting = true;

        /** @var array<int, BusinessDocument> $attachedDocs */
        $attachedDocs = [];

        try {
            $tenant = DB::transaction(function () use (&$attachedDocs) {
                $coordinates = array_merge(
                    [[
                        'lat'  => $this->latitude,
                        'lng'  => $this->longitude,
                        'name' => 'Main Location',
                        'type' => 'parent',
                    ]],
                    $this->hasSubBranches
                        ? array_map(fn (array $m) => Arr::except($m, ['uid']), $this->markers)
                        : []
                );

                $tenant = Tenant::create([
                    'name'              => $this->name,
                    'slug'              => $this->slug,
                    'type_of_tenant_id' => $this->type_of_tenant_id,
                    'address'           => $this->address,
                    'barangay'          => $this->barangay,
                    'email'             => $this->public_email,
                    'contact_number'    => $this->contact_number,
                    'logo'              => $this->logo_path,
                    'coordinates'       => $coordinates,
                    'is_active'         => $this->is_active,
                    'is_recommended'    => $this->is_recommended,
                    'verified_at'       => now(),
                ]);

                TenantSetting::create([
                    'tenant_id' => $tenant->id,
                    'key'       => 'business_info',
                    'value'     => [
                        'description'   => $this->description,
                        'opening_hours' => [
                            'opening' => $this->open_24_hours ? null : $this->opening_time,
                            'closing' => $this->open_24_hours ? null : $this->closing_time,
                            'is_24hr' => $this->open_24_hours,
                        ],
                        'barangay' => $this->barangay,
                        'city'     => $this->city,
                        'province' => $this->province,
                    ],
                ]);

                $adminUser = User::create([
                    'name'        => $this->admin_name,
                    'email'       => $this->admin_email,
                    'password'    => Hash::make($this->password),
                    'tenant_id'   => $tenant->id,
                    'active_mode' => User::MODE_BUSINESS,
                    'is_active'   => true,
                    'avatar'      => $this->admin_avatar_path,
                ]);

                $adminUser->syncRoles(['tourist', 'admin']);

                $application = BusinessApplication::create([
                    'user_id'                      => $adminUser->id,
                    'business_name'                => $tenant->name,
                    'business_type'                => $this->business_type,
                    'type_of_tenant_id'            => $tenant->type_of_tenant_id,
                    'business_registration_number' => $this->business_registration_number,
                    'tin_number'                   => $this->tin_number,
                    'owner_full_name'              => $this->admin_name,
                    'owner_id_type'                => $this->owner_id_type,
                    'owner_id_number'              => $this->owner_id_number,
                    'owner_birthdate'              => $this->owner_birthdate ?: null,
                    'contact_email'                => $this->admin_email,
                    'contact_phone'                => $this->contact_number ?: null,
                    'address'                      => $this->address,
                    'barangay'                     => $this->barangay,
                    'city'                         => $this->city,
                    'province'                     => $this->province,
                    'coordinates'                  => $coordinates,
                    'logo_path'                    => $this->logo_path,
                    'cover_photo_path'             => $this->cover_photo_path,
                    'owner_avatar_path'            => $this->admin_avatar_path,
                    'status'                       => BusinessApplication::STATUS_APPROVED,
                    'source'                       => BusinessApplication::SOURCE_SUPERADMIN_DIRECT,
                    'approved_tenant_id'           => $tenant->id,
                    'submitted_at'                 => now(),
                    'reviewed_at'                  => now(),
                    'reviewed_by'                  => Auth::id(),
                ]);

                $service = app(BusinessApplicationService::class);

                foreach ($this->document_uploads as $docType => $file) {
                    if (! $file) continue;
                    $attachedDocs[] = $service->attachDocument(
                        $application,
                        $adminUser,
                        $docType,
                        $file,
                        [
                            'document_number' => $docType === BusinessDocument::TYPE_BIR_2303
                                ? $this->tin_number
                                : null,
                            'issued_at'  => null,
                            'expires_at' => $docType === BusinessDocument::TYPE_MAYORS_PERMIT
                                ? now()->addYear()
                                : null,
                        ],
                    );
                }

                $tenantType = $this->tenantTypes->firstWhere('id', (int) $this->type_of_tenant_id);
                foreach ($this->getDefaultPropertyTypes($tenantType) as $ptName) {
                    PropertyType::create(['tenant_id' => $tenant->id, 'name' => $ptName]);
                }

                $this->createDefaultServices($tenant);

                return $tenant;
            });

            $this->welcomeEmailSent = false;
            try {
                Mail::to($this->admin_email)->send(new TenantAdminWelcome(
                    adminName:     $this->admin_name,
                    adminEmail:    $this->admin_email,
                    adminPassword: $this->password,
                    businessName:  $tenant->name,
                    loginUrl:      route('login'),
                ));
                $this->welcomeEmailSent = true;
            } catch (\Throwable $mailError) {
                Log::warning('Tenant welcome email failed', [
                    'tenant_id' => $tenant->id,
                    'email'     => $this->admin_email,
                    'error'     => $mailError->getMessage(),
                ]);
            }

            $this->createdAdminEmail    = $this->admin_email;
            $this->createdAdminPassword = $this->password;
            $this->createdTenantName    = $tenant->name;
            $this->createdTenantId      = $tenant->id;
            $this->showSuccessModal     = true;
            $this->dispatch('scroll-to-top');

        } catch (\Throwable $e) {
            foreach ([$this->logo_path, $this->cover_photo_path, $this->admin_avatar_path] as $path) {
                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            foreach ($attachedDocs as $doc) {
                if ($doc->stored_path && Storage::disk('public')->exists($doc->stored_path)) {
                    Storage::disk('public')->delete($doc->stored_path);
                }
                if ($doc->watermarked_path && Storage::disk('public')->exists($doc->watermarked_path)) {
                    Storage::disk('public')->delete($doc->watermarked_path);
                }
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
            'logo', 'logo_path', 'cover_photo', 'cover_photo_path',
            'is_active', 'is_recommended',
            'business_type', 'business_registration_number', 'tin_number',
            'owner_id_type', 'owner_id_number', 'owner_birthdate', 'document_uploads',
            'admin_name', 'admin_email', 'password', 'password_confirmation',
            'admin_avatar', 'admin_avatar_path',
            'createdAdminEmail', 'createdAdminPassword', 'createdTenantName', 'createdTenantId',
            'selectedMarkerIndex', 'mapVersion', 'markerSearch',
            'showNewTenantTypeModal', 'newTenantTypeName', 'newTenantTypeDescription',
            'welcomeEmailSent',
        ]);
        $this->resetValidation();
        $this->latitude  = self::DEFAULT_LAT;
        $this->longitude = self::DEFAULT_LNG;
        $this->mapView = ['lat' => self::DEFAULT_LAT, 'lng' => self::DEFAULT_LNG, 'zoom' => 13];
        $this->is_active = true;
        $this->dispatch('toast', message: 'Ready to add another business.', type: 'info');
    }

    protected function getDefaultPropertyTypes(?TypeOfTenant $type): array
    {
        if (! $type) {
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

    public function updateMarkerOrder(string $orderedUids): void
    {
        $uids = explode(',', $orderedUids);
        $markers = collect($this->markers);

        $this->markers = collect($uids)
            ->map(fn ($uid) => $markers->firstWhere('uid', $uid))
            ->filter()
            ->merge($markers->whereNotIn('uid', $uids))
            ->values()
            ->toArray();

        $this->mapVersion++;
    }

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
        // SVG marker icon — stored raw (compression N/A for SVGs).
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

    public function openNewTenantTypeModal(): void
    {
        $this->reset(['newTenantTypeName', 'newTenantTypeDescription']);
        $this->resetErrorBag(['newTenantTypeName', 'newTenantTypeDescription']);
        $this->showNewTenantTypeModal = true;
    }

    public function closeNewTenantTypeModal(): void
    {
        $this->showNewTenantTypeModal = false;
        $this->reset(['newTenantTypeName', 'newTenantTypeDescription']);
        $this->resetErrorBag(['newTenantTypeName', 'newTenantTypeDescription']);
    }

    public function createTenantType(): void
    {
        $this->validate([
            'newTenantTypeName'        => 'required|string|min:2|max:255',
            'newTenantTypeDescription' => 'nullable|string|max:1000',
        ], [], [
            'newTenantTypeName'        => 'type name',
            'newTenantTypeDescription' => 'description',
        ]);

        $exists = TypeOfTenant::whereRaw('LOWER(type) = ?', [Str::lower($this->newTenantTypeName)])->exists();

        if ($exists) {
            $this->addError('newTenantTypeName', 'A business type with this name already exists.');
            return;
        }

        try {
            $type = TypeOfTenant::create([
                'type'        => $this->newTenantTypeName,
                'description' => $this->newTenantTypeDescription ?: null,
            ]);

            $this->type_of_tenant_id = (string) $type->id;
            unset($this->tenantTypes);
            $this->closeNewTenantTypeModal();

            $this->dispatch('toast', message: "Business type '{$type->type}' created and selected.", type: 'success');
        } catch (\Throwable $e) {
            Log::error('Business type creation failed: ' . $e->getMessage(), [
                'name' => $this->newTenantTypeName,
            ]);
            $this->addError('newTenantTypeName', 'Failed to create type. Please try again.');
        }
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
    x-on:scroll-to-top.window="window.scrollTo({ top: 0, behavior: 'smooth' })"
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
                    'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300': toast.type === 'success',
                    'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300': toast.type === 'error',
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
            <svg class="animate-spin h-5 w-5 text-primary-600 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Creating the business, uploading documents & admin account…</span>
        </div>
    </div>

    @if(session()->has('message'))
        <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 border-l-4 border-l-emerald-500 p-4 rounded-md text-sm text-emerald-700 dark:text-emerald-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform · Onboard Business</span>
            </div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Add Tenant</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Onboard a walk-in business. Legal documents will be stored and a KYB record created automatically.
            </p>
        </div>
        <button type="button" wire:click="goToTenantList"
                @if($step > 1 || $name !== '') wire:confirm="Leave without saving? Your progress on this tenant will be lost." @endif
                class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to tenants
        </button>
    </div>

    {{-- Sticky Step Indicator --}}
    <div class="sticky top-0 z-30 bg-[#F8F7F3] dark:bg-gray-900/95 backdrop-blur-sm py-3 mb-2">
        <nav aria-label="Progress" class="max-w-5xl mx-auto">
            <ol class="flex items-center gap-2">
                @foreach($this->stepLabels as $stepNum => $label)
                    <li wire:key="step-indicator-{{ $stepNum }}" class="flex items-center gap-2 min-w-0 {{ $stepNum < count($this->stepLabels) ? 'flex-1' : '' }}">
                        <button type="button" wire:click="goToStep({{ $stepNum }})"
                                @if($stepNum > $furthestStep) disabled @endif
                                aria-current="{{ $step === $stepNum ? 'step' : 'false' }}"
                                class="flex items-center gap-2 shrink-0 group {{ $stepNum > $furthestStep ? 'cursor-not-allowed opacity-60' : 'cursor-pointer' }} focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-colors
                                {{ $step > $stepNum ? 'bg-emerald-500 text-white' : ($step === $stepNum ? 'bg-primary-600 text-white ring-4 ring-primary-500/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400') }}">
                                @if($step > $stepNum)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    {{ $stepNum }}
                                @endif
                            </span>
                            <span class="hidden lg:inline text-xs font-medium truncate {{ $step >= $stepNum ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $label }}
                            </span>
                        </button>
                        @if($stepNum < count($this->stepLabels))
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
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10 p-4 text-sm text-rose-700 dark:text-rose-300">
            <p class="font-semibold mb-1">Please fix {{ $errors->count() }} field{{ $errors->count() === 1 ? '' : 's' }} before continuing:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ═══════════ STEP 1 — BUSINESS DETAILS ═══════════ --}}
    @if($step === 1)

        {{-- Cover photo (crop modal) --}}
        <section class="card p-6 mb-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Step 1 — Business Details</span>
            </div>
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white -mt-3 mb-5">Cover Photo <span class="text-sm font-normal text-gray-400">(optional)</span></h2>

            <div class="relative aspect-[3/1] w-full rounded-xl overflow-hidden bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700">
                @if($this->coverPreviewUrl())
                    <img src="{{ $this->coverPreviewUrl() }}" alt="Cover photo" loading="lazy" decoding="async" class="w-full h-full object-cover">
                @else
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-white/85">
                        <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-xs font-semibold uppercase tracking-wider">Cover photo</p>
                    </div>
                @endif
            </div>

            <div
                x-data="imageCropper({
                    wireProperty: 'cover_photo',
                    aspect: 3,
                    title: 'Crop cover photo',
                    description: 'Drag to reposition · Scroll to zoom',
                })"
                x-init="init()"
                class="mt-3 flex items-center gap-2 flex-wrap"
            >
                <label for="cover-upload"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white px-3 py-2 text-xs font-semibold transition cursor-pointer focus-within:ring-2 focus-within:ring-primary-500/50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ $cover_photo_path ? 'Replace cover' : 'Upload cover photo' }}
                    <input type="file" id="cover-upload" x-ref="input" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>

                @if($cover_photo_path)
                    <button type="button" wire:click="removeCoverPhoto" wire:confirm="Remove the cover photo?"
                            class="inline-flex items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 px-3 py-2 text-xs font-semibold hover:border-rose-400 hover:text-rose-600 transition">
                        Remove
                    </button>
                @endif

                <div wire:loading wire:target="cover_photo" class="flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400">
                    <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Uploading…
                </div>
            </div>
            @error('cover_photo') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
        </section>

        {{-- Logo (crop modal) --}}
        <section class="card p-6 mb-5">
            <div class="flex items-center gap-5">
                <div class="shrink-0 w-20 h-20 rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 flex items-center justify-center overflow-hidden">
                    @if($this->logoPreviewUrl())
                        <img src="{{ $this->logoPreviewUrl() }}" alt="Business logo" loading="lazy" decoding="async" class="w-full h-full object-cover">
                    @else
                        <svg class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    @endif
                </div>

                <div
                    x-data="imageCropper({
                        wireProperty: 'logo',
                        aspect: 1,
                        title: 'Crop logo',
                        description: 'Square crop works best',
                    })"
                    x-init="init()"
                    class="flex-1 min-w-0"
                >
                    <p class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Business Logo <span class="text-xs font-normal text-gray-400">(optional)</span></p>

                    <div class="flex items-center gap-2 flex-wrap">
                        <label for="logo-upload"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white px-3 py-2 text-xs font-semibold transition cursor-pointer focus-within:ring-2 focus-within:ring-primary-500/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            {{ $logo_path ? 'Replace logo' : 'Upload logo' }}
                            <input type="file" id="logo-upload" x-ref="input" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="sr-only">
                        </label>

                        @if($logo_path)
                            <button type="button" wire:click="removeLogo" wire:confirm="Remove the logo?"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 px-3 py-2 text-xs font-semibold hover:border-rose-400 hover:text-rose-600 transition">
                                Remove
                            </button>
                        @endif

                        <div wire:loading wire:target="logo" class="flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400">
                            <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Uploading…
                        </div>
                    </div>

                    @error('logo') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block" role="alert">{{ $message }}</span> @enderror
                </div>
            </div>
        </section>

        {{-- Business information --}}
        <section class="card p-6 mb-5">
            <div class="flex items-center gap-3 mb-5">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Business Information
                </span>
            </div>

            <div class="space-y-5">
                <div>
                    <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="field-name" wire:model.live.debounce.300ms="name" class="input" placeholder="e.g. Gawahon Eco Park" maxlength="255">
                    @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
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
                    @error('slug') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="field-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category <span class="text-rose-500">*</span></label>
                            <button type="button" wire:click="openNewTenantTypeModal"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                New
                            </button>
                        </div>
                        <select id="field-type" wire:model="type_of_tenant_id" class="select">
                            <option value="">— Select category —</option>
                            @foreach($this->tenantTypes as $type)
                                <option wire:key="type-{{ $type->id }}" value="{{ $type->id }}">{{ $type->type }}</option>
                            @endforeach
                        </select>
                        @error('type_of_tenant_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="field-public-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Public Email <span class="text-rose-500">*</span></label>
                        <input type="email" id="field-public-email" wire:model.live.debounce.400ms="public_email" class="input" placeholder="business@email.com" maxlength="255">
                        @error('public_email') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="field-contact" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Number</label>
                        <input type="tel" id="field-contact" inputmode="numeric" pattern="[0-9]*" maxlength="11"
                               wire:model.live.debounce.400ms="contact_number"
                               x-on:input="event.target.value = event.target.value.replace(/[^0-9]/g, '').slice(0, 11)"
                               class="input" placeholder="09xxxxxxxxx">
                        @error('contact_number') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="field-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Short Description <span class="text-[10px] font-normal text-gray-400">(optional)</span></label>
                    <textarea id="field-description" wire:model="description" rows="3" class="textarea" placeholder="A short introduction to your business" maxlength="500"></textarea>
                    @error('description') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Business Hours</label>
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-600 dark:text-gray-300">
                            <input type="checkbox" wire:model.live="open_24_hours" class="rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                            Open 24 hours
                        </label>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 {{ $open_24_hours ? 'opacity-50 pointer-events-none' : '' }}">
                        <div>
                            <label for="field-opening" class="text-xs text-gray-500 dark:text-gray-400">Opening time</label>
                            <input type="time" id="field-opening" wire:model="opening_time" class="input" @if($open_24_hours) disabled @endif>
                            @error('opening_time') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="field-closing" class="text-xs text-gray-500 dark:text-gray-400">Closing time</label>
                            <input type="time" id="field-closing" wire:model="closing_time" class="input" @if($open_24_hours) disabled @endif>
                            @error('closing_time') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Legal & Identity --}}
        <section class="card p-6 mb-5">
            <div class="flex items-center gap-3 mb-5">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Legal &amp; Identity
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-business-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Registration Type <span class="text-rose-500">*</span>
                    </label>
                    <select id="field-business-type" wire:model.live="business_type" class="select">
                        <option value="">— Select type —</option>
                        @foreach($this->businessTypeLabels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('business_type') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="field-regno" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Registration No. <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="field-regno"
                           wire:model="business_registration_number"
                           maxlength="50"
                           autocomplete="off"
                           placeholder="{{ $this->registrationNumberPlaceholder }}"
                           x-on:input="
                               const up = $event.target.value.toUpperCase();
                               if (up !== $event.target.value) {
                                   $event.target.value = up;
                                   $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                               }
                           "
                           class="input font-mono">
                    @error('business_registration_number') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="field-tin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        TIN <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="field-tin"
                           wire:model="tin_number"
                           placeholder="123-456-789-000"
                           inputmode="numeric"
                           maxlength="15"
                           autocomplete="off"
                           x-on:blur="
                               const digits = ($event.target.value || '').replace(/\D/g, '').slice(0, 12);
                               let parts = [];
                               if (digits.length > 0) parts.push(digits.slice(0, 3));
                               if (digits.length > 3) parts.push(digits.slice(3, 6));
                               if (digits.length > 6) parts.push(digits.slice(6, 9));
                               if (digits.length > 9) parts.push(digits.slice(9, 12));
                               const formatted = parts.join('-');
                               if (formatted !== $event.target.value) {
                                   $event.target.value = formatted;
                                   $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                               }
                           "
                           class="input font-mono">
                    @error('tin_number') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="field-owner-id-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Owner ID Type <span class="text-rose-500">*</span>
                    </label>
                    <select id="field-owner-id-type" wire:model.live="owner_id_type" class="select">
                        <option value="">— Select an accepted ID —</option>
                        @foreach($this->ownerIdTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('owner_id_type') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="field-owner-id-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Owner ID Number <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="field-owner-id-number"
                           wire:model.blur="owner_id_number"
                           maxlength="{{ $this->ownerIdMaxLength }}"
                           autocomplete="off"
                           placeholder="{{ $this->ownerIdPlaceholder }}"
                           class="input font-mono">
                    @error('owner_id_number') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">{{ $this->ownerIdHint }}</p>
                </div>

                <div class="md:col-span-2 sm:max-w-xs">
                    <label for="field-owner-birthdate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Owner Date of Birth <span class="text-[10px] font-normal text-gray-400">(optional)</span>
                    </label>
                    <input type="date"
                           id="field-owner-birthdate"
                           wire:model.blur="owner_birthdate"
                           max="{{ now()->subYears(18)->format('Y-m-d') }}"
                           min="1900-01-01"
                           class="input">
                    @error('owner_birthdate') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Documents --}}
        <section class="card p-6 mb-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Required Documents
                    </span>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider
                    {{ $this->uploadedRequiredCount === count($this->requiredDocuments)
                        ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                    {{ $this->uploadedRequiredCount }} / {{ count($this->requiredDocuments) }}
                </span>
            </div>

            <div class="space-y-2.5">
                @foreach($this->requiredDocuments as $docType)
                    @php
                        $file = $this->document_uploads[$docType] ?? null;
                        $label = $this->documentLabels[$docType] ?? $docType;
                        $isOwnerIdDoc = $docType === BusinessDocument::TYPE_OWNER_ID;
                        $ownerIdLabel = $this->selectedOwnerIdLabel;
                    @endphp
                    <div wire:key="req-slot-{{ $docType }}"
                         class="border rounded-xl p-3.5 transition
                            {{ $file
                                ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-500/[0.04]'
                                : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900' }}">

                        @if ($file)
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $label }}</p>
                                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                        {{ method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : 'File ready' }}
                                    </p>
                                </div>
                                <button type="button"
                                        wire:click="clearDocumentUpload('{{ $docType }}')"
                                        class="text-[11px] font-semibold text-rose-500 hover:text-rose-700 transition active:scale-95">
                                    Remove
                                </button>
                            </div>
                        @else
                            <label for="file-{{ $docType }}" class="flex items-center gap-3 cursor-pointer group">
                                <div class="shrink-0 w-9 h-9 rounded-lg bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 group-hover:border-primary-500 group-hover:text-primary-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $label }}</p>
                                    @if ($isOwnerIdDoc && $ownerIdLabel)
                                        <p class="mt-0.5 inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/30 rounded px-1.5 py-0.5">
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/>
                                            </svg>
                                            Should be your {{ $ownerIdLabel }}
                                        </p>
                                    @endif
                                </div>
                                <input type="file"
                                       id="file-{{ $docType }}"
                                       wire:model="document_uploads.{{ $docType }}"
                                       accept=".pdf,.jpg,.jpeg,.png,.webp"
                                       class="sr-only">
                            </label>
                        @endif

                        <div wire:loading wire:target="document_uploads.{{ $docType }}" class="mt-2 flex items-center gap-2 text-[11px] text-primary-600 dark:text-primary-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Uploading…
                        </div>

                        @error("document_uploads.{$docType}")
                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>

            <details class="mt-4 border-t border-gray-100 dark:border-gray-700/60 pt-4">
                <summary class="cursor-pointer list-none flex items-center justify-between py-1 group">
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-300 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition">
                        Additional documents (optional)
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </summary>
                <div class="mt-3 space-y-2.5">
                    @foreach($this->optionalDocuments as $docType)
                        @php
                            $file = $this->document_uploads[$docType] ?? null;
                            $label = $this->documentLabels[$docType] ?? $docType;
                        @endphp
                        <div wire:key="opt-slot-{{ $docType }}"
                             class="border rounded-xl p-3 transition
                                {{ $file
                                    ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-500/[0.04]'
                                    : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900' }}">
                            @if ($file)
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <p class="flex-1 text-sm font-medium text-gray-900 dark:text-white truncate">{{ $label }}</p>
                                    <button type="button"
                                            wire:click="clearDocumentUpload('{{ $docType }}')"
                                            class="text-[11px] font-semibold text-gray-500 hover:text-rose-600 transition">
                                        Remove
                                    </button>
                                </div>
                            @else
                                <label for="file-opt-{{ $docType }}" class="flex items-center gap-3 cursor-pointer group">
                                    <div class="shrink-0 w-8 h-8 rounded-lg bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 group-hover:border-primary-500 group-hover:text-primary-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </div>
                                    <p class="flex-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</p>
                                    <input type="file"
                                           id="file-opt-{{ $docType }}"
                                           wire:model="document_uploads.{{ $docType }}"
                                           accept=".pdf,.jpg,.jpeg,.png,.webp"
                                           class="sr-only">
                                </label>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        </section>

        {{-- Visibility --}}
        <section class="card p-6 mb-5">
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
                        <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        Recommended Destination
                    </span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_recommended" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep" class="btn-primary active:scale-95 transition-transform">
                Next: Map Location →
            </button>
        </div>
    @endif

    {{-- ═══════════ STEP 2 — MAP LOCATION ═══════════ --}}
    @if($step === 2)

        <div class="card p-6 space-y-5">
            <div class="flex items-center gap-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Step 2 — Location</span>
            </div>
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white -mt-3">Map Location Setup</h2>

            {{-- Controls row --}}
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="useMyLocation" @if($locationConfirmed) disabled @endif
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 text-xs font-semibold transition active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Use my location
                </button>

                @if($locationConfirmed)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Location locked
                    </span>
                @endif
            </div>

            {{-- Map — wire:ignore'd, stable key that only changes on step
                 navigation (not on every pin move). The Alpine locationPicker
                 module owns the marker; the SFC owns lat/lng. --}}
            <div
                wire:ignore
                wire:key="tenant-main-map-stable"
                x-data="locationPicker({
                    containerId: 'tenant-main-map',
                    wireMethod: 'setMainLocation',
                    geocodeMethod: 'resolveAddress',
                    initialLat: {{ $latitude }},
                    initialLng: {{ $longitude }},
                })"
                x-init="init()"
                class="relative rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900"
                style="height: 420px;"
            >
                <x-map
                    id="tenant-main-map"
                    :center="[(float) $longitude, (float) $latitude]"
                    :zoom="14"
                    height="100%"
                    provider="carto-voyager"
                    theme="auto"
                    :max-zoom="22"
                    class="h-full w-full"
                >
                    <x-map-controls
                        :zoom="true"
                        :compass="true"
                        :locate="false"
                        :fullscreen="true"
                        :scale="true"
                        position="top-right"
                    />
                </x-map>

                <div x-cloak
                     :class="hasPin ? 'hidden' : ''"
                     class="absolute inset-x-0 top-3 mx-auto w-max pointer-events-none rounded-full bg-gray-900/80 backdrop-blur-sm text-white text-[11px] font-semibold px-3 py-1.5">
                    Click the map to drop a pin
                </div>
            </div>

            {{-- Looking-up-address chip --}}
            <div class="relative -mt-3 ml-3 w-max">
                <div wire:loading wire:target="resolveAddress"
                     class="inline-flex items-center gap-1.5 rounded-full bg-gray-900/85 backdrop-blur-sm text-white text-[11px] font-semibold px-3 py-1.5 shadow-lg">
                    <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Looking up address…
                </div>
            </div>

            {{-- Coordinates line --}}
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-[11px] font-mono text-gray-500 dark:text-gray-400 tabular-nums">
                    {{ number_format($latitude, 6) }}, {{ number_format($longitude, 6) }}
                </p>
                @if(!$locationConfirmed)
                    <button type="button" wire:click="confirmLocation"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 text-xs font-semibold transition active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Confirm location
                    </button>
                @endif
            </div>

            @error('latitude') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
            @error('longitude') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror

            {{-- Address fields — auto-filled by the reverse geocoder.
                 Directly below the map so the fill is in view. --}}
            <div class="pt-3 border-t border-gray-100 dark:border-gray-700/60">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Address</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label for="field-address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Street Address</label>
                        <input type="text" id="field-address" wire:model="address" class="input" placeholder="Street, Building, etc." maxlength="255">
                    </div>
                    <div>
                        <label for="field-barangay" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barangay</label>
                        <input type="text" id="field-barangay" wire:model="barangay" class="input" maxlength="255">
                    </div>
                    <div>
                        <label for="field-city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City / Municipality</label>
                        <input type="text" id="field-city" wire:model="city" class="input" maxlength="255">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="field-province" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Province</label>
                        <input type="text" id="field-province" wire:model="province" class="input" maxlength="255">
                    </div>
                </div>
            </div>

            <div class="flex justify-between pt-2">
                <button type="button" wire:click="prevStep" class="btn-secondary active:scale-95 transition-transform">← Back</button>
                <button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep"
                        @if(!$locationConfirmed) disabled @endif
                        class="btn-primary active:scale-95 transition-transform disabled:opacity-50 disabled:cursor-not-allowed">
                    Next: Sub-Establishments →
                </button>
            </div>
        </div>
    @endif

    {{-- ═══════════ STEP 3 — SUB-ESTABLISHMENTS ═══════════ --}}
    @if($step === 3)
        <div class="card p-6 space-y-6">
            <div class="flex items-center gap-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Step 3 — Sub-Establishments</span>
            </div>
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white -mt-3">Sub-Establishments</h2>
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
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 text-xs font-semibold border border-primary-200 dark:border-primary-500/30 hover:bg-primary-100 dark:hover:bg-primary-500/20 transition active:scale-95">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Add Category
                            </button>
                        </div>
                    </div>

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
                                            <div class="relative flex h-10 w-10 items-center justify-center transform-gpu will-change-transform transition-transform duration-200 group-hover:scale-110 active:scale-95" style="cursor: pointer;">
                                                <svg class="absolute inset-0 size-10 drop-shadow-md fill-white dark:fill-gray-900 stroke-slate-400 dark:stroke-slate-600 stroke-1" viewBox="0 0 24 24" aria-hidden="true">
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

                    @if(count($this->filteredMarkers) > 0)
                        <ul wire:sort="updateMarkerOrder" class="space-y-2">
                            @foreach($this->filteredMarkers as $index => $marker)
                                @php
                                    $originalIndex = array_search($marker['uid'], array_column($this->markers, 'uid'));
                                    $type = $marker['type'] ?? '';
                                @endphp
                                <li wire:key="marker-row-{{ $marker['uid'] }}"
                                    wire:sort:item="{{ $marker['uid'] }}"
                                    class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700 cursor-grab active:cursor-grabbing
                                            {{ $selectedMarkerIndex === $originalIndex ? 'ring-2 ring-primary-500/40 border-primary-500/30' : '' }}"
                                    @click="$wire.selectedMarkerIndex = {{ $originalIndex }}"
                                    title="Drag to reorder"
                                >
                                    <span class="shrink-0 text-gray-400 dark:text-gray-500 cursor-grab">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M7 2a2 2 0 100 4 2 2 0 000-4zM7 8a2 2 0 100 4 2 2 0 000-4zM7 14a2 2 0 100 4 2 2 0 000-4zM13 2a2 2 0 100 4 2 2 0 000-4zM13 8a2 2 0 100 4 2 2 0 000-4zM13 14a2 2 0 100 4 2 2 0 000-4z"/></svg>
                                    </span>
                                    <input type="text" wire:model.debounce.500ms="markers.{{ $originalIndex }}.name" placeholder="Place name" aria-label="Place name" class="input !py-2 flex-1 min-w-[140px]">
                                    <input type="number" step="any" min="-90" max="90" wire:model.debounce.500ms="markers.{{ $originalIndex }}.lat" placeholder="Lat" aria-label="Latitude" class="input !py-2 !w-28 font-mono">
                                    <input type="number" step="any" min="-180" max="180" wire:model.debounce.500ms="markers.{{ $originalIndex }}.lng" placeholder="Lng" aria-label="Longitude" class="input !py-2 !w-28 font-mono">
                                    <select wire:model.live="markers.{{ $originalIndex }}.type"
                                            class="select !py-2 !w-48 {{ empty($marker['type']) ? 'border-rose-300 dark:border-rose-500' : '' }}">
                                        <option value="">Select category *</option>
                                        @foreach($this->markerCategories as $cat)
                                            <option wire:key="cat-{{ $cat['key'] }}" value="{{ $cat['key'] }}">{{ $cat['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="focusMarker({{ $originalIndex }})" aria-label="Locate on map" class="text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 shrink-0 active:scale-95 transition-transform">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    </button>
                                    <button type="button" wire:click="removeMarker({{ $originalIndex }})" aria-label="Remove nearby place" class="text-rose-500 hover:text-rose-700 shrink-0 active:scale-95 transition-transform">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
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

    {{-- ═══════════ STEP 4 — ADMIN ACCOUNT ═══════════ --}}
    @if($step === 4)
        <div class="card p-6 space-y-6">
            <div class="flex items-center gap-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Step 4 — Admin Account</span>
            </div>
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white -mt-3">Admin Account Assignment</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500 -mt-4">A welcome email with these credentials will be sent to the admin automatically.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-admin-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin Full Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="field-admin-name" wire:model="admin_name" class="input" maxlength="255">
                    @error('admin_name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="field-admin-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin Login Email <span class="text-rose-500">*</span></label>
                    <input type="email" id="field-admin-email" wire:model.live.debounce.400ms="admin_email" class="input" placeholder="admin@business.com" maxlength="255">
                    @error('admin_email') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">Must be different from the public email above.</p>
                </div>
            </div>

            {{-- Admin photo — cropper + live preview --}}
            <div
                x-data="avatarPreview()"
                x-on:avatar-preview.window="setUrl($event.detail.url)"
                x-on:avatar-cleared.window="clear()"
                class="flex items-center gap-5"
            >
                <div class="relative shrink-0 w-20 h-20">
                    <img
                        :src="previewUrl || '{{ $this->avatarPreviewUrl() ?? '' }}'"
                        :class="(previewUrl || {{ $this->avatarPreviewUrl() ? 'true' : 'false' }}) ? 'block' : 'hidden'"
                        class="w-20 h-20 rounded-full object-cover border-2 border-primary-500 shadow-md"
                        alt="Admin photo"
                        loading="eager"
                        decoding="async"
                    >
                    <div
                        :class="(previewUrl || {{ $this->avatarPreviewUrl() ? 'true' : 'false' }}) ? 'hidden' : 'flex'"
                        class="w-20 h-20 rounded-full border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 items-center justify-center text-gray-400"
                    >
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>

                    <div
                        wire:loading.flex
                        wire:target="admin_avatar"
                        class="absolute inset-0 rounded-full bg-black/55 backdrop-blur-[2px] items-center justify-center z-10 pointer-events-none"
                        aria-hidden="true"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>

                    <div
                        x-data="imageCropper({
                            wireProperty: 'admin_avatar',
                            aspect: 1,
                            title: 'Crop admin photo',
                            description: 'Square crop works best',
                            previewEvent: 'avatar-preview',
                        })"
                        x-init="init()"
                        class="absolute bottom-0 right-0 z-20"
                    >
                        <label class="block bg-primary-600 hover:bg-primary-500 text-white rounded-full p-2 cursor-pointer shadow-lg transition-colors focus-within:ring-2 focus-within:ring-primary-600/50 active:scale-95"
                               aria-label="Upload admin photo">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <input type="file" x-ref="input" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="hidden">
                        </label>
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Admin Photo <span class="text-xs font-normal text-gray-400">(optional)</span></p>

                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            type="button"
                            wire:click="removeAdminAvatar"
                            wire:confirm="Remove the photo?"
                            :class="previewUrl ? 'hidden' : ({{ $this->avatarPreviewUrl() ? 'true' : 'false' }} ? 'inline-flex' : 'hidden')"
                            class="inline-flex items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 px-3 py-2 text-xs font-semibold hover:border-rose-400 hover:text-rose-600 transition"
                        >
                            Remove photo
                        </button>

                        @if($admin_avatar_path)
                            <button
                                type="button"
                                wire:click="removeAdminAvatar"
                                wire:confirm="Remove the photo?"
                                :class="previewUrl ? 'hidden' : 'inline-flex'"
                                class="inline-flex items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 px-3 py-2 text-xs font-semibold hover:border-rose-400 hover:text-rose-600 transition"
                            >
                                Remove photo
                            </button>
                        @endif
                    </div>

                    @error('admin_avatar') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Password --}}
            <div x-data="{ show: false, score: 0, pwd: '' }"
                 x-on:password-generated.window="pwd = $event.detail.password; score = window.pwStrength(pwd)">
                <label for="field-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password <span class="text-rose-500">*</span></label>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <input :type="show ? 'text' : 'password'" id="field-password" wire:model="password"
                               @input="pwd = $event.target.value; score = window.pwStrength(pwd)"
                               class="input pr-10" placeholder="Min. 8 characters">
                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 active:scale-95 transition-transform" :aria-label="show ? 'Hide password' : 'Show password'">
                            <svg :class="show ? 'hidden' : 'block'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg :class="show ? 'block' : 'hidden'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.025 10.025 0 012.132-3.592m3.16-2.11A9.958 9.958 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.958 9.958 0 01-4.132 5.411M15 12a3 3 0 11-6 0 3 3 0 016 0z M3 3l18 18"/></svg>
                        </button>
                    </div>
                    <button type="button" wire:click="generatePassword" class="px-3 py-2 rounded-xl bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition active:scale-95 whitespace-nowrap">
                        Generate
                    </button>
                </div>

                <div class="mt-1.5 flex items-center gap-2" :class="pwd.length > 0 ? 'flex' : 'hidden'">
                    <div class="flex-1 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-300"
                             :class="{ 'bg-rose-500': score <= 1, 'bg-amber-500': score === 2 || score === 3, 'bg-emerald-500': score >= 4 }"
                             :style="`width: ${(score / 5) * 100}%`"></div>
                    </div>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 w-16 text-right"
                          x-text="['Too weak','Weak','Fair','Good','Strong','Excellent'][score]"></span>
                </div>

                @error('password') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="field-password-confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Password <span class="text-rose-500">*</span></label>
                <input type="password" id="field-password-confirmation" wire:model="password_confirmation" class="input">
                @error('password_confirmation') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                <button type="button" wire:click="prevStep" class="btn-secondary active:scale-95 transition-transform">← Back</button>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="relative px-8 py-3 rounded-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-70 text-white text-sm font-semibold shadow-lg shadow-emerald-500/20 transition focus-visible:ring-2 focus-visible:ring-emerald-500/50 active:scale-95">
                    <span wire:loading.remove wire:target="save">Complete Registration &amp; Save</span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Creating…
                    </span>
                </button>
            </div>
        </div>
    @endif

    {{-- ═══════════ SUCCESS MODAL ═══════════ --}}
    @if($showSuccessModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             role="dialog" aria-modal="true" aria-labelledby="success-modal-title">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-8 max-w-md w-full shadow-2xl"
                 x-data="{ copiedAll: false, email: @js($createdAdminEmail), password: @js($createdAdminPassword) }">
                <div class="text-center">
                    <div class="mx-auto w-14 h-14 rounded-full bg-emerald-100 dark:bg-emerald-500/20 flex items-center justify-center mb-4">
                        <svg class="w-7 h-7 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3 id="success-modal-title" class="text-xl font-bold text-gray-900 dark:text-white mb-1">{{ $createdTenantName ?? 'Business' }} is live!</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">Credentials below — copy them if you need a manual fallback.</p>

                    @if($welcomeEmailSent)
                        <div class="mb-4 flex items-center gap-2.5 rounded-xl border border-emerald-200/70 dark:border-emerald-500/30 bg-emerald-50/60 dark:bg-emerald-500/[0.06] px-3 py-2.5 text-xs text-emerald-800 dark:text-emerald-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span class="leading-relaxed text-left">Welcome email sent to <strong class="font-semibold">{{ $createdAdminEmail }}</strong> with login details.</span>
                        </div>
                    @else
                        <div class="mb-4 flex items-center gap-2.5 rounded-xl border border-amber-200/70 dark:border-amber-500/30 bg-amber-50/60 dark:bg-amber-500/[0.06] px-3 py-2.5 text-xs text-amber-800 dark:text-amber-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                            <span class="leading-relaxed text-left">Welcome email <strong class="font-semibold">could not be delivered</strong>. Share the credentials below manually.</span>
                        </div>
                    @endif

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
                        <span :class="copiedAll ? 'hidden' : 'inline'">Copy credentials</span>
                        <span :class="copiedAll ? 'inline-flex items-center gap-1' : 'hidden'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
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
    @endif

    {{-- ═══════════ ADD CATEGORY MODAL ═══════════ --}}
    @if($showAddCategoryModal)
        <div class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 p-4"
             x-on:keydown.escape.window="$wire.closeAddCategoryModal()"
             @click.self="$wire.closeAddCategoryModal()">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add Marker Category</h3>
                    <button type="button" wire:click="closeAddCategoryModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Key (slug)</label>
                        <input type="text" wire:model="newCategoryKey" class="input" placeholder="e.g. restaurant" maxlength="50">
                        @error('newCategoryKey') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Label</label>
                        <input type="text" wire:model="newCategoryLabel" class="input" placeholder="Restaurant" maxlength="100">
                        @error('newCategoryLabel') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                        <input type="color" wire:model="newCategoryColor" class="h-10 w-full rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer">
                        @error('newCategoryColor') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Icon (SVG)</label>
                        <input type="file" wire:model="newCategoryIcon" accept=".svg" class="input">
                        @error('newCategoryIcon') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" wire:click="closeAddCategoryModal" class="btn-secondary active:scale-95 transition-transform">Cancel</button>
                    <button type="button" wire:click="saveNewCategory" wire:loading.attr="disabled" wire:target="saveNewCategory" class="btn-primary active:scale-95 transition-transform inline-flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="saveNewCategory">Add Category</span>
                        <span wire:loading wire:target="saveNewCategory" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Adding…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════ ADD BUSINESS TYPE MODAL ═══════════ --}}
    @if($showNewTenantTypeModal)
        <div class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
             x-on:keydown.escape.window="$wire.closeNewTenantTypeModal()"
             @click.self="$wire.closeNewTenantTypeModal()">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="p-2 bg-primary-50 dark:bg-primary-500/10 rounded-lg text-primary-600 dark:text-primary-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add Business Type</h3>
                    </div>
                    <button type="button" wire:click="closeNewTenantTypeModal"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition active:scale-95 rounded-lg p-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                            aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    Create a new business type. It will be available platform-wide for all tenants.
                </p>

                <div class="space-y-4">
                    <div>
                        <label for="field-new-tenant-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type Name <span class="text-rose-500">*</span></label>
                        <input type="text" id="field-new-tenant-type" wire:model="newTenantTypeName"
                               wire:keydown.enter.prevent="createTenantType" class="input"
                               placeholder="e.g. Beach Resort" autofocus maxlength="255">
                        @error('newTenantTypeName') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="field-new-tenant-type-desc" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Description <span class="text-gray-400 dark:text-gray-500 font-normal">(Optional)</span>
                        </label>
                        <textarea id="field-new-tenant-type-desc" wire:model="newTenantTypeDescription" rows="3" class="textarea" placeholder="Briefly describe this type" maxlength="1000"></textarea>
                        @error('newTenantTypeDescription') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" wire:click="closeNewTenantTypeModal" class="btn-secondary active:scale-95 transition-transform">Cancel</button>
                    <button type="button" wire:click="createTenantType" wire:loading.attr="disabled" wire:target="createTenantType"
                            class="btn-primary active:scale-95 transition-transform inline-flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="createTenantType">Create Type</span>
                        <span wire:loading wire:target="createTenantType" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Creating…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Image crop modal — singleton --}}
    <x-image-crop-modal />
</div>

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
</script>