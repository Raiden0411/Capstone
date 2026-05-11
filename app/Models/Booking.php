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
        'check_in'  => 'date',
        'check_out' => 'date',
    ];

    public const PAYMENT_DEADLINE_HOURS = 3;

    // ─── Relationships ─────────────────────────────────────────
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
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

    // ─── Accessors ────────────────────────────────────────────
    public function getCheckInAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function getCheckOutAttribute($value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function getPaymentDeadlineAttribute(): ?Carbon
    {
        if ($this->status !== 'pending') {
            return null;
        }

        return $this->created_at->addHours(self::PAYMENT_DEADLINE_HOURS);
    }

    // ─── Helpers ──────────────────────────────────────────────
    public function isOverdue(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $paid = $this->payments()->where('payment_status', 'paid')->sum('amount');
        if ($paid >= $this->total_amount) {
            return false;
        }

        return $this->created_at->diffInHours(now()) >= self::PAYMENT_DEADLINE_HOURS;
    }

    // ─── Model Boot ───────────────────────────────────────────
    protected static function booted()
    {
        static::updated(function (Booking $booking) {
            if (in_array($booking->status, ['completed', 'cancelled'])) {
                $propertyIds = $booking->items()
                    ->pluck('property_id')
                    ->unique()
                    ->values()
                    ->toArray();

                // Explicit optional params to quiet Intelephense
                Property::whereIn('id', $propertyIds, 'and', false)
                    ->update(['status' => 'available']);
            }
        });
    }
}