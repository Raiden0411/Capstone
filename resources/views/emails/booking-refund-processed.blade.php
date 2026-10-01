@component('mail::message')
# Refund Processed

Hi {{ $booking->user->name ?? 'there' }},

Good news — your refund has been processed.

@component('mail::panel')
**Reference:** #{{ $booking->booking_reference }}
**Refund amount:** ₱{{ number_format((float) $booking->refund_amount, 2) }}
**Refund percentage:** {{ $booking->refund_percentage }}%
**Completed:** {{ $booking->refund_processed_at?->format('M j, Y g:i A') ?? now()->format('M j, Y g:i A') }}
@if($booking->paymongo_refund_id)
**PayMongo refund ID:** {{ $booking->paymongo_refund_id }}
@endif
@endcomponent

The refund has been returned to the **original payment method** you used for this booking.

@component('mail::table')
| Provider | Expected arrival |
|:--|:--|
| GCash / Maya | 1–3 business days |
| Credit / Debit Card | 5–10 business days |
| Bank Transfer | 3–7 business days |
@endcomponent

If you don't see the amount within the window above, contact your bank or e-wallet provider with the PayMongo refund ID above. If they can't locate it, reply to this email and we'll investigate.

Thanks for booking with us,
{{ config('app.name') }}
@endcomponent