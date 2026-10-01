<?php

namespace App\Models;

use App\Mail\PasswordResetLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use function getPermissionsTeamId;
use function setPermissionsTeamId;

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
 * @property-read EloquentCollection<int, BusinessMembership> $businessMemberships
 * @property-read int|null $business_memberships_count
 * @property-read EloquentCollection<int, Tenant> $businesses
 * @property-read int|null $businesses_count
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use HasRoles {
        roles as protected rolesBase;
    }

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

    // ═════════════════════════════════════════════════════════
    //  Team-scoped role accessors
    //
    //  ── WHY DIRECT DB WRITES FOR MUTATIONS ──
    //
    //  Spatie's assignRole()/syncRoles() call $this->hasRole(...)
    //  before inserting, to avoid duplicates. Our hasRole override
    //  looks at [tenant_id, 0] for backward-compat with super-admin
    //  pivots. That combination silently breaks writes:
    //
    //    1. Seeder writes tourist+admin at team 0 (tenant_id null).
    //    2. Later, syncRoles(['tourist','admin']) runs at team 1
    //       (tenant_id now set). Spatie's guard calls hasRole →
    //       our override finds the team-0 pivot → returns true →
    //       Spatie skips the team-1 insert.
    //    3. Same for assignRoleAtTeam(7, 'admin') after a second
    //       business is approved — the pivot never lands at team 7,
    //       and the owner is locked out of /admin/* on the new
    //       business.
    //
    //  The mutators below therefore bypass Spatie entirely and
    //  write directly to model_has_roles. Reads (hasRole/hasAnyRole/
    //  roles()) still flow through Spatie's relation, wrapped with
    //  a pinned team context.
    //
    //  ── SUPER-ADMIN SPECIFICALLY ──
    //
    //  hasRole('super-admin') allows a fallback at team_id = 0, so
    //  the platform-level pivot is visible from any tenant context.
    //  No other role gets that fallback — that would be a privilege
    //  leak.
    // ═════════════════════════════════════════════════════════

    public function hasRole($roles, ?string $guard = null): bool
    {
        $names = $this->normalizeRoleNames($roles);

        return $this->roleNameExists($names, $guard);
    }

    public function hasAnyRole(...$roles): bool
    {
        $names = $this->normalizeRoleNames($roles);

        return $this->roleNameExists($names, null);
    }

    /**
     * Assign one or more roles to this user at their active team.
     */
    public function assignRole(...$roles): static
    {
        return $this->assignRoleAtTeam($this->tenant_id ?? 0, ...$roles);
    }

    /**
     * Assign roles at an EXPLICIT team.
     *
     * Used by BusinessApplicationService::approve() when approving
     * a second (or later) business for an existing owner — the role
     * must be written at the NEW tenant's team, not at the owner's
     * active tenant's team.
     */
    public function assignRoleAtTeam(int $teamId, ...$roles): static
    {
        $roleIds = $this->resolveRoleIds($roles);

        if (! empty($roleIds)) {
            $rows = array_map(fn (int $rid): array => [
                'role_id'    => $rid,
                'model_id'   => $this->getKey(),
                'model_type' => self::class,
                'team_id'    => $teamId,
            ], $roleIds);

            // insertOrIgnore → INSERT IGNORE (MySQL) / INSERT OR
            // IGNORE (SQLite) / ON CONFLICT DO NOTHING (Postgres).
            // The PK on model_has_roles is (team_id, role_id,
            // model_id, model_type), so re-inserts are safe no-ops.
            DB::table('model_has_roles')->insertOrIgnore($rows);
        }

        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Remove one or more roles at the user's active team.
     */
    public function removeRole($role): static
    {
        $roleIds = $this->resolveRoleIds([$role]);
        $teamId  = $this->tenant_id ?? 0;

        if (! empty($roleIds)) {
            DB::table('model_has_roles')
                ->where('model_id', $this->getKey())
                ->where('model_type', self::class)
                ->where('team_id', $teamId)
                ->whereIn('role_id', $roleIds)
                ->delete();
        }

        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Replace this user's roles at their active team with the given
     * set. Roles at other teams (including team 0) are untouched —
     * a user may legitimately be a tourist at team 0 AND an admin
     * at their active tenant.
     */
    public function syncRoles(...$roles): static
    {
        $roleIds = $this->resolveRoleIds($roles);
        $teamId  = $this->tenant_id ?? 0;

        DB::transaction(function () use ($teamId, $roleIds): void {
            DB::table('model_has_roles')
                ->where('model_id', $this->getKey())
                ->where('model_type', self::class)
                ->where('team_id', $teamId)
                ->delete();

            if (! empty($roleIds)) {
                $rows = array_map(fn (int $rid): array => [
                    'role_id'    => $rid,
                    'model_id'   => $this->getKey(),
                    'model_type' => self::class,
                    'team_id'    => $teamId,
                ], $roleIds);

                DB::table('model_has_roles')->insert($rows);
            }
        });

        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Normalize the many shapes Spatie accepts into a flat list of
     * role NAMES.
     *
     * @return array<int, string>
     */
    private function normalizeRoleNames(mixed $roles): array
    {
        $flat = collect(is_array($roles) ? $roles : [$roles])
            ->flatten()
            ->all();

        return collect($flat)
            ->map(function (mixed $r): ?string {
                if (is_string($r)) {
                    if (str_contains($r, '|')) {
                        return null; // re-expanded below
                    }
                    return $r;
                }
                if ($r instanceof RoleContract) {
                    return $r->name;
                }
                if (is_int($r)) {
                    return SpatieRole::query()->whereKey($r)->value('name');
                }
                return null;
            })
            ->flatMap(function (?string $name) use ($flat): array {
                if ($name === null) {
                    return collect($flat)
                        ->filter(fn ($x) => is_string($x) && str_contains($x, '|'))
                        ->flatMap(fn (string $x) => array_map('trim', explode('|', $x)))
                        ->all();
                }
                return [$name];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Normalize inputs into a flat list of role IDs.
     *
     * @param  array<int, mixed>  $roles
     * @return array<int, int>
     */
    private function resolveRoleIds(array $roles): array
    {
        $names = $this->normalizeRoleNames($roles);

        if (empty($names)) {
            return [];
        }

        return SpatieRole::query()
            ->whereIn('name', $names)
            ->where('guard_name', 'web')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Does this user hold any of $names?
     *
     * Checks the user's ACTIVE team. Falls back to team_id = 0 only
     * when 'super-admin' is among the requested names, so the
     * platform-level pivot is visible from any context. No other
     * role gets that fallback — a stale team-0 'admin' pivot must
     * never grant admin at an arbitrary tenant.
     *
     * @param  array<int, string>  $names
     */
    private function roleNameExists(array $names, ?string $guard): bool
    {
        if (empty($names) || ! $this->exists) {
            return false;
        }

        $teamId = $this->tenant_id ?? 0;

        $base = fn () => DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->getKey())
            ->where('model_has_roles.model_type', self::class)
            ->whereIn('roles.name', $names)
            ->when($guard !== null, fn ($q) => $q->where('roles.guard_name', $guard));

        if ($base()->where('model_has_roles.team_id', $teamId)->exists()) {
            return true;
        }

        if ($teamId !== 0 && in_array('super-admin', $names, true)) {
            return $base()->where('model_has_roles.team_id', 0)->exists();
        }

        return false;
    }

    /**
     * Team-agnostic permission check — the equivalent of
     * `$this->getAllPermissions()->contains('name', $permission)`.
     *
     * Reads model_has_permissions and model_has_roles ⋈ role_has_permissions
     * directly, filtered to THIS user's tenant_id. Does NOT consult
     * getPermissionsTeamId().
     *
     * WHY THIS EXISTS:
     *
     *   Spatie's getAllPermissions() reads $this->permissions and
     *   $this->roles, both of which bake getPermissionsTeamId() into the
     *   query at relation-build time. In any context where the ambient
     *   context is stale — the SetPermissionsTeamId middleware runs AFTER
     *   StartSession so it can still be 0 in early lifecycle hooks, and
     *   queue workers/CLI commands never run it at all — the check
     *   returns an empty set for a legitimate user whose pivot rows exist
     *   at the correct team_id.
     *
     *   A direct pivot read against $this->tenant_id is deterministic:
     *   its result does not depend on any ambient Spatie state.
     *
     * Both a direct permission at the user's team AND a permission
     * reachable through a role at the user's team count, mirroring what
     * the Spatie check was intended to cover.
     */
    public function hasPermissionAtTenant(string $permission): bool
    {
        if (! $this->exists || ! $this->tenant_id) {
            return false;
        }

        $teamId = $this->tenant_id;

        $hasDirect = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $this->getKey())
            ->where('model_has_permissions.model_type', self::class)
            ->where('model_has_permissions.team_id', $teamId)
            ->where('permissions.name', $permission)
            ->where('permissions.guard_name', 'web')
            ->exists();

        if ($hasDirect) {
            return true;
        }

        return DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_id', $this->getKey())
            ->where('model_has_roles.model_type', self::class)
            ->where('model_has_roles.team_id', $teamId)
            ->where('permissions.name', $permission)
            ->where('permissions.guard_name', 'web')
            ->exists();
    }

    /**
     * Team-agnostic super-admin lookup.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWhereSuperAdmin(Builder $query): Builder
    {
        return $query->whereIn('id', function ($sub): void {
            $sub->select('model_has_roles.model_id')
                ->from('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', self::class)
                ->where('roles.name', 'super-admin');
        });
    }

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

    /** @return HasMany<BusinessMembership, $this> */
    public function businessMemberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    /** @return BelongsToMany<Tenant, $this> */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'business_memberships')
            ->withPivot(['role', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Spatie's roles() relation, overridden so the ambient team
     * context is pinned to THIS user's tenant while the relation is
     * being built.
     *
     * Related model is declared as SpatieRole (the concrete
     * Eloquent model) rather than the RoleContract interface,
     * because PHPStan requires the generic type parameter of
     * BelongsToMany to be a Model subclass. The runtime binding is
     * identical — Spatie's roles() relation is always backed by the
     * concrete Role model.
     *
     * @return BelongsToMany<SpatieRole, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->runWithUserTeam(
            fn (): BelongsToMany => $this->rolesBase()
        );
    }

    /**
     * Pin the ambient Spatie team context to THIS user's tenant for
     * the duration of $callback, then restore the previous value.
     * Used only by the roles() relation override now — mutations
     * write directly to model_has_roles.
     */
    private function runWithUserTeam(callable $callback): mixed
    {
        $previous = getPermissionsTeamId();
        setPermissionsTeamId($this->tenant_id ?? 0);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previous);
        }
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

    public function canRegisterBusiness(): bool
    {
        if ($this->hasRole('super-admin')) {
            return false;
        }

        return ! $this->hasPendingBusinessApplication();
    }

    // ─── Multi-business helpers ─────────────────────────

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

    public function isAdminOf(int $tenantId): bool
    {
        return $this->ownsBusiness($tenantId);
    }

    public function businessCount(): int
    {
        return $this->businessMemberships()->count();
    }

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