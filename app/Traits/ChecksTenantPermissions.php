<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
     *
     * Does NOT consult Spatie's ambient team context. Reads pivot tables
     * directly, filtered by $user->tenant_id — deterministic regardless
     * of which request lifecycle the caller runs in.
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

        if (! $user->tenant_id) {
            return false;
        }

        return $this->userHasPermissionAtTenant($user, $permission);
    }

    /**
     * Team-agnostic equivalent of
     *   $user->getAllPermissions()->contains('name', $permission)
     *
     * Two paths count, mirroring what the Spatie check was intended to
     * cover:
     *
     *   1. A direct permission assigned to the user at their tenant.
     *   2. A permission reachable through any role the user holds at
     *      their tenant.
     *
     * Filtered by team_id = $user->tenant_id, NOT by the ambient
     * getPermissionsTeamId() value — which can still be 0 (the guest
     * sentinel) when this method is called from a Livewire render,
     * returning an empty permission set for legitimate employees.
     */
    protected function userHasPermissionAtTenant(User $user, string $permission): bool
    {
        $modelType = $user::class;
        $userId    = $user->getKey();
        $teamId    = $user->tenant_id;

        $hasDirect = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $userId)
            ->where('model_has_permissions.model_type', $modelType)
            ->where('model_has_permissions.team_id', $teamId)
            ->where('permissions.name', $permission)
            ->where('permissions.guard_name', 'web')
            ->exists();

        if ($hasDirect) {
            return true;
        }

        return DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', $modelType)
            ->where('model_has_roles.team_id', $teamId)
            ->where('permissions.name', $permission)
            ->where('permissions.guard_name', 'web')
            ->exists();
    }
}