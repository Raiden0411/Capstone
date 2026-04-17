<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Now using the Spatie package method
        if ($request->user() && $request->user()->hasRole('admin')) { 
            return $next($request);
        }

        abort(403, 'Unauthorized access. Admins only.');
    }
}