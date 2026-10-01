{{-- resources/views/emails/booking-reserved.blade.php --}}
@php
    // Prefer the actual paid payment row. Falls back to 20% of total when the
    // PayMongo webhook has not yet flipped the payment to `paid` — either the
    // mail fires from a job before the webhook, or the flow is manual.
    $paidNow = (float) $booking->payments()
        ->withoutGlobalScope(\App\Scopes\TenantScope::class)
        ->where('payment_status', 'paid')
        ->sum('amount');

    if ($paidNow <= 0.0) {
        $paidNow = round((float) $booking->total_amount * 0.20, 2);
    }

    $balanceOnArrival = max(0, round((float) $booking->total_amount - $paidNow, 2));
@endphp

<x-mail::message>
# Reservation confirmed

Hi {{ \Illuminate\Support\Str::before($booking->user?->name ?? 'there', ' ') }},

Your reservation is held. You've paid the deposit — the balance is settled directly with {{ $booking->tenant?->name ?? 'the business' }} on arrival.

<x-mail::table>
| | |
| :--- | :--- |
| **Reference** | `{{ $booking->booking_reference }}` |
| **Property** | {{ $booking->items->first()?->property?->name ?? 'Your booking' }} |
| **Business** | {{ $booking->tenant?->name ?? '—' }} |
| **Start** | {{ $booking->check_in?->format('M d, Y') }} |
| **End** | {{ $booking->check_out?->format('M d, Y') }} |
</x-mail::table>

## Payment breakdown

<x-mail::table>
| | |
| :--- | :--- |
| **Total** | ₱{{ number_format((float) $booking->total_amount, 2) }} |
| **Paid now (deposit)** | ₱{{ number_format($paidNow, 2) }} |
| **Balance on arrival** | ₱{{ number_format($balanceOnArrival, 2) }} |
</x-mail::table>

<x-mail::panel>
The remaining balance is paid directly to {{ $booking->tenant?->name ?? 'the business' }} on arrival. Bring your booking reference — that's all you need.
</x-mail::panel>

<x-mail::button :url="route('booking.receipt', ['booking' => $booking->id])">
View Receipt
</x-mail::button>

If your plans change, you can review or cancel this reservation from **My Bookings**.

Thanks,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
You're receiving this because you placed a reservation on {{ config('app.name') }}. Questions about the balance or arrival time? Reply to this email — we're happy to help.
</x-slot:subcopy>
</x-mail::message>