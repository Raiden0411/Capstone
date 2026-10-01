<?php

namespace App\Observers;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\PublicNotificationService;
use App\Services\SuperadminNotificationService;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\DB;

class AccountDeletionRequestObserver
{
    public function __construct(
        protected UserNotificationService $userNotifications,
        protected PublicNotificationService $publicNotifications,
        protected SuperadminNotificationService $superadminCounts,
    ) {}

    public function created(AccountDeletionRequest $request): void
    {
        DB::afterCommit(function () use ($request): void {
            $request->loadMissing('user:id,name,email');

            $applicant     = $request->user;
            $applicantName = $applicant->name ?? 'A user';
            $scopeLabel    = $request->scopeLabel();

            // Team-agnostic super-admin lookup — see User::scopeWhereSuperAdmin.
            $superadmins = User::whereSuperAdmin()->get();

            $this->userNotifications->notifyMany($superadmins, [
                'scope'   => UserNotification::SCOPE_PLATFORM,
                'type'    => 'deletion_request',
                'title'   => 'New deletion request',
                'message' => "{$applicantName} requested account deletion ({$scopeLabel}).",
                'url'     => route('superadmin.deletion-requests.show', ['request' => $request->id]),
                'icon'    => 'alert',
                'color'   => 'rose',
            ]);

            $this->superadminCounts->flush();
        });
    }

    public function updated(AccountDeletionRequest $request): void
    {
        if (! $request->wasChanged('status')) {
            return;
        }

        DB::afterCommit(function () use ($request): void {
            $request->loadMissing('user:id,name,email');

            $applicant = $request->user;
            if (! $applicant) {
                return;
            }

            $scope = $applicant->tenant_id
                ? UserNotification::SCOPE_BUSINESS
                : UserNotification::SCOPE_TOURIST;

            $payload = match ($request->status) {
                AccountDeletionRequest::STATUS_APPROVED => [
                    'type'    => 'deletion_request',
                    'title'   => 'Deletion request approved',
                    'message' => 'Your account deletion request was approved.',
                    'icon'    => 'check-circle',
                    'color'   => 'emerald',
                ],
                AccountDeletionRequest::STATUS_REJECTED => [
                    'type'    => 'deletion_request',
                    'title'   => 'Deletion request rejected',
                    'message' => 'Your account deletion request was rejected. Contact support for details.',
                    'icon'    => 'alert',
                    'color'   => 'rose',
                ],
                AccountDeletionRequest::STATUS_CANCELLED => [
                    'type'    => 'deletion_request',
                    'title'   => 'Deletion request cancelled',
                    'message' => 'Your account deletion request was cancelled.',
                    'icon'    => 'check-circle',
                    'color'   => 'slate',
                ],
                default => null,
            };

            if ($payload === null) {
                return;
            }

            $this->userNotifications->notify($applicant, array_merge($payload, [
                'scope' => $scope,
            ]));

            $this->publicNotifications->flushForUserId((int) $applicant->id);
        });
    }
}