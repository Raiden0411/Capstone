<?php

namespace App\Services;

use App\Models\BusinessApplication;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class SuperadminNotificationService
{
    public const CACHE_KEY = 'superadmin_notifications_count';
    public const CACHE_TTL = 45;      // seconds
    public const MAX_ITEMS = 5;

    /** Statuses that count as "awaiting review". */
    private const PENDING_STATUSES = [
        BusinessApplication::STATUS_PENDING,
        BusinessApplication::STATUS_UNDER_REVIEW,
    ];

    /**
     * Cached pending count — scalar only, never a Collection.
     */
    public function pendingCount(): int
    {
        return (int) Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => BusinessApplication::query()
                ->whereIn('status', self::PENDING_STATUSES)
                ->count(),
        );
    }

    /**
     * Fresh recent applications — not cached. Single indexed query of ≤5 rows.
     *
     * @return Collection<int, BusinessApplication>
     */
    public function recentApplications(int $limit = self::MAX_ITEMS): Collection
    {
        return BusinessApplication::query()
            ->with(['user:id,name,email,avatar'])
            ->whereIn('status', self::PENDING_STATUSES)
            ->latest('submitted_at')
            ->take($limit)
            ->get();
    }

    /**
     * Call this from BusinessApplicationService::approve/reject/requestRevision
     * to force the badge to update on the next request.
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}