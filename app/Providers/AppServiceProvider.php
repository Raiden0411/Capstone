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
        */
        Livewire::addNamespace(namespace: 'public',     viewPath: resource_path('views/public'));
        Livewire::addNamespace(namespace: 'superadmin', viewPath: resource_path('views/superadmin'));
        Livewire::addNamespace(namespace: 'tenant',     viewPath: resource_path('views/tenant'));

        /*
        |--------------------------------------------------------------------------
        | Manual Component Registrations
        |--------------------------------------------------------------------------
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
        | `auth.login.ip` — 20 GET /login requests per minute per IP.
        |   Catches scrapers and naive brute-forcers hammering the page.
        |   The POST path goes through Livewire's /livewire/update endpoint,
        |   so this limiter only fires on page renders. The real credential
        |   brute-force guard lives inside the SFC's login() method, which
        |   checks a per-email+IP key before Auth::attempt() runs.
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

        /*
        |--------------------------------------------------------------------------
        | Query Performance Monitoring
        |--------------------------------------------------------------------------
        */
        if (app()->environment('local', 'staging')) {
            DB::listen(function ($query) {
                if ($query->time > 200) {
                    Log::warning('Slow query detected', [
                        'sql'      => $query->sql,
                        'bindings' => $query->bindings,
                        'time'     => $query->time,
                    ]);
                }
            });
        }
    }
}