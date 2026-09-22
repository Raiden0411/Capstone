<x-mail::message>
@if($forAdmin)
# 🔔 New business application

A new KYB application has been submitted and is waiting for review.
@else
# 📬 We received your application

Hi {{ \Illuminate\Support\Str::before($application->user?->name ?? 'there', ' ') }},

Thanks for submitting **{{ $application->business_name }}**. Our team will review it within 1–2 business days.
@endif

<x-mail::table>
| | |
| :--- | :--- |
@if($forAdmin)
| **Applicant** | {{ $application->user?->name }} · {{ $application->user?->email }} |
@endif
| **Business** | {{ $application->business_name }} |
@if($forAdmin)
| **Type** | {{ $application->business_type }} |
@endif
| **Registration No.** | {{ $application->business_registration_number }} |
| **TIN** | {{ $application->tin_number }} |
@if($forAdmin)
| **Contact** | {{ $application->contact_email }} · {{ $application->contact_phone }} |
@endif
| **Submitted** | {{ $application->submitted_at?->format('M d, Y g:i A') }} |
</x-mail::table>

@if($forAdmin)
<x-mail::button :url="route('superadmin.business-applications.show', $application->id)">
Review Application
</x-mail::button>
@else
<x-mail::button :url="route('register_business')">
Check Application Status
</x-mail::button>

You'll get another email the moment the review is complete.
@endif

Thanks,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
@if($forAdmin)
You're receiving this because you're a platform administrator. Applications awaiting review are also visible at your dashboard.
@else
You're receiving this because you submitted a business application on {{ config('app.name') }}. Questions? Reply to this email.
@endif
</x-slot:subcopy>
</x-mail::message>