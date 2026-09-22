<x-mail::message>
# 🔒 Your password was reset

Hi {{ \Illuminate\Support\Str::before($adminName ?: 'there', ' ') }},

The password for your **{{ $businessName }}** account on {{ config('app.name') }} was just reset by a platform administrator.

<x-mail::table>
| | |
| :--- | :--- |
| **Business** | {{ $businessName }} |
| **Login email** | {{ $adminEmail }} |
| **New password** | `{{ $newPassword }}` |
</x-mail::table>

<x-mail::panel>
**Change this password immediately after logging in.** It was sent over email and should not be reused.
</x-mail::panel>

If you did **not** request this reset, contact platform support right away — your account may have been compromised.

<x-mail::button :url="$loginUrl">
Log In Now
</x-mail::button>

Thanks,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
You're receiving this because a platform administrator reset the password for your business account. If this was unexpected, reply to this email immediately.
</x-slot:subcopy>
</x-mail::message>