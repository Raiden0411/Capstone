<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Transaction extends Model 
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'booking_id', 'type', 'amount', 'description'];
    
    public function booking() { return $this->belongsTo(Booking::class); }
}