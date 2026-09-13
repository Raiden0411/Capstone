<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin Model
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        /** @disregard P1013 */
        static::addGlobalScope(new TenantScope);

        /** @disregard P1013 */
        static::creating(function ($model): void {
            // Explicit tenant_id always wins.
            if (!empty($model->tenant_id)) {
                return;
            }

            // Derive from a parent resource when the model can.
            // @disregard P1013
            $derived = $model->deriveTenantIdFromParent();
            if ($derived) {
                $model->tenant_id = $derived;
                return;
            }

            // Fallback: authenticated tenant user's tenant_id.
            // Never trust this for tourist-created children — those must
            // set tenant_id explicitly or derive it from the parent.
            $user = Auth::user();
            if (!$user || $user->hasRole('super-admin')) {
                return;
            }

            if (!empty($user->tenant_id)) {
                $model->tenant_id = $user->tenant_id;
            }
        });
    }

    /**
     * Override in models whose tenant can be derived from a parent resource.
     * Example: BookingItem derives from its Booking.
     */
    protected function deriveTenantIdFromParent(): ?int
    {
        return null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}