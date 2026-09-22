<x-mail::message>
# 🔔 New booking received

A new booking has been placed for **{{ $booking->tenant?->name ?? 'your business' }}**.

<x-mail::table>
| | |
| :--- | :--- |
| **Reference** | `{{ $booking->booking_reference }}` |
| **Guest** | {{ $booking->user?->name }} · {{ $booking->user?->email }} |
| **Phone** | {{ $booking->user?->phone ?: '—' }} |
| **Property** | {{ $booking->items->first()?->property?->name ?? '—' }} |
| **Start** | {{ $booking->check_in?->format('M d, Y') }} |
| **End** | {{ $booking->check_out?->format('M d, Y') }} |
| **Total** | ₱{{ number_format((float) $booking->total_amount, 2) }} |
| **Payment** | Pending |
</x-mail::table>

<x-mail::button :url="route('tenant.bookings.show', $booking->id)">
View Booking
</x-mail::button>

Thanks,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
You're receiving this because you're an admin for {{ $booking->tenant?->name }}. Manage notification preferences from your business dashboard.
</x-slot:subcopy>
</x-mail::message>