<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Production-safe error rendering.
        //
        // APP_DEBUG=true (local)  → return null → Laravel's Whoops page renders.
        // APP_DEBUG=false (public) → sanitized response. Full exception is
        // still logged to storage/logs/laravel.log by Laravel's default
        // handler before this closure fires.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (config('app.debug')) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

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