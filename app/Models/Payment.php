<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Payment extends Model 
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'booking_id', 'amount', 'payment_method', 'payment_status', 'reference_number', 'paid_at'];
    
    protected function casts(): array { return ['paid_at' => 'datetime']; }
    
    public function booking() { return $this->belongsTo(Booking::class); }
}