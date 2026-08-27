<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

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