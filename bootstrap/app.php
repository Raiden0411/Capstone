<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // EnvKit reverse-proxy support — keeps the https scheme and public host.
        // Safe behind the EnvKit tunnel. Removing this breaks URL generation
        // and secure cookies on the public URL.
        $middleware->trustProxies(at: '*');

        // Spatie Permission middleware aliases.
        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Global security headers.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Spatie teams: establish the ambient team context AFTER
        // StartSession has resolved Auth::user() from the session, and
        // BEFORE any controller / SFC action issues a role check.
        $middleware->web(append: [
            \App\Http\Middleware\SetPermissionsTeamId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Production-safe error rendering with server-side logging.
        //
        // APP_DEBUG=true (local)  → return null → Laravel's Whoops page renders.
        // APP_DEBUG=false (public) → sanitized response + error log entry.
        //
        // WHY WE LOG HERE:
        //   Laravel does not log HttpException (403/404/419/429) by default.
        //   When this closure sanitizes one, the file:line of the abort()
        //   would otherwise be lost — no Whoops, no log, no browser detail.
        //   The Log::error() call below closes that gap: the user still
        //   sees the generic fallback, but laravel.log carries the actual
        //   source so debugging does not require toggling APP_DEBUG.
        $exceptions->render(function (Throwable $e, Request $request) {
            // AuthenticationException must pass through — Laravel's default
            // handler redirects to login (HTML) or emits 401 JSON. Without
            // this, the closure would compute a 500 and return plaintext.
            if ($e instanceof AuthenticationException) {
                return null;
            }

            if (config('app.debug')) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            // Log every non-404 error we are about to mask. Excluding 404
            // keeps missing-asset noise (favicon.ico, etc.) out of the log.
            if ($status !== 404) {
                Log::error('HTTP ' . $status . ' — ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'file'      => $e->getFile() . ':' . $e->getLine(),
                    'method'    => $request->method(),
                    'url'       => $request->fullUrl(),
                    'route'     => optional($request->route())->getName(),
                    'user_id'   => optional($request->user())->id,
                ]);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => match ($status) {
                        404     => 'Resource not found.',
                        403     => 'Forbidden.',
                        419     => 'Session expired. Please refresh.',
                        429     => 'Too many requests.',
                        default => 'Something went wrong.',
                    },
                ], $status);
            }

            $view = 'errors.' . $status;
            if (view()->exists($view)) {
                return response()->view($view, [], $status);
            }

            return response('Service unavailable.', $status, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        });
    })
    ->create();