<?php

namespace App\Models;

use App\Observers\BookingObserver;
use App\Scopes\TenantScope;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $booking_reference
 * @property \Illuminate\Support\Carbon $check_in
 * @property \Illuminate\Support\Carbon $check_out
 * @property numeric $total_amount
 * @property string $status
 * @property string $booking_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Carbon\Carbon|null $payment_deadline
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BookingItem> $items
 * @property-read int|null $items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BookingService> $services
 * @property-read int|null $services_count
 * @property-read \App\Models\Tenant $tenant
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \App\Models\User $user
 * @method static Builder<static>|Booking active()
 * @method static \Database\Factories\BookingFactory factory($count = null, $state = [])
 * @method static Builder<static>|Booking newModelQuery()
 * @method static Builder<static>|Booking newQuery()
 * @method static Builder<static>|Booking query()
 * @method static Builder<static>|Booking whereBookingReference($value)
 * @method static Builder<static>|Booking whereBookingType($value)
 * @method static Builder<static>|Booking whereCheckIn($value)
 * @method static Builder<static>|Booking whereCheckOut($value)
 * @method static Builder<static>|Booking whereCreatedAt($value)
 * @method static Builder<static>|Booking whereId($value)
 * @method static Builder<static>|Booking whereStatus($value)
 * @method static Builder<static>|Booking whereTenantId($value)
 * @method static Builder<static>|Booking whereTotalAmount($value)
 * @method static Builder<static>|Booking whereUpdatedAt($value)
 * @method static Builder<static>|Booking whereUserId($value)
 * @mixin \Eloquent
 */
#[ObservedBy([BookingObserver::class])]
class Booking extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'booking_reference',
        'check_in',
        'check_out',
        'booking_time',
        'total_amount',
        'status',
        'booking_type',
    ];

    protected function casts(): array
    {
        return [
            'check_in'     => 'date',
            'check_out'    => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public const PAYMENT_DEADLINE_MINUTES = 30;

    public const TYPE_FULL        = 'full';
    public const TYPE_RESERVATION = 'reservation';

    public const STATUS_PENDING    = 'pending';
    public const STATUS_CONFIRMED  = 'confirmed';
    public const STATUS_RESERVED   = 'reserved';
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_CHECKED_IN = 'checked_in';

    /** Statuses that release the booked properties back to "available". */
    public const RELEASING_STATUSES = [self::STATUS_COMPLETED, self::STATUS_CANCELLED];

    // ── Relationships ────────────────────────────────────
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(BookingService::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // ── Accessors ────────────────────────────────────────
    public function getPaymentDeadlineAttribute(): ?Carbon
    {
        if ($this->status !== self::STATUS_PENDING) {
            return null;
        }

        return $this->created_at?->copy()->addMinutes(self::PAYMENT_DEADLINE_MINUTES);
    }

    // ── Helpers ──────────────────────────────────────────
    public function paidAmount(): float
    {
        return (float) $this->payments()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', 'paid')
            ->sum('amount');
    }

    public function isFullyPaid(): bool
    {
        return $this->paidAmount() >= (float) $this->total_amount;
    }

    public function isOverdue(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        if ($this->isFullyPaid()) {
            return false;
        }

        return $this->created_at
            ?->copy()
            ->addMinutes(self::PAYMENT_DEADLINE_MINUTES)
            ->isPast() ?? false;
    }

    // ── Scopes ───────────────────────────────────────────
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::RELEASING_STATUSES);
    }

    // ── Boot ─────────────────────────────────────────────
    protected static function booted(): void
    {
        static::updated(function (Booking $booking): void {
            // Only act when the status actually changed into a releasing state.
            if (!$booking->wasChanged('status')) {
                return;
            }

            if (!in_array($booking->status, self::RELEASING_STATUSES, true)) {
                return;
            }

            $propertyIds = $booking->items()
                ->withoutGlobalScope(TenantScope::class)
                ->pluck('property_id')
                ->unique()
                ->filter()
                ->values()
                ->all();

            if (empty($propertyIds)) {
                return;
            }

            DB::table('properties')
                ->whereIn('id', $propertyIds)
                ->update(['status' => 'available']);
        });
    }
}