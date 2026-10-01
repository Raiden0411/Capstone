<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    use AuthorizesRequests;

    public function destroy(Booking $booking)
    {
        $this->authorize('forceDelete', $booking);

        DB::transaction(function () use ($booking): void {
            $booking->forceDelete();
        });

        return redirect()
            ->route('tenant.bookings.index')
            ->with('message', 'Booking deleted successfully.');
    }
}