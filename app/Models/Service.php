<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Service extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'price', 'is_active'];

    public function bookingServices() { return $this->hasMany(BookingService::class); }
}