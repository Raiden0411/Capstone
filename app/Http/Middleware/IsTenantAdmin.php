<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        //    currently-active tenant. The 1a migration backfilled this
        //    for every existing user with a tenant_id, so the check is
        //    authoritative for anyone who existed before this deploy.
        //
        //    Factory-created users in tests (which do NOT create pivot
        //    rows) fall through to the legacy branch below.
        $membership = $user->businessMemberships()
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (! $membership) {
            // ── Legacy fallback ──
            // Pre-1a semantic: any user with a tenant_id and the
            // global 'admin' role passed. Kept so tests and any
            // out-of-band users (seeded directly via tinker) still work.
            if ($user->hasAnyRole(['admin'])) {
                return $next($request);
            }

            if ($user->getAllPermissions()->isEmpty()) {
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
        if ($user->getAllPermissions()->isEmpty()) {
            abort(403, 'You have no assigned permissions.');
        }

        return $next($request);
    }
}