{{-- resources/views/public/pages/⚡edit-business-application.blade.php --}}
<?php

use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\SiteSetting;
use App\Models\TypeOfTenant;
use App\Services\BusinessApplicationService;
use App\Services\ReverseGeocodeService;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Title('Business Application')]
class extends Component
{
    use WithFileUploads;
    use HandlesImageUploads;

    #[Locked]
    public BusinessApplication $application;

    #[Url(keep: true)]
    public int $step = 1;

    public const TOTAL_STEPS = 3;

    public string $business_name        = '';
    public string $business_type        = '';
    public string $type_of_tenant_id    = '';
    public string $business_description = '';

    public $logo = null;
    public ?string $logo_path = null;

    public $cover_photo = null;
    public ?string $cover_photo_path = null;

    public string $address  = '';
    public string $barangay = '';
    public string $city     = '';
    public string $province = '';

    public ?float $businessLat = null;
    public ?float $businessLng = null;

    public int $locationVersion = 0;

    public string $business_registration_number = '';
    public string $tin_number                   = '';

    /** @var array<string, mixed> */
    public array $uploads = [];

    public string $owner_full_name = '';
    public string $owner_id_type   = '';
    public string $owner_id_number = '';
    public string $owner_birthdate = '';

    public $owner_avatar = null;
    public ?string $owner_avatar_path = null;

    public string $contact_email = '';
    public string $contact_phone = '';

    public ?string $saveError   = null;
    public ?string $submitError = null;

    public function mount(BusinessApplication $application): void
    {
        $this->assertOwnership($application);

        $this->application = $application;

        $this->business_name                = (string) ($application->business_name ?? '');
        $this->business_type                = (string) ($application->business_type ?? '');
        $this->type_of_tenant_id            = (string) ($application->type_of_tenant_id ?? '');
        $this->business_registration_number = (string) ($application->business_registration_number ?? '');
        $this->tin_number                   = (string) ($application->tin_number ?? '');
        $this->business_description         = (string) ($application->metadata['description'] ?? '');
        $this->logo_path                    = $application->logo_path;
        $this->cover_photo_path             = $application->cover_photo_path;

        $this->owner_full_name   = (string) ($application->owner_full_name ?? '');
        $this->owner_id_type     = (string) ($application->owner_id_type ?? '');
        $this->owner_id_number   = (string) ($application->owner_id_number ?? '');
        $this->owner_birthdate   = $application->owner_birthdate?->format('Y-m-d') ?? '';
        $this->owner_avatar_path = $application->owner_avatar_path;

        $this->contact_email = (string) ($application->contact_email ?? '');
        $this->contact_phone = (string) ($application->contact_phone ?? '');

        $this->address  = (string) ($application->address ?? '');
        $this->barangay = (string) ($application->barangay ?? '');
        $this->city     = (string) ($application->city ?? '');
        $this->province = (string) ($application->province ?? '');

        $coords = $application->coordinates ?? [];
        if (isset($coords[0]['lat'], $coords[0]['lng'])) {
            $this->businessLat = (float) $coords[0]['lat'];
            $this->businessLng = (float) $coords[0]['lng'];
        }

        $this->lockOwnerFieldsIfFollowUp();

        $this->step = max(1, min(self::TOTAL_STEPS, $this->step));
    }

    /**
     * Conditional layout:
     *   First-time applicant    → layouts.auth        (focused public flow)
     *   Existing business owner → tenant.layouts.app  (inside the admin shell)
     *
     * Follow-up form is an admin action, not a marketing surface.
     * Rendering it inside the tenant shell keeps the sidebar and tenant
     * header visible so the owner never loses the sense of being inside
     * their dashboard.
     */
    public function render()
    {
        return $this->view()->layout(
            $this->isFollowUpApplication
                ? 'tenant.layouts.app'
                : 'layouts.auth'
        );
    }

    public function hydrate(): void
    {
        $this->assertOwnership($this->application);

        $this->lockOwnerFieldsIfFollowUp();
    }

    protected function assertOwnership(BusinessApplication $application): void
    {
        abort_unless(Auth::check(), 403, 'Your session has expired. Please sign in again.');
        abort_unless($application->user_id === Auth::id(), 403);
        abort_unless($application->isEditable(), 403, 'This application is not editable.');
    }

    protected function requireOwnership(): void
    {
        $this->assertOwnership($this->application);
    }

    protected function storageUrl(?string $path): ?string
    {
        if (! $path) return null;
        return '/storage/' . ltrim($path, '/');
    }

