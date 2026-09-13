<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * Cancel a booking if it is unpaid past the deadline.
     * Concurrency-safe: locks the row inside a transaction.
     *
     * @return bool True when the booking was cancelled by this call.
     */
    public function cancelIfOverdue(Booking $booking): bool
    {
        if ($booking->status !== Booking::STATUS_PENDING) {
            return false;
        }

        return (bool) DB::transaction(function () use ($booking) {
            /** @var Booking|null $locked */
            $locked = Booking::withoutGlobalScope(\App\Scopes\TenantScope::class)
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->first();

            if (!$locked || $locked->status !== Booking::STATUS_PENDING) {
                return false;
            }

            if (!$locked->isOverdue()) {
                return false;
            }

            $locked->update(['status' => Booking::STATUS_CANCELLED]);

            return true;
        });
    }
}