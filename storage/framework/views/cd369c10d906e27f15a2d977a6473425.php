
<?php

use App\Mail\TenantAdminPasswordReset;
use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TypeOfTenant;
use App\Models\User;
use App\Services\BusinessApplicationService;
use App\Services\ReverseGeocodeService;
use App\Traits\HandlesImageUploads;
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
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('superadmin.layouts.app')]
#[Title('Edit Tenant')]
class extends Component {
    use WithFileUploads;
    use HandlesImageUploads;

    public Tenant $tenantRecord;

    /** Locked — loaded in mount, never trusted from client. */
    #[Locked]
    public ?int $adminUserRecordId = null;

    #[Locked]
    public ?int $businessApplicationId = null;

    // ═══ Philippine government ID formats ═══
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

    // ═══ Business Details ═══
    public string $name = '';
    public string $slug = '';
    public bool $slugEditable = true;
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

    // ═══ Brand assets (stored on upload) ═══
    public $logo;
    public ?string $logo_path = null;

    public $cover_photo;
    public ?string $cover_photo_path = null;

    // ═══ Legal & Identity ═══
    public string $business_type = '';
    public string $business_registration_number = '';
    public string $tin_number = '';
    public string $owner_id_type = '';
    public string $owner_id_number = '';
    public string $owner_birthdate = '';

    /** @var array<string, mixed> */
    public array $document_uploads = [];

    // ═══ Visibility ═══
    public bool $is_active = true;
    public bool $is_recommended = false;

    // ═══ Location ═══
    public float $latitude = 10.900977766937142;
    public float $longitude = 123.07055771888716;
    public bool $satellite = false;
    public string $locationMode = 'main';
    public int $mapVersion = 0;
    public array $mapView = [
        'lat' => 10.900977766937142,
        'lng' => 123.07055771888716,
        'zoom' => 13,
    ];

    // ═══ Nearby markers ═══
    public array $markers = [];
    public ?int $selectedMarkerIndex = null;
    public array $markerCategories = [];

    // ═══ Admin Account ═══
    public string $admin_name = '';
    public string $admin_email = '';
    public string $admin_password = '';
    public string $admin_password_confirmation = '';
    public $admin_avatar;
    public ?string $admin_avatar_path = null;

    // ═══ Modals ═══
    public bool $showAddCategoryModal = false;
    public string $newCategoryKey = '';
    public string $newCategoryLabel = '';
    public string $newCategoryColor = '#3b82f6';
    public $newCategoryIcon;

    public bool $showNewTenantTypeModal = false;
    public string $newTenantTypeName = '';
    public string $newTenantTypeDescription = '';

    // ─────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────

    public function mount(Tenant $tenant): void
    {
        $this->tenantRecord = $tenant;

        $this->name              = $tenant->name ?? '';
        $this->slug              = $tenant->slug ?? '';
        $this->type_of_tenant_id = (string) ($tenant->type_of_tenant_id ?? '');
        $this->address           = $tenant->address ?? '';
        $this->public_email      = $tenant->email ?? '';
        $this->contact_number    = $tenant->contact_number ?? '';
        $this->is_active         = (bool) $tenant->is_active;
        $this->is_recommended    = (bool) $tenant->is_recommended;
        $this->logo_path         = $tenant->logo;

        $coords = $tenant->coordinates ?? [];
        $this->latitude  = (float) ($coords[0]['lat'] ?? 10.900977766937142);
        $this->longitude = (float) ($coords[0]['lng'] ?? 123.07055771888716);

        $this->markers = array_slice($coords, 1);
        foreach ($this->markers as &$marker) {
            if (!isset($marker['uid'])) {
                $marker['uid'] = (string) Str::uuid();
            }
        }
        unset($marker);

        $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => 13];

        $businessInfo = TenantSetting::where('tenant_id', $tenant->id)
                                     ->where('key', 'business_info')
                                     ->first();

        if ($businessInfo && is_array($businessInfo->value)) {
            $info = $businessInfo->value;
            $this->description   = $info['description'] ?? '';
            $this->open_24_hours = (bool) ($info['opening_hours']['is_24hr'] ?? false);
            $this->opening_time  = $info['opening_hours']['opening'] ?? '08:00';
            $this->closing_time  = $info['opening_hours']['closing'] ?? '17:00';
            $this->barangay      = $info['barangay'] ?? '';
            $this->city          = $info['city'] ?? '';
            $this->province      = $info['province'] ?? '';
        }

        $adminUser = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->select('id', 'name', 'email', 'tenant_id', 'avatar')
            ->first();

        if ($adminUser) {
            $this->adminUserRecordId = $adminUser->id;
            $this->admin_name        = $adminUser->name;
            $this->admin_email       = $adminUser->email;
            $this->admin_avatar_path = $adminUser->avatar;
        }

        $app = BusinessApplication::where('approved_tenant_id', $tenant->id)
            ->latest('id')
            ->first();

        if ($app) {
            $this->businessApplicationId        = $app->id;
            $this->business_type                = (string) ($app->business_type ?? '');
            $this->business_registration_number = (string) ($app->business_registration_number ?? '');
            $this->tin_number                   = (string) ($app->tin_number ?? '');
            $this->owner_id_type                = (string) ($app->owner_id_type ?? '');
            $this->owner_id_number              = (string) ($app->owner_id_number ?? '');
            $this->owner_birthdate              = $app->owner_birthdate?->format('Y-m-d') ?? '';
            $this->cover_photo_path             = $app->cover_photo_path;

            if (!$this->admin_avatar_path && $app->owner_avatar_path) {
                $this->admin_avatar_path = $app->owner_avatar_path;
            }
        }

