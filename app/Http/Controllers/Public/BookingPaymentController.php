<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Scopes\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingPaymentController extends Controller
{
    public function success(Request $request, $bookingId)
    {
        $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($bookingId);

        abort_unless(Auth::id() === $booking->user_id, 403);

        return redirect()->route('booking.payment.processing', ['bookingId' => $booking->id]);
    }

    public function cancel(Request $request, $bookingId)
    {
        $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($bookingId);

        abort_unless(Auth::id() === $booking->user_id, 403);

        DB::transaction(function () use ($booking): void {
            $locked = Booking::withoutGlobalScope(TenantScope::class)
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked && $locked->status === Booking::STATUS_PENDING) {
                $locked->update(['status' => Booking::STATUS_CANCELLED]);
            }
        });

        $propertyId = $booking->items()
            ->withoutGlobalScope(TenantScope::class)
            ->value('property_id');

        if ($propertyId) {
            return redirect()
                ->route('booking.create', ['publicproperty' => $propertyId])
                ->with('error', 'Payment was cancelled. The temporary booking has been cancelled.');
        }

        return redirect()->route('my-bookings')->with('error', 'Payment cancelled.');
    }
}