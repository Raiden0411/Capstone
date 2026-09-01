<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $checkIn = Carbon::now()->addDays(fake()->numberBetween(1, 30));
        $nights = fake()->numberBetween(1, 5);
        $checkOut = $checkIn->copy()->addDays($nights);

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'booking_reference' => 'BK-' . strtoupper(Str::random(8)),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total_amount' => fake()->randomFloat(2, 1000, 10000),
            'status' => Booking::STATUS_PENDING,
            'booking_type' => Booking::TYPE_FULL,
            'created_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Booking::STATUS_PENDING,
            'created_at' => now()->subMinutes(31),
        ]);
    }

    /**
     * Associate the booking with a specific tenant and user.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}