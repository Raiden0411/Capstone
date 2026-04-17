<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'type_of_tenant_id', 'address', 'contact_number', 'email', 'logo', 'latitude', 'longitude', 'is_active'];

    public function typeOfTenant() { return $this->belongsTo(TypeOfTenant::class); }
    public function users() { return $this->hasMany(User::class); }
    public function properties() { return $this->hasMany(Property::class); }
    public function services() { return $this->hasMany(Service::class); }
    public function customers() { return $this->hasMany(Customer::class); }
    public function bookings() { return $this->hasMany(Booking::class); }
    public function settings() { return $this->hasMany(TenantSetting::class); }
}