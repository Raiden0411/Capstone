<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Event extends Model
{
    use BelongsToTenant;

   protected $fillable = [
    'tenant_id',
    'name',
    'barangay',
    'description',
    'type',
    'start_date',
    'end_date',
    'coordinates',
    'image_path',
    'is_active',
    'featured',          // ← add this
];

protected $casts = [
    'start_date'  => 'datetime',
    'end_date'    => 'datetime',
    'coordinates' => 'array',
    'is_active'   => 'boolean',
    'featured'    => 'boolean',   // ← add this
];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where('start_date', '>=', now()->subDay());
    }
}