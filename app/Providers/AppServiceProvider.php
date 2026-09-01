<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use App\Policies\RolePolicy;
use App\Models\Event;
use App\Policies\EventPolicy;

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
        |
        | Registers view paths for our Livewire single-file component namespaces.
        | This allows routes like `public::pages.explore-map` to resolve properly.
        |
        */

        Livewire::addNamespace(
            namespace: 'public',
            viewPath: resource_path('views/public'),
        );

        Livewire::addNamespace(
            namespace: 'superadmin',
            viewPath: resource_path('views/superadmin'),
        );

        Livewire::addNamespace(
            namespace: 'tenant',
            viewPath: resource_path('views/tenant'),
        );

        /*
        |--------------------------------------------------------------------------
        | Manual Component Registrations
        |--------------------------------------------------------------------------
        |
        | Keep explicit component registrations for special cases and components
        | that may not resolve automatically via namespaces.
        |
        */

        // Manually register the public booking component
        Livewire::addComponent(
            name: 'public::pages.create-booking',
            viewPath: resource_path('views/public/pages/⚡create-booking.blade.php')
        );

        // Manually register the explore-map component (with ⚡ prefix)
        Livewire::addComponent(
            name: 'public::pages.explore-map',
            viewPath: resource_path('views/public/pages/⚡explore-map.blade.php')
        );

        // NEW: Manually register the payment processing component
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

        Gate::before(function ($user, $ability, $models) {
            if (isset($models[0]) && $models[0] instanceof Role) {
                return null;
            }

            return $user->hasRole('super-admin') ? true : null;
        });
    }
}