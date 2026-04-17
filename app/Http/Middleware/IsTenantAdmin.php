<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsTenantAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow if they are a regular admin OR a super admin
        if ($request->user() && $request->user()->hasAnyRole(['admin', 'super-admin'])) { 
            return $next($request);
        }

        abort(403, 'Unauthorized access.');
    }
}