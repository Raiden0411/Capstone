<x-mail::message>
# 👋 Welcome to {{ config('app.name') }}

Hi {{ \Illuminate\Support\Str::before($adminName ?: 'there', ' ') }},

Your business **{{ $businessName }}** has been registered on {{ config('app.name') }}. You can now log in and start managing your listings, properties, services, and bookings.

<x-mail::table>
| | |
| :--- | :--- |
| **Business** | {{ $businessName }} |
| **Login email** | {{ $adminEmail }} |
| **Temporary password** | `{{ $adminPassword }}` |
</x-mail::table>

<x-mail::panel>
**Change your password immediately after your first login.** This temporary password was sent over email and should not be reused.
</x-mail::panel>

<x-mail::button :url="$loginUrl">
Log In to Your Dashboard
</x-mail::button>

Welcome aboard,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
You're receiving this because your business was registered on {{ config('app.name') }}. If you weren't expecting this email, please contact support immediately.
</x-slot:subcopy>
</x-mail::message>