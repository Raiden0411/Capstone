<?php

namespace App\Observers;

use App\Models\BusinessApplication;
use App\Services\PublicNotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Invalidates the applicant's public-notification cache on any KYB
 * lifecycle event that changes what their header dropdown shows.
 *
 * The status transitions that matter (draft → pending, pending →
 * approved/rejected/needs_revision) all happen inside
 * BusinessApplicationService methods — but the observer approach means
 * any future code path (a command, a job, a test helper) gets the flush
 * without needing to remember to call it.
 */
class BusinessApplicationObserver
{
    public function __construct(
        protected PublicNotificationService $notifications,
    ) {}

    public function created(BusinessApplication $application): void
    {
        $this->flush((int) $application->user_id);
    }

    public function updated(BusinessApplication $application): void
    {
        if (! $application->wasChanged('status')) {
            return;
        }

        $this->flush((int) $application->user_id);
    }

    public function deleted(BusinessApplication $application): void
    {
        $this->flush((int) $application->user_id);
    }

    private function flush(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        DB::afterCommit(fn () => $this->notifications->flushForUserId($userId));
    }
}