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
use App\Models\Booking;
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
        Livewire::addNamespace(namespace: 'public',     viewPath: resource_path('views/public'));
        Livewire::addNamespace(namespace: 'superadmin', viewPath: resource_path('views/superadmin'));
        Livewire::addNamespace(namespace: 'tenant',     viewPath: resource_path('views/tenant'));

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
        Livewire::addComponent(
            name: 'tenant::pages.business.business-switcher',
            viewPath: resource_path('views/tenant/pages/business/⚡business-switcher.blade.php')
        );

        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Event::class, EventPolicy::class);

        /*
        |--------------------------------------------------------------------------
        | Super-admin bypass
        |--------------------------------------------------------------------------
        | Laravel 12+ delivers gate arguments as a single nested array to the
        | before callback, not spread. Normalize at the top so downstream
        | checks see the actual model instances.
        |
        | Carve-outs: Role (so Spatie's own Gate::before still authorizes
        | permission strings) and Booking (tenant bookings are private
        | commercial records that platform-level administration does not
        | read or modify).
        */
        Gate::before(function ($user, string $ability, ...$models) {
            if (count($models) === 1 && is_array($models[0])) {
                $models = $models[0];
            }

            if (!empty($models) && ($models[0] ?? null) instanceof Role) {
                return null;
            }

            if (!empty($models)) {
                $target = $models[0];
                if ($target instanceof Booking) {
                    return null;
                }
                if (is_string($target) && ltrim($target, '\\') === Booking::class) {
                    return null;
                }
            }

            if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });

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

        DB::listen(function ($query) {
            $threshold = (int) config('app.slow_query_ms', 500);

            if ($query->time < $threshold) {
                return;
            }

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