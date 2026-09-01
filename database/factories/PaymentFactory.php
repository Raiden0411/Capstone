<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'booking_id' => Booking::factory(),
            'amount' => fake()->randomFloat(2, 500, 5000),
            'payment_method' => fake()->randomElement(['card', 'gcash', 'paymaya']),
            'payment_type' => Payment::TYPE_FULL,
            'payment_status' => 'unpaid',
            'reference_number' => null,
            'paymongo_session_id' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Associate the payment with a specific booking and inherit its tenant.
     */
    public function forBooking(Booking $booking): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_id' => $booking->id,
            'tenant_id' => $booking->tenant_id,
        ]);
    }
}