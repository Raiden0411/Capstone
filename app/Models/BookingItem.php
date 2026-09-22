<?php

namespace App\Models;

use App\Scopes\TenantScope;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $booking_id
 * @property int $property_id
 * @property numeric $price
 * @property int $quantity
 * @property numeric $subtotal
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Booking $booking
 * @property-read \App\Models\Property $property
 * @property-read \App\Models\Tenant $tenant
 * @method static \Database\Factories\BookingItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingItem whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingItem wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingItem wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingItem whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingItem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BookingItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BookingItem extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'property_id',
        'price',
        'quantity',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'price'    => 'decimal:2',
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

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}