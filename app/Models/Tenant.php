<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type_of_tenant_id',
        'address',
        'barangay',
        'contact_number',
        'email',
        'logo',
        'coordinates',
        'is_active',
        'is_recommended',
        'permit_expires_at',
        'verified_at',
        'last_permit_prompt_at',
    ];

    protected function casts(): array
    {
        return [
            'coordinates'           => 'array',
            'is_active'             => 'boolean',
            'is_recommended'        => 'boolean',
            'permit_expires_at'     => 'date',
            'verified_at'           => 'datetime',
            'last_permit_prompt_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────
    public function typeOfTenant(): BelongsTo
    {
        return $this->belongsTo(TypeOfTenant::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    /**
     * The KYB application that produced this tenant.
     * FK lives on business_applications.approved_tenant_id → HasOne, not BelongsTo.
     */
    public function businessApplication(): HasOne
    {
        return $this->hasOne(BusinessApplication::class, 'approved_tenant_id');
    }

    public function permitReminders(): HasMany
    {
        return $this->hasMany(PermitRenewalReminder::class);
    }

    /**
     * The most recent Mayor's Permit document on file.
     */
    public function latestMayorsPermit(): ?BusinessDocument
    {
        $app = $this->businessApplication;
        if (!$app) {
            return null;
        }

        return $app->documents()
            ->ofType(BusinessDocument::TYPE_MAYORS_PERMIT)
            ->latest('created_at')
            ->first();
    }

    // ── Coordinates helpers ──────────────────────────────
    public function getPrimaryCoordinates(): ?array
    {
        $coords = $this->coordinates;
        if (!empty($coords) && isset($coords[0])) {
            return $coords[0];
        }

        return null;
    }

    public function getAllCoordinates(): array
    {
        return $this->coordinates ?? [];
    }

    // ── Scopes ───────────────────────────────────────────
    public function scopeRecommended(Builder $query): Builder
    {
        return $query->where('is_recommended', true);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    public function scopeWithPermitExpiringBefore(Builder $query, Carbon $date): Builder
    {
        return $query->whereNotNull('permit_expires_at')
            ->where('permit_expires_at', '<=', $date);
    }

    // ── Helpers ──────────────────────────────────────────
    public function isRecommended(): bool
    {
        return (bool) $this->is_recommended;
    }

    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    public function hasPermit(): bool
    {
        return !is_null($this->permit_expires_at);
    }

    public function isPermitExpired(): bool
    {
        return $this->permit_expires_at && $this->permit_expires_at->isPast();
    }

    public function permitExpiresSoon(int $days = 30): bool
    {
        return $this->permit_expires_at
            && $this->permit_expires_at->isFuture()
            && $this->permit_expires_at->diffInDays(now()) <= $days;
    }

    public function currentPermitReminder(): ?PermitRenewalReminder
    {
        return $this->permitReminders()
            ->where('year', now()->year)
            ->first();
    }

    public function hasPendingPermitReminder(): bool
    {
        return $this->permitReminders()
            ->whereIn('status', [
                PermitRenewalReminder::STATUS_PENDING,
                PermitRenewalReminder::STATUS_SENT,
            ])
            ->exists();
    }
}