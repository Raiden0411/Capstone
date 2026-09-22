<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Guards mutating Livewire actions with the same permission strings the
 * route-level `permission:` middleware uses.
 *
 * WHY THIS EXISTS:
 *
 *   Route middleware only runs on the original page request. Every
 *   subsequent Livewire action — `delete`, `cancelBooking`, `submit`,
 *   `update` — is a fresh POST to /livewire-{hash}/update, which has NO
 *   `permission:` middleware. Without an in-component check, a user with
 *   only `view bookings` can invoke `delete()` because the component's
 *   only guard is a policy that checks tenant_id, not the permission.
 */
trait ChecksTenantPermissions
{
    /**
     * Abort 403 unless the current user holds the given permission.
     * Call at the top of every mutating Livewire action.
     */
    protected function requirePermission(string $permission): void
    {
        abort_unless(
            $this->tenantCan($permission),
            403,
            "You are not authorized to perform this action ({$permission})."
        );
    }

    /**
     * Boolean form — safe to use in Blade to conditionally render action
     * buttons the user cannot invoke. Public so Blade templates can call
     * `$this->tenantCan('...')`.
     */
    public function tenantCan(string $permission): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        // Admins and super-admins bypass all permission checks, matching
        // IsTenantAdmin's existing bypass.
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Tenant-scoped users must hold the specific permission.
        return (bool) $user->tenant_id
            && $user->getAllPermissions()->contains('name', $permission);
    }
}