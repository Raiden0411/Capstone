<?php

namespace App\Models;

use App\Scopes\TenantScope;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $booking_id
 * @property int $service_id
 * @property int $quantity
 * @property numeric $subtotal
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Booking $booking
 * @property-read \App\Models\Service $service
 * @property-read \App\Models\Tenant $tenant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingService newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingService newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingService query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingService whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingService whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingService whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingService whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingService whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingService whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingService whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingService whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BookingService extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'service_id',
        'quantity',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    protected function deriveTenantIdFromParent(): ?int
    {
        if (!$this->booking_id) {
            return null;
        }

        return Booking::withoutGlobalScope(TenantScope::class)
            ->whereKey($this->booking_id)
            ->value('tenant_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}