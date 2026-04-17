<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class BookingService extends Model 
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'booking_id', 'service_id', 'quantity', 'subtotal'];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function service() { return $this->belongsTo(Service::class); }
}