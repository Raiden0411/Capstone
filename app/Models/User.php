<?php

namespace App\Models;

use App\Mail\PasswordResetLink;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $avatar
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property string $active_mode
 * @property Carbon|null $privacy_accepted_at
 * @property string|null $privacy_policy_version
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BusinessMembership> $businessMemberships
 * @property-read int|null $business_memberships_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenant> $businesses
 * @property-read int|null $businesses_count
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable, HasRoles;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'is_active',
        'avatar',
        'phone',
        'active_mode',
        'privacy_accepted_at',
        'privacy_policy_version',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'   => 'datetime',
            'password'            => 'hashed',
            'is_active'           => 'boolean',
            'privacy_accepted_at' => 'datetime',
        ];
    }

    public const MODE_TOURIST  = 'tourist';
    public const MODE_BUSINESS = 'business';

    // ─── Relationships ──────────────────────────────────

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasOne<Employee, $this> */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<BusinessApplication, $this> */
    public function businessApplications(): HasMany
    {
        return $this->hasMany(BusinessApplication::class);
    }

    /** @return HasMany<BusinessDocument, $this> */
    public function businessDocuments(): HasMany
    {
        return $this->hasMany(BusinessDocument::class);
    }

    // ─── 1a: Multi-business relationships ───────────────

    /**
     * The durable (user → business) pivot rows.
     *
     * @return HasMany<BusinessMembership, $this>
     */
    public function businessMemberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    /**
     * Businesses this user is a member of, with pivot role/is_active.
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'business_memberships')
            ->withPivot(['role', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    // ─── Dual-role helpers ──────────────────────────────

    public function activeBusinessApplication(): ?BusinessApplication
    {
        /** @var BusinessApplication|null $application */
        $application = $this->businessApplications()
            ->whereIn('status', [
                BusinessApplication::STATUS_DRAFT,
                BusinessApplication::STATUS_NEEDS_REVISION,
            ])
            ->latest()
            ->first();

        return $application;
    }

    /**
     * True if the user is a business OWNER of any business.
     *
     * Preserved from pre-1a behavior: checks `tenant_id` + the global
     * Spatie 'admin' role. Under 1b this becomes team-scoped — the
     * check moves into a `BusinessMembership` query. For 1a the pivot
     * is present but supplementary; the legacy check is what every
     * existing call site expects.
     */
    public function isBusinessOwner(): bool
    {
        return ! is_null($this->tenant_id) && $this->hasRole('admin');
    }

    public function canSwitchModes(): bool
    {
        return $this->isBusinessOwner();
    }

    public function hasPendingBusinessApplication(): bool
    {
        return $this->businessApplications()
            ->whereIn('status', [
                BusinessApplication::STATUS_PENDING,
                BusinessApplication::STATUS_UNDER_REVIEW,
            ])
            ->exists();
    }

    /**
     * 1a: Existing owners MUST be allowed to apply for an additional
     * business (Answer A). Only super-admins and users with a live
     * pending application are blocked.
     */
    public function canRegisterBusiness(): bool
    {
        if ($this->hasRole('super-admin')) {
            return false;
        }

        return ! $this->hasPendingBusinessApplication();
    }

    // ─── 1a: Multi-business helpers ─────────────────────

    /**
     * Does this user own or administer the given tenant?
     *
     * Phase 2 (BusinessSwitcherService) calls this to validate that a
     * requested switch target is actually a business the user is
     * allowed to switch to. A failure here means a tampered request.
     */
    public function ownsBusiness(int $tenantId): bool
    {
        return $this->businessMemberships()
            ->where('tenant_id', $tenantId)
            ->whereIn('role', [
                BusinessMembership::ROLE_OWNER,
                BusinessMembership::ROLE_ADMIN,
            ])
            ->exists();
    }

    /**
     * Admin-level membership of a specific tenant — same predicate as
     * ownsBusiness() but reads more clearly at the call site when
     * checking whether an actor may administer a target business.
     */
    public function isAdminOf(int $tenantId): bool
    {
        return $this->ownsBusiness($tenantId);
    }

    /**
     * How many businesses this user is a member of.
     */
    public function businessCount(): int
    {
        return $this->businessMemberships()->count();
    }

    /**
     * The pivot row for the user's currently-active business.
     *
     * Returns null when the user has no active business (tourist-only
     * account, or a membership exists but none is marked active).
     */
    public function activeMembership(): ?BusinessMembership
    {
        if (! $this->tenant_id) {
            return null;
        }

        /** @var BusinessMembership|null $membership */
        $membership = $this->businessMemberships()
            ->where('tenant_id', $this->tenant_id)
            ->first();

        return $membership;
    }

    // ─── GDPR consent helpers ───────────────────────────

    public function hasAcceptedCurrentPrivacyPolicy(): bool
    {
        return $this->privacy_accepted_at !== null
            && $this->privacy_policy_version === config('legal.privacy_policy_version');
    }

    // ─── Password reset notification ────────────────────

    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $this->email,
        ], false));

        Mail::to($this->email)->send(new PasswordResetLink(
            recipientName:  $this->name,
            recipientEmail: $this->email,
            resetUrl:       $resetUrl,
        ));
    }

    // ─── Scopes ─────────────────────────────────────────

    public function scopeTourists($query)
    {
        return $query->whereNull('tenant_id');
    }

    public function scopeBusinessOwners($query)
    {
        return $query->whereNotNull('tenant_id')
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'));
    }
}