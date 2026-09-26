<?php
// app/Models/BusinessDocument.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $business_application_id
 * @property int $user_id
 * @property string $document_type
 * @property bool $is_renewal
 * @property string $original_filename
 * @property string $stored_path
 * @property string|null $watermarked_path
 * @property string $mime_type
 * @property int $file_size
 * @property string|null $file_hash
 * @property string|null $document_number
 * @property \Illuminate\Support\Carbon|null $issued_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string $verification_status
 * @property string|null $verification_notes
 * @property \Illuminate\Support\Carbon|null $watermarked_at
 * @property int|null $renewal_reviewed_by
 * @property \Illuminate\Support\Carbon|null $renewal_reviewed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\BusinessApplication|null $application
 * @property-read \App\Models\User $user
 * @property-read \App\Models\User|null $renewalReviewer
 * @method static Builder<static>|BusinessDocument expiringWithin(int $days)
 * @method static Builder<static>|BusinessDocument kyb()
 * @method static Builder<static>|BusinessDocument newModelQuery()
 * @method static Builder<static>|BusinessDocument newQuery()
 * @method static Builder<static>|BusinessDocument ofType(string $type)
 * @method static Builder<static>|BusinessDocument onlyTrashed()
 * @method static Builder<static>|BusinessDocument pendingReview()
 * @method static Builder<static>|BusinessDocument query()
 * @method static Builder<static>|BusinessDocument renewals()
 * @method static Builder<static>|BusinessDocument whereBusinessApplicationId($value)
 * @method static Builder<static>|BusinessDocument whereCreatedAt($value)
 * @method static Builder<static>|BusinessDocument whereDeletedAt($value)
 * @method static Builder<static>|BusinessDocument whereDocumentNumber($value)
 * @method static Builder<static>|BusinessDocument whereDocumentType($value)
 * @method static Builder<static>|BusinessDocument whereExpiresAt($value)
 * @method static Builder<static>|BusinessDocument whereFileHash($value)
 * @method static Builder<static>|BusinessDocument whereFileSize($value)
 * @method static Builder<static>|BusinessDocument whereId($value)
 * @method static Builder<static>|BusinessDocument whereIsRenewal($value)
 * @method static Builder<static>|BusinessDocument whereIssuedAt($value)
 * @method static Builder<static>|BusinessDocument whereMimeType($value)
 * @method static Builder<static>|BusinessDocument whereOriginalFilename($value)
 * @method static Builder<static>|BusinessDocument whereRenewalReviewedAt($value)
 * @method static Builder<static>|BusinessDocument whereRenewalReviewedBy($value)
 * @method static Builder<static>|BusinessDocument whereStoredPath($value)
 * @method static Builder<static>|BusinessDocument whereUpdatedAt($value)
 * @method static Builder<static>|BusinessDocument whereUserId($value)
 * @method static Builder<static>|BusinessDocument whereVerificationNotes($value)
 * @method static Builder<static>|BusinessDocument whereVerificationStatus($value)
 * @method static Builder<static>|BusinessDocument whereWatermarkedAt($value)
 * @method static Builder<static>|BusinessDocument whereWatermarkedPath($value)
 * @method static Builder<static>|BusinessDocument withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|BusinessDocument withoutTrashed()
 * @mixin \Eloquent
 */
class BusinessDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_application_id',
        'user_id',
        'document_type',
        'is_renewal',
        'original_filename',
        'stored_path',
        'watermarked_path',
        'mime_type',
        'file_size',
        'file_hash',
        'document_number',
        'issued_at',
        'expires_at',
        'verification_status',
        'verification_notes',
        'watermarked_at',
        'renewal_reviewed_by',
        'renewal_reviewed_at',
    ];

    protected $casts = [
        'is_renewal'          => 'boolean',
        'issued_at'           => 'date',
        'expires_at'          => 'date',
        'watermarked_at'      => 'datetime',
        'renewal_reviewed_at' => 'datetime',
    ];

    // ── Constants ──────────────────────────────────────────

    public const TYPE_DTI_SEC_CDA    = 'dti_sec_cda';
    public const TYPE_BIR_2303       = 'bir_2303';
    public const TYPE_MAYORS_PERMIT  = 'mayors_permit';
    public const TYPE_OWNER_ID       = 'owner_id';
    public const TYPE_AUTHORIZATION  = 'authorization_letter';
    public const TYPE_BANK_PROOF     = 'bank_proof';
    public const TYPE_SAMPLE_RECEIPT = 'sample_receipt';
    public const TYPE_OTHER          = 'other';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    // ── Relationships ──────────────────────────────────────

    public function application(): BelongsTo
    {
        return $this->belongsTo(BusinessApplication::class, 'business_application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user who reviewed this renewal. Null for initial KYB rows —
     * those are reviewed as part of the parent application, not
     * per-document.
     */
    public function renewalReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renewal_reviewed_by');
    }

    // ── Helpers ────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function expiresSoon(int $days = 30): bool
    {
        return $this->expires_at
            && $this->expires_at->isFuture()
            && $this->expires_at->diffInDays(now()) <= $days;
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeExpiringWithin($query, int $days)
    {
        return $query->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }

    /**
     * Only renewal uploads.
     */
    public function scopeRenewals(Builder $query): Builder
    {
        return $query->where('is_renewal', true);
    }

    /**
     * Only initial KYB uploads.
     */
    public function scopeKyb(Builder $query): Builder
    {
        return $query->where('is_renewal', false);
    }

    /**
     * Anything awaiting review — used by the superadmin queue.
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('verification_status', self::STATUS_PENDING);
    }
}