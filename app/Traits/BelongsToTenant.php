<?php

namespace App\Traits;

use App\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        /** @disregard P1013 */
        static::addGlobalScope(new TenantScope);

        /** @disregard P1013 */
        static::creating(function ($model) {
            if (Auth::check()) {
                $user = Auth::user();

                if (!$user->hasRole('super-admin') && !$model->tenant_id) {
                    $model->tenant_id = $user->tenant_id;
                }
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}