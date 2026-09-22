<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $tenant_id
 * @property string $scope
 * @property string $status
 * @property string|null $reason
 * @property string|null $review_notes
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $reviewer
 * @property-read \App\Models\Tenant|null $tenant
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest label()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest whereReviewNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest whereReviewedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest whereReviewedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountDeletionRequest whereUserId($value)
 * @mixin \Eloquent
 */
class AccountDeletionRequest extends Model
{
    public const SCOPE_BUSINESS_ONLY = 'business_only';
    public const SCOPE_BOTH          = 'both';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const SCOPE_LABELS = [
        self::SCOPE_BUSINESS_ONLY => 'Business account only',
        self::SCOPE_BOTH          => 'Business + tourist account',
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING   => 'Pending Review',
        self::STATUS_APPROVED  => 'Approved',
        self::STATUS_REJECTED  => 'Rejected',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'user_id',
        'tenant_id',
        'scope',
        'status',
        'reason',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ─── Helpers ─────────────────────────────────────────
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopeLabel(): string
    {
        return self::SCOPE_LABELS[$this->scope] ?? ucfirst(str_replace('_', ' ', (string) $this->scope));
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isBoth(): bool
    {
        return $this->scope === self::SCOPE_BOTH;
    }

    public function isBusinessOnly(): bool
    {
        return $this->scope === self::SCOPE_BUSINESS_ONLY;
    }

    // ─── Scopes ──────────────────────────────────────────
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}