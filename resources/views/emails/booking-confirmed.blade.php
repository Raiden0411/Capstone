<x-mail::message>
# ✅ Booking confirmed

Hi {{ \Illuminate\Support\Str::before($booking->user?->name ?? 'there', ' ') }},

Your payment has been received and your booking is now confirmed.

<x-mail::table>
| | |
| :--- | :--- |
| **Reference** | `{{ $booking->booking_reference }}` |
| **Property** | {{ $booking->items->first()?->property?->name ?? 'Your booking' }} |
| **Business** | {{ $booking->tenant?->name ?? '—' }} |
| **Start** | {{ $booking->check_in?->format('M d, Y') }} |
| **End** | {{ $booking->check_out?->format('M d, Y') }} |
| **Total Paid** | ₱{{ number_format((float) $booking->total_amount, 2) }} |
</x-mail::table>

<x-mail::button :url="route('booking.receipt', ['booking' => $booking->id])">
View Receipt
</x-mail::button>

Have a great trip — everything is set.

Thanks,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
You're receiving this because you placed a booking on {{ config('app.name') }}. Questions? Reply to this email — we're happy to help.
</x-slot:subcopy>
</x-mail::message>