<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property-read \App\Models\Booking|null $booking
 */
class Payment extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'amount',
        'payment_method',
        'payment_type',
        'payment_status',
        'reference_number',
        'paymongo_session_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public const TYPE_FULL = 'full';
    public const TYPE_RESERVATION = 'reservation';

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}