    #[Computed]
    public function isFollowUpApplication(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        /** @var BusinessApplication|null $previous */
        $previous = BusinessApplication::query()
            ->where('user_id', Auth::id())
            ->where('status', BusinessApplication::STATUS_APPROVED)
            ->whereKeyNot($this->application->id)
            ->latest('reviewed_at')
            ->first();

        if (! $previous) {
            return false;
        }

        $user = Auth::user();

        $resolved = [
            $previous->owner_full_name ?: $user?->name,
            $previous->owner_id_type,
            $previous->owner_id_number,
            $previous->contact_email ?: $user?->email,
            $previous->contact_phone ?: $user?->phone,
        ];

        foreach ($resolved as $value) {
            if (empty($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *     owner_full_name: string,
     *     owner_id_type: string,
     *     owner_id_number: string,
     *     owner_birthdate: string,
     *     owner_avatar_path: ?string,
     *     contact_email: string,
     *     contact_phone: string,
     * }|null
     */
    #[Computed]
    public function lockedOwnerData(): ?array
    {
        if (! $this->isFollowUpApplication) {
            return null;
        }

        /** @var BusinessApplication|null $previous */
        $previous = BusinessApplication::query()
            ->where('user_id', Auth::id())
            ->where('status', BusinessApplication::STATUS_APPROVED)
            ->whereKeyNot($this->application->id)
            ->latest('reviewed_at')
            ->first();

        if (! $previous) {
            return null;
        }

        $user = Auth::user();

        return [
            'owner_full_name'   => (string) ($previous->owner_full_name ?: $user?->name ?? ''),
            'owner_id_type'     => (string) ($previous->owner_id_type ?? ''),
            'owner_id_number'   => (string) ($previous->owner_id_number ?? ''),
            'owner_birthdate'   => $previous->owner_birthdate?->format('Y-m-d') ?? '',
            'owner_avatar_path' => $previous->owner_avatar_path,
            'contact_email'     => (string) ($previous->contact_email ?: $user?->email ?? ''),
            'contact_phone'     => (string) ($previous->contact_phone ?: $user?->phone ?? ''),
        ];
    }

    protected function lockOwnerFieldsIfFollowUp(): void
    {
        $locked = $this->lockedOwnerData;

        if ($locked === null) {
            return;
        }

        $this->owner_full_name   = $locked['owner_full_name'];
        $this->owner_id_type     = $locked['owner_id_type'];
        $this->owner_id_number   = $locked['owner_id_number'];
        $this->owner_birthdate   = $locked['owner_birthdate'];
        $this->owner_avatar_path = $locked['owner_avatar_path'];
        $this->contact_email     = $locked['contact_email'];
        $this->contact_phone     = $locked['contact_phone'];
    }

    #[Computed]
    public function siteName(): string
    {
        return (string) SiteSetting::getValue('site_name', config('app.name'));
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        return $this->storageUrl(SiteSetting::getValue('site_logo'));
    }

    /** @return array<int, string> */
    #[Computed]
    public function stepLabels(): array
    {
        return [
            1 => 'Business & Location',
            2 => 'Documents & Verification',
            3 => 'Owner & Review',
        ];
    }

    #[Computed]
    public function isRevision(): bool
    {
        return $this->application->status === BusinessApplication::STATUS_NEEDS_REVISION
            && ! empty($this->application->revision_notes);
    }

    #[Computed]
    public function tenantTypes()
    {
        return TypeOfTenant::query()->orderBy('type')->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function ownerIdTypes(): array
    {
        return BusinessApplication::OWNER_ID_TYPES;
    }

    /** @return array<string, string> */
    #[Computed]
    public function businessTypeLabels(): array
    {
        return BusinessApplication::BUSINESS_TYPE_LABELS;
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
    public function documents()
    {
        return $this->application->documents()
            ->orderBy('document_type')
            ->get();
    }

    #[Computed]
    public function documentsByType(): array
    {
        return $this->documents->keyBy('document_type')->all();
    }

    #[Computed]
    public function requiredDocuments(): array
    {
        return BusinessApplication::REQUIRED_DOCUMENTS;
    }

    #[Computed]
    public function optionalDocuments(): array
    {
        return BusinessApplication::OPTIONAL_DOCUMENTS;
    }

    #[Computed]
    public function documentLabels(): array
    {
        return BusinessApplication::DOCUMENT_LABELS;
    }

    /** @return array<int, string> */
    #[Computed]
    public function uploadedDocumentTypes(): array
    {
        return $this->documents
            ->pluck('document_type')
            ->unique()
            ->values()
            ->all();
    }

    #[Computed]
    public function hasAllRequiredDocuments(): bool
    {
        return empty(array_diff($this->requiredDocuments, $this->uploadedDocumentTypes));
    }

    #[Computed]
    public function completionPercent(): int
    {
        $checks = [
            ! empty($this->business_name),
            ! empty($this->business_type),
            ! empty($this->type_of_tenant_id),
            ! empty($this->business_registration_number),
            ! empty($this->tin_number),
            ! empty($this->owner_full_name),
            ! empty($this->owner_id_type),
            ! empty($this->owner_id_number),
            ! empty($this->contact_email),
            ! empty($this->contact_phone),
            $this->hasAllRequiredDocuments,
        ];

        $done = count(array_filter($checks));

        return (int) round(($done / count($checks)) * 100);
    }

    #[Computed]
    public function isReadyToSubmit(): bool
    {
        foreach ([
            'business_name',
            'business_type',
            'business_registration_number',
            'tin_number',
            'owner_full_name',
            'owner_id_type',
            'owner_id_number',
            'contact_email',
            'contact_phone',
        ] as $field) {
            if (empty($this->{$field})) {
                return false;
            }
        }

        if (! $this->type_of_tenant_id) {
            return false;
        }

        return $this->hasAllRequiredDocuments;
    }

    #[Computed]
    public function uploadedRequiredCount(): int
    {
        return $this->documents
            ->whereIn('document_type', $this->requiredDocuments)
            ->count();
    }

    /** @return array<int, string> */
    #[Computed]
    public function missingRequiredDocuments(): array
    {
        $missing = array_diff($this->requiredDocuments, $this->uploadedDocumentTypes);
        $labels  = BusinessApplication::DOCUMENT_LABELS;

        return array_values(array_map(
            fn (string $type) => $labels[$type] ?? $type,
            $missing,
        ));
    }

    /**
     * @return array{kind: string, step: int, message: string}
     */
    #[Computed]
    public function submitBlocker(): array
    {
        if (empty($this->business_name) || empty($this->business_type) || empty($this->type_of_tenant_id)) {
            return [
                'kind'    => 'business',
                'step'    => 1,
                'message' => 'Finish your business details on Step 1.',
            ];
        }

        if (empty($this->business_registration_number) || empty($this->tin_number)) {
            return [
                'kind'    => 'registration',
                'step'    => 2,
                'message' => 'Add your registration number and TIN on Step 2.',
            ];
        }

        $missingDocs = $this->missingRequiredDocuments;
        if (count($missingDocs) > 0) {
            return [
                'kind'    => 'documents',
                'step'    => 2,
                'message' => 'Upload the remaining required document'
                    . (count($missingDocs) === 1 ? '' : 's')
                    . ' on Step 2: ' . implode(', ', $missingDocs) . '.',
            ];
        }

        if (
            empty($this->owner_full_name)
            || empty($this->owner_id_type)
            || empty($this->owner_id_number)
            || empty($this->contact_email)
            || empty($this->contact_phone)
        ) {
            return [
                'kind'    => 'owner',
                'step'    => 3,
                'message' => 'Complete your owner and contact details on Step 3.',
            ];
        }

        return [
            'kind'    => 'other',
            'step'    => 3,
            'message' => 'Complete every required field to continue.',
        ];
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
    public function estimatedMinutesLeft(): int
    {
        $pct = $this->completionPercent;

        return match (true) {
            $pct >= 100 => 0,
            $pct >= 80  => 2,
            $pct >= 60  => 3,
            $pct >= 40  => 5,
            $pct >= 20  => 7,
            default     => 10,
        };
    }

    #[Computed]
    public function mapCenter(): array
    {
        if ($this->businessLat !== null && $this->businessLng !== null) {
            return [(float) $this->businessLng, (float) $this->businessLat];
        }

        return [123.07391289720677, 10.900736693923502];
    }

    #[Computed]
    public function mapZoom(): int
    {
        return ($this->businessLat !== null && $this->businessLng !== null) ? 16 : 12;
    }

    #[Computed]
    public function hasCoordinates(): bool
    {
        return $this->businessLat !== null && $this->businessLng !== null;
    }

    #[Computed]
    public function applicantFirstName(): string
    {
        $name = trim((string) (Auth::user()?->name ?? 'there'));
        $parts = preg_split('/\s+/', $name) ?: [];

        return $parts[0] ?: 'there';
    }

    public function setBusinessLocation($lat, $lng): void
    {
        $this->requireOwnership();

        $lat = (float) $lat;
        $lng = (float) $lng;

        if (!is_finite($lat) || !is_finite($lng)) {
            return;
        }
        if (abs($lat) > 90 || abs($lng) > 180) {
            return;
        }

        $this->businessLat = round($lat, 7);
        $this->businessLng = round($lng, 7);

        unset($this->hasCoordinates, $this->mapCenter, $this->mapZoom);

        $this->dispatch('map:fly-to', center: [(float) $this->businessLng, (float) $this->businessLat], zoom: 16);
    }

    public function resolveAddress(float $lat, float $lng): void
    {
        $this->requireOwnership();

        if (!is_finite($lat) || !is_finite($lng)) {
            return;
        }
        if (abs($lat) > 90 || abs($lng) > 180) {
            return;
        }

        try {
            $result = app(ReverseGeocodeService::class)->reverse($lat, $lng);
        } catch (\Throwable $e) {
            Log::warning('Reverse geocode failed', [
                'application_id' => $this->application->id,
                'lat'            => $lat,
                'lng'            => $lng,
                'error'          => $e->getMessage(),
            ]);
            return;
        }

        if ($result === null) {
            return;
        }

        if ($result['address'] !== '') {
            $this->address = $result['address'];
        }
        if ($result['barangay'] !== '') {
            $this->barangay = $result['barangay'];
        }
        if ($result['city'] !== '') {
            $this->city = $result['city'];
        }
        if ($result['province'] !== '') {
            $this->province = $result['province'];
        }
    }

    public function refreshAddressFromPin(): void
    {
        $this->requireOwnership();

        if ($this->businessLat === null || $this->businessLng === null) {
            $this->dispatch('toast', message: 'Drop a pin first.', type: 'error');
            return;
        }

        $this->address  = '';
        $this->barangay = '';
        $this->city     = '';
        $this->province = '';

        $this->resolveAddress((float) $this->businessLat, (float) $this->businessLng);

        $this->dispatch('toast', message: 'Address refreshed from pin.', type: 'success');
    }

    public function clearLocation(): void
    {
        $this->requireOwnership();

        $this->businessLat = null;
        $this->businessLng = null;

        $this->dispatch('map:pin-cleared');
        $this->dispatch('toast', message: 'Location cleared.', type: 'info');
    }

    public function useMyLocation(): void
    {
        $this->requireOwnership();

        $this->dispatch('request-geolocation');
    }

    public function updatedLogo(): void
    {
        $this->requireOwnership();

        if (!$this->logo) return;

        try {
            $this->validate([
                'logo' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ], [
                'logo.max'   => 'Logo must be 2 MB or smaller.',
                'logo.image' => 'Logo must be an image (JPG, PNG, or WEBP).',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->logo = null;
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: $first ?: 'Invalid logo.', type: 'error');
            return;
        }

        $this->replaceAsset(
            uploadedFile: $this->logo,
            column: 'logo_path',
            folder: "kyb-assets/{$this->application->id}/logo",
            previousPath: $this->application->logo_path,
            successMessage: 'Logo uploaded.',
            context: 'tenant-logo',
        );

        $this->logo = null;
    }

    public function removeLogo(): void
    {
        $this->requireOwnership();
        $this->removeAsset('logo_path', 'Logo removed.');
    }

    public function updatedCoverPhoto(): void
    {
        $this->requireOwnership();

        if (!$this->cover_photo) return;

        try {
            $this->validate([
                'cover_photo' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ], [
                'cover_photo.max'   => 'Cover photo must be 4 MB or smaller.',
                'cover_photo.image' => 'Cover photo must be an image (JPG, PNG, or WEBP).',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->cover_photo = null;
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: $first ?: 'Invalid cover photo.', type: 'error');
            return;
        }

        $this->replaceAsset(
            uploadedFile: $this->cover_photo,
            column: 'cover_photo_path',
            folder: "kyb-assets/{$this->application->id}/cover",
            previousPath: $this->application->cover_photo_path,
            successMessage: 'Cover photo uploaded.',
            context: 'tenant-cover',
        );

        $this->cover_photo = null;
    }

    public function removeCoverPhoto(): void
    {
        $this->requireOwnership();
        $this->removeAsset('cover_photo_path', 'Cover photo removed.');
    }

    public function updatedOwnerAvatar(): void
    {
        $this->requireOwnership();

        if ($this->isFollowUpApplication) {
            $this->owner_avatar = null;
            $this->dispatch('toast', message: 'Owner photo is locked to your verified record.', type: 'info');
            return;
        }

        if (!$this->owner_avatar) return;

        try {
            $this->validate([
                'owner_avatar' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ], [
                'owner_avatar.max'   => 'Photo must be 5 MB or smaller.',
                'owner_avatar.image' => 'Photo must be an image (JPG, PNG, or WEBP).',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->owner_avatar = null;
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: $first ?: 'Invalid photo.', type: 'error');
            return;
        }

        $this->replaceAsset(
            uploadedFile: $this->owner_avatar,
            column: 'owner_avatar_path',
            folder: "kyb-assets/{$this->application->id}/avatar",
            previousPath: $this->application->owner_avatar_path,
            successMessage: 'Photo uploaded.',
            context: 'avatars',
        );

        $this->owner_avatar = null;
    }

    public function removeOwnerAvatar(): void
    {
        $this->requireOwnership();

        if ($this->isFollowUpApplication) {
            $this->dispatch('toast', message: 'Owner photo is locked to your verified record.', type: 'info');
            return;
        }

        $this->removeAsset('owner_avatar_path', 'Photo removed.');
    }

    protected function replaceAsset(
        $uploadedFile,
        string $column,
        string $folder,
        ?string $previousPath,
        string $successMessage,
        string $context,
    ): void {
        $newPath = null;

        try {
            $newPath = $this->storeImage($uploadedFile, $folder, 'public', $context);

            if (!$newPath) {
                throw new \RuntimeException('Storage returned no path.');
            }

            DB::transaction(function () use ($newPath, $column): void {
                $this->application->update([$column => $newPath]);
            });

            if ($previousPath && Storage::disk('public')->exists($previousPath)) {
                Storage::disk('public')->delete($previousPath);
            }

            $localProperty = match ($column) {
                'logo_path'         => 'logo_path',
                'cover_photo_path'  => 'cover_photo_path',
                'owner_avatar_path' => 'owner_avatar_path',
                default             => null,
            };
            if ($localProperty !== null) {
                $this->$localProperty = $newPath;
            }

            $this->dispatch('toast', message: $successMessage, type: 'success');
        } catch (\Throwable $e) {
            if ($newPath && Storage::disk('public')->exists($newPath)) {
                Storage::disk('public')->delete($newPath);
            }

            Log::error('KYB asset upload failed', [
                'application_id' => $this->application->id,
                'column'         => $column,
                'error'          => $e->getMessage(),
            ]);

            $this->dispatch('toast', message: 'Upload failed. Try again.', type: 'error');
        }
    }

    protected function removeAsset(string $column, string $successMessage): void
    {
        $old = $this->application->{$column};

        try {
            $this->application->update([$column => null]);

            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }

            $localProperty = match ($column) {
                'logo_path'         => 'logo_path',
                'cover_photo_path'  => 'cover_photo_path',
                'owner_avatar_path' => 'owner_avatar_path',
                default             => null,
            };
            if ($localProperty !== null) {
                $this->$localProperty = null;
            }

            $this->dispatch('toast', message: $successMessage, type: 'info');
        } catch (\Throwable $e) {
            Log::error('KYB asset removal failed', [
                'application_id' => $this->application->id,
                'column'         => $column,
                'error'          => $e->getMessage(),
            ]);
            $this->dispatch('toast', message: 'Could not remove the file.', type: 'error');
        }
    }

    protected function stepRules(int $step): array
    {
        return match ($step) {
            1 => [
                'business_name'     => ['required', 'string', 'min:3', 'max:255'],
                'business_type'     => ['required', Rule::in(BusinessApplication::BUSINESS_TYPES)],
                'type_of_tenant_id' => ['required', 'integer', 'exists:type_of_tenants,id'],
                'business_description' => ['nullable', 'string', 'max:500'],

                'address'  => ['nullable', 'string', 'max:255'],
                'barangay' => ['nullable', 'string', 'max:255'],
                'city'     => ['nullable', 'string', 'max:255'],
                'province' => ['nullable', 'string', 'max:255'],

                'businessLat' => ['nullable', 'numeric', 'between:-90,90'],
                'businessLng' => ['nullable', 'numeric', 'between:-180,180'],
            ],
            2 => [
                'business_registration_number' => [
                    'required', 'string', 'min:4', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/',
                ],
                'tin_number' => [
                    'required', 'string', 'regex:/^\d{3}[-\s]?\d{3}[-\s]?\d{3}(?:[-\s]?\d{3})?$/',
                ],
            ],
            3 => $this->isFollowUpApplication ? [] : [
                'owner_full_name' => ['required', 'string', 'min:3', 'max:255'],
                'owner_id_type'   => ['required', Rule::in(array_keys(BusinessApplication::OWNER_ID_TYPES))],
                'owner_id_number' => ['required', 'string', 'min:4', 'max:100'],
                'owner_birthdate' => ['nullable', 'date', 'before:today'],
                'contact_email'   => ['required', 'email', 'max:255'],
                'contact_phone'   => [
                    'required', 'string', 'max:20',
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        $digits = preg_replace('/\D/', '', (string) $value);
                        if ($digits === '' || strlen($digits) < 7 || strlen($digits) > 15) {
                            $fail('Use a valid contact number (7–15 digits, landline or mobile).');
                        }
                    },
                ],
            ],
            default => [],
        };
    }

    protected function rules(): array
    {
        return array_merge(
            $this->stepRules(1),
            $this->stepRules(2),
            $this->stepRules(3),
        );
    }

    protected function messages(): array
    {
        return [
            'business_registration_number.required' => 'Please enter your registration number.',
            'business_registration_number.regex'    => 'Registration numbers can only contain letters, digits, and dashes.',
            'business_registration_number.min'      => 'That registration number looks too short.',
            'tin_number.required'                   => 'Please enter your TIN.',
            'tin_number.regex'                      => 'Enter a valid TIN: 123-456-789 or 123-456-789-000.',
            'type_of_tenant_id.required'            => 'Please select a business category.',
            'business_type.required'                => 'Please select a registration type.',
            'owner_id_type.required'                => 'Please select the type of ID you will upload.',
            'owner_id_type.in'                      => 'That ID type is not accepted. Choose one from the list.',
            'owner_id_number.required'              => 'Please enter the ID number exactly as it appears on your card.',
            'owner_id_number.min'                   => 'That ID number looks too short. Please double-check it.',
        ];
    }

    public function gotoStep(int $step): void
    {
        $this->requireOwnership();

        $this->step = max(1, min(self::TOTAL_STEPS, $step));
        $this->saveError   = null;
        $this->submitError = null;

        $this->dispatch('scroll-to-top');
    }

    public function next(): void
    {
        $this->requireOwnership();

        $this->saveError = null;

        $currentRules = $this->stepRules($this->step);
        if (!empty($currentRules)) {
            $this->validate($currentRules);
        }

        try {
            $this->application->update($this->payloadForStep($this->step));
        } catch (\Throwable $e) {
            Log::error('KYB step save failed', [
                'application_id' => $this->application->id,
                'step'           => $this->step,
                'error'          => $e->getMessage(),
            ]);
            $this->saveError = 'Could not save your progress. Please try again.';
            return;
        }

        $this->invalidateApplicationCaches();

        $this->step = min(self::TOTAL_STEPS, $this->step + 1);
        $this->dispatch('scroll-to-top');
    }

    public function back(): void
    {
        $this->requireOwnership();

        $this->step = max(1, $this->step - 1);
        $this->saveError   = null;
        $this->submitError = null;
        $this->dispatch('scroll-to-top');
    }

    protected function invalidateApplicationCaches(): void
    {
        unset(
            $this->documents,
            $this->documentsByType,
            $this->uploadedDocumentTypes,
            $this->hasAllRequiredDocuments,
            $this->completionPercent,
            $this->isReadyToSubmit,
            $this->uploadedRequiredCount,
            $this->missingRequiredDocuments,
            $this->submitBlocker,
        );
    }

    protected function payloadForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'business_name'        => trim($this->business_name),
                'business_type'        => $this->business_type,
                'type_of_tenant_id'    => $this->type_of_tenant_id,
                'address'              => $this->address ?: null,
                'barangay'             => $this->barangay ?: null,
                'city'                 => $this->city ?: null,
                'province'             => $this->province ?: null,
                'metadata'             => array_merge(
                    $this->application->metadata ?? [],
                    ['description' => $this->business_description ?: null],
                ),
                'coordinates' => ($this->businessLat !== null && $this->businessLng !== null)
                    ? [[
                        'lat'  => $this->businessLat,
                        'lng'  => $this->businessLng,
                        'type' => 'parent',
                    ]]
                    : null,
            ],
            2 => [
                'business_registration_number' => strtoupper(trim($this->business_registration_number)),
                'tin_number'                   => trim($this->tin_number),
            ],
            3 => $this->ownerPayload(),
            default => [],
        };
    }

    protected function ownerPayload(): array
    {
        $locked = $this->lockedOwnerData;

        if ($locked !== null) {
            return [
                'owner_full_name' => $locked['owner_full_name'],
                'owner_id_type'   => $locked['owner_id_type'] ?: null,
                'owner_id_number' => $locked['owner_id_number'] ?: null,
                'owner_birthdate' => $locked['owner_birthdate'] ?: null,
                'contact_email'   => $locked['contact_email'],
                'contact_phone'   => $locked['contact_phone'],
            ];
        }

        return [
            'owner_full_name' => trim($this->owner_full_name),
            'owner_id_type'   => $this->owner_id_type ?: null,
            'owner_id_number' => $this->owner_id_number ?: null,
            'owner_birthdate' => $this->owner_birthdate ?: null,
            'contact_email'   => $this->contact_email,
            'contact_phone'   => $this->contact_phone,
        ];
    }

    public function updated(string $name, mixed $value): void
    {
        if (!str_starts_with($name, 'uploads.')) {
            return;
        }

        $documentType = substr($name, strlen('uploads.'));

        if (empty($value)) {
            return;
        }

        $this->attachDocument($documentType);
    }

    public function attachDocument(string $documentType): void
    {
        $this->requireOwnership();

        $allowed = array_merge($this->requiredDocuments, $this->optionalDocuments);
        if (!in_array($documentType, $allowed, true)) {
            unset($this->uploads[$documentType]);
            return;
        }

        $file = $this->uploads[$documentType] ?? null;
        if (!$file) {
            return;
        }

        try {
            $file->getSize();
        } catch (\Throwable) {
            unset($this->uploads[$documentType]);
            $this->dispatch('toast', message: 'Your upload expired. Please select the file again.', type: 'error');
            return;
        }

        try {
            $this->validate([
                "uploads.{$documentType}" => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            ], [
                "uploads.{$documentType}.max"   => 'Documents must be 10 MB or smaller.',
                "uploads.{$documentType}.mimes" => 'Only PDF, JPG, PNG, and WEBP are accepted.',
            ]);
        } catch (\League\Flysystem\UnableToRetrieveMetadata) {
            unset($this->uploads[$documentType]);
            $this->dispatch('toast', message: 'Your upload expired. Please select the file again.', type: 'error');
            return;
        } catch (\Illuminate\Validation\ValidationException $e) {
            unset($this->uploads[$documentType]);
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: $first ?: 'Invalid file.', type: 'error');
            return;
        }

        try {
            $service = app(BusinessApplicationService::class);

            DB::transaction(function () use ($service, $documentType, $file): void {
                $service->attachDocument(
                    $this->application,
                    Auth::user(),
                    $documentType,
                    $file,
                    [
                        'document_number' => null,
                        'issued_at'       => null,
                        'expires_at'      => null,
                    ],
                );

                $this->application->documents()
                    ->ofType($documentType)
                    ->where('id', '!=', $this->application->documents()->ofType($documentType)->latest('id')->value('id'))
                    ->get()
                    ->each(fn ($doc) => $doc->delete());
            });

            unset($this->uploads[$documentType]);

            $this->invalidateApplicationCaches();

            $this->dispatch('toast', message: 'Uploaded and watermarked.', type: 'success');
        } catch (\Throwable $e) {
            Log::error('KYB document upload failed', [
                'application_id' => $this->application->id,
                'document_type'  => $documentType,
                'error'          => $e->getMessage(),
            ]);
            unset($this->uploads[$documentType]);
            $this->dispatch('toast', message: 'Upload failed. Please try again.', type: 'error');
        }
    }

    public function deleteDocument(int $documentId): void
    {
        $this->requireOwnership();

        $doc = $this->application->documents()->whereKey($documentId)->first();
        if (!$doc) {
            return;
        }

        try {
            $doc->delete();

            $this->invalidateApplicationCaches();

            $this->dispatch('toast', message: 'Document removed.', type: 'success');
        } catch (\Throwable $e) {
            Log::error('KYB document delete failed', [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);
            $this->dispatch('toast', message: 'Could not remove the document.', type: 'error');
        }
    }

    public function documentUrl(BusinessDocument $doc): ?string
    {
        return $this->storageUrl($doc->watermarked_path ?: $doc->stored_path);
    }

    public function isImageDocument(BusinessDocument $doc): bool
    {
        return str_starts_with((string) $doc->mime_type, 'image/');
    }

    public function submit(BusinessApplicationService $service)
    {
        $this->requireOwnership();

        $this->submitError = null;

        if (! $this->isReadyToSubmit) {
            $blocker = $this->submitBlocker;

            $this->submitError = $blocker['message'];
            $this->step        = max(1, min(self::TOTAL_STEPS, $blocker['step']));

            $this->dispatch('toast', message: $blocker['message'], type: 'error');
            $this->dispatch('scroll-to-top');

            return null;
        }

        $this->validate();

        $this->application->refresh();

        if (! $this->hasAllRequiredDocuments) {
            $missing = $this->missingRequiredDocuments;

            $this->submitError = empty($missing)
                ? 'Please complete every required field before submitting.'
                : 'Please upload the following required document(s) before submitting: '
                    . implode(', ', $missing) . '.';

            $this->step = 2;
            $this->dispatch('scroll-to-top');

            return null;
        }

        try {
            $this->application->update(array_merge(
                $this->payloadForStep(1),
                $this->payloadForStep(2),
                $this->payloadForStep(3),
            ));
        } catch (\Throwable $e) {
            Log::error('KYB save before submit failed', [
                'application_id' => $this->application->id,
                'error'          => $e->getMessage(),
            ]);
            $this->submitError = 'Could not save your application. Please try again.';
            return null;
        }

        try {
            $service->submit($this->application->fresh(['documents']));
        } catch (\Throwable $e) {
            Log::warning('KYB submit blocked', [
                'application_id' => $this->application->id,
                'error'          => $e->getMessage(),
            ]);
            $this->submitError = $e->getMessage();
            return null;
        }

        session()->flash('message', 'Your business application was submitted for review.');

        return $this->isFollowUpApplication
            ? redirect()->route('tenant.businesses.index')
            : redirect()->route('register_business');
    }
};
?>

@push('styles')
    @once
        <style>
            .toast-item {
                animation: toastIn .22s cubic-bezier(.16,1,.3,1);
            }
            @keyframes toastIn {
                from { opacity: 0; transform: translateY(8px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .toast-item { animation: none; }
            }
        </style>
    @endonce
@endpush

@php
    /*
     * In the tenant layout the app already has a sticky header at top-0.
     * This sub-header must sit below it — tenant header is 4rem mobile
     * + safe-area, 5rem at md+ + safe-area. Otherwise the two overlap.
     */
    $__subHeaderOffset = $this->isFollowUpApplication
        ? 'top-[calc(4rem+env(safe-area-inset-top))] md:top-[calc(5rem+env(safe-area-inset-top))]'
        : 'top-0';

    $__sectionEyebrow = 'inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400';
    $__sectionRule    = 'h-px w-4 bg-amber-500';
@endphp

<main
    x-data="{
        toasts: [],
        reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        locating: false,
    }"
    x-on:toast.window="
        const id = Date.now() + Math.random();
        toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
        setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 3500);
    "
    x-on:scroll-to-top.window="window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' })"
    x-on:request-geolocation.window="
        if (locating) return;
        if (!navigator.geolocation) {
            $dispatch('toast', { message: 'Geolocation is not supported on this device.', type: 'error' });
            return;
        }
        if (window.isSecureContext === false) {
            $dispatch('toast', { message: 'Location needs a secure connection (https:// or http://127.0.0.1).', type: 'error' });
            return;
        }
        locating = true;
        $dispatch('toast', { message: 'Requesting your location…', type: 'info' });
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                Promise.resolve($wire.setBusinessLocation(lat, lng))
                    .then(() => Promise.resolve($wire.resolveAddress(lat, lng)))
                    .then(() => $dispatch('toast', { message: 'Location found.', type: 'success' }))
                    .catch(() => $dispatch('toast', { message: 'Could not save the location.', type: 'error' }))
                    .finally(() => { locating = false; });
            },
            (err) => {
                const msg = err.code === 1 ? 'Location access was denied. Check your browser permissions.'
                           : err.code === 3 ? 'Location request timed out.'
                           : 'Could not determine your location.';
                $dispatch('toast', { message: msg, type: 'error' });
                locating = false;
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
        );
    "
    class="min-h-screen">

    <div class="fixed z-[2000] flex flex-col gap-2 pointer-events-none
                bottom-[max(1rem,var(--safe-bottom))]
                right-[max(1rem,var(--safe-right))]
                w-[calc(100vw-2rem)] max-w-sm">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="toast-item pointer-events-auto rounded-xl pl-4 pr-1.5 py-3 shadow-lg text-sm font-medium border
                        flex items-center gap-2"
                 :class="{
                     'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300': toast.type === 'success',
                     'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300': toast.type === 'error',
                     'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                 }">
                <span class="flex-1 leading-snug" x-text="toast.message"></span>
                <button type="button"
                        @click="toasts = toasts.filter(t => t.id !== toast.id)"
                        aria-label="Dismiss notification"
                        class="shrink-0 inline-flex items-center justify-center w-11 h-11 sm:w-7 sm:h-7 rounded-md opacity-60 hover:opacity-100 transition-opacity
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current/40">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </template>
    </div>

    <div class="sticky {{ $__subHeaderOffset }} z-20 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md border-b border-gray-200 dark:border-gray-800">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-4 pt-[max(1rem,env(safe-area-inset-top))]">

            <div class="flex items-center justify-between gap-4 mb-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    @if($this->logoUrl)
                        <img src="{{ $this->logoUrl }}" alt="{{ $this->siteName }}" loading="lazy" decoding="async" class="w-8 h-8 object-contain rounded-lg shrink-0">
                    @else
                        <div class="w-8 h-8 rounded-lg bg-primary-600 flex items-center justify-center text-white shrink-0 font-semibold text-sm">
                            {{ strtoupper(substr($this->siteName, 0, 1)) }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400">
                            Business Setup
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ $this->siteName }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="hidden sm:inline-flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 rounded-full px-2.5 py-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                        </svg>
                        Draft saved
                    </span>
                    <a href="{{ $this->isFollowUpApplication
                                  ? route('tenant.businesses.index')
                                  : route('register_business') }}"
                       wire:navigate
                       class="shrink-0 inline-flex items-center gap-1 min-h-[44px] text-[11px] font-medium
                              text-gray-500 dark:text-gray-400 hover:text-rose-600 dark:hover:text-rose-400
                              transition-colors -mx-1 px-3 rounded
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span class="hidden sm:inline">
                            {{ $this->isFollowUpApplication ? 'Back to businesses' : 'Save & exit' }}
                        </span>
                    </a>
                </div>
            </div>

