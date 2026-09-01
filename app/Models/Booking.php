<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use App\Scopes\TenantScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Booking extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'booking_reference',
        'check_in',
        'check_out',
        'total_amount',
        'status',
        'booking_type',
    ];

    protected $casts = [
        'check_in'  => 'date',
        'check_out' => 'date',
    ];

    public const PAYMENT_DEADLINE_MINUTES = 30;

    public const TYPE_FULL = 'full';
    public const TYPE_RESERVATION = 'reservation';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CHECKED_IN = 'checked_in';

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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

    // Accessors
    public function getCheckInAttribute(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function getCheckOutAttribute(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function getPaymentDeadlineAttribute(): ?Carbon
    {
        if ($this->status !== self::STATUS_PENDING) {
            return null;
        }
        return $this->created_at->addMinutes(self::PAYMENT_DEADLINE_MINUTES);
    }

    // Helpers
    public function isOverdue(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        // Remove tenant scope because this may be called from a queue job
        $paid = $this->payments()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', 'paid')
            ->sum('amount');

        if ($paid >= $this->total_amount) {
            return false;
        }

        return $this->created_at
            ->addMinutes(self::PAYMENT_DEADLINE_MINUTES)
            ->isPast();
    }

    public function cancelIfOverdue(): void
    {
        if ($this->isOverdue()) {
            $this->update(['status' => self::STATUS_CANCELLED]);
        }
    }

    // Boot
    protected static function booted()
    {
        static::updated(function (Booking $booking) {
            // Get property IDs from booking items (remove tenant scope for safety)
            $propertyIds = $booking->items()
                ->withoutGlobalScope(TenantScope::class)
                ->pluck('property_id')
                ->unique()
                ->values()
                ->toArray();

            if (empty($propertyIds)) {
                return;
            }

            // Determine new property status based on booking status
            $newStatus = match ($booking->status) {
                self::STATUS_CONFIRMED => 'occupied',
                self::STATUS_RESERVED  => 'reserved',
                self::STATUS_COMPLETED,
                self::STATUS_CANCELLED => 'available',
                default                => null, // no change for pending, checked_in, etc.
            };

            if ($newStatus) {
                DB::table('properties')
                    ->whereIn('id', $propertyIds)
                    ->update(['status' => $newStatus]);
            }
        });
    }
}