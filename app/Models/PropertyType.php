<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Property> $properties
 * @property-read int|null $properties_count
 * @property-read \App\Models\Tenant|null $tenant
 * @method static Builder<static>|PropertyType availableForTenant(?int $tenantId)
 * @method static \Database\Factories\PropertyTypeFactory factory($count = null, $state = [])
 * @method static Builder<static>|PropertyType newModelQuery()
 * @method static Builder<static>|PropertyType newQuery()
 * @method static Builder<static>|PropertyType query()
 * @method static Builder<static>|PropertyType whereCreatedAt($value)
 * @method static Builder<static>|PropertyType whereId($value)
 * @method static Builder<static>|PropertyType whereName($value)
 * @method static Builder<static>|PropertyType whereTenantId($value)
 * @method static Builder<static>|PropertyType whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PropertyType extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name'];

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Scope a query to only include property types available for a given tenant.
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