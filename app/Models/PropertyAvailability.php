<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property \Illuminate\Support\Carbon $date
 * @property bool $is_available
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Property $property
 * @property-read \App\Models\Tenant $tenant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyAvailability newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyAvailability newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyAvailability query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyAvailability whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyAvailability whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyAvailability whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyAvailability whereIsAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyAvailability wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyAvailability whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PropertyAvailability whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PropertyAvailability extends Model
{
    use BelongsToTenant;

    protected $table = 'property_availability';

    protected $fillable = ['tenant_id', 'property_id', 'date', 'is_available'];

    protected function casts(): array
    {
        return [
            'date'         => 'date',
            'is_available' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}