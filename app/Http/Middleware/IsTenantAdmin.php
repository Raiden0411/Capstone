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

        // 1. Always allow super‑admins and business owners
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $next($request);
        }

        // 2. Allow employees / staff who have been given permissions via a custom role
        if ($user->tenant_id && $user->getAllPermissions()->count() > 0) {
            return $next($request);
        }

        // 3. Everyone else (tourists) is blocked
        abort(403, 'Unauthorized access.');
    }
}