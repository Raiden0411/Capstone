<?php

namespace Database\Factories;

use App\Models\BookingItem;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingItemFactory extends Factory
{
    protected $model = BookingItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'booking_id' => Booking::factory(),
            'property_id' => Property::factory(),
            'price' => fake()->randomFloat(2, 800, 5000),
            'quantity' => 1,
            'subtotal' => fake()->randomFloat(2, 800, 5000),
        ];
    }

    /**
     * Associate the item with a specific booking, property, and tenant.
     */
    public function forBooking(Booking $booking, Property $property): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_id' => $booking->id,
            'property_id' => $property->id,
            'tenant_id' => $booking->tenant_id,
        ]);
    }
}