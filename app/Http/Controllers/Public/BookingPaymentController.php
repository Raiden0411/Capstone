<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Scopes\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingPaymentController extends Controller
{
    public function success(Request $request, $bookingId)
    {
        $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($bookingId);

        abort_unless(Auth::id() === $booking->user_id, 403);

        return redirect()->route('booking.payment.processing', ['bookingId' => $booking->id]);
    }

    /**
     * PayMongo `cancel_url` handler.
     *
     * ── WHAT THIS HANDLER MUST NOT DO ──────────────────────────────
     *
     * This endpoint is hit in three cases:
     *
     *   1. The user clicks "Cancel" on the PayMongo hosted checkout.
     *   2. The user presses the browser Back button on the checkout
     *      page — PayMongo intercepts the navigation and redirects
     *      here.
     *   3. The checkout session expires (PayMongo's own timeout).
     *
     * In every case, the user's intent is "let me out of this page",
     * not "kill my booking forever". A user who backs out to review
     * their cart and then retries payment five seconds later must
     * find the booking still PENDING.
     *
     * Cancelling here — which a previous version did, by flipping the
     * status inside a transaction — produced the exact bug the user
     * reported: hit Back on PayMongo, booking shows as `cancelled`,
     * no way to retry.
     *
     * ── WHERE THE CANCEL ACTUALLY HAPPENS ──────────────────────────
     *
     * The 30-minute payment window is enforced exclusively by the
     * scheduled command `bookings:cancel-overdue` (see
     * routes/console.php). It runs every minute and flips any
     * PENDING booking whose `created_at` is older than
     * `Booking::PAYMENT_DEADLINE_MINUTES`. That is the single source
     * of truth for overdue cancellation.
     *
     * This handler only routes the user back to a safe page where
     * they can retry.
     *
     * ── FLASH KEY: `info`, NOT `message` ───────────────────────────
     *
     * `message` renders as a success (emerald) banner on
     * /my-bookings. A cancel-due-to-navigation is NOT a success — it
     * is a factual notice. Using `info` renders a sky-blue banner
     * with an info icon, which is the correct visual signal.
     */
    public function cancel(Request $request, $bookingId)
    {
        $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($bookingId);

        abort_unless(Auth::id() === $booking->user_id, 403);

        // Do NOT touch the booking status. The scheduled command owns
        // cancellation; the customer gets the rest of their 30-minute
        // window to complete or retry.
        return redirect()
            ->route('my-bookings')
            ->with(
                'info',
                'Payment was not completed. You can retry from My Bookings before the '
                . Booking::PAYMENT_DEADLINE_MINUTES
                . '-minute window closes.'
            );
    }
}