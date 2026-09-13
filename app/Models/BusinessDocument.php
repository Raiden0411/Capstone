<?php
// app/Models/BusinessDocument.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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