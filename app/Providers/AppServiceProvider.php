<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
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
        // 1. Manually bind the Spatie Role model to our custom Policy
        Gate::policy(Role::class, RolePolicy::class);

        // 2. Implicitly grant "Super Admin" role all permissions
        Gate::before(function ($user, $ability, $models) {
            
            // SECURITY FIX: If the model being checked is a Role, DO NOT auto-grant.
            // This forces Laravel to use our RolePolicy, preventing the Super Admin
            // from accidentally editing or deleting the 'super-admin' role.
            if (isset($models[0]) && $models[0] instanceof Role) {
                return null; 
            }

            // For everything else, Super Admins get a free pass!
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}