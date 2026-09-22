<?php

namespace App\Http\Middleware;

use App\Models\AccountDeletionRequest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects tenant users with a pending account-deletion request away
 * from the admin area, sending them to a dedicated status page where
 * they can see the request and cancel it if they change their mind.
 *
 * Lives on the /admin route group. Super-admins bypass entirely.
 * Tourists never reach this middleware (they're outside /admin).
 */
class BlockIfDeletionPending
{
    /**
     * Route names that stay reachable even while a deletion request is
     * pending. Everything else under /admin gets redirected to the
     * pending page.
     *
     * We allow-list by route name (not path) so URL restructuring doesn't
     * silently break the flow.
     */
    private const ALLOWED_ROUTES = [
        'tenant.account.deletion-pending',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Super-admins have their own dashboard; they don't use /admin.
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        // Only tenant users are affected. If somehow a non-tenant user
        // reaches this middleware, skip the query.
        if (! $user->tenant_id) {
            return $next($request);
        }

        // Allow the pending page itself so the redirect target doesn't
        // cause an infinite loop.
        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        // Single indexed lookup on (user_id, status) — microseconds.
        $hasPending = AccountDeletionRequest::query()
            ->where('user_id', $user->id)
            ->where('status', AccountDeletionRequest::STATUS_PENDING)
            ->exists();

        if (! $hasPending) {
            return $next($request);
        }

        return redirect()->route('tenant.account.deletion-pending');
    }
}