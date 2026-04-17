<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return null;
    }

    public function view(User $user, Booking $booking): bool
    {
        // Fixed: checking tenant_id directly on the booking
        return $user->tenant_id === $booking->tenant_id;
    }

    public function update(User $user, Booking $booking): bool
    {
        // Fixed: checking tenant_id directly on the booking
        return $user->tenant_id === $booking->tenant_id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        // Fixed: checking tenant_id directly on the booking
        return $user->tenant_id === $booking->tenant_id;
    }
}