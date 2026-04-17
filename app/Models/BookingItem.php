<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class BookingItem extends Model 
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'booking_id', 'property_id', 'price', 'check_out', 'quantity', 'subtotal'];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function property() { return $this->belongsTo(Property::class); }
}