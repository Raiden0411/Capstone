<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;                         // 👈 add this
use Spatie\Permission\Models\Role;
use App\Policies\RolePolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Manually register the public booking component
        Livewire::addComponent(
            name: 'public::pages.create-booking',
            viewPath: resource_path('views/public/pages/create-booking.blade.php')
        );

        Gate::policy(Role::class, RolePolicy::class);

        Gate::before(function ($user, $ability, $models) {
            if (isset($models[0]) && $models[0] instanceof Role) {
                return null;
            }
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}