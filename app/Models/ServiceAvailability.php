<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $service_id
 * @property \Illuminate\Support\Carbon $date
 * @property bool $is_available
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Service $service
 * @property-read \App\Models\Tenant $tenant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceAvailability newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceAvailability newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceAvailability query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceAvailability whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceAvailability whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceAvailability whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceAvailability whereIsAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceAvailability whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceAvailability whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceAvailability whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ServiceAvailability extends Model
{
    use BelongsToTenant;

    protected $table = 'service_availability';

    protected $fillable = [
        'tenant_id',
        'service_id',
        'date',
        'is_available',
    ];

    protected $casts = [
        'date' => 'date',
        'is_available' => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}