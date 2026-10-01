<?php

namespace App\Models;

use App\Observers\TenantObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
 * @property-read \App\Models\TypeOfTenant|null $typeOfTenant
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessMembership> $memberships
 * @property-read int|null $memberships_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessMembership> $owners
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessMembership> $admins
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessMembership> $employees
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Property> $properties
 * @property-read int|null $properties_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Service> $services
 * @property-read int|null $services_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Booking> $bookings
 * @property-read int|null $bookings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TenantSetting> $settings
 * @property-read int|null $settings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Event> $events
 * @property-read int|null $events_count
 * @property-read \App\Models\BusinessApplication|null $businessApplication
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PermitRenewalReminder> $permitReminders
 * @property-read int|null $permit_reminders_count
 * @method static \Database\Factories\TenantFactory factory($count = null, $state = [])
 * @method static Builder<static>|Tenant newModelQuery()
 * @method static Builder<static>|Tenant newQuery()
 * @method static Builder<static>|Tenant query()
 * @method static Builder<static>|Tenant recommended()
 * @method static Builder<static>|Tenant verified()
 * @method static Builder<static>|Tenant whereAddress($value)
 * @method static Builder<static>|Tenant whereBarangay($value)
 * @method static Builder<static>|Tenant whereContactNumber($value)
 * @method static Builder<static>|Tenant whereCoordinates($value)
 * @method static Builder<static>|Tenant whereCreatedAt($value)
 * @method static Builder<static>|Tenant whereEmail($value)
 * @method static Builder<static>|Tenant whereId($value)
 * @method static Builder<static>|Tenant whereIsActive($value)
 * @method static Builder<static>|Tenant whereIsRecommended($value)
 * @method static Builder<static>|Tenant whereLastPermitPromptAt($value)
 * @method static Builder<static>|Tenant whereLogo($value)
 * @method static Builder<static>|Tenant whereName($value)
 * @method static Builder<static>|Tenant wherePermitExpiresAt($value)
 * @method static Builder<static>|Tenant whereSlug($value)
 * @method static Builder<static>|Tenant whereTypeOfTenantId($value)
 * @method static Builder<static>|Tenant whereUpdatedAt($value)
 * @method static Builder<static>|Tenant whereVerifiedAt($value)
 * @method static Builder<static>|Tenant withPermitExpiringBefore(\Illuminate\Support\Carbon $date)
 * @mixin \Eloquent
 */
#[ObservedBy([TenantObserver::class])]
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

    // ── 1a: Multi-business relations ─────────────────────

    /** @return HasMany<BusinessMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    /** @return HasMany<BusinessMembership, $this> */
    public function owners(): HasMany
    {
        return $this->memberships()
            ->where('role', BusinessMembership::ROLE_OWNER);
    }

    /**
     * Owners + admins. The set that may administer this business.
     *
     * @return HasMany<BusinessMembership, $this>
     */
    public function admins(): HasMany
    {
        return $this->memberships()
            ->whereIn('role', [
                BusinessMembership::ROLE_OWNER,
                BusinessMembership::ROLE_ADMIN,
            ]);
    }

    /** @return HasMany<BusinessMembership, $this> */
    public function employees(): HasMany
    {
        return $this->memberships()
            ->where('role', BusinessMembership::ROLE_EMPLOYEE);
    }

    // ── 1b: Team-context-free role lookups ───────────────

    /**
     * The tenant's admin user, or null when none exists.
     *
     * WHY A DIRECT PIVOT QUERY:
     *
     *   The obvious form —
     *
     *       $this->users()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
     *
     *   — goes through Spatie's `roles()` relation, which adds a
     *   mandatory `wherePivot('team_id', getPermissionsTeamId())`. In a
     *   Livewire test or any context without `SetPermissionsTeamId`
     *   middleware (background job, CLI command), the ambient context is
     *   null and the pivot filter yields zero rows — even though the
     *   pivot rows exist and carry the right team_id.
     *
     *   This method bypasses Spatie entirely and reads the pivot table
     *   directly, filtered to THIS tenant's id. Its result is the same
     *   regardless of who calls it or from where.
     */
    public function adminUser(): ?User
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('users.tenant_id', $this->id)
            ->whereExists(function ($q): void {
                $q->select(DB::raw(1))
                    ->from('model_has_roles as mhr')
                    ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                    ->whereColumn('mhr.model_id', 'users.id')
                    ->where('mhr.model_type', User::class)
                    ->where('r.name', 'admin')
                    ->where('mhr.team_id', $this->id);
            })
            ->first();

        return $user;
    }

    // ── Permit / document helpers (unchanged) ────────────

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