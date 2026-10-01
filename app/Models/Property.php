<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $property_type_id
 * @property string $name
 * @property string|null $description
 * @property int $capacity
 * @property int $quantity
 * @property int $min_stay_days
 * @property int|null $max_stay_days
 * @property string $price
 * @property string $status
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Property extends Model
{
    use HasFactory;
    use BelongsToTenant;

    public const DEFAULT_MIN_STAY_DAYS = 1;

    protected $fillable = [
        'tenant_id',
        'property_type_id',
        'name',
        'description',
        'price',
        'capacity',
        'quantity',
        'min_stay_days',
        'max_stay_days',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'         => 'decimal:2',
            'capacity'      => 'integer',
            'quantity'      => 'integer',
            'min_stay_days' => 'integer',
            'max_stay_days' => 'integer',
            'is_active'     => 'boolean',
        ];
    }

    /** @return BelongsTo<PropertyType, $this> */
    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    /** @return HasMany<PropertyImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class);
    }

    /** @return HasMany<PropertyAvailability, $this> */
    public function availabilities(): HasMany
    {
        return $this->hasMany(PropertyAvailability::class);
    }

    /** @return HasMany<BookingItem, $this> */
    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    // ── Duration limits ─────────────────────────────────────

    public function hasDurationLimits(): bool
    {
        return $this->max_stay_days !== null
            || $this->effectiveMinStayDays() > self::DEFAULT_MIN_STAY_DAYS;
    }

    public function effectiveMinStayDays(): int
    {
        return max(
            self::DEFAULT_MIN_STAY_DAYS,
            (int) ($this->min_stay_days ?? self::DEFAULT_MIN_STAY_DAYS),
        );
    }

    public function effectiveMaxStayDays(): ?int
    {
        if ($this->max_stay_days === null) {
            return null;
        }

        // Guard against a misconfigured row where the admin set max < min.
        return max($this->effectiveMinStayDays(), (int) $this->max_stay_days);
    }

    public function isDurationWithinLimits(int $days): bool
    {
        if ($days < $this->effectiveMinStayDays()) {
            return false;
        }

        $max = $this->effectiveMaxStayDays();

        if ($max !== null && $days > $max) {
            return false;
        }

        return true;
    }
}