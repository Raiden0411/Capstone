<?php

namespace App\Traits;

use App\Scopes\TenantScope;
use App\Models\Tenant; // <-- Added missing Tenant model import
use Illuminate\Support\Facades\Auth; // <-- Added Auth Facade import

trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        // 1. Apply the Invisible Wall (Global Scope)
        static::addGlobalScope(new TenantScope);

        // 2. Automatically assign the tenant_id when creating a new record
        static::creating(function ($model) {
            
            if (Auth::check()) {
                /** @var \App\Models\User $user */
                $user = Auth::user();
                
                // If a tenant admin creates a property/booking, auto-assign their tenant_id
                if (!$user->hasRole('super-admin')) {
                    $model->tenant_id = $user->tenant_id;
                }
            }
            
        });
    }

    // Add a relationship so you can always call $model->tenant
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}