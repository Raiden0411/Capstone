<?php

namespace App\Observers;

use App\Models\BusinessApplication;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\PublicNotificationService;
use App\Services\SuperadminNotificationService;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\DB;

class BusinessApplicationObserver
{
    public function __construct(
        protected PublicNotificationService $notifications,
        protected UserNotificationService $userNotifications,
        protected SuperadminNotificationService $superadminCounts,
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
        $this->createStatusNotification($application);
        $this->notifySuperadminsOnSubmission($application);
    }

    public function deleted(BusinessApplication $application): void
    {
        $this->flush((int) $application->user_id);
    }

    // ─────────────────────────────────────────────────────
    //  Applicant-facing
    // ─────────────────────────────────────────────────────

    private function createStatusNotification(BusinessApplication $application): void
    {
        DB::afterCommit(function () use ($application): void {
            $applicant = $application->user;
            if (! $applicant) {
                return;
            }

            $name = $application->business_name ?? 'Your business';

            $payload = match ($application->status) {
                BusinessApplication::STATUS_NEEDS_REVISION => [
                    'scope'   => UserNotification::SCOPE_TOURIST,
                    'type'    => 'kyb',
                    'title'   => 'Revision requested',
                    'message' => "Your application for {$name} needs updates before review.",
                    'url'     => route('register_business.edit', ['application' => $application->id]),
                    'icon'    => 'alert',
                    'color'   => 'amber',
                ],
                BusinessApplication::STATUS_APPROVED => [
                    'scope'   => UserNotification::SCOPE_BUSINESS,
                    'type'    => 'kyb',
                    'title'   => 'Business approved',
                    'message' => "{$name} is now live on the platform.",
                    'url'     => route('tenant.dashboard'),
                    'icon'    => 'check-circle',
                    'color'   => 'emerald',
                ],
                BusinessApplication::STATUS_REJECTED => [
                    'scope'   => UserNotification::SCOPE_TOURIST,
                    'type'    => 'kyb',
                    'title'   => 'Application rejected',
                    'message' => $application->rejection_reason
                        ?: "Your application for {$name} was rejected.",
                    'url'     => route('register_business'),
                    'icon'    => 'alert',
                    'color'   => 'rose',
                ],
                default => null,
            };

            if ($payload === null) {
                return;
            }

            $this->userNotifications->notify($applicant, $payload);
        });
    }

    // ─────────────────────────────────────────────────────
    //  Superadmin-facing
    // ─────────────────────────────────────────────────────

    private function notifySuperadminsOnSubmission(BusinessApplication $application): void
    {
        if ($application->status !== BusinessApplication::STATUS_PENDING) {
            return;
        }

        DB::afterCommit(function () use ($application): void {
            // Team-agnostic — see User::scopeWhereSuperAdmin.
            $superadmins = User::whereSuperAdmin()->get();
            if ($superadmins->isEmpty()) {
                return;
            }

            $name = $application->business_name ?? 'Untitled application';

            $applicantName = $application->owner_full_name
                ?? $application->user->name
                ?? 'An applicant';

            $this->userNotifications->notifyMany($superadmins, [
                'scope'   => UserNotification::SCOPE_PLATFORM,
                'type'    => 'kyb',
                'title'   => 'New KYB application',
                'message' => "{$applicantName} submitted {$name} for review.",
                'url'     => route('superadmin.business-applications.show', $application),
                'icon'    => 'inbox',
                'color'   => 'amber',
            ]);

            $this->superadminCounts->flush();
        });
    }

    // ─────────────────────────────────────────────────────
    //  Cache flushes
    // ─────────────────────────────────────────────────────

    private function flush(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        DB::afterCommit(fn () => $this->notifications->flushForUserId($userId));
    }
}