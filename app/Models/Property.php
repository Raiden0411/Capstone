<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Property extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'property_type_id',
        'name',
        'description',
        'price',
        'capacity',
        'quantity',      // ← added
        'status',
        'is_active',
    ];

    public function propertyType() { return $this->belongsTo(PropertyType::class); }
    public function images() { return $this->hasMany(PropertyImage::class); }
    public function availabilities() { return $this->hasMany(PropertyAvailability::class); }
    public function bookingItems() { return $this->hasMany(BookingItem::class); }
}