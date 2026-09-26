<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BusinessApplication;
use App\Models\Tenant;
use App\Models\User;
use App\Scopes\TenantScope;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class PublicNotificationService
{
    /** Cache TTL for the computed payload (seconds). */
    public const CACHE_TTL = 45;

    /** Max items returned in the dropdown. */
    public const MAX_DISPLAYED = 6;

    /**
     * Build the notification payload for a user — tourist + business
     * items merged. Used by the public-facing bell.
     *
     * @return array{items: array<int, array<string, mixed>>, count: int}
     */
    public function forUser(?User $user): array
    {
        if (! $user) {
            return ['items' => [], 'count' => 0];
        }

        return Cache::remember(
            self::cacheKeyFor((int) $user->id),
            self::CACHE_TTL,
            fn () => $this->build($user)
        );
    }

    /**
     * Build the notification payload for a tenant admin — business
     * items ONLY.
     *
     * @return array{items: array<int, array<string, mixed>>, count: int}
     */
    public function forBusiness(?User $user): array
    {
        if (! $user || ! $user->tenant_id) {
            return ['items' => [], 'count' => 0];
        }

        return Cache::remember(
            self::businessCacheKeyFor((int) $user->id),
            self::CACHE_TTL,
            function () use ($user): array {
                $items = $this->businessNotifications($user);

                usort($items, fn ($a, $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));

                return [
                    'items' => array_slice($items, 0, self::MAX_DISPLAYED),
                    'count' => count($items),
                ];
            }
        );
    }

    /**
     * Invalidate both notification caches for a user by ID.
     */
    public function flushForUserId(int $userId): void
    {
        Cache::forget(self::cacheKeyFor($userId));
        Cache::forget(self::businessCacheKeyFor($userId));
    }

    /**
     * Invalidate the cache for a user.
     */
    public function flush(User $user): void
    {
        $this->flushForUserId((int) $user->id);
    }

    /**
     * Invalidate the notification cache for every admin user of a tenant.
     */
    public function flushTenantAdmins(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }

        User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->pluck('id')
            ->each(fn ($id) => $this->flushForUserId((int) $id));
    }

    private static function cacheKeyFor(int $userId): string
    {
        return "public_notifications:{$userId}";
    }

    private static function businessCacheKeyFor(int $userId): string
    {
        return "tenant_notifications:{$userId}";
    }

    protected function build(User $user): array
    {
        $items = array_merge(
            $this->touristNotifications($user),
            $user->isBusinessOwner() ? $this->businessNotifications($user) : [],
        );

        usort($items, fn ($a, $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));

        $items = array_slice($items, 0, self::MAX_DISPLAYED);

        return [
            'items' => $items,
            'count' => count($items),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Tourist-facing notifications
    // ─────────────────────────────────────────────────────────────

    protected function touristNotifications(User $user): array
    {
        $items = [];

        $bookings = Booking::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->whereIn('status', [
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_RESERVED,
            ])
            ->with([
                'items'                 => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property'        => fn ($q) => $q->withoutGlobalScope(TenantScope::class)
                    ->select('id', 'name', 'tenant_id'),
                'items.property.tenant' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)
                    ->select('id', 'name', 'slug'),
            ])
            ->latest('updated_at')
            ->limit(3)
            ->get();

        foreach ($bookings as $booking) {
            $property = $booking->items->first()?->property;
            $tenant   = $property?->tenant;
            $place    = $property->name ?? $tenant->name ?? 'your booking';

            if ($booking->status === Booking::STATUS_PENDING) {
                $deadline = $booking->created_at?->copy()->addMinutes(Booking::PAYMENT_DEADLINE_MINUTES);
                $isUrgent = $deadline && $deadline->isFuture() && $deadline->diffInMinutes(now()) <= 120;

                $items[] = [
                    'id'      => "booking-pending-{$booking->id}",
                    'type'    => 'booking',
                    'icon'    => 'clock',
                    'color'   => $isUrgent ? 'rose' : 'amber',
                    'title'   => $isUrgent ? 'Payment due soon' : 'Payment pending',
                    'message' => $isUrgent && $deadline
                        ? 'Complete payment within ' . $deadline->diffForHumans(now(), CarbonInterface::DIFF_ABSOLUTE)
                        : "Complete payment for your booking at {$place}.",
                    'url'     => route('my-bookings'),
                    'time'    => $booking->updated_at->timestamp ?? $booking->created_at->timestamp ?? time(),
                ];

                continue;
            }

            if ($booking->status === Booking::STATUS_RESERVED) {
                $items[] = [
                    'id'      => "booking-reserved-{$booking->id}",
                    'type'    => 'booking',
                    'icon'    => 'check-circle',
                    'color'   => 'blue',
                    'title'   => 'Reservation confirmed',
                    'message' => "Your reservation at {$place} is locked in. Pay the balance before check-in.",
                    'url'     => route('my-bookings'),
                    'time'    => $booking->updated_at->timestamp ?? time(),
                ];

                continue;
            }

            if ($booking->status === Booking::STATUS_CONFIRMED) {
                $items[] = [
                    'id'      => "booking-confirmed-{$booking->id}",
                    'type'    => 'booking',
                    'icon'    => 'check-circle',
                    'color'   => 'emerald',
                    'title'   => 'Booking confirmed',
                    'message' => "Your trip to {$place} is confirmed.",
                    'url'     => route('booking.receipt', ['booking' => $booking->id]),
                    'time'    => $booking->updated_at->timestamp ?? time(),
                ];
            }
        }

        /** @var BusinessApplication|null $application */
        $application = BusinessApplication::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                BusinessApplication::STATUS_NEEDS_REVISION,
                BusinessApplication::STATUS_PENDING,
                BusinessApplication::STATUS_UNDER_REVIEW,
                BusinessApplication::STATUS_APPROVED,
                BusinessApplication::STATUS_REJECTED,
            ])
            ->latest('updated_at')
            ->first();

        if ($application) {
            $items[] = match ($application->status) {
                BusinessApplication::STATUS_NEEDS_REVISION => [
                    'id'      => "kyb-revision-{$application->id}",
                    'type'    => 'kyb',
                    'icon'    => 'alert',
                    'color'   => 'amber',
                    'title'   => 'Revision requested',
                    'message' => 'Your business application needs updates before review.',
                    'url'     => route('register_business.edit', ['application' => $application->id]),
                    'time'    => $application->updated_at->timestamp ?? time(),
                ],
                BusinessApplication::STATUS_PENDING,
                BusinessApplication::STATUS_UNDER_REVIEW => [
                    'id'      => "kyb-review-{$application->id}",
                    'type'    => 'kyb',
                    'icon'    => 'clock',
                    'color'   => 'blue',
                    'title'   => 'Under review',
                    'message' => 'Your business application is being reviewed.',
                    'url'     => route('register_business'),
                    'time'    => $application->updated_at->timestamp ?? time(),
                ],
                BusinessApplication::STATUS_APPROVED => [
                    'id'      => "kyb-approved-{$application->id}",
                    'type'    => 'kyb',
                    'icon'    => 'check-circle',
                    'color'   => 'emerald',
                    'title'   => 'Business approved',
                    'message' => 'Your business is now live on the platform.',
                    'url'     => route('tenant.dashboard'),
                    'time'    => $application->updated_at->timestamp ?? time(),
                ],
                BusinessApplication::STATUS_REJECTED => [
                    'id'      => "kyb-rejected-{$application->id}",
                    'type'    => 'kyb',
                    'icon'    => 'alert',
                    'color'   => 'rose',
                    'title'   => 'Application rejected',
                    'message' => $application->rejection_reason ?: 'Your business application was rejected.',
                    'url'     => route('register_business'),
                    'time'    => $application->updated_at->timestamp ?? time(),
                ],
                default => null,
            };

            $items = array_values(array_filter($items));
        }

        return $items;
    }

    // ─────────────────────────────────────────────────────────────
    //  Business-owner notifications
    // ─────────────────────────────────────────────────────────────

    protected function businessNotifications(User $user): array
    {
        if (! $user->tenant_id) {
            return [];
        }

        $items = [];

        $pendingCount = Booking::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('status', Booking::STATUS_PENDING)
            ->count();

        if ($pendingCount > 0) {
            /** @var Booking|null $latest */
            $latest = Booking::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('status', Booking::STATUS_PENDING)
                ->with('user:id,name')
                ->latest('created_at')
                ->first();

            $items[] = [
                'id'      => "tenant-pending-{$user->tenant_id}",
                'type'    => 'business',
                'icon'    => 'inbox',
                'color'   => 'amber',
                'title'   => $pendingCount === 1
                    ? 'New booking request'
                    : "{$pendingCount} booking requests",
                'message' => $latest && $latest->user && $latest->user->name
                    ? "Latest from {$latest->user->name}. Review and confirm."
                    : 'Review and confirm incoming bookings.',
                'url'     => route('tenant.bookings.index'),
                // Larastan: `created_at` is always populated on a fetched
                // Eloquent model — no nullsafe on the second segment.
                'time'    => $latest?->created_at->timestamp ?? time(),
            ];
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->find($user->tenant_id);

        if ($tenant && $tenant->permit_expires_at) {
            if ($tenant->isPermitExpired()) {
                $items[] = [
                    'id'      => "permit-expired-{$tenant->id}",
                    'type'    => 'business',
                    'icon'    => 'alert',
                    'color'   => 'rose',
                    'title'   => "Mayor's Permit expired",
                    'message' => 'Upload a renewed permit to keep your listing active.',
                    'url'     => route('tenant.settings.index'),
                    'time'    => $tenant->permit_expires_at->timestamp,
                ];
            } elseif ($tenant->permitExpiresSoon(60)) {
                $items[] = [
                    'id'      => "permit-soon-{$tenant->id}",
                    'type'    => 'business',
                    'icon'    => 'clock',
                    'color'   => 'amber',
                    'title'   => 'Permit expires soon',
                    'message' => 'Your Mayor\'s Permit expires ' . $tenant->permit_expires_at->diffForHumans() . '.',
                    'url'     => route('tenant.settings.index'),
                    'time'    => $tenant->permit_expires_at->timestamp,
                ];
            }
        }

        return $items;
    }
}