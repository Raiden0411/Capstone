<?php
// app/Models/BusinessDocument.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $business_application_id
 * @property int $user_id
 * @property string $document_type
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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\BusinessApplication|null $application
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument expiringWithin(int $days)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereBusinessApplicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereDocumentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereDocumentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereFileHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereFileSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereIssuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereOriginalFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereStoredPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereVerificationNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereVerificationStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereWatermarkedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument whereWatermarkedPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDocument withoutTrashed()
 * @mixin \Eloquent
 */
class BusinessDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_application_id',
        'user_id',
        'document_type',
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
    ];

    protected $casts = [
        'issued_at'      => 'date',
        'expires_at'     => 'date',
        'watermarked_at' => 'datetime',
    ];

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

    public function application(): BelongsTo
    {
        return $this->belongsTo(BusinessApplication::class, 'business_application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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

    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeExpiringWithin($query, int $days)
    {
        return $query->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }
}