            <div class="mb-4">
                <h1 class="text-lg sm:text-xl font-bold tracking-tight text-gray-900 dark:text-white leading-tight">
                    @if($this->isFollowUpApplication)
                        Adding a new business, {{ $this->applicantFirstName }}
                    @else
                        Welcome back, {{ $this->applicantFirstName }}!
                    @endif
                </h1>
                <p class="mt-0.5 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                    @if($this->isFollowUpApplication)
                        Your owner details are pre-verified and locked. Just tell us about the new business.
                    @elseif($this->completionPercent >= 100)
                        Everything's in place. You're ready to submit for review.
                    @else
                        You're <strong class="text-gray-700 dark:text-gray-300">{{ $this->completionPercent }}%</strong> through your application.
                        @if($this->estimatedMinutesLeft > 0)
                            About <strong class="text-gray-700 dark:text-gray-300">{{ $this->estimatedMinutesLeft }} minute{{ $this->estimatedMinutesLeft === 1 ? '' : 's' }}</strong> left.
                        @endif
                    @endif
                </p>
            </div>

            <div class="flex items-center gap-2 mb-3">
                @foreach ($this->stepLabels as $num => $label)
                    @php
                        $isActive   = ($step === $num);
                        $isComplete = ($step > $num);
                    @endphp
                    <button type="button"
                            wire:click="gotoStep({{ $num }})"
                            class="flex-1 flex items-center gap-2 group text-left min-h-[44px] py-1 rounded-lg
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   active:scale-[0.98] transition-transform
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                        <span class="shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold transition-all duration-200
                            {{ $isActive
                                ? 'bg-primary-600 text-white ring-4 ring-primary-500/20'
                                : ($isComplete
                                    ? 'bg-emerald-500 text-white'
                                    : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 group-hover:bg-gray-300 dark:group-hover:bg-gray-600') }}">
                            @if ($isComplete)
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            @else
                                {{ $num }}
                            @endif
                        </span>
                        <span class="hidden md:block text-xs font-medium truncate transition-colors
                            {{ $isActive ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $label }}
                        </span>
                    </button>

                    @if (!$loop->last)
                        <div class="h-px flex-1 max-w-[20px] {{ $isComplete ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                    @endif
                @endforeach
            </div>

            <div class="h-1 w-full rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden">
                <div class="h-full bg-gradient-to-r from-primary-500 to-primary-600 transition-all duration-500 ease-out"
                     style="width: {{ $this->completionPercent }}%"></div>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        @if ($this->isRevision)
            <div class="mb-6 rounded-2xl border border-amber-200/80 dark:border-amber-500/30 bg-amber-50/60 dark:bg-amber-500/[0.06] p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="shrink-0 w-9 h-9 rounded-full bg-amber-100 dark:bg-amber-500/20 border border-amber-200 dark:border-amber-500/30 flex items-center justify-center text-amber-700 dark:text-amber-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300 mb-1">
                            Note from your reviewer
                        </p>
                        <p class="text-sm text-amber-950 dark:text-amber-100 leading-relaxed italic">
                            "{{ $application->revision_notes }}"
                        </p>
                        <p class="mt-2 text-[11px] text-amber-700/70 dark:text-amber-300/70">
                            Fix the item(s) above, then resubmit. Your other information is saved.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @if ($saveError || $submitError)
            <div role="alert" aria-live="polite"
                 class="mb-5 flex items-center gap-2.5 rounded-xl border border-rose-200/80 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 px-3.5 py-2.5 text-xs sm:text-sm text-rose-800 dark:text-rose-300 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                <span class="font-medium">{{ $saveError ?: $submitError }}</span>
            </div>
        @endif

        @if ($errors->any() && ! $saveError && ! $submitError)
            <div role="alert" aria-live="polite"
                 class="mb-5 rounded-xl border border-rose-200/80 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 px-3.5 py-3 text-xs sm:text-sm text-rose-800 dark:text-rose-300 shadow-sm">
                <p class="font-semibold mb-1">
                    Please fix {{ $errors->count() }} field{{ $errors->count() === 1 ? '' : 's' }}:
                </p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($step === 1)

            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm overflow-hidden mb-5">
                <div class="px-6 pt-5 pb-3 flex items-center justify-between">
                    <p class="{{ $__sectionEyebrow }}">
                        <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                        Cover Photo
                        <span class="text-[10px] font-normal normal-case tracking-normal text-gray-400">(optional)</span>
                    </p>
                    @if($cover_photo_path)
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                            Uploaded
                        </span>
                    @endif
                </div>

                <div class="px-6 pb-5">
                    <div class="relative aspect-[3/1] w-full rounded-xl overflow-hidden bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700">
                        @if($cover_photo_path)
                            <img src="{{ '/storage/' . ltrim($cover_photo_path, '/') }}" alt="Cover photo" loading="lazy" decoding="async" class="w-full h-full object-cover">
                        @else
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-white/85">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
                            description: 'Drag to reposition · Scroll to zoom · Pinch on mobile',
                        })"
                        x-init="init()"
                        class="mt-3 flex items-center gap-2 flex-wrap"
                    >
                        <label for="cover-upload"
                               class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-10 px-3.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold
                                      transition-all duration-200 active:scale-95 cursor-pointer
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-within:ring-2 focus-within:ring-primary-500/50 focus-within:ring-offset-2 dark:focus-within:ring-offset-gray-900">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            {{ $cover_photo_path ? 'Replace cover' : 'Upload cover photo' }}
                            <input type="file" id="cover-upload" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="sr-only">
                        </label>

                        @if($cover_photo_path)
                            <button type="button"
                                    x-data="{
                                        armed: false,
                                        _t: null,
                                        arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                        unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                        destroy() { clearTimeout(this._t); }
                                    }"
                                    @click="armed ? (unarm(), $wire.removeCoverPhoto()) : arm()"
                                    wire:loading.attr="disabled"
                                    wire:target="removeCoverPhoto"
                                    :class="armed
                                        ? 'border-amber-400 bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/40'
                                        : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'"
                                    class="inline-flex items-center justify-center h-11 sm:h-10 px-3.5 rounded-lg border text-xs font-semibold
                                           transition-all duration-200 active:scale-95
                                           hover:border-rose-400 hover:text-rose-600 dark:hover:text-rose-400
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <span x-show="!armed">Remove</span>
                                <span x-show="armed" x-cloak>Confirm</span>
                            </button>
                        @endif

