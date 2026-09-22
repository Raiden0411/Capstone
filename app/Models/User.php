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

    /** @return HasMany<BusinessDocument, $this> */
    public function businessDocuments(): HasMany
    {
        return $this->hasMany(BusinessDocument::class);
    }

    // ─── Dual-role helpers ──────────────────────────────
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
        if ($this->isBusinessOwner()) {
            return false;
        }

        if ($this->hasRole('super-admin')) {
            return false;
        }

        return ! $this->hasPendingBusinessApplication();
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