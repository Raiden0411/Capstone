<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $tenant_id
 * @property string $role
 * @property bool $is_active
 * @property Carbon|null $joined_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Tenant $tenant
 */
class BusinessMembership extends Model
{
    use HasFactory;

    public const ROLE_OWNER    = 'owner';
    public const ROLE_ADMIN    = 'admin';
    public const ROLE_EMPLOYEE = 'employee';

    public const ROLES = [
        self::ROLE_OWNER,
        self::ROLE_ADMIN,
        self::ROLE_EMPLOYEE,
    ];

    /**
     * Labels for the tenant-roles UI (Phase 3).
     */
    public const ROLE_LABELS = [
        self::ROLE_OWNER    => 'Owner',
        self::ROLE_ADMIN    => 'Admin',
        self::ROLE_EMPLOYEE => 'Employee',
    ];

    protected $fillable = [
        'user_id',
        'tenant_id',
        'role',
        'is_active',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    // ─── Relationships ──────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ─── Role predicates ────────────────────────────────

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    /**
     * Owners and admins both pass admin-gated checks. This is the
     * predicate IsTenantAdmin trusts.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [
            self::ROLE_OWNER,
            self::ROLE_ADMIN,
        ], true);
    }

    public function isEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? ucfirst($this->role);
    }
}