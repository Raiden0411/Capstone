<x-mail::message>
# 📝 A few changes needed

Hi {{ \Illuminate\Support\Str::before($application->user?->name ?? 'there', ' ') }},

Our reviewer has looked at **{{ $application->business_name }}** and asked for a few changes before we can approve it.

Here's what needs to change:

<x-mail::panel>
{{ $notes }}
</x-mail::panel>

Your other information is saved. Log in, fix the item above, and resubmit — it takes just a minute.

<x-mail::button :url="route('register_business.edit', $application->id)">
Review & Resubmit
</x-mail::button>

Thanks,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
You're receiving this because your business application is being reviewed. If anything is unclear, just reply to this email.
</x-slot:subcopy>
</x-mail::message>