                        <div wire:loading wire:target="cover_photo" class="flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Uploading…
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6 mb-5">
                <p class="{{ $__sectionEyebrow }} mb-5">
                    <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                    Business Logo
                    <span class="text-[10px] font-normal normal-case tracking-normal text-gray-400">(optional)</span>
                </p>

                <div class="flex items-center gap-5">
                    <div class="shrink-0 w-20 h-20 rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 flex items-center justify-center overflow-hidden">
                        @if($logo_path)
                            <img src="{{ '/storage/' . ltrim($logo_path, '/') }}" alt="Business logo" loading="lazy" decoding="async" class="w-full h-full object-cover">
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
                        <div class="flex items-center gap-2 flex-wrap">
                            <label for="logo-upload"
                                   class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-10 px-3.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold
                                          transition-all duration-200 active:scale-95 cursor-pointer
                                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                          focus-within:ring-2 focus-within:ring-primary-500/50 focus-within:ring-offset-2 dark:focus-within:ring-offset-gray-900">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                {{ $logo_path ? 'Replace logo' : 'Upload logo' }}
                                <input type="file" id="logo-upload" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="sr-only">
                            </label>

                            @if($logo_path)
                                <button type="button"
                                        x-data="{
                                            armed: false,
                                            _t: null,
                                            arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                            unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                            destroy() { clearTimeout(this._t); }
                                        }"
                                        @click="armed ? (unarm(), $wire.removeLogo()) : arm()"
                                        wire:loading.attr="disabled"
                                        wire:target="removeLogo"
                                        :class="armed
                                            ? 'border-amber-400 bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/40'
                                            : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'"
                                        class="inline-flex items-center justify-center h-11 sm:h-10 px-3.5 rounded-lg border text-xs font-semibold
                                               transition-all duration-200 active:scale-95
                                               hover:border-rose-400 hover:text-rose-600 dark:hover:text-rose-400
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                               disabled:opacity-60 disabled:cursor-not-allowed">
                                    <span x-show="!armed">Remove</span>
                                    <span x-show="armed" x-cloak>Confirm</span>
                                </button>
                            @endif

                            <div wire:loading wire:target="logo" class="flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Uploading…
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                <p class="{{ $__sectionEyebrow }} mb-5">
                    <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                    Business Information
                </p>

                <div class="space-y-5">
                    <div>
                        <label for="business_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Business Name <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="business_name" wire:model="business_name"
                               placeholder="e.g. Gawahon Eco Park"
                               maxlength="255"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                        @error('business_name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="business_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Registration Type <span class="text-rose-500" aria-hidden="true">*</span>
                            </label>
                            <select id="business_type" wire:model.live="business_type"
                                    class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition
                                           [touch-action:manipulation]">
                                <option value="">— Select —</option>
                                @foreach($this->businessTypeLabels as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('business_type') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="type_of_tenant_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Category <span class="text-rose-500" aria-hidden="true">*</span>
                            </label>
                            <select id="type_of_tenant_id" wire:model="type_of_tenant_id"
                                    class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition
                                           [touch-action:manipulation]">
                                <option value="">— Select —</option>
                                @foreach($this->tenantTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->type }}</option>
                                @endforeach
                            </select>
                            @error('type_of_tenant_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="business_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Short Description <span class="text-[10px] font-normal text-gray-400">(optional)</span>
                        </label>
                        <textarea id="business_description"
                                  wire:model="business_description"
                                  rows="3"
                                  maxlength="500"
                                  placeholder="A short introduction to your business"
                                  class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition resize-none"></textarea>
                        @error('business_description') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6 mt-5">
                <p class="{{ $__sectionEyebrow }} mb-4">
                    <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                    Location
                </p>

                <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button"
                                wire:click="useMyLocation"
                                wire:loading.attr="disabled"
                                wire:target="useMyLocation"
                                class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-10 px-3.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Use my location
                        </button>

                        @if($this->hasCoordinates)
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 rounded-full px-2 py-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                                Pinned
                            </span>
                            <button type="button"
                                    wire:click="clearLocation"
                                    class="inline-flex items-center justify-center h-11 sm:h-10 px-3 rounded-lg text-[11px] font-semibold text-gray-500 hover:text-rose-600 dark:text-gray-400 dark:hover:text-rose-400
                                           transition-all duration-200 active:scale-95
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                Clear
                            </button>
                        @endif
                    </div>

                    @if($this->hasCoordinates)
                        <button type="button"
                                wire:click="refreshAddressFromPin"
                                wire:loading.attr="disabled"
                                wire:target="refreshAddressFromPin,resolveAddress"
                                class="inline-flex items-center justify-center gap-1 h-11 sm:h-10 px-3 rounded-lg text-[11px] font-semibold text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Force refresh
                        </button>
                    @endif
                </div>

                <div class="relative">
                    <div wire:ignore
                         x-data="locationPicker({
                             initialLat: {{ $businessLat ?? 'null' }},
                             initialLng: {{ $businessLng ?? 'null' }},
                         })"
                         x-init="init()"
                         x-on:map:pin-cleared.window="clearMarker()"
                         class="relative h-72 sm:h-80 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900">
                        <x-map
                            id="business-location-map"
                            :center="$this->mapCenter"
                            :zoom="$this->mapZoom"
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
                                :fullscreen="false"
                                :scale="false"
                                position="top-right"
                            />
                        </x-map>

                        <div x-cloak
                             :class="hasPin ? 'hidden' : ''"
                             class="absolute inset-x-0 top-3 mx-auto w-max pointer-events-none
                                    rounded-full bg-gray-900/80 backdrop-blur-sm text-white
                                    text-[11px] font-semibold px-3 py-1.5">
                            Click the map to drop a pin
                        </div>
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

                @if($this->hasCoordinates)
                    <p class="mt-2 text-[10px] font-mono text-gray-400 dark:text-gray-500 tabular-nums">
                        {{ number_format($businessLat, 6) }}, {{ number_format($businessLng, 6) }}
                    </p>
                @endif

                @error('businessLat') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                @error('businessLng') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Street Address</label>
                        <input type="text" id="address" wire:model="address"
                               autocomplete="street-address"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                    </div>

                    <div>
                        <label for="barangay" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Barangay</label>
                        <input type="text" id="barangay" wire:model="barangay"
                               autocomplete="address-level3"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                    </div>

                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">City / Municipality</label>
                        <input type="text" id="city" wire:model="city"
                               autocomplete="address-level2"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="province" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Province</label>
                        <input type="text" id="province" wire:model="province"
                               autocomplete="address-level1"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                    </div>
                </div>
            </section>
        @endif

        @if ($step === 2)

            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6 mb-5">
                <p class="{{ $__sectionEyebrow }} mb-5">
                    <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                    Registration Details
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="business_registration_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Registration No. <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <input type="text"
                               id="business_registration_number"
                               wire:model="business_registration_number"
                               placeholder="{{ $this->registrationNumberPlaceholder }}"
                               maxlength="50"
                               autocomplete="off"
                               x-on:input="
                                   const up = $event.target.value.toUpperCase();
                                   if (up !== $event.target.value) {
                                       $event.target.value = up;
                                       $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                                   }
                               "
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition font-mono">
                        @error('business_registration_number') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="tin_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            TIN <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <input type="text"
                               id="tin_number"
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
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition font-mono">
                        @error('tin_number') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="{{ $__sectionEyebrow }}">
                        <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                        Required Documents
                    </p>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider
                        {{ $this->hasAllRequiredDocuments
                            ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30'
                            : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                        {{ $this->uploadedRequiredCount }} / {{ count($this->requiredDocuments) }}
                    </span>
                </div>

                <div class="space-y-2.5">
                    @foreach ($this->requiredDocuments as $docType)
                        @php
                            $existing = $this->documentsByType[$docType] ?? null;
                        @endphp
                        <div wire:key="slot-{{ $docType }}"
                             class="border rounded-xl p-3.5 transition
                                {{ $existing
                                    ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-500/[0.04]'
                                    : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900' }}">

                            @if ($existing)
                                <div class="flex items-start gap-3">
                                    @php
                                        $url     = $this->documentUrl($existing);
                                        $isImage = $this->isImageDocument($existing);
                                    @endphp

                                    @if ($isImage && $url)
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                           class="block shrink-0 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-primary-500 transition
                                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                            <img src="{{ $url }}" alt="" loading="lazy" decoding="async" class="w-12 h-12 object-cover">
                                        </a>
                                    @else
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                           class="shrink-0 w-12 h-12 rounded-lg bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 flex flex-col items-center justify-center text-rose-600 dark:text-rose-400 hover:border-rose-400 transition
                                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="text-[8px] font-bold uppercase mt-0.5">PDF</span>
                                        </a>
                                    @endif

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                                {{ $this->documentLabels[$docType] ?? $docType }}
                                            </p>
                                        </div>
                                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                            {{ $existing->original_filename }}
                                        </p>
                                        <div class="mt-1.5 flex items-center gap-3">
                                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                               class="relative text-[11px] font-semibold text-primary-600 dark:text-primary-400 hover:underline py-2.5 px-1 -mx-1 -my-2.5
                                                      before:absolute before:content-[''] before:-inset-1 before:rounded
                                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 transition">
                                                View
                                            </a>
                                            <span class="w-px h-3 bg-gray-300 dark:bg-gray-700" aria-hidden="true"></span>
                                            <button type="button"
                                                    x-data="{
                                                        armed: false,
                                                        _t: null,
                                                        arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                                        unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                                        destroy() { clearTimeout(this._t); }
                                                    }"
                                                    @click="armed ? (unarm(), $wire.deleteDocument({{ $existing->id }})) : arm()"
                                                    wire:loading.attr="disabled"
                                                    wire:target="deleteDocument"
                                                    class="relative text-[11px] font-semibold transition-colors py-2.5 px-1 -mx-1 -my-2.5
                                                           before:absolute before:content-[''] before:-inset-1 before:rounded
                                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                                           disabled:opacity-60 disabled:cursor-not-allowed"
                                                    :class="armed ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400 hover:text-rose-600 dark:hover:text-rose-400'">
                                                <span x-show="!armed">Replace</span>
                                                <span x-show="armed" x-cloak>Confirm?</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <label for="file-{{ $docType }}"
                                       class="flex items-center gap-3 cursor-pointer group min-h-[44px]
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                                    <div class="shrink-0 w-10 h-10 rounded-lg bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 group-hover:border-primary-500 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $this->documentLabels[$docType] ?? $docType }}
                                        </p>
                                    </div>
                                    <input type="file"
                                           id="file-{{ $docType }}"
                                           wire:model="uploads.{{ $docType }}"
                                           accept=".pdf,.jpg,.jpeg,.png,.webp"
                                           class="sr-only">
                                </label>
                            @endif

                            <div wire:loading wire:target="uploads.{{ $docType }}"
                                 class="mt-2 flex items-center gap-2 text-[11px] text-primary-600 dark:text-primary-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Uploading…
                            </div>
                        </div>
                    @endforeach
                </div>

                <details class="mt-4 border-t border-gray-100 dark:border-gray-700/60 pt-4">
                    <summary class="cursor-pointer list-none flex items-center justify-between min-h-[44px] py-1.5 group
                                    [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                        <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-300 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition">
                            Additional documents (optional)
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>
                    <div class="mt-3 space-y-2.5">
                        @foreach ($this->optionalDocuments as $docType)
                            @php
                                $existing = $this->documentsByType[$docType] ?? null;
                            @endphp
                            <div wire:key="opt-{{ $docType }}"
                                 class="border rounded-xl p-3 transition
                                    {{ $existing
                                        ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-500/[0.04]'
                                        : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900' }}">
                                @if ($existing)
                                    <div class="flex items-center gap-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <p class="flex-1 text-sm font-medium text-gray-900 dark:text-white truncate">
                                            {{ $this->documentLabels[$docType] ?? $docType }}
                                        </p>
                                        <button type="button"
                                                x-data="{
                                                    armed: false,
                                                    _t: null,
                                                    arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                                    unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                                    destroy() { clearTimeout(this._t); }
                                                }"
                                                @click="armed ? (unarm(), $wire.deleteDocument({{ $existing->id }})) : arm()"
                                                wire:loading.attr="disabled"
                                                wire:target="deleteDocument"
                                                class="relative text-[11px] font-semibold transition-colors py-2.5 px-1.5 -mx-1.5 -my-2.5
                                                       before:absolute before:content-[''] before:-inset-1 before:rounded
                                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                                       disabled:opacity-60 disabled:cursor-not-allowed"
                                                :class="armed ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 hover:text-rose-600 dark:hover:text-rose-400'">
                                            <span x-show="!armed">Remove</span>
                                            <span x-show="armed" x-cloak>Confirm?</span>
                                        </button>
                                    </div>
                                @else
                                    <label for="file-opt-{{ $docType }}" class="flex items-center gap-3 cursor-pointer group min-h-[44px]
                                                                                [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                                        <div class="shrink-0 w-10 h-10 rounded-lg bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 group-hover:border-primary-500 group-hover:text-primary-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                        </div>
                                        <p class="flex-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ $this->documentLabels[$docType] ?? $docType }}
                                        </p>
                                        <input type="file"
                                               id="file-opt-{{ $docType }}"
                                               wire:model="uploads.{{ $docType }}"
                                               accept=".pdf,.jpg,.jpeg,.png,.webp"
                                               class="sr-only">
                                    </label>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </details>
            </section>

            @if (!$this->isReadyToSubmit)
                @php
                    $blocker = $this->submitBlocker;
                @endphp
                <div class="mt-5 rounded-xl border border-amber-200/80 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/[0.06] px-3.5 py-3 shadow-sm">
                    <div class="flex items-start gap-2.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                        </svg>
                        <div class="min-w-0 text-xs sm:text-sm">
                            <p class="font-semibold text-amber-900 dark:text-amber-200">
                                {{ $blocker['message'] }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        @if ($step === 3)

            @if ($this->isFollowUpApplication)
                <div class="mb-5 rounded-2xl border border-emerald-200/80 dark:border-emerald-500/30 bg-emerald-50/70 dark:bg-emerald-500/[0.06] p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-9 h-9 rounded-full bg-emerald-100 dark:bg-emerald-500/20 border border-emerald-200 dark:border-emerald-500/30 flex items-center justify-center text-emerald-700 dark:text-emerald-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300 mb-1">
                                Verified owner details
                            </p>
                            <p class="text-sm text-emerald-950 dark:text-emerald-100 leading-relaxed">
                                Your owner identity was verified during your earlier application. These fields are locked to that record — you cannot change them here.
                            </p>
                            <p class="mt-2 text-[11px] text-emerald-800/70 dark:text-emerald-300/70">
                                If anything needs to change, contact support.
                            </p>
                        </div>
                    </div>
                </div>

                <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6 mb-5">
                    <div class="flex items-center justify-between mb-5">
                        <p class="{{ $__sectionEyebrow }}">
                            <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                            Owner Photo
                        </p>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                     bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300
                                     border border-emerald-200 dark:border-emerald-500/30">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                            Verified
                        </span>
                    </div>

                    <div class="flex items-center gap-5">
                        <div class="shrink-0 w-20 h-20 rounded-full border-2 border-emerald-200 dark:border-emerald-500/30 bg-gray-50 dark:bg-gray-900 flex items-center justify-center overflow-hidden">
                            @if($owner_avatar_path)
                                <img src="{{ '/storage/' . ltrim($owner_avatar_path, '/') }}" alt="Owner photo" loading="lazy" decoding="async" class="w-full h-full object-cover">
                            @else
                                <span class="text-2xl font-bold text-gray-500 dark:text-gray-400">
                                    {{ strtoupper(substr($owner_full_name ?: 'O', 0, 1)) }}
                                </span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                {{ $owner_full_name ?: '—' }}
                            </p>
                            <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                Photo inherited from your verified record.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6 mb-5">
                    <div class="flex items-center justify-between mb-5">
                        <p class="{{ $__sectionEyebrow }}">
                            <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                            Owner Information
                        </p>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                     bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300
                                     border border-emerald-200 dark:border-emerald-500/30">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                            Verified
                        </span>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                        <div class="min-w-0">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Full Name</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white truncate">{{ $owner_full_name ?: '—' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">ID Type</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white truncate">
                                {{ $this->selectedOwnerIdLabel ?? '—' }}
                            </dd>
                        </div>
                        <div class="min-w-0 sm:col-span-2">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">ID Number</dt>
                            <dd class="mt-1 text-sm font-medium font-mono text-gray-900 dark:text-white truncate tabular-nums">{{ $owner_id_number ?: '—' }}</dd>
                        </div>
                        <div class="min-w-0 sm:col-span-2">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Date of Birth</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                @if($owner_birthdate)
                                    {{ \Illuminate\Support\Carbon::parse($owner_birthdate)->format('F j, Y') }}
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 italic">Not provided</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-5">
                        <p class="{{ $__sectionEyebrow }}">
                            <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                            Contact Information
                        </p>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                     bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300
                                     border border-emerald-200 dark:border-emerald-500/30">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                            Verified
                        </span>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                        <div class="min-w-0">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white truncate">{{ $contact_email ?: '—' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Phone</dt>
                            <dd class="mt-1 text-sm font-medium font-mono text-gray-900 dark:text-white truncate tabular-nums">{{ $contact_phone ?: '—' }}</dd>
                        </div>
                    </dl>
                </section>

            @else

                <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6 mb-5">
                    <p class="{{ $__sectionEyebrow }} mb-5">
                        <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                        Owner Photo
                        <span class="text-[10px] font-normal normal-case tracking-normal text-gray-400">(optional)</span>
                    </p>

                    <div class="flex items-center gap-5">
                        <div class="shrink-0 w-20 h-20 rounded-full border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 flex items-center justify-center overflow-hidden">
                            @if($owner_avatar_path)
                                <img src="{{ '/storage/' . ltrim($owner_avatar_path, '/') }}" alt="Owner photo" loading="lazy" decoding="async" class="w-full h-full object-cover">
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            @endif
                        </div>

                        <div
                            x-data="imageCropper({
                                wireProperty: 'owner_avatar',
                                aspect: 1,
                                title: 'Crop owner photo',
                                description: 'Square crop works best',
                            })"
                            x-init="init()"
                            class="flex-1 min-w-0"
                        >
                            <div class="flex items-center gap-2 flex-wrap">
                                <label for="avatar-upload"
                                       class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-10 px-3.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold
                                              transition-all duration-200 active:scale-95 cursor-pointer
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                              focus-within:ring-2 focus-within:ring-primary-500/50 focus-within:ring-offset-2 dark:focus-within:ring-offset-gray-900">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    {{ $owner_avatar_path ? 'Replace photo' : 'Upload photo' }}
                                    <input type="file" id="avatar-upload" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="sr-only">
                                </label>

                                @if($owner_avatar_path)
                                    <button type="button"
                                            x-data="{
                                                armed: false,
                                                _t: null,
                                                arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                                unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                                destroy() { clearTimeout(this._t); }
                                            }"
                                            @click="armed ? (unarm(), $wire.removeOwnerAvatar()) : arm()"
                                            wire:loading.attr="disabled"
                                            wire:target="removeOwnerAvatar"
                                            :class="armed
                                                ? 'border-amber-400 bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/40'
                                                : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'"
                                            class="inline-flex items-center justify-center h-11 sm:h-10 px-3.5 rounded-lg border text-xs font-semibold
                                                   transition-all duration-200 active:scale-95
                                                   hover:border-rose-400 hover:text-rose-600 dark:hover:text-rose-400
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                                   disabled:opacity-60 disabled:cursor-not-allowed">
                                        <span x-show="!armed">Remove</span>
                                        <span x-show="armed" x-cloak>Confirm</span>
                                    </button>
                                @endif

                                <div wire:loading wire:target="owner_avatar" class="flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    Uploading…
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                    <p class="{{ $__sectionEyebrow }} mb-5">
                        <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                        Owner Information
                    </p>

                    <div class="space-y-5">
                        <div>
                            <label for="owner_full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Full Name <span class="text-rose-500" aria-hidden="true">*</span>
                            </label>
                            <input type="text" id="owner_full_name" wire:model="owner_full_name"
                                   placeholder="Juan dela Cruz"
                                   autocomplete="name"
                                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                            @error('owner_full_name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label for="owner_id_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Type of Government ID <span class="text-rose-500" aria-hidden="true">*</span>
                                </label>
                                <select id="owner_id_type" wire:model.live="owner_id_type"
                                        class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition
                                               [touch-action:manipulation]">
                                    <option value="">— Select an accepted ID —</option>
                                    @foreach($this->ownerIdTypes as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('owner_id_type') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label for="owner_id_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    ID Number <span class="text-rose-500" aria-hidden="true">*</span>
                                </label>
                                <input type="text" id="owner_id_number" wire:model="owner_id_number"
                                       placeholder="Number as printed on your ID"
                                       autocomplete="off"
                                       class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                                @error('owner_id_number') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-2 sm:max-w-xs">
                                <label for="owner_birthdate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                    Date of Birth <span class="text-[10px] font-normal text-gray-400">(optional)</span>
                                </label>
                                <input type="date" id="owner_birthdate" wire:model="owner_birthdate"
                                       autocomplete="bday"
                                       max="{{ now()->subDay()->format('Y-m-d') }}"
                                       class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6 mt-5">
                    <p class="{{ $__sectionEyebrow }} mb-5">
                        <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                        Contact Information
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="contact_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Email <span class="text-rose-500" aria-hidden="true">*</span>
                            </label>
                            <input type="email" id="contact_email" wire:model="contact_email"
                                   autocomplete="email" inputmode="email"
                                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                            @error('contact_email') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="contact_phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Phone <span class="text-rose-500" aria-hidden="true">*</span>
                            </label>
                            <input type="tel" id="contact_phone" wire:model="contact_phone"
                                   placeholder="09xxxxxxxxx"
                                   autocomplete="tel"
                                   inputmode="numeric"
                                   maxlength="13"
                                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                            @error('contact_phone') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>
            @endif

            <section class="mt-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 bg-white dark:bg-gray-800/90 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700/60 bg-gradient-to-r from-primary-50/60 to-transparent dark:from-primary-500/[0.06] dark:to-transparent">
                    <p class="{{ $__sectionEyebrow }}">
                        <span class="{{ $__sectionRule }}" aria-hidden="true"></span>
                        Review &amp; Submit
                    </p>
                    <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                        One last look. Everything below will be visible to our reviewers.
                    </p>
                </div>

                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">
                        Public Listing Preview
                    </p>

                    <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 max-w-sm">
                        @php
                            $previewCover = $this->cover_photo_path
                                ? '/storage/' . ltrim($this->cover_photo_path, '/')
                                : null;
                            $previewLogo = $this->logo_path
                                ? '/storage/' . ltrim($this->logo_path, '/')
                                : null;
                        @endphp

                        @if($previewCover)
                            <img src="{{ $previewCover }}" alt="" loading="lazy" decoding="async" class="w-full aspect-[3/1] object-cover">
                        @else
                            <div class="w-full aspect-[3/1] bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white/70 text-xs font-semibold uppercase tracking-wider">
                                No cover yet
                            </div>
                        @endif

                        <div class="p-4">
                            <div class="flex items-start gap-3">
                                <div class="shrink-0 w-12 h-12 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                    @if($previewLogo)
                                        <img src="{{ $previewLogo }}" alt="" loading="lazy" decoding="async" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400">
                                            {{ strtoupper(substr($business_name ?: 'B', 0, 2)) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $business_name ?: 'Your Business Name' }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                        {{ $city ?: 'Victorias City' }}{{ $province ? ', ' . $province : '' }}
                                    </p>
                                    @if($business_description)
                                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400 leading-relaxed line-clamp-2">
                                            {{ $business_description }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 px-5 py-4">
                    <div class="min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Business Name</dt>
                        <dd class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white truncate">{{ $business_name ?: '—' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Registration No.</dt>
                        <dd class="mt-0.5 text-sm font-mono font-medium text-gray-900 dark:text-white truncate tabular-nums">{{ $business_registration_number ?: '—' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Owner Name</dt>
                        <dd class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white truncate">{{ $owner_full_name ?: '—' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">TIN</dt>
                        <dd class="mt-0.5 text-sm font-mono font-medium text-gray-900 dark:text-white truncate tabular-nums">{{ $tin_number ?: '—' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Location Pinned</dt>
                        <dd class="mt-0.5 text-sm font-medium {{ $this->hasCoordinates ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                            {{ $this->hasCoordinates ? '✓ Yes' : '⚠ Not yet' }}
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Documents</dt>
                        <dd class="mt-0.5 text-sm font-medium tabular-nums {{ $this->hasAllRequiredDocuments ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                            {{ $this->uploadedRequiredCount }} / {{ count($this->requiredDocuments) }}
                            {{ $this->hasAllRequiredDocuments ? '✓' : '(incomplete)' }}
                        </dd>
                    </div>
                </dl>

                <div class="px-5 pb-5 flex items-center gap-3 flex-wrap">
                    <button type="button" wire:click="gotoStep(1)"
                            class="relative text-[11px] font-semibold text-primary-600 dark:text-primary-400 hover:underline py-2.5 px-1 -mx-1 -my-2.5
                                   before:absolute before:content-[''] before:-inset-1 before:rounded
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 transition-colors">
                        ← Edit business &amp; location
                    </button>
                    <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
                    <button type="button" wire:click="gotoStep(2)"
                            class="relative text-[11px] font-semibold text-primary-600 dark:text-primary-400 hover:underline py-2.5 px-1 -mx-1 -my-2.5
                                   before:absolute before:content-[''] before:-inset-1 before:rounded
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 transition-colors">
                        Edit documents &amp; verification
                    </button>
                    @if(!$this->isFollowUpApplication)
                        <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
                        <button type="button" wire:click="gotoStep(3)"
                                class="relative text-[11px] font-semibold text-primary-600 dark:text-primary-400 hover:underline py-2.5 px-1 -mx-1 -my-2.5
                                       before:absolute before:content-[''] before:-inset-1 before:rounded
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 transition-colors">
                            Edit owner &amp; contact
                        </button>
                    @endif
                </div>
            </section>
        @endif

        <div class="sticky z-10 mt-5
                    bottom-[max(1rem,var(--safe-bottom))]">
            <div class="bg-white/95 dark:bg-gray-800/95 backdrop-blur border border-gray-200 dark:border-gray-700 rounded-2xl shadow-lg p-2.5 flex items-center justify-between gap-3">

                @if ($step > 1)
                    <button type="button"
                            wire:click="back"
                            wire:loading.attr="disabled"
                            wire:target="back,next,submit"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                   transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back
                    </button>
                @else
                    <div></div>
                @endif

                <div class="hidden sm:flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                    <span class="font-semibold tabular-nums">{{ $step }}</span>
                    <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">/</span>
                    <span class="tabular-nums">{{ count($this->stepLabels) }}</span>
                </div>

                @if ($step < count($this->stepLabels))
                    <button type="button"
                            wire:click="next"
                            wire:loading.attr="disabled"
                            wire:target="back,next,submit"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="next">Continue</span>
                        <span wire:loading wire:target="next">Saving…</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                @else
                    <button type="button"
                            wire:click="submit"
                            wire:loading.attr="disabled"
                            wire:target="back,next,submit"
                            data-submit-ready="{{ $this->isReadyToSubmit ? '1' : '0' }}"
                            title="{{ $this->isReadyToSubmit ? 'Submit your application for review' : 'Complete every required field and upload every required document first' }}"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-wait">
                        <span wire:loading.remove wire:target="submit">Submit for Review</span>
                        <span wire:loading wire:target="submit">Submitting…</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        <p class="mt-3 text-center text-[11px] text-gray-400 dark:text-gray-500">
            Progress saves automatically when you continue. You can close this tab and come back anytime.
        </p>
    </div>

    <x-image-crop-modal />
</main>