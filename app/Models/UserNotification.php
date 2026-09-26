<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    use HasFactory;

    public const SCOPE_TOURIST  = 'tourist';
    public const SCOPE_BUSINESS = 'business';
    public const SCOPE_PLATFORM = 'platform';

    protected $fillable = [
        'user_id',
        'scope',
        'type',
        'title',
        'message',
        'url',
        'icon',
        'color',
        'read_at',
    ];

    protected $casts = [
        'read_at'    => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    /**
     * Best URL to send the user to when they tap this notification.
     * Priority: observer-set url → type-based fallback → scope fallback.
     * Role/permission aware so an employee never lands on an admin page.
     */
    public function resolvedUrl(?User $user = null): ?string
    {
        if (! empty($this->url)) {
            return $this->url;
        }

        $user ??= $this->user;
        $type = (string) $this->type;

        return match ($this->scope) {
            self::SCOPE_BUSINESS => $this->businessUrlForType($type, $user),
            self::SCOPE_TOURIST  => $this->touristUrlForType($type, $user),
            self::SCOPE_PLATFORM => $this->platformUrlForType($type, $user),
            default              => null,
        };
    }

    private function businessUrlForType(string $type, ?User $user): string
    {
        $isAdmin  = $user && $user->hasAnyRole(['admin', 'super-admin']);
        $fallback = $isAdmin
            ? route('tenant.dashboard')
            : route('tenant.employee.dashboard');

        if (str_starts_with($type, 'renewal_') || str_starts_with($type, 'permit_expiry')) {
            return $isAdmin ? route('tenant.documents.index') : $fallback;
        }

        if (str_starts_with($type, 'booking')) {
            return ($user && $user->can('view bookings'))
                ? route('tenant.bookings.index')
                : $fallback;
        }

        if ($type === 'kyb' || str_starts_with($type, 'kyb_') || str_starts_with($type, 'business_application')) {
            return $isAdmin ? route('tenant.settings.index') : $fallback;
        }

        if (str_starts_with($type, 'deletion')) {
            return route('tenant.account.index');
        }

        if (str_starts_with($type, 'payment')) {
            return ($user && $user->can('view payments'))
                ? route('tenant.payments.index')
                : $fallback;
        }

        if (str_starts_with($type, 'event')) {
            return ($user && $user->can('view events'))
                ? route('tenant.events.index')
                : $fallback;
        }

        return $fallback;
    }

    private function touristUrlForType(string $type, ?User $user): string
    {
        if (str_starts_with($type, 'booking')) {
            return route('my-bookings');
        }

        if ($type === 'kyb' || str_starts_with($type, 'kyb_') || str_starts_with($type, 'business_application')) {
            return route('register_business');
        }

        if (str_starts_with($type, 'account') || str_starts_with($type, 'deletion')) {
            return route('profile');
        }

        return route('notifications.index');
    }

    private function platformUrlForType(string $type, ?User $user): string
    {
        if (str_starts_with($type, 'kyb') || str_starts_with($type, 'business_application')) {
            return route('superadmin.business-applications.index');
        }

        if (str_starts_with($type, 'deletion')) {
            return route('superadmin.deletion-requests.index');
        }

        if (str_starts_with($type, 'renewal') || str_starts_with($type, 'permit_expiry')) {
            return route('superadmin.renewals.index');
        }

        if (str_starts_with($type, 'tenant')) {
            return route('superadmin.tenants.index');
        }

        return route('superadmin.notifications.index');
    }
}