        $this->markerCategories = SiteSetting::getValue('marker_categories', []);
    }

    // ─────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────

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

    /** @return array<string, BusinessDocument> */
    #[Computed]
    public function documentsByType(): array
    {
        if (!$this->businessApplicationId) {
            return [];
        }

        return BusinessDocument::where('business_application_id', $this->businessApplicationId)
            ->get()
            ->keyBy('document_type')
            ->all();
    }

    #[Computed]
    public function existingDocumentCount(): int
    {
        return count($this->documentsByType);
    }

    #[Computed]
    public function uploadedRequiredCount(): int
    {
        $existing = array_keys($this->documentsByType);

        return collect($this->requiredDocuments)
            ->filter(fn (string $t) => isset($this->document_uploads[$t]) || in_array($t, $existing, true))
            ->count();
    }

    // ─────────────────────────────────────────────────────
    //  Asset preview URLs
    // ─────────────────────────────────────────────────────

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

    public function documentUrl(BusinessDocument $doc): ?string
    {
        $path = $doc->watermarked_path ?: $doc->stored_path;
        return $path ? asset('storage/' . $path) : null;
    }

    public function isImageDocument(BusinessDocument $doc): bool
    {
        return str_starts_with((string) $doc->mime_type, 'image/');
    }

    // ─────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            // Business
            'name' => ['required', 'string', 'min:3', 'max:255', Rule::unique('tenants', 'name')->ignore($this->tenantRecord->id)],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tenants', 'slug')->ignore($this->tenantRecord->id)],
            'type_of_tenant_id' => ['required', 'integer', Rule::exists('type_of_tenants', 'id')],
            'address' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'public_email' => ['required', 'email:rfc', 'max:255', Rule::unique('tenants', 'email')->ignore($this->tenantRecord->id)],
            'contact_number' => ['nullable', 'string', 'regex:/^[0-9]{10,11}$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => array_filter([
                'nullable',
                'date_format:H:i',
                $this->open_24_hours ? null : 'after:opening_time',
            ]),
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'cover_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active' => ['boolean'],
            'is_recommended' => ['boolean'],

            // Legal
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

            // Location
            'latitude' => ['required', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['required', 'numeric', 'min:-180', 'max:180'],

            // Nearby
            'markers' => ['array', 'max:20'],
            'markers.*.name' => ['required', 'string', 'max:100'],
            'markers.*.lat' => ['required', 'numeric', 'min:-90', 'max:90'],
            'markers.*.lng' => ['required', 'numeric', 'min:-180', 'max:180'],
            'markers.*.type' => ['required', 'string', 'max:255'],

            // Admin
            'admin_name' => ['required', 'string', 'min:3', 'max:255'],
            'admin_email' => [
                'required', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($this->adminUserRecordId),
            ],
            'admin_password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'admin_avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'public_email.unique'   => 'This public email is already registered to another business.',
            'public_email.email'    => 'Enter a valid public email address.',
            'admin_email.unique'    => 'This admin login email is already taken.',
            'admin_email.email'     => 'Enter a valid admin email address.',
            'contact_number.regex'  => 'Contact number must be 10 or 11 digits, numbers only.',
            'password.confirmed'    => 'Password confirmation does not match.',
            'closing_time.after'    => 'Closing time must be later than the opening time.',
            'slug.regex'            => 'Slug may only contain lowercase letters, numbers and single hyphens.',
            'slug.unique'           => 'This slug is already taken — try another.',
            'markers.max'           => 'You can add up to 20 nearby places.',
            'markers.*.type.required' => 'Please select a category for this nearby place.',
            'logo.mimes'            => 'Logo must be a valid image file (JPEG, PNG, or WebP).',
            'logo.max'              => 'Logo must not exceed 10MB.',
            'cover_photo.mimes'     => 'Cover photo must be a valid image (JPEG, PNG, or WebP).',
            'cover_photo.max'       => 'Cover photo must not exceed 5MB.',
            'admin_avatar.mimes'    => 'Photo must be a valid image (JPEG, PNG, or WebP).',
            'admin_avatar.max'      => 'Photo must not exceed 2MB.',
            'business_registration_number.required' => 'Enter the registration number printed on the certificate.',
            'business_registration_number.regex'    => 'Registration numbers can only contain letters, digits, and dashes.',
            'tin_number.required'   => 'Enter the TIN shown on the BIR Form 2303.',
            'tin_number.regex'      => 'Enter a valid TIN: 123-456-789 or 123-456-789-000.',
            'owner_id_type.required'   => 'Select the type of government ID you uploaded.',
            'owner_id_number.required' => 'Enter the ID number exactly as printed on the card.',
            'owner_birthdate.date'     => 'Enter a valid date of birth.',
            'owner_birthdate.before'   => 'The owner must be at least 18 years old.',
            'owner_birthdate.after'    => 'Please enter a date after 1900.',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'type_of_tenant_id'        => 'business category',
            'public_email'             => 'public email',
            'admin_email'              => 'admin login email',
            'business_type'            => 'registration type',
            'business_registration_number' => 'registration number',
            'tin_number'               => 'TIN',
            'owner_id_type'            => 'owner ID type',
            'owner_id_number'          => 'owner ID number',
            'owner_birthdate'          => 'owner date of birth',
            'newCategoryKey'           => 'category key',
            'newCategoryLabel'         => 'category label',
            'newCategoryColor'         => 'category color',
            'newCategoryIcon'          => 'category icon',
            'newTenantTypeName'        => 'type name',
            'newTenantTypeDescription' => 'description',
        ];
    }

    // ─────────────────────────────────────────────────────
    //  Field hooks
    // ─────────────────────────────────────────────────────

    public function updatedName($value): void
    {
        $this->name = trim((string) $value);
        if (! $this->slugEditable) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function updated(string $property): void
    {
        $trimFields = [
            'name', 'address', 'barangay', 'city', 'province', 'description',
            'admin_name', 'public_email', 'admin_email', 'contact_number',
            'business_registration_number', 'tin_number', 'owner_id_number',
            'newTenantTypeName', 'newTenantTypeDescription',
        ];

        if (in_array($property, $trimFields, true)) {
            $this->$property = trim((string) $this->$property);
        }

        if ($property === 'contact_number') {
            $this->contact_number = substr(preg_replace('/[^0-9]/', '', $this->contact_number), 0, 11);
        }

        if (preg_match('/^markers\.\d+\.type$/', $property)) {
            $this->mapVersion++;
        }

        $liveValidated = ['slug', 'public_email', 'admin_email', 'contact_number'];
        if (in_array($property, $liveValidated, true) && $this->$property !== '') {
            $this->validateOnly($property);
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

    // ─────────────────────────────────────────────────────
    //  Asset uploads — routed through HandlesImageUploads
    //  so every image is compressed against its config context:
    //    logo         → 'tenant-logo'  (512 KB / 1024×1024)
    //    cover_photo  → 'tenant-cover' (2 MB  / 2560×1440)
    //    admin_avatar → 'avatars'      (512 KB / 800×800)
    // ─────────────────────────────────────────────────────

    public function updatedLogo(): void
    {
        $this->storeAsset(
            uploadedFile: $this->logo,
            pathProperty: 'logo_path',
            folder: 'tenant-logos',
            mimeRules: ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
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
            mimeRules: ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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

        $oldPath = $this->{$pathProperty};

        try {
            // storeImage() → HandlesImageUploads → ImageCompressionService
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
                'tenant_id' => $this->tenantRecord->id,
                'folder'    => $folder,
                'error'     => $e->getMessage(),
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

    // ─────────────────────────────────────────────────────
    //  Document upload / removal
    // ─────────────────────────────────────────────────────

    public function updatedDocumentUploads($value, $key): void
    {
        $documentType = (string) $key;

        if (!$value) {
            unset($this->document_uploads[$documentType]);
            return;
        }

        $allowed = array_merge($this->requiredDocuments, $this->optionalDocuments);
        if (!in_array($documentType, $allowed, true)) {
            unset($this->document_uploads[$documentType]);
            return;
        }

        if (!$this->businessApplicationId) {
            try {
                $app = $this->ensureBusinessApplication();
                $this->businessApplicationId = $app->id;
            } catch (\Throwable $e) {
                Log::error('Failed to create BusinessApplication for tenant', [
                    'tenant_id' => $this->tenantRecord->id,
                    'error'     => $e->getMessage(),
                ]);
                unset($this->document_uploads[$documentType]);
                $this->dispatch('toast', message: 'Could not prepare the KYB record. Please save the tenant first.', type: 'error');
                return;
            }
        }

        try {
            $this->validate([
                "document_uploads.{$documentType}" => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            ], [
                "document_uploads.{$documentType}.max"   => 'Documents must be 10 MB or smaller.',
                "document_uploads.{$documentType}.mimes" => 'Only PDF, JPG, PNG, and WEBP are accepted.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            unset($this->document_uploads[$documentType]);
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: $first ?: 'Invalid file.', type: 'error');
            return;
        }

        try {
            $service = app(BusinessApplicationService::class);
            $application = BusinessApplication::findOrFail($this->businessApplicationId);
            $user = Auth::user();

            DB::transaction(function () use ($application, $documentType, $value, $service, $user): void {
                $application->documents()
                    ->ofType($documentType)
                    ->get()
                    ->each(fn ($doc) => $doc->delete());

                $service->attachDocument(
                    $application,
                    $user,
                    $documentType,
                    $value,
                    [
                        'document_number' => $documentType === BusinessDocument::TYPE_BIR_2303
                            ? $this->tin_number
                            : null,
                        'issued_at'  => null,
                        'expires_at' => $documentType === BusinessDocument::TYPE_MAYORS_PERMIT
                            ? now()->addYear()
                            : null,
                    ],
                );
            });

            unset($this->document_uploads[$documentType]);
            unset($this->documentsByType, $this->existingDocumentCount, $this->uploadedRequiredCount);

            $this->dispatch('toast', message: 'Document uploaded and watermarked.', type: 'success');
        } catch (\Throwable $e) {
            unset($this->document_uploads[$documentType]);
            Log::error('KYB document upload failed (edit)', [
                'tenant_id'     => $this->tenantRecord->id,
                'document_type' => $documentType,
                'error'         => $e->getMessage(),
            ]);
            $this->dispatch('toast', message: 'Upload failed. Please try again.', type: 'error');
        }
    }

    public function deleteDocument(int $documentId): void
    {
        $doc = BusinessDocument::find($documentId);

        if (!$doc) {
            return;
        }

        if ((int) $doc->business_application_id !== (int) $this->businessApplicationId) {
            abort(403);
        }

        try {
            $doc->delete();
            unset($this->documentsByType, $this->existingDocumentCount, $this->uploadedRequiredCount);
            $this->dispatch('toast', message: 'Document removed.', type: 'info');
        } catch (\Throwable $e) {
            Log::error('KYB document delete failed', [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);
            $this->dispatch('toast', message: 'Could not remove the document.', type: 'error');
        }
    }

    protected function ensureBusinessApplication(): BusinessApplication
    {
        return DB::transaction(function () {
            $existing = BusinessApplication::where('approved_tenant_id', $this->tenantRecord->id)
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            $admin = $this->adminUserRecordId
                ? User::find($this->adminUserRecordId)
                : User::where('tenant_id', $this->tenantRecord->id)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                    ->first();

            return BusinessApplication::create([
                'user_id'           => $admin?->id ?? Auth::id(),
                'business_name'     => $this->tenantRecord->name,
                'type_of_tenant_id' => $this->tenantRecord->type_of_tenant_id,
                'logo_path'         => $this->tenantRecord->logo,
                'contact_email'     => $this->tenantRecord->email,
                'contact_phone'     => $this->tenantRecord->contact_number,
                'address'           => $this->tenantRecord->address,
                'coordinates'       => $this->tenantRecord->coordinates,
                'status'            => BusinessApplication::STATUS_APPROVED,
                'source'            => BusinessApplication::SOURCE_SUPERADMIN_DIRECT,
                'approved_tenant_id' => $this->tenantRecord->id,
                'submitted_at'      => now(),
                'reviewed_at'       => now(),
                'reviewed_by'       => Auth::id(),
            ]);
        });
    }

    // ─────────────────────────────────────────────────────
    //  Reverse geocode
    // ─────────────────────────────────────────────────────

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
            Log::warning('Reverse geocode failed (tenant edit)', [
                'tenant_id' => $this->tenantRecord->id,
                'lat'       => $lat,
                'lng'       => $lng,
                'error'     => $e->getMessage(),
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

    // ─────────────────────────────────────────────────────
    //  Map interactions
    // ─────────────────────────────────────────────────────

    public function setLocationMode(string $mode): void
    {
        if (in_array($mode, ['main', 'nearby'], true)) {
            $this->locationMode = $mode;
            $this->selectedMarkerIndex = null;
            $this->mapVersion++;
        }
    }

    public function addMarker(): void
    {
        $this->addMarkerAt($this->mapView['lat'], $this->mapView['lng']);
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

    protected function addMarkerAt(float|string $lat, float|string $lng): void
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
            'lat' => round((float) $lat, 6),
            'lng' => round((float) $lng, 6),
            'zoom' => 15,
        ];
        $this->dispatch('toast', message: 'Nearby place added. Please set its category.', type: 'info');
    }

    #[On('map:click')]
    public function onMapClick(float|string $lat, float|string $lng): void
    {
        if ($this->locationMode === 'main') {
            $this->latitude = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => $this->mapView['zoom']];
            $this->mapVersion++;
            $this->resolveAddress($lat, $lng);
        } else {
            $this->addMarkerAt($lat, $lng);
        }
    }

    #[On('map:marker-drag-end')]
    public function onMarkerDragEnd(string $id, float|string $lat, float|string $lng): void
    {
        if ($id === 'main-marker' && $this->locationMode === 'main') {
            $this->latitude = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => $this->mapView['zoom']];
            $this->mapVersion++;
            $this->resolveAddress($lat, $lng);
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
    public function onMarkerClicked(string $id, float|string $lat, float|string $lng): void
    {
        if (str_starts_with($id, 'sub-marker-')) {
            $this->selectedMarkerIndex = (int) substr($id, strlen('sub-marker-'));
            $this->locationMode = 'nearby';
            $this->mapVersion++;
        } elseif ($id === 'main-marker') {
            $this->locationMode = 'main';
            $this->selectedMarkerIndex = null;
            $this->mapVersion++;
        }
    }

    #[On('map:center-changed')]
    public function onMapCenterChanged(float|string $lat, float|string $lng): void
    {
        $this->mapView['lat'] = round((float) $lat, 6);
        $this->mapView['lng'] = round((float) $lng, 6);

        if ($this->locationMode === 'main') {
            $this->latitude = $this->mapView['lat'];
            $this->longitude = $this->mapView['lng'];
        }
    }

    #[On('map:zoom-changed')]
    public function onMapZoomChanged(int|string $zoom): void
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
    public function onGeolocationResult(float|string $lat, float|string $lng): void
    {
        if ($this->locationMode === 'main') {
            $this->latitude = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView = ['lat' => $this->latitude, 'lng' => $this->longitude, 'zoom' => 16];
            $this->mapVersion++;
            $this->resolveAddress($lat, $lng);
        } else {
            $this->addMarkerAt($lat, $lng);
        }
    }

    // ─────────────────────────────────────────────────────
    //  Save
    // ─────────────────────────────────────────────────────

    public function update()
    {
        $this->validate($this->rules(), $this->messages(), $this->validationAttributes());

        $oldLogoPath     = $this->tenantRecord->logo;
        $passwordChanged = false;

        try {
            DB::transaction(function () use (&$passwordChanged, $oldLogoPath) {
                $tenant = Tenant::query()
                    ->whereKey($this->tenantRecord->id)
                    ->lockForUpdate()
                    ->first();

                if (! $tenant) {
                    throw new \RuntimeException('Tenant no longer exists.');
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
                    'is_recommended'    => $this->is_recommended,
                ]);

                TenantSetting::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'key' => 'business_info'],
                    ['value' => [
                        'description'   => $this->description,
                        'opening_hours' => [
                            'opening' => $this->open_24_hours ? null : $this->opening_time,
                            'closing' => $this->open_24_hours ? null : $this->closing_time,
                            'is_24hr' => $this->open_24_hours,
                        ],
                        'barangay' => $this->barangay,
                        'city'     => $this->city,
                        'province' => $this->province,
                    ]],
                );

                if ($this->adminUserRecordId) {
                    $admin = User::find($this->adminUserRecordId);

                    if ($admin) {
                        $admin->update([
                            'name'   => $this->admin_name,
                            'email'  => $this->admin_email,
                            'avatar' => $this->admin_avatar_path,
                        ]);

                        if ($this->admin_password) {
                            $admin->update(['password' => Hash::make($this->admin_password)]);
                            $passwordChanged = true;
                        }

                        if (! $admin->hasRole('tourist')) {
                            $admin->assignRole('tourist');
                        }
                    }
                } else {
                    $newAdmin = User::create([
                        'name'        => $this->admin_name,
                        'email'       => $this->admin_email,
                        'password'    => Hash::make($this->admin_password ?: Str::password(16)),
                        'tenant_id'   => $tenant->id,
                        'active_mode' => User::MODE_BUSINESS,
                        'is_active'   => true,
                        'avatar'      => $this->admin_avatar_path,
                    ]);

                    $newAdmin->syncRoles(['tourist', 'admin']);
                    $this->adminUserRecordId = $newAdmin->id;

                    if ($this->admin_password) {
                        $passwordChanged = true;
                    }
                }

                $application = $this->businessApplicationId
                    ? BusinessApplication::find($this->businessApplicationId)
                    : null;

                if (!$application) {
                    $application = BusinessApplication::create([
                        'user_id'            => $this->adminUserRecordId,
                        'business_name'      => $tenant->name,
                        'type_of_tenant_id'  => $tenant->type_of_tenant_id,
                        'logo_path'          => $this->logo_path,
                        'cover_photo_path'   => $this->cover_photo_path,
                        'owner_avatar_path'  => $this->admin_avatar_path,
                        'contact_email'      => $tenant->email,
                        'contact_phone'      => $tenant->contact_number,
                        'address'            => $tenant->address,
                        'coordinates'        => $coordinates,
                        'status'             => BusinessApplication::STATUS_APPROVED,
                        'source'             => BusinessApplication::SOURCE_SUPERADMIN_DIRECT,
                        'approved_tenant_id' => $tenant->id,
                        'submitted_at'       => now(),
                        'reviewed_at'        => now(),
                        'reviewed_by'        => Auth::id(),
                    ]);

                    $this->businessApplicationId = $application->id;
                }

                $application->update([
                    'business_name'                => $this->name,
                    'business_type'                => $this->business_type,
                    'type_of_tenant_id'            => $this->type_of_tenant_id,
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
                ]);
            });

            if ($oldLogoPath && $oldLogoPath !== $this->logo_path && Storage::disk('public')->exists($oldLogoPath)) {
                Storage::disk('public')->delete($oldLogoPath);
            }

            if ($passwordChanged && $this->admin_password) {
                try {
                    Mail::to($this->admin_email)->send(new TenantAdminPasswordReset(
                        adminName:    $this->admin_name,
                        adminEmail:   $this->admin_email,
                        newPassword:  $this->admin_password,
                        businessName: $this->name,
                        loginUrl:     route('login'),
                    ));
                } catch (\Throwable $mailError) {
                    Log::warning('Tenant password reset email failed', [
                        'tenant_id' => $this->tenantRecord->id,
                        'email'     => $this->admin_email,
                        'error'     => $mailError->getMessage(),
                    ]);
                }
            }

            session()->flash('message', $passwordChanged
                ? 'Business details updated. Password reset email sent to the admin.'
                : 'Business details successfully updated!');

            return $this->redirectRoute('superadmin.tenants.index', navigate: true);

        } catch (\Throwable $e) {
            Log::error('Tenant update failed', [
                'tenant_id' => $this->tenantRecord->id,
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            $this->dispatch('toast', message: 'Something went wrong while updating the tenant. Please try again.', type: 'error');
        }
    }

    // ─────────────────────────────────────────────────────
    //  Add Category modal
    // ─────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────
    //  Add Business Type modal
    // ─────────────────────────────────────────────────────

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

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    
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
                    'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300': toast.type === 'success',
                    'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300': toast.type === 'error',
                    'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                }"
            >
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    
    <div wire:loading.delay.longer wire:target="update"
         class="fixed inset-0 z-40 bg-white/60 dark:bg-gray-900/60 backdrop-blur-sm flex items-center justify-center pointer-events-none">
        <div class="flex items-center gap-3 bg-white dark:bg-gray-800 rounded-2xl shadow-xl px-6 py-4 pointer-events-auto">
            <svg class="animate-spin h-5 w-5 text-primary-600 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Saving changes…</span>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 border-l-4 border-l-emerald-500 p-4 rounded-md text-sm text-emerald-700 dark:text-emerald-300 font-medium">
            <?php echo e(session('message')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform · Edit Business</span>
            </div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit Tenant</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Update business information, legal details, documents, and the admin account.
            </p>
        </div>
        <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to tenants
        </a>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10 p-4 text-sm text-rose-700 dark:text-rose-300">
            <p class="font-semibold mb-1">Please fix <?php echo e($errors->count()); ?> field<?php echo e($errors->count() === 1 ? '' : 's'); ?> before saving:</p>
            <ul class="list-disc list-inside space-y-0.5">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li><?php echo e($error); ?></li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ul>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form wire:submit="update" class="space-y-5">

        
        <section class="card p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Cover Photo <span class="text-gray-400 font-normal">(optional)</span></span>
            </div>

            <div class="relative aspect-[3/1] w-full rounded-xl overflow-hidden bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->coverPreviewUrl()): ?>
                    <img src="<?php echo e($this->coverPreviewUrl()); ?>" alt="Cover photo" loading="lazy" decoding="async" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-white/85">
                        <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-xs font-semibold uppercase tracking-wider">Cover photo</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                    <?php echo e($cover_photo_path ? 'Replace cover' : 'Upload cover photo'); ?>

                    <input type="file" id="cover-upload" x-ref="input" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cover_photo_path): ?>
                    <button type="button" wire:click="removeCoverPhoto" wire:confirm="Remove the cover photo?"
                            class="inline-flex items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 px-3 py-2 text-xs font-semibold hover:border-rose-400 hover:text-rose-600 transition">
                        Remove
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div wire:loading wire:target="cover_photo" class="flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400">
                    <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Uploading…
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['cover_photo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>

        
        <section class="card p-6">
            <div class="flex items-center gap-5">
                <div class="shrink-0 w-20 h-20 rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 flex items-center justify-center overflow-hidden">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->logoPreviewUrl()): ?>
                        <img src="<?php echo e($this->logoPreviewUrl()); ?>" alt="Business logo" loading="lazy" decoding="async" class="w-full h-full object-cover">
                    <?php else: ?>
                        <svg class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                            <?php echo e($logo_path ? 'Replace logo' : 'Upload logo'); ?>

                            <input type="file" id="logo-upload" x-ref="input" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="sr-only">
                        </label>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logo_path): ?>
                            <button type="button" wire:click="removeLogo" wire:confirm="Remove the logo?"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 px-3 py-2 text-xs font-semibold hover:border-rose-400 hover:text-rose-600 transition">
                                Remove
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div wire:loading wire:target="logo" class="flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400">
                            <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Uploading…
                        </div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['logo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </section>

        
        <section class="card p-6">
            <div class="flex items-center gap-3 mb-5">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Business Information
                </span>
            </div>

            <div class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business Name <span class="text-rose-500">*</span></label>
                        <input type="text" id="field-name" wire:model.live.debounce.300ms="name" class="input" maxlength="255">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="field-slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300">URL slug</label>
                            <button type="button" wire:click="toggleSlugEdit" class="text-xs font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                                <?php echo e($slugEditable ? 'Auto-generate' : 'Edit manually'); ?>

                            </button>
                        </div>
                        <div class="flex rounded-xl overflow-hidden border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-900">
                            <span class="py-2.5 px-3 bg-gray-200 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 border-r border-gray-300 dark:border-gray-600">spot/</span>
                            <input type="text" id="field-slug" wire:model.live.debounce.400ms="slug" <?php if(!$slugEditable): ?> readonly <?php endif; ?> class="flex-1 bg-transparent border-none py-2.5 px-4 text-sm outline-none <?php echo e($slugEditable ? 'text-gray-900 dark:text-white cursor-text' : 'text-gray-500 dark:text-gray-400 cursor-default'); ?>">
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['slug'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->tenantTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'type-'.e($type->id).''; ?>wire:key="type-<?php echo e($type->id); ?>" value="<?php echo e($type->id); ?>"><?php echo e($type->type); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['type_of_tenant_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label for="field-public-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Public Email <span class="text-rose-500">*</span></label>
                        <input type="email" id="field-public-email" wire:model.live.debounce.400ms="public_email" class="input" maxlength="255">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['public_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label for="field-contact" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Number</label>
                        <input type="tel" id="field-contact" inputmode="numeric" pattern="[0-9]*" maxlength="11"
                               wire:model.live.debounce.400ms="contact_number"
                               x-on:input="event.target.value = event.target.value.replace(/[^0-9]/g, '').slice(0, 11)"
                               class="input" placeholder="09xxxxxxxxx">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['contact_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <div>
                    <label for="field-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Short Description <span class="text-[10px] font-normal text-gray-400">(optional)</span></label>
                    <textarea id="field-description" wire:model="description" rows="3" class="textarea" placeholder="A short introduction to this business" maxlength="500"></textarea>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Business Hours</label>
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-600 dark:text-gray-300">
                            <input type="checkbox" wire:model.live="open_24_hours" class="rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                            Open 24 hours
                        </label>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 <?php echo e($open_24_hours ? 'opacity-50 pointer-events-none' : ''); ?>">
                        <div>
                            <label for="field-opening" class="text-xs text-gray-500 dark:text-gray-400">Opening time</label>
                            <input type="time" id="field-opening" wire:model="opening_time" class="input" <?php if($open_24_hours): ?> disabled <?php endif; ?>>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['opening_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label for="field-closing" class="text-xs text-gray-500 dark:text-gray-400">Closing time</label>
                            <input type="time" id="field-closing" wire:model="closing_time" class="input" <?php if($open_24_hours): ?> disabled <?php endif; ?>>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['closing_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        
        <section class="card p-6">
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
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->businessTypeLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['business_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                           placeholder="<?php echo e($this->registrationNumberPlaceholder); ?>"
                           x-on:input="
                               const up = $event.target.value.toUpperCase();
                               if (up !== $event.target.value) {
                                   $event.target.value = up;
                                   $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                               }
                           "
                           class="input font-mono">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['business_registration_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['tin_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <label for="field-owner-id-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Owner ID Type <span class="text-rose-500">*</span>
                    </label>
                    <select id="field-owner-id-type" wire:model.live="owner_id_type" class="select">
                        <option value="">— Select an accepted ID —</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->ownerIdTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['owner_id_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <label for="field-owner-id-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Owner ID Number <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="field-owner-id-number"
                           wire:model.blur="owner_id_number"
                           maxlength="<?php echo e($this->ownerIdMaxLength); ?>"
                           autocomplete="off"
                           placeholder="<?php echo e($this->ownerIdPlaceholder); ?>"
                           class="input font-mono">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['owner_id_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400"><?php echo e($this->ownerIdHint); ?></p>
                </div>

                <div class="md:col-span-2 sm:max-w-xs">
                    <label for="field-owner-birthdate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Owner Date of Birth <span class="text-[10px] font-normal text-gray-400">(optional)</span>
                    </label>
                    <input type="date"
                           id="field-owner-birthdate"
                           wire:model.blur="owner_birthdate"
                           max="<?php echo e(now()->subYears(18)->format('Y-m-d')); ?>"
                           min="1900-01-01"
                           class="input">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['owner_birthdate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </section>

        
        <section class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Required Documents
                    </span>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider
                    <?php echo e($this->uploadedRequiredCount === count($this->requiredDocuments)
                        ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700'); ?>">
                    <?php echo e($this->uploadedRequiredCount); ?> / <?php echo e(count($this->requiredDocuments)); ?>

                </span>
            </div>

            <div class="space-y-2.5">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->requiredDocuments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $docType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $existing     = $this->documentsByType[$docType] ?? null;
                        $label        = $this->documentLabels[$docType] ?? $docType;
                        $isOwnerIdDoc = $docType === BusinessDocument::TYPE_OWNER_ID;
                        $ownerIdLabel = $this->selectedOwnerIdLabel;
                    ?>
                    <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'req-slot-'.e($docType).''; ?>wire:key="req-slot-<?php echo e($docType); ?>"
                         class="border rounded-xl p-3.5 transition
                            <?php echo e($existing
                                ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-500/[0.04]'
                                : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900'); ?>">

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($existing): ?>
                            <div class="flex items-start gap-3">
                                <?php
                                    $url     = $this->documentUrl($existing);
                                    $isImage = $this->isImageDocument($existing);
                                ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isImage && $url): ?>
                                    <a href="<?php echo e($url); ?>" target="_blank" rel="noopener noreferrer"
                                       class="block shrink-0 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-primary-500 transition">
                                        <img src="<?php echo e($url); ?>" alt="" loading="lazy" decoding="async" class="w-12 h-12 object-cover">
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo e($url); ?>" target="_blank" rel="noopener noreferrer"
                                       class="shrink-0 w-12 h-12 rounded-lg bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 flex flex-col items-center justify-center text-rose-600 dark:text-rose-400 hover:border-rose-400 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="text-[8px] font-bold uppercase mt-0.5">PDF</span>
                                    </a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($label); ?></p>
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                        <?php echo e($existing->original_filename); ?>

                                    </p>
                                    <div class="mt-1.5 flex items-center gap-3">
                                        <a href="<?php echo e($url); ?>" target="_blank" rel="noopener noreferrer"
                                           class="text-[11px] font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                                            View
                                        </a>
                                        <span class="w-px h-3 bg-gray-300 dark:bg-gray-700"></span>
                                        <label for="file-replace-<?php echo e($docType); ?>" class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition cursor-pointer">
                                            Replace
                                            <input type="file"
                                                   id="file-replace-<?php echo e($docType); ?>"
                                                   wire:model="document_uploads.<?php echo e($docType); ?>"
                                                   accept=".pdf,.jpg,.jpeg,.png,.webp"
                                                   class="sr-only">
                                        </label>
                                        <span class="w-px h-3 bg-gray-300 dark:bg-gray-700"></span>
                                        <button type="button"
                                                wire:click="deleteDocument(<?php echo e($existing->id); ?>)"
                                                wire:confirm="Remove this document?"
                                                class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 transition">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <label for="file-<?php echo e($docType); ?>" class="flex items-center gap-3 cursor-pointer group">
                                <div class="shrink-0 w-9 h-9 rounded-lg bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 group-hover:border-primary-500 group-hover:text-primary-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($label); ?></p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isOwnerIdDoc && $ownerIdLabel): ?>
                                        <p class="mt-0.5 inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/30 rounded px-1.5 py-0.5">
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/>
                                            </svg>
                                            Should be your <?php echo e($ownerIdLabel); ?>

                                        </p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <input type="file"
                                       id="file-<?php echo e($docType); ?>"
                                       wire:model="document_uploads.<?php echo e($docType); ?>"
                                       accept=".pdf,.jpg,.jpeg,.png,.webp"
                                       class="sr-only">
                            </label>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div wire:loading wire:target="document_uploads.<?php echo e($docType); ?>" class="mt-2 flex items-center gap-2 text-[11px] text-primary-600 dark:text-primary-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Uploading…
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["document_uploads.{$docType}"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
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
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->optionalDocuments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $docType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $existing = $this->documentsByType[$docType] ?? null;
                            $label    = $this->documentLabels[$docType] ?? $docType;
                        ?>
                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'opt-slot-'.e($docType).''; ?>wire:key="opt-slot-<?php echo e($docType); ?>"
                             class="border rounded-xl p-3 transition
                                <?php echo e($existing
                                    ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-500/[0.04]'
                                    : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900'); ?>">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($existing): ?>
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <p class="flex-1 text-sm font-medium text-gray-900 dark:text-white truncate"><?php echo e($label); ?></p>
                                    <button type="button"
                                            wire:click="deleteDocument(<?php echo e($existing->id); ?>)"
                                            wire:confirm="Remove this document?"
                                            class="text-[11px] font-semibold text-gray-500 hover:text-rose-600 transition">
                                        Remove
                                    </button>
                                </div>
                            <?php else: ?>
                                <label for="file-opt-<?php echo e($docType); ?>" class="flex items-center gap-3 cursor-pointer group">
                                    <div class="shrink-0 w-8 h-8 rounded-lg bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 group-hover:border-primary-500 group-hover:text-primary-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </div>
                                    <p class="flex-1 text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo e($label); ?></p>
                                    <input type="file"
                                           id="file-opt-<?php echo e($docType); ?>"
                                           wire:model="document_uploads.<?php echo e($docType); ?>"
                                           accept=".pdf,.jpg,.jpeg,.png,.webp"
                                           class="sr-only">
                                </label>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </details>
        </section>

        
        <section class="card p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active / Pending</span>
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

        
        <section class="card p-6 space-y-4">
            <div class="flex items-center gap-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Location</span>
            </div>
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white -mt-3">Location &amp; Nearby Places</h2>

            <div class="flex flex-wrap gap-2">
                <button type="button"
                        wire:click="setLocationMode('main')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50
                               <?php echo e($locationMode === 'main' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'); ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Edit Main Location
                </button>
                <button type="button"
                        wire:click="setLocationMode('nearby')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50
                               <?php echo e($locationMode === 'nearby' ? 'bg-primary-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'); ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    Add / Edit Nearby Places
                </button>
                <button type="button" wire:click="openAddCategoryModal"
                        class="ml-auto inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 text-xs font-semibold border border-primary-200 dark:border-primary-500/30 hover:bg-primary-100 dark:hover:bg-primary-500/20 transition active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Category
                </button>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="useMyLocation" class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Use my location
                </button>
                <button type="button" wire:click="toggleSatellite" class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                    <?php echo e($satellite ? 'Street View' : 'Satellite'); ?>

                </button>
            </div>

            
            <div class="card overflow-hidden relative" style="height: 420px;">
                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tenant-edit-map-'.e($mapVersion).''; ?>wire:key="tenant-edit-map-<?php echo e($mapVersion); ?>">
                    <?php if (isset($component)) { $__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\Map::resolve(['center' => [(float)$mapView['lng'], (float)$mapView['lat']],'zoom' => $mapView['zoom'],'height' => '420px','provider' => $satellite ? 'custom' : 'carto-voyager','style' => $satellite ? route('map.satellite.style') : null,'lightStyle' => $satellite ? route('map.satellite.style') : null,'darkStyle' => $satellite ? route('map.satellite.style') : null,'theme' => 'auto','class' => 'h-full w-full','events' => ['click', 'marker-clicked', 'marker-drag-end']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\Map::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'tenant-edit-map']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                        <?php if (isset($component)) { $__componentOriginal30d4ce5150bc700b8142cf87b21ef225 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal30d4ce5150bc700b8142cf87b21ef225 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapControls::resolve(['zoom' => true,'compass' => true,'locate' => true,'fullscreen' => true,'scale' => true,'position' => 'top-right'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-controls'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapControls::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal30d4ce5150bc700b8142cf87b21ef225)): ?>
<?php $attributes = $__attributesOriginal30d4ce5150bc700b8142cf87b21ef225; ?>
<?php unset($__attributesOriginal30d4ce5150bc700b8142cf87b21ef225); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal30d4ce5150bc700b8142cf87b21ef225)): ?>
<?php $component = $__componentOriginal30d4ce5150bc700b8142cf87b21ef225; ?>
<?php unset($__componentOriginal30d4ce5150bc700b8142cf87b21ef225); ?>
<?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $markers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $marker): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $type = $marker['type'] ?? '';
                                $category = collect($this->markerCategories)->firstWhere('key', $type);
                                $color = $category['color'] ?? '#94a3b8';
                                $iconSvg = $category['icon_svg'] ?? null;
                            ?>
                            <?php if (isset($component)) { $__componentOriginalfdc07447b73c389f668e824ec2f32988 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfdc07447b73c389f668e824ec2f32988 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapMarker::resolve(['lat' => $marker['lat'],'lng' => $marker['lng'],'color' => $color,'id' => 'sub-marker-'.e($index).'','draggable' => $locationMode === 'nearby'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-marker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapMarker::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:key' => 'sub-marker-'.e($marker['uid']).'-'.e($marker['type']).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                <?php if (isset($component)) { $__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MarkerContent::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marker-content'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MarkerContent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                    <div class="relative flex h-10 w-10 items-center justify-center transform-gpu will-change-transform transition-transform duration-200 group-hover:scale-110 active:scale-95" style="cursor: pointer;">
                                        <svg class="absolute inset-0 size-10 drop-shadow-md fill-white dark:fill-gray-900 stroke-slate-400 dark:stroke-slate-600 stroke-1" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                        </svg>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($iconSvg): ?>
                                            <div class="absolute mb-1 size-[18px] text-gray-800 dark:text-white">
                                                <?php echo str_replace('<svg ', '<svg class="size-full stroke-current fill-none" ', $iconSvg); ?>

                                            </div>
                                        <?php else: ?>
                                            <span class="absolute mb-1 text-[10px] font-bold text-gray-800 dark:text-white">
                                                <?php echo e(strtoupper(substr($type, 0, 1))); ?>

                                            </span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5)): ?>
<?php $attributes = $__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5; ?>
<?php unset($__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5)): ?>
<?php $component = $__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5; ?>
<?php unset($__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MarkerPopup::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marker-popup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MarkerPopup::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                    <div class="p-2">
                                        <strong class="text-gray-900 dark:text-white"><?php echo e($marker['name']); ?></strong>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($category['label'] ?? 'Uncategorized'); ?></p>
                                    </div>
                                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce)): ?>
<?php $attributes = $__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce; ?>
<?php unset($__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce)): ?>
<?php $component = $__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce; ?>
<?php unset($__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce); ?>
<?php endif; ?>
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfdc07447b73c389f668e824ec2f32988)): ?>
<?php $attributes = $__attributesOriginalfdc07447b73c389f668e824ec2f32988; ?>
<?php unset($__attributesOriginalfdc07447b73c389f668e824ec2f32988); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfdc07447b73c389f668e824ec2f32988)): ?>
<?php $component = $__componentOriginalfdc07447b73c389f668e824ec2f32988; ?>
<?php unset($__componentOriginalfdc07447b73c389f668e824ec2f32988); ?>
<?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                        <?php if (isset($component)) { $__componentOriginalfdc07447b73c389f668e824ec2f32988 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfdc07447b73c389f668e824ec2f32988 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapMarker::resolve(['lat' => $latitude,'lng' => $longitude,'color' => '#ef4444','id' => 'main-marker','draggable' => $locationMode === 'main'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-marker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapMarker::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:key' => 'main-marker-'.e($latitude).'-'.e($longitude).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                            <?php if (isset($component)) { $__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MarkerContent::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marker-content'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MarkerContent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                <div class="relative flex items-center justify-center transform-gpu will-change-transform transition-transform duration-200 group-hover:scale-110 active:scale-95">
                                    <svg class="h-10 w-10 drop-shadow-lg" viewBox="0 0 24 24" fill="#ef4444" stroke="white" stroke-width="1.5" aria-hidden="true">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                                        <circle cx="12" cy="9" r="2.5" fill="white"/>
                                    </svg>
                                </div>
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5)): ?>
<?php $attributes = $__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5; ?>
<?php unset($__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5)): ?>
<?php $component = $__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5; ?>
<?php unset($__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5); ?>
<?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MarkerPopup::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marker-popup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MarkerPopup::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                <div class="p-2">
                                    <strong class="text-gray-900 dark:text-white">Main Location</strong>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($latitude); ?>, <?php echo e($longitude); ?></p>
                                </div>
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce)): ?>
<?php $attributes = $__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce; ?>
<?php unset($__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce)): ?>
<?php $component = $__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce; ?>
<?php unset($__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce); ?>
<?php endif; ?>
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfdc07447b73c389f668e824ec2f32988)): ?>
<?php $attributes = $__attributesOriginalfdc07447b73c389f668e824ec2f32988; ?>
<?php unset($__attributesOriginalfdc07447b73c389f668e824ec2f32988); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfdc07447b73c389f668e824ec2f32988)): ?>
<?php $component = $__componentOriginalfdc07447b73c389f668e824ec2f32988; ?>
<?php unset($__componentOriginalfdc07447b73c389f668e824ec2f32988); ?>
<?php endif; ?>
                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d)): ?>
<?php $attributes = $__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d; ?>
<?php unset($__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d)): ?>
<?php $component = $__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d; ?>
<?php unset($__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d); ?>
<?php endif; ?>
                </div>

                
                <div wire:loading wire:target="resolveAddress"
                     class="absolute bottom-3 left-3 z-10 pointer-events-none">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-900/85 backdrop-blur-sm text-white text-[11px] font-semibold px-3 py-1.5 shadow-lg">
                        <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Looking up address…
                    </span>
                </div>
            </div>

            
            <p class="text-[11px] font-mono text-gray-500 dark:text-gray-400 tabular-nums">
                <?php echo e(number_format($latitude, 6)); ?>, <?php echo e(number_format($longitude, 6)); ?>

            </p>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['latitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['longitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
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

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($locationMode === 'nearby'): ?>
                <div class="pt-3 border-t border-gray-100 dark:border-gray-700/60">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Nearby places <span class="text-gray-400 font-normal">(<?php echo e(count($markers)); ?>/20)</span>
                        </span>
                        <button type="button" wire:click="addMarker" class="text-xs font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                            + Add nearby place
                        </button>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($markers) > 0): ?>
                        <div class="flex flex-wrap items-center gap-3 mb-3 text-[11px] text-gray-500 dark:text-gray-400">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->markerCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <span <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'legend-'.e($cat['key']).''; ?>wire:key="legend-<?php echo e($cat['key']); ?>" class="inline-flex items-center gap-1">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background:<?php echo e($cat['color']); ?>"></span>
                                    <?php echo e($cat['label']); ?>

                                </span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                        <div class="space-y-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $markers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $marker): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php
                                    $type = $marker['type'] ?? '';
                                ?>
                                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'marker-row-'.e($marker['uid']).''; ?>wire:key="marker-row-<?php echo e($marker['uid']); ?>"
                                     class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700
                                            <?php echo e($selectedMarkerIndex === $index ? 'ring-2 ring-primary-500/40 border-primary-500/30' : ''); ?>">
                                    <input type="text" wire:model.debounce.500ms="markers.<?php echo e($index); ?>.name" placeholder="Place name" class="input !py-2 flex-1 min-w-[140px]">
                                    <input type="number" step="any" min="-90" max="90" wire:model.debounce.500ms="markers.<?php echo e($index); ?>.lat" placeholder="Lat" class="input !py-2 !w-28 font-mono">
                                    <input type="number" step="any" min="-180" max="180" wire:model.debounce.500ms="markers.<?php echo e($index); ?>.lng" placeholder="Lng" class="input !py-2 !w-28 font-mono">
                                    <select wire:model.live="markers.<?php echo e($index); ?>.type" class="select !py-2 !w-48 <?php echo e(empty($marker['type']) ? 'border-rose-300 dark:border-rose-500' : ''); ?>">
                                        <option value="">Select category *</option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->markerCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <option <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'cat-'.e($cat['key']).''; ?>wire:key="cat-<?php echo e($cat['key']); ?>" value="<?php echo e($cat['key']); ?>"><?php echo e($cat['label']); ?></option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                    <button type="button" wire:click="removeMarker(<?php echo e($index); ?>)" class="text-rose-500 hover:text-rose-700 active:scale-95 transition-transform" aria-label="Remove nearby place">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400">No nearby places yet. Click the map or use the button to add.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>

        
        <section class="card p-6 space-y-6">
            <div class="flex items-center gap-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Admin Account</span>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500 -mt-4">If you set a new password, the admin is notified by email automatically.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-admin-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin Full Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="field-admin-name" wire:model="admin_name" class="input" maxlength="255">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['admin_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <label for="field-admin-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin Login Email <span class="text-rose-500">*</span></label>
                    <input type="email" id="field-admin-email" wire:model.live.debounce.400ms="admin_email" class="input" maxlength="255">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['admin_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            
            <div
                x-data="avatarPreview()"
                x-on:avatar-preview.window="setUrl($event.detail.url)"
                x-on:avatar-cleared.window="clear()"
                class="flex items-center gap-5"
            >
                <div class="relative shrink-0 w-20 h-20">
                    <img
                        :src="previewUrl || '<?php echo e($this->avatarPreviewUrl() ?? ''); ?>'"
                        :class="(previewUrl || <?php echo e($this->avatarPreviewUrl() ? 'true' : 'false'); ?>) ? 'block' : 'hidden'"
                        class="w-20 h-20 rounded-full object-cover border-2 border-primary-500 shadow-md"
                        alt="Admin photo"
                        loading="eager"
                        decoding="async"
                    >
                    <div
                        :class="(previewUrl || <?php echo e($this->avatarPreviewUrl() ? 'true' : 'false'); ?>) ? 'hidden' : 'flex'"
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

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($admin_avatar_path): ?>
                        <button
                            type="button"
                            wire:click="removeAdminAvatar"
                            wire:confirm="Remove the photo?"
                            :class="previewUrl ? 'hidden' : 'inline-flex'"
                            class="items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 px-3 py-2 text-xs font-semibold hover:border-rose-400 hover:text-rose-600 transition"
                        >
                            Remove photo
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['admin_avatar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-100 dark:border-gray-700/60">
                <div>
                    <label for="field-admin-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New password <span class="text-[10px] font-normal text-gray-400">(leave blank to keep current)</span></label>
                    <input type="password" id="field-admin-password" wire:model="admin_password" class="input" autocomplete="new-password">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['admin_password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <label for="field-admin-password-confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm new password</label>
                    <input type="password" id="field-admin-password-confirmation" wire:model="admin_password_confirmation" class="input" autocomplete="new-password">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['admin_password_confirmation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </section>

        
        <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit" wire:loading.attr="disabled" wire:target="update"
                    class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove wire:target="update">Save Changes</span>
                <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
        </div>
    </form>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showAddCategoryModal): ?>
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
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newCategoryKey'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Label</label>
                        <input type="text" wire:model="newCategoryLabel" class="input" placeholder="Restaurant" maxlength="100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newCategoryLabel'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                        <input type="color" wire:model="newCategoryColor" class="h-10 w-full rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newCategoryColor'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Icon (SVG)</label>
                        <input type="file" wire:model="newCategoryIcon" accept=".svg" class="input">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newCategoryIcon'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showNewTenantTypeModal): ?>
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
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newTenantTypeName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <label for="field-new-tenant-type-desc" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Description <span class="text-gray-400 dark:text-gray-500 font-normal">(Optional)</span>
                        </label>
                        <textarea id="field-new-tenant-type-desc" wire:model="newTenantTypeDescription" rows="3" class="textarea" placeholder="Briefly describe this type" maxlength="1000"></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newTenantTypeDescription'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if (isset($component)) { $__componentOriginalb992f09e6b42df8ffd0c7230daf18927 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb992f09e6b42df8ffd0c7230daf18927 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.image-crop-modal','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('image-crop-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb992f09e6b42df8ffd0c7230daf18927)): ?>
<?php $attributes = $__attributesOriginalb992f09e6b42df8ffd0c7230daf18927; ?>
<?php unset($__attributesOriginalb992f09e6b42df8ffd0c7230daf18927); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb992f09e6b42df8ffd0c7230daf18927)): ?>
<?php $component = $__componentOriginalb992f09e6b42df8ffd0c7230daf18927; ?>
<?php unset($__componentOriginalb992f09e6b42df8ffd0c7230daf18927); ?>
<?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\superadmin\pages\tenant\⚡edit-tenant.blade.php ENDPATH**/ ?>