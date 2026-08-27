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

        // 2. Always allow business owners (admin) and super-admins if they have a tenant_id.
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $next($request);
        }

        // 3. Allow employees / staff who have permissions via a custom role.
        if ($user->getAllPermissions()->count() > 0) {
            return $next($request);
        }

        // 4. Everyone else is blocked.
        abort(403, 'Unauthorized access.');
    }
}