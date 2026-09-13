<?php

namespace App\Models;

use App\Scopes\TenantScope;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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