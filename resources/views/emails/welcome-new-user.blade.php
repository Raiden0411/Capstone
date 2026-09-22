<x-mail::message>
# 👋 Welcome, {{ \Illuminate\Support\Str::before($userName ?: 'there', ' ') }}!

Your **{{ config('app.name') }}** account is ready. You can sign in any time with **{{ $userEmail }}**.

@if($isBusinessOwner)

You chose **Business Owner** at signup. Your next step is to complete your business profile so our team can review and verify your listing.

Have the following ready:

- DTI, SEC, or CDA registration
- BIR Form 2303
- Mayor's Permit
- Owner's valid ID

<x-mail::button :url="route('register_business')">
Continue Business Registration
</x-mail::button>

@else

Start exploring Victorias City — find stays, join events, and book your next adventure.

<x-mail::button :url="route('home')">
Explore Destinations
</x-mail::button>

@endif

If you have any questions, just reply to this email — we're happy to help.

Thanks,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
You're receiving this because you created an account on {{ config('app.name') }}. You can manage your account and communication preferences from your profile.
</x-slot:subcopy>
</x-mail::message>