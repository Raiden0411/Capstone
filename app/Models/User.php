<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // ─── Mode constants ─────────────────────────────────
    public const MODE_TOURIST  = 'tourist';
    public const MODE_BUSINESS = 'business';

    // ─── Relationships ──────────────────────────────────
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * All KYB applications submitted by this user.
     */
    public function businessApplications(): HasMany
    {
        return $this->hasMany(BusinessApplication::class);
    }

    /**
     * The current editable (draft / needs-revision) application, if any.
     */
    public function activeBusinessApplication(): ?BusinessApplication
    {
        return $this->businessApplications()
            ->whereIn('status', [
                BusinessApplication::STATUS_DRAFT,
                BusinessApplication::STATUS_NEEDS_REVISION,
            ])
            ->latest()
            ->first();
    }

    /**
     * All KYB documents this user has uploaded.
     */
    public function businessDocuments(): HasMany
    {
        return $this->hasMany(BusinessDocument::class);
    }

    // ─── Dual-role helpers ──────────────────────────────
    /**
     * True when the user owns a verified business (admin role + tenant).
     */
    public function isBusinessOwner(): bool
    {
        return !is_null($this->tenant_id) && $this->hasRole('admin');
    }

    /**
     * Whether the user can toggle between tourist and business modes.
     */
    public function canSwitchModes(): bool
    {
        return $this->isBusinessOwner();
    }

    /**
     * Whether the user has any non-draft KYB application in progress.
     */
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
     * Whether the user should see the "Register Business" CTA.
     */
    public function canRegisterBusiness(): bool
    {
        if ($this->isBusinessOwner()) {
            return false;
        }

        if ($this->hasRole('super-admin')) {
            return false;
        }

        return !$this->hasPendingBusinessApplication();
    }

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