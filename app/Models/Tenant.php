<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $type_of_tenant_id
 * @property string $address
 * @property string|null $barangay
 * @property string $contact_number
 * @property string $email
 * @property string|null $logo
 * @property array<array-key, mixed>|null $coordinates
 * @property bool $is_active
 * @property bool $is_recommended
 * @property Carbon|null $permit_expires_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $last_permit_prompt_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
    /** @return BelongsTo<TypeOfTenant, $this> */
    public function typeOfTenant(): BelongsTo
    {
        return $this->belongsTo(TypeOfTenant::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Property, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<TenantSetting, $this> */
    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return HasOne<BusinessApplication, $this> */
    public function businessApplication(): HasOne
    {
        return $this->hasOne(BusinessApplication::class, 'approved_tenant_id');
    }

    /** @return HasMany<PermitRenewalReminder, $this> */
    public function permitReminders(): HasMany
    {
        return $this->hasMany(PermitRenewalReminder::class);
    }

    public function latestMayorsPermit(): ?BusinessDocument
    {
        /** @var BusinessApplication|null $app */
        $app = $this->businessApplication;
        if (! $app) {
            return null;
        }

        /** @var BusinessDocument|null $document */
        $document = BusinessDocument::query()
            ->where('business_application_id', $app->id)
            ->where('document_type', BusinessDocument::TYPE_MAYORS_PERMIT)
            ->latest('created_at')
            ->first();

        return $document;
    }

    // ── Coordinates helpers ──────────────────────────────
    public function getPrimaryCoordinates(): ?array
    {
        /** @var array<int, array<string, mixed>>|null $coords */
        $coords = $this->coordinates;

        if (! empty($coords) && isset($coords[0])) {
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
        return ! is_null($this->verified_at);
    }

    public function hasPermit(): bool
    {
        return ! is_null($this->permit_expires_at);
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
        /** @var PermitRenewalReminder|null $reminder */
        $reminder = $this->permitReminders()
            ->where('year', now()->year)
            ->first();

        return $reminder;
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