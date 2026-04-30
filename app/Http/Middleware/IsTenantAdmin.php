<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class IsTenantAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            Log::info('IsTenantAdmin middleware', [
                'user_id' => $request->user()->id,
                'roles' => $request->user()->getRoleNames()->toArray(),
                'has_admin' => $request->user()->hasRole('admin'),
                'has_any' => $request->user()->hasAnyRole(['admin', 'super-admin']),
            ]);
        }

        if ($request->user() && $request->user()->hasAnyRole(['admin', 'super-admin'])) { 
            return $next($request);
        }

        Log::warning('IsTenantAdmin blocked', [
            'user_id' => $request->user()?->id,
            'roles' => $request->user()?->getRoleNames()->toArray(),
        ]);

        abort(403, 'Unauthorized access.');
    }
}