@php
    // Inferred reason: if the booking still has no paid payment row,
    // it was most likely auto-released due to the payment deadline.
    $wasPaid = $booking->payments()
        ->withoutGlobalScope(\App\Scopes\TenantScope::class)
        ->where('payment_status', 'paid')
        ->exists();
@endphp

<x-mail::message>
# Booking cancelled

Hi {{ \Illuminate\Support\Str::before($booking->user?->name ?? 'there', ' ') }},

@if(! $wasPaid)
Your booking was automatically released because the payment deadline passed. Nothing was charged.
@else
Your booking has been cancelled.
@endif

<x-mail::table>
| | |
| :--- | :--- |
| **Reference** | `{{ $booking->booking_reference }}` |
| **Property** | {{ $booking->items->first()?->property?->name ?? 'Your booking' }} |
| **Business** | {{ $booking->tenant?->name ?? '—' }} |
| **Original Dates** | {{ $booking->check_in?->format('M d, Y') }} – {{ $booking->check_out?->format('M d, Y') }} |
| **Status** | Cancelled |
</x-mail::table>

You're welcome to book again at any time — the same property and dates may still be available.

<x-mail::button :url="route('explore.map')">
Explore Destinations
</x-mail::button>

Thanks,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
You're receiving this because a booking on your account was cancelled. If this was unexpected, reply to this email.
</x-slot:subcopy>
</x-mail::message>