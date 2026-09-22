<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use App\Policies\RolePolicy;
use App\Models\Event;
use App\Policies\EventPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Livewire Component Namespaces
        |--------------------------------------------------------------------------
        | Three top-level namespaces map to the three actor contexts. Any SFC
        | under resources/views/public resolves as `public::...`, etc.
        */
        Livewire::addNamespace(namespace: 'public',     viewPath: resource_path('views/public'));
        Livewire::addNamespace(namespace: 'superadmin', viewPath: resource_path('views/superadmin'));
        Livewire::addNamespace(namespace: 'tenant',     viewPath: resource_path('views/tenant'));

        /*
        |--------------------------------------------------------------------------
        | Manual Component Registrations
        |--------------------------------------------------------------------------
        | SFCs whose paths don't resolve through the namespace auto-resolution
        | are registered explicitly. Livewire uses these registrations verbatim
        | without running its directory scanner.
        |
        | The `deletion-request` folder SHOULD be picked up by the superadmin
        | namespace, but Livewire v4's resolver silently skips it. Registering
        | the two files explicitly bypasses the scanner.
        |
        | The slow-query dashboard lives under a new `health/` subfolder — the
        | same resolver behavior is expected, so it's registered explicitly too.
        */
        Livewire::addComponent(
            name: 'public::pages.create-booking',
            viewPath: resource_path('views/public/pages/⚡create-booking.blade.php')
        );
        Livewire::addComponent(
            name: 'public::pages.explore-map',
            viewPath: resource_path('views/public/pages/⚡explore-map.blade.php')
        );
        Livewire::addComponent(
            name: 'public::pages.payment-processing',
            viewPath: resource_path('views/public/pages/⚡payment-processing.blade.php')
        );
        Livewire::addComponent(
            name: 'superadmin::pages.deletion-request.view-deletion-requests',
            viewPath: resource_path('views/superadmin/pages/deletion-request/⚡view-deletion-requests.blade.php')
        );
        Livewire::addComponent(
            name: 'superadmin::pages.deletion-request.show-deletion-request',
            viewPath: resource_path('views/superadmin/pages/deletion-request/⚡show-deletion-request.blade.php')
        );
        Livewire::addComponent(
            name: 'superadmin::pages.health.slow-query-dashboard',
            viewPath: resource_path('views/superadmin/pages/health/⚡slow-query-dashboard.blade.php')
        );

        /*
        |--------------------------------------------------------------------------
        | Authorization Policies
        |--------------------------------------------------------------------------
        */
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Event::class, EventPolicy::class);

        /*
        |--------------------------------------------------------------------------
        | Super-admin bypass
        |--------------------------------------------------------------------------
        | IMPORTANT: This callback must NOT block Spatie's permission checks.
        | It returns TRUE only for super-admins, and NULL for everyone else,
        | so Spatie's own Gate::before (registered by the permission package)
        | can still authorize permission strings like `view analytics`.
        |
        | Uses variadic `...$models` so it works whether the caller passes 0,
        | 1, or many models — avoids ArgumentCountError on Laravel 11+.
        */
        Gate::before(function ($user, string $ability, ...$models) {
            // Never short-circuit role management abilities
            if (!empty($models) && ($models[0] ?? null) instanceof Role) {
                return null;
            }

            // Super admin bypasses everything else
            if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
                return true;
            }

            // Fall through to Spatie's permission check
            return null;
        });

        /*
        |--------------------------------------------------------------------------
        | Rate Limiters
        |--------------------------------------------------------------------------
        | Route-level limiters protect GET requests against scrapers and naive
        | floods. The credential-level brute-force guards live INSIDE their
        | respective SFCs (per-email+IP keys checked before Auth::attempt(),
        | User::create(), or Password::sendResetLink()) — those are the ones
        | that actually stop account takeover, spam registration, and
        | password-reset abuse.
        |
        | `auth.login.ip` — 20 GET /login requests per minute per IP.
        |   The POST path goes through Livewire's /livewire/update endpoint,
        |   so this limiter only fires on page renders. The real credential
        |   brute-force guard lives inside the login SFC.
        |
        | `auth.register.ip` — 10 GET /register requests per minute per IP.
        |   Tighter than login because legitimate users almost never reload
        |   the register page more than a few times. The credential-level
        |   guard lives inside the register SFC's register() method.
        |
        | `auth.password.request.ip` — 5 GET /forgot-password requests per
        |   minute per IP. Prevents the SMTP relay from being abused as a
        |   spam amplifier. The email+IP-level guard inside the SFC is
        |   even tighter (3 attempts / 60s) to stop email bombing of a
        |   single target address.
        */
        RateLimiter::for('auth.login.ip', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response(
                        'Too many requests. Please slow down.',
                        429,
                        $headers
                    );
                });
        });

        RateLimiter::for('auth.register.ip', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response(
                        'Too many registration attempts. Please wait a moment and try again.',
                        429,
                        $headers
                    );
                });
        });

        RateLimiter::for('auth.password.request.ip', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response(
                        'Too many password reset requests. Please slow down.',
                        429,
                        $headers
                    );
                });
        });

        /*
        |--------------------------------------------------------------------------
        | Slow Query Logging
        |--------------------------------------------------------------------------
        | Every query slower than config('app.slow_query_ms') is written as
        | one JSON line to the `slow_queries` channel — the file
        | storage/logs/slow-queries.log. The `slow-queries:harvest` scheduled
        | command aggregates these into the slow_query_aggregates table for
        | the /platform/health/queries dashboard.
        |
        | Three safety guards:
        |   • Recursion — any query touching slow_query_aggregates is skipped,
        |     so the harvest command's own INSERTs never feed themselves back.
        |   • Exception safety — a logging failure is swallowed. A broken log
        |     channel must never 500 the request that triggered a slow query.
        |   • Threshold — read from config('app.slow_query_ms'). NOT env().
        |     env() returns null when config is cached, and Larastan's
        |     larastan.noEnvCallsOutsideOfConfig rule forbids env() outside
        |     the config/ directory. The default (200ms local / 500ms prod)
        |     is resolved once in config/app.php and read from there.
        */
        DB::listen(function ($query) {
            $threshold = (int) config('app.slow_query_ms', 500);

            if ($query->time < $threshold) {
                return;
            }

            // Recursion guard: never log the slow-query infrastructure itself.
            if (str_contains($query->sql, 'slow_query_aggregates')) {
                return;
            }

            try {
                Log::channel('slow_queries')->warning('slow_query', [
                    'sql'        => (string) $query->sql,
                    'bindings'   => $query->bindings,
                    'time_ms'    => round((float) $query->time, 2),
                    'connection' => $query->connectionName,
                    'route'      => request()->route()?->getName(),
                ]);
            } catch (\Throwable) {
                // Never break the request because logging failed.
            }
        });
    }
}