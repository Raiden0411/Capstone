<?php

namespace App\Services;

use App\Models\AccountDeletionRequest;
use App\Models\BusinessApplication;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class SuperadminNotificationService
{
    /**
     * Cache key for the KYB pending-count badge.
     * Counts BusinessApplications with status pending or under_review.
     */
    public const CACHE_KEY = 'superadmin_notifications_count';

    /**
     * Cache key for the deletion-request pending-count badge.
     * Counts AccountDeletionRequests with status pending.
     *
     * NOTE: These are two DISTINCT badges with two DISTINCT caches.
     * Forgetting one does NOT refresh the other. `flush()` forgets both
     * so a state change on either side leaves the superadmin UI
     * consistent on the next page load.
     */
    public const DELETION_CACHE_KEY = 'superadmin_deletion_requests_count';

    public const CACHE_TTL = 45;      // seconds
    public const MAX_ITEMS = 5;

    /** Statuses that count as "awaiting review" for the KYB badge. */
    private const PENDING_STATUSES = [
        BusinessApplication::STATUS_PENDING,
        BusinessApplication::STATUS_UNDER_REVIEW,
    ];

    /**
     * Cached KYB pending count — scalar only, never a Collection.
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
     * Cached deletion-request pending count — scalar only, never a Collection.
     */
    public function deletionRequestCount(): int
    {
        return (int) Cache::remember(
            self::DELETION_CACHE_KEY,
            self::CACHE_TTL,
            fn () => AccountDeletionRequest::query()
                ->where('status', AccountDeletionRequest::STATUS_PENDING)
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
     * Invalidate BOTH superadmin badges.
     *
     * Call from every state change that affects a superadmin count:
     *   • BusinessApplicationService::submit/approve/reject/requestRevision
     *   • DeletionRequestController::approve/reject
     *   • Any SFC that creates or cancels an AccountDeletionRequest
     *
     * Forgetting both keys on every flush is deliberate — the individual
     * reads are a single indexed COUNT each, so the extra cache miss is
     * negligible, and this guarantees the superadmin badge never shows a
     * stale count after ANY state change, regardless of which side fired.
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::DELETION_CACHE_KEY);
    }
}