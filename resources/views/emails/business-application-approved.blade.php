<x-mail::message>
# ✅ Your business is live

Hi {{ \Illuminate\Support\Str::before($application->user?->name ?? 'there', ' ') }},

Great news — **{{ $application->business_name }}** has been approved and is now visible on {{ config('app.name') }}.

<x-mail::table>
| | |
| :--- | :--- |
| **Business** | {{ $application->business_name }} |
| **Public URL** | [{{ route('tenant.show', $tenant->slug) }}]({{ route('tenant.show', $tenant->slug) }}) |
| **Approved** | {{ $application->reviewed_at?->format('M d, Y g:i A') }} |
</x-mail::table>

## What's next

1. **Add your offerings** — properties, services, and events from your dashboard.
2. **Upload a business logo** — it helps your listing stand out in the directory.
3. **Reply to bookings quickly** — a fast response rate keeps travellers coming back.

<x-mail::button :url="route('tenant.dashboard')">
Open Your Dashboard
</x-mail::button>

We're glad you're here.

Thanks,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
You're receiving this because you submitted a business application on {{ config('app.name') }}. Questions? Just reply to this email.
</x-slot:subcopy>
</x-mail::message>