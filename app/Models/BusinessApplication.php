<?php

namespace App\Models;

use App\Observers\BusinessApplicationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $business_name
 * @property string|null $business_type
 * @property string|null $business_registration_number
 * @property string|null $business_registration_number_canonical
 * @property string|null $tin_number
 * @property string|null $tin_canonical
 * @property string|null $owner_full_name
 * @property string|null $owner_id_type
 * @property string|null $owner_id_number
 * @property Carbon|null $owner_birthdate
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $address
 * @property string|null $barangay
 * @property string|null $city
 * @property string|null $province
 * @property array<array-key, mixed>|null $coordinates
 * @property string|null $logo_path
 * @property string|null $cover_photo_path
 * @property string|null $owner_avatar_path
 * @property int|null $type_of_tenant_id
 * @property string $status
 * @property string $source
 * @property string|null $rejection_reason
 * @property string|null $revision_notes
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property int|null $approved_tenant_id
 * @property array<array-key, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[ObservedBy([BusinessApplicationObserver::class])]
class BusinessApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'business_name',
        'business_type',
        'business_registration_number',
        'business_registration_number_canonical',
        'tin_number',
        'tin_canonical',
        'owner_full_name',
        'cover_photo_path',
        'owner_id_type',
        'owner_id_number',
        'owner_birthdate',
        'contact_email',
        'contact_phone',
        'address',
        'barangay',
        'city',
        'province',
        'coordinates',
        'logo_path',
        'owner_avatar_path',
        'type_of_tenant_id',
        'status',
        'source',
        'rejection_reason',
        'revision_notes',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'approved_tenant_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'coordinates'     => 'array',
            'metadata'        => 'array',
            'owner_birthdate' => 'date',
            'submitted_at'    => 'datetime',
            'reviewed_at'     => 'datetime',
        ];
    }

    // ── Constants ────────────────────────────────────────
    public const STATUS_DRAFT          = 'draft';
    public const STATUS_PENDING        = 'pending';
    public const STATUS_UNDER_REVIEW   = 'under_review';
    public const STATUS_NEEDS_REVISION = 'needs_revision';
    public const STATUS_APPROVED       = 'approved';
    public const STATUS_REJECTED       = 'rejected';

    public const STATUS_LABELS = [
        'draft'          => 'Draft',
        'pending'        => 'Pending',
        'under_review'   => 'Under Review',
        'needs_revision' => 'Needs Revision',
        'approved'       => 'Approved',
        'rejected'       => 'Rejected',
    ];

    public const SOURCE_SELF_SERVE        = 'self_serve';
    public const SOURCE_SUPERADMIN_DIRECT = 'superadmin_direct';

    public const SOURCE_LABELS = [
        'self_serve'        => 'Self-Serve KYB',
        'superadmin_direct' => 'Superadmin Onboarded',
    ];

    public const BUSINESS_TYPES = ['dti', 'sec', 'cda'];

    public const BUSINESS_TYPE_LABELS = [
        'dti' => 'DTI — Sole Proprietorship',
        'sec' => 'SEC — Corporation or Partnership',
        'cda' => 'CDA — Cooperative',
    ];

    public const REGISTRATION_NUMBER_PLACEHOLDERS = [
        'dti' => 'DTI-2024-123456',
        'sec' => 'CS20241234567',
        'cda' => 'CDA-2024-12345',
    ];

    public const REGISTRATION_NUMBER_HINTS = [
        'dti' => 'Found on your DTI Certificate of Business Name Registration.',
        'sec' => 'Your SEC Company Registration Number (CS or PS).',
        'cda' => 'Your CDA Certificate of Registration number.',
    ];

    public const REQUIRED_DOCUMENTS = [
        'dti_sec_cda',
        'bir_2303',
        'mayors_permit',
        'owner_id',
    ];

    public const OPTIONAL_DOCUMENTS = [
        'authorization_letter',
        'bank_proof',
        'sample_receipt',
    ];

    public const DOCUMENT_LABELS = [
        'dti_sec_cda'          => 'DTI / SEC / CDA Registration',
        'bir_2303'             => 'BIR Form 2303',
        'mayors_permit'        => "Mayor's Permit",
        'owner_id'             => 'Owner Valid ID',
        'authorization_letter' => 'Authorization Letter',
        'bank_proof'           => 'Bank Proof',
        'sample_receipt'       => 'Sample Receipt',
    ];

    public const OWNER_ID_TYPES = [
        'national_id'     => 'National ID (Physical, Printed ePhilID, or Digital)',
        'passport'        => 'Philippine Passport',
        'drivers_license' => "Driver's License (including BLTO)",
        'umid'            => 'UMID',
        'sss_id'          => 'SSS ID',
        'prc_id'          => 'PRC ID',
        'postal_id'       => 'Philippine Postal ID',
        'pagibig_id'      => 'HDMF ID (Pag-IBIG Loyalty Plus Card only)',
    ];

    // ── Relationships ────────────────────────────────────
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<Tenant, $this> */
    public function approvedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'approved_tenant_id');
    }

    /** @return BelongsTo<TypeOfTenant, $this> */
    public function typeOfTenant(): BelongsTo
    {
        return $this->belongsTo(TypeOfTenant::class, 'type_of_tenant_id');
    }

    /** @return HasMany<BusinessDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(BusinessDocument::class);
    }

    /** @return HasMany<BusinessDocumentVerification, $this> */
    public function verifications(): HasMany
    {
        return $this->hasMany(BusinessDocumentVerification::class);
    }

    // ── Helpers ──────────────────────────────────────────
    public function isEditable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_NEEDS_REVISION,
        ], true);
    }

    public function documentOf(string $type): ?BusinessDocument
    {
        /** @var BusinessDocument|null $document */
        $document = $this->documents->firstWhere('document_type', $type);

        return $document;
    }

    public function hasAllRequiredDocuments(): bool
    {
        $uploaded = $this->documents->pluck('document_type')->unique()->all();

        return empty(array_diff(self::REQUIRED_DOCUMENTS, $uploaded));
    }

    public function isReadyForSubmission(): bool
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

        return $this->hasAllRequiredDocuments();
    }

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
            $this->hasAllRequiredDocuments(),
        ];

        $done = count(array_filter($checks));

        return (int) round(($done / count($checks)) * 100);
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? ucfirst(str_replace('_', ' ', (string) $this->source));
    }

    public function isSuperadminOnboarded(): bool
    {
        return $this->source === self::SOURCE_SUPERADMIN_DIRECT;
    }

    // ── Scopes ───────────────────────────────────────────
    public function scopePendingReview($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
        ]);
    }

    public function scopeSelfServe($query)
    {
        return $query->where('source', self::SOURCE_SELF_SERVE);
    }

    public function scopeSuperadminOnboarded($query)
    {
        return $query->where('source', self::SOURCE_SUPERADMIN_DIRECT);
    }

    // ── Boot ─────────────────────────────────────────────
    protected static function booted(): void
    {
        static::saving(function (BusinessApplication $application): void {
            $application->tin_canonical = filled($application->tin_number)
                ? (preg_replace('/[^0-9]/', '', (string) $application->tin_number) ?: null)
                : null;

            $application->business_registration_number_canonical = filled($application->business_registration_number)
                ? (strtoupper(preg_replace('/\s+/', '', (string) $application->business_registration_number)) ?: null)
                : null;
        });
    }
}