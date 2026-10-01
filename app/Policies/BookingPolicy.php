<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * No super-admin bypass. Tenant bookings are the tenant's private
     * commercial records. Platform-level administration does not include
     * reading or modifying them. Super-admin has tenant_id = null, so
     * the method bodies below deny access via the tenant comparison.
     */
    public function view(User $user, Booking $booking): bool
    {
        return $user->tenant_id === $booking->tenant_id;
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->tenant_id === $booking->tenant_id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->tenant_id === $booking->tenant_id;
    }

    /**
     * Hard delete. Requires the booking to be cancelled so a live
     * reservation cannot be destroyed from under a guest.
     * Widen to include STATUS_COMPLETED if your policy requires it.
     */
    public function forceDelete(User $user, Booking $booking): bool
    {
        if ($user->tenant_id !== $booking->tenant_id) {
            return false;
        }

        if (! $user->hasPermissionAtTenant('delete bookings')) {
            return false;
        }

        return $booking->status === Booking::STATUS_CANCELLED;
    }
}