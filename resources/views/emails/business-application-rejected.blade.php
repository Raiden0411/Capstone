<x-mail::message>
# Update on your application

Hi {{ \Illuminate\Support\Str::before($application->user?->name ?? 'there', ' ') }},

Thanks for applying to list **{{ $application->business_name }}** on {{ config('app.name') }}.

After review, we're unable to approve this application at this time.

Here's the reason our reviewer provided:

<x-mail::panel>
{{ $reason }}
</x-mail::panel>

You can start a new application any time once the issue is resolved. If you have questions or believe this was a mistake, reply to this email — we're happy to help.

<x-mail::button :url="route('register_business')">
Start a New Application
</x-mail::button>

Thanks,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
You're receiving this because you submitted a business application on {{ config('app.name') }}. Our support team reads every reply.
</x-slot:subcopy>
</x-mail::message>