<x-mail::message>
# 🧾 Booking received

Hi {{ \Illuminate\Support\Str::before($booking->user?->name ?? 'there', ' ') }},

We've received your booking request. Complete payment within **{{ \App\Models\Booking::PAYMENT_DEADLINE_MINUTES }} minutes** to confirm your slot — after that, it will be released automatically.

<x-mail::table>
| | |
| :--- | :--- |
| **Reference** | `{{ $booking->booking_reference }}` |
| **Property** | {{ $booking->items->first()?->property?->name ?? 'Your booking' }} |
| **Business** | {{ $booking->tenant?->name ?? '—' }} |
| **Start** | {{ $booking->check_in?->format('M d, Y') }} |
| **End** | {{ $booking->check_out?->format('M d, Y') }} |
| **Total** | ₱{{ number_format((float) $booking->total_amount, 2) }} |
| **Status** | Pending payment |
</x-mail::table>

<x-mail::button :url="route('my-bookings')">
Complete Payment
</x-mail::button>

We'll send another email the moment your payment is confirmed.

Thanks,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
If you didn't make this booking, please contact support immediately — the slot will be released automatically after the deadline.
</x-slot:subcopy>
</x-mail::message>