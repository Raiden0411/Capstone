<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        if (!Auth::check()) {
            // No authenticated user: no scope filtering (public context)
            return;
        }

        $user = Auth::user();

        if ($user->hasRole('super-admin')) {
            // Super admins bypass tenant scope
            return;
        }

        $tenantId = $user->tenant_id;
        if (!$tenantId) {
            // Authenticated user without tenant (tourist) sees nothing
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->getTable() . '.tenant_id', $tenantId);
    }
}