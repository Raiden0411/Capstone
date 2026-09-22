<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;

class PaymentCallbackController extends Controller
{
    public function success(Booking $booking): RedirectResponse
    {
        return redirect()
            ->route('tenant.payments.index')
            ->with('message', 'Payment completed! The payment record has been updated.');
    }

    public function cancel(Booking $booking): RedirectResponse
    {
        return redirect()
            ->route('tenant.payments.index')
            ->with('error', 'Payment was cancelled.');
    }
}