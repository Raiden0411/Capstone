<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'type_of_tenant_id',
        'status',
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

    /** Human-readable labels for application statuses. */
    public const STATUS_LABELS = [
        'draft'          => 'Draft',
        'pending'        => 'Pending',
        'under_review'   => 'Under Review',
        'needs_revision' => 'Needs Revision',
        'approved'       => 'Approved',
        'rejected'       => 'Rejected',
    ];

    public const BUSINESS_TYPES = ['dti', 'sec', 'cda'];

    /** Human-readable labels for registration types. */
    public const BUSINESS_TYPE_LABELS = [
        'dti' => 'DTI — Sole Proprietorship',
        'sec' => 'SEC — Corporation or Partnership',
        'cda' => 'CDA — Cooperative',
    ];

    /**
     * Example registration-number format per registration type. Drives the
     * placeholder text on Step 1 of the KYB form so the hint matches
     * whichever authority issued the applicant's certificate.
     */
    public const REGISTRATION_NUMBER_PLACEHOLDERS = [
        'dti' => 'DTI-2024-123456',
        'sec' => 'CS20241234567',
        'cda' => 'CDA-2024-12345',
    ];

    /**
     * One-line explanation of what the registration number is, per type.
     * Rendered as helper text below the field.
     */
    public const REGISTRATION_NUMBER_HINTS = [
        'dti' => 'Found on your DTI Certificate of Business Name Registration.',
        'sec' => 'Your SEC Company Registration Number (CS or PS).',
        'cda' => 'Your CDA Certificate of Registration number.',
    ];

    /** Documents always required for every application. */
    public const REQUIRED_DOCUMENTS = [
        'dti_sec_cda',
        'bir_2303',
        'mayors_permit',
        'owner_id',
    ];

    /** Optional or conditional. */
    public const OPTIONAL_DOCUMENTS = [
        'authorization_letter',
        'bank_proof',
        'sample_receipt',
    ];

    /** Human-readable labels for document types. */
    public const DOCUMENT_LABELS = [
        'dti_sec_cda'          => 'DTI / SEC / CDA Registration',
        'bir_2303'             => 'BIR Form 2303',
        'mayors_permit'        => "Mayor's Permit",
        'owner_id'             => 'Owner Valid ID',
        'authorization_letter' => 'Authorization Letter',
        'bank_proof'           => 'Bank Proof',
        'sample_receipt'       => 'Sample Receipt',
    ];

    /**
     * Accepted owner identification documents.
     *
     * Keys are stored in `business_applications.owner_id_type`; values are
     * the human-readable labels shown in the application form.
     *
     * Only IDs issued by a Philippine government agency — or the equivalent
     * digital variants recognized by the Philippine Statistics Authority —
     * are accepted.
     */
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'approved_tenant_id');
    }

    public function typeOfTenant(): BelongsTo
    {
        return $this->belongsTo(TypeOfTenant::class, 'type_of_tenant_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BusinessDocument::class);
    }

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
        return $this->documents->firstWhere('document_type', $type);
    }

    public function hasAllRequiredDocuments(): bool
    {
        $uploaded = $this->documents->pluck('document_type')->unique()->all();

        return empty(array_diff(self::REQUIRED_DOCUMENTS, $uploaded));
    }

    /**
     * Whether the application has every required field and document
     * needed to enter the review queue.
     */
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

        if (!$this->type_of_tenant_id) {
            return false;
        }

        return $this->hasAllRequiredDocuments();
    }

    /**
     * Percentage of required setup completed.
     */
    public function completionPercent(): int
    {
        $checks = [
            !empty($this->business_name),
            !empty($this->business_type),
            !empty($this->type_of_tenant_id),
            !empty($this->business_registration_number),
            !empty($this->tin_number),
            !empty($this->owner_full_name),
            !empty($this->owner_id_type),
            !empty($this->owner_id_number),
            !empty($this->contact_email),
            !empty($this->contact_phone),
            $this->hasAllRequiredDocuments(),
        ];

        $done = count(array_filter($checks));

        return (int) round(($done / count($checks)) * 100);
    }

    // ── Scopes ───────────────────────────────────────────
    public function scopePendingReview($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
        ]);
    }

    // ── Boot ─────────────────────────────────────────────
    /**
     * Keep the canonical columns in sync with the human-readable
     * originals on every save. Canonical forms are used by the
     * uniqueness check in BusinessApplicationService::submit().
     *
     *   TIN   → digits only               "123-456-789-000" → "123456789000"
     *   Reg# → uppercase, no whitespace   "cs2024 1234567"  → "CS20241234567"
     */
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