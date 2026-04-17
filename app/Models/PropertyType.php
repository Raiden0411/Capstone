<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;

class PropertyType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name'];

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Scope a query to only include property types available for a given tenant.
     *
     * @param Builder $query
     * @param int|null $tenantId
     * @return Builder
     */
    public function scopeAvailableForTenant(Builder $query, ?int $tenantId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where(function ($subQuery) use ($tenantId) {
                $subQuery->whereNull('tenant_id');
                if ($tenantId) {
                    $subQuery->orWhere('tenant_id', $tenantId);
                }
            });
    }
}