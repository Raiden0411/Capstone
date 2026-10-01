<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\SuperadminNotificationService;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\DB;

class TenantObserver
{
    public function __construct(
        protected UserNotificationService $userNotifications,
        protected SuperadminNotificationService $superadminCounts,
    ) {}

    public function created(Tenant $tenant): void
    {
        DB::afterCommit(function () use ($tenant): void {
            // Team-agnostic — see User::scopeWhereSuperAdmin.
            $superadmins = User::whereSuperAdmin()->get();

            $this->userNotifications->notifyMany($superadmins, [
                'scope'   => UserNotification::SCOPE_PLATFORM,
                'type'    => 'tenant',
                'title'   => 'New business onboarded',
                'message' => "{$tenant->name} is now live on the platform.",
                'url'     => route('superadmin.tenants.preview', $tenant),
                'icon'    => 'check-circle',
                'color'   => 'emerald',
            ]);

            // Refresh superadmin badge counts.
            $this->superadminCounts->flush();
        });
    }
}