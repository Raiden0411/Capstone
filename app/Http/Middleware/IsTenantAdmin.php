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

        if (!$user) {
            abort(403, 'Unauthorized access.');
        }

        // 1. Require the user to be linked to a business.
        if (!$user->tenant_id) {
            abort(403, 'Your account is not linked to a business.');
        }

        // 2. Business owners and super-admins bypass all per-route checks.
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $next($request);
        }

        // 3. Employees must have at least one permission to enter the admin area.
        //    The SPECIFIC permission for each route is enforced by the
        //    `permission:` middleware on the individual route groups
        //    (see routes/web.php). Do NOT grant blanket access here.
        if ($user->getAllPermissions()->isEmpty()) {
            abort(403, 'You have no assigned permissions.');
        }

        // 4. Pass through — route-level `permission:` middleware will
        //    authorize the specific action this request requires.
        return $next($request);
    }
}