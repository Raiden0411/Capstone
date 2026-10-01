<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IsTenantAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized access.');
        }

        // 1. Super-admins bypass every check. They operate at the
        //    platform level and are never a member of a tenant.
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        // 2. Every other actor must have an active tenant pointer.
        if (! $user->tenant_id) {
            abort(403, 'Your account is not linked to a business.');
        }

        // 3. Pivot check: the user must hold a membership row for their
        //    currently-active tenant. Owners/admins pass unconditionally;
        //    employees need at least one assigned permission.
        //
        //    NOTE: employees created via /admin/employees/create do NOT
        //    get a business_memberships row. They always fall through
        //    to the legacy branch below. Do not "fix" that here — the
        //    membership row is an owner/admin concept only.
        $membership = $user->businessMemberships()
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (! $membership) {
            // ── Legacy fallback ──
            // Pre-1a semantic: any user with a tenant_id and either the
            // global 'admin' role OR any tenant-scoped permission passed.
            if ($user->hasAnyRole(['admin'])) {
                return $next($request);
            }

            if (! $this->userHasTenantPermissions($user)) {
                abort(403, 'You have no assigned permissions.');
            }

            return $next($request);
        }

        // 4. Owners and admins pass unconditionally. The specific
        //    action-level permission is enforced by the route-group
        //    `permission:` middleware — do NOT grant blanket access here.
        if ($membership->isAdmin()) {
            return $next($request);
        }

        // 5. Employees need at least one permission to enter /admin.
        if (! $this->userHasTenantPermissions($user)) {
            abort(403, 'You have no assigned permissions.');
        }

        return $next($request);
    }

    /**
     * Team-agnostic equivalent of `$user->getAllPermissions()->isNotEmpty()`.
     *
     * Reads model_has_permissions and model_has_roles directly, filtered
     * by $user->tenant_id. Does NOT consult getPermissionsTeamId().
     *
     * WHY THIS EXISTS:
     *   Spatie's getAllPermissions() reads $this->permissions and
     *   $this->roles, both of which bake getPermissionsTeamId() into the
     *   query at relation-build time. In this middleware's request
     *   lifecycle the ambient context can still be 0 (the guest
     *   sentinel) — even though SetPermissionsTeamId ran earlier in the
     *   web group — so the check returns empty for legitimate employees
     *   and every /admin/* page load aborts 403.
     *
     *   A direct pivot read against $user->tenant_id is deterministic:
     *   its result does not depend on any ambient Spatie state.
     *
     * Both a direct permission at the user's team AND a role-with-
     * permissions at the user's team count. Mirrors the two paths the
     * Spatie check was intended to cover.
     */
    protected function userHasTenantPermissions(User $user): bool
    {
        if (! $user->tenant_id) {
            return false;
        }

        $modelType = $user::class;
        $userId    = $user->getKey();
        $teamId    = $user->tenant_id;

        $hasDirectPermission = DB::table('model_has_permissions')
            ->where('model_id', $userId)
            ->where('model_type', $modelType)
            ->where('team_id', $teamId)
            ->exists();

        if ($hasDirectPermission) {
            return true;
        }

        return DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', $modelType)
            ->where('model_has_roles.team_id', $teamId)
            ->exists();
    }
}