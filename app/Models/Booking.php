<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;

class Booking extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'booking_reference',
        'check_in',
        'check_out',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
    ];

    /**
     * 访问器：确保 check_in 始终返回 Carbon 实例
     */
    public function getCheckInAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    /**
     * 访问器：确保 check_out 始终返回 Carbon 实例
     */
    public function getCheckOutAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function services()
    {
        return $this->hasMany(BookingService::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}