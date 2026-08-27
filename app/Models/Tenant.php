<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type_of_tenant_id',
        'address',
        'contact_number',
        'email',
        'logo',
        'coordinates',
        'is_active',
        'is_recommended',
    ];

    protected $casts = [
        'coordinates'    => 'array',
        'is_active'      => 'boolean',
        'is_recommended' => 'boolean',
    ];

    public function typeOfTenant()
    {
        return $this->belongsTo(TypeOfTenant::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function settings()
    {
        return $this->hasMany(TenantSetting::class);
    }

    // ---------- Coordinates helpers ----------
    public function getPrimaryCoordinates(): ?array
    {
        $coords = $this->coordinates;
        if (!empty($coords) && isset($coords[0])) {
            return $coords[0]; // expects ['lat' => ..., 'lng' => ..., 'name' => ..., 'type' => 'parent']
        }
        return null;
    }

    public function getAllCoordinates(): array
    {
        return $this->coordinates ?? [];
    }

    // ---------- Scopes ----------
    public function scopeRecommended($query)
    {
        return $query->where('is_recommended', true);
    }

    // ---------- Helpers ----------
    public function isRecommended(): bool
    {
        return (bool) $this->is_recommended;
    }
}