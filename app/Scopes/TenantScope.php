<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param \Illuminate\Database\Eloquent\Builder<TModel> $builder
     * @param TModel $model
     */
    public function apply(Builder $builder, Model $model)
    {
        if (Auth::check()) {
            
            /** @var \App\Models\User $user */
            $user = Auth::user();

            if (!$user->hasRole('super-admin')) {
                // TRICK: Wrap it in an array! 
                // This forces VS Code to recognize the exact method signature.
                $builder->where([
                    $model->getTable() . '.tenant_id' => $user->tenant_id
                ]);
            }
        }
    }
}