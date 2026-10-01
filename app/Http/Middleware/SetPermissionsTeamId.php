<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function setPermissionsTeamId;

/**
 * Sets the ambient Spatie team context for the current request.
 *
 * WHY THIS IS NEEDED:
 *
 *   With 'teams' => true, Spatie's hasRole(), hasPermissionTo(), and the
 *   User::role() / User::permission() query scopes all filter by
 *   getPermissionsTeamId(). That value defaults to NULL on every request,
 *   and NULL never matches any pivot row (pivot team_id is NOT NULL).
 *
 *   This middleware establishes the correct context once, at the start of
 *   the request:
 *
 *     • unauthenticated       → 0  (platform)
 *     • authenticated,
 *       tenant_id = null      → 0  (super-admin or pre-tenant user)
 *     • authenticated,
 *       tenant_id = N         → N  (tenant-scoped user)
 *
 *   The User model's per-call overrides (see User::hasRole override) save
 *   and restore this ambient value, so cross-entity checks like
 *   $otherUser->hasRole('admin') still see the caller's context on return.
 *
 * WHY IT LIVES IN THE WEB GROUP:
 *
 *   It must run AFTER StartSession (so Auth::user() resolves from the
 *   session) but BEFORE any controller or SFC action that issues a role
 *   check. Appending to the web group satisfies both — StartSession runs
 *   first, this runs last.
 */
class SetPermissionsTeamId
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            setPermissionsTeamId(0);

            return $next($request);
        }

        setPermissionsTeamId($user->tenant_id ?? 0);

        return $next($request);
    }
}