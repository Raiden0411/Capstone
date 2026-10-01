@component('mail::message')
# Booking Cancelled

Hi {{ $booking->user->name ?? 'there' }},

Your booking has been cancelled.

@component('mail::panel')
**Reference:** #{{ $booking->booking_reference }}
**Booking total:** ₱{{ number_format((float) $booking->total_amount, 2) }}
@if($booking->cancelled_by === 'admin')
**Cancelled by:** The business
@else
**Cancelled by:** You
@endif
@if($booking->cancellation_reason)
**Reason:** {{ $booking->cancellation_reason }}
@endif
@endcomponent

@if($booking->hasRefund())
## Refund Information

Your cancellation qualifies for a **{{ $booking->refund_percentage }}% refund** of the amount you paid.

@component('mail::table')
| | |
|:--|--:|
| **Paid amount** | ₱{{ number_format((float) $booking->payments->where('payment_status', 'paid')->sum('amount'), 2) }} |
| **Refund amount** | ₱{{ number_format((float) $booking->refund_amount, 2) }} |
| **Refund status** | {{ ucfirst($booking->refund_status) }} |
@endcomponent

@if($booking->refund_status === 'pending')
Your refund is being processed and will be returned to the **original payment method** you used. This typically takes **3–10 business days** to appear on your statement, depending on your bank or e-wallet provider.

You will receive a separate email once the refund has been completed.
@elseif($booking->refund_status === 'processed')
Your refund has been completed. It has been returned to the **original payment method** you used. Please allow **3–10 business days** for it to appear on your statement if it hasn't shown up yet.
@elseif($booking->refund_status === 'rejected')
We were unable to process your refund automatically. Our support team will reach out to arrange an alternative.
@endif

@elseif($booking->cancelled_by === 'admin')
## No Payment Due

The business cancelled this booking. Any pending payment is void — nothing further is owed.

@else
## No Refund Due

This cancellation falls outside the refund window. Under our cancellation policy:
- **7 or more days before your booking:** full refund
- **3 to 6 days before your booking:** 50% refund
- **Less than 3 days before your booking:** no refund

@endif

If you have any questions, reply to this email and we will get back to you.

Thanks,
{{ config('app.name') }}
@endcomponent