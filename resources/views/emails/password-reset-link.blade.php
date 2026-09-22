<x-mail::message>
# 🔐 Reset your password

Hi {{ \Illuminate\Support\Str::before($recipientName ?: 'there', ' ') }},

We received a request to reset the password for your **{{ config('app.name') }}** account (**{{ $recipientEmail }}**).

Click the button below to choose a new password. This link expires in **{{ $expiresInMinutes }} minutes**.

<x-mail::button :url="$resetUrl">
Reset Password
</x-mail::button>

If the button doesn't work, copy and paste this link into your browser:

<x-mail::panel>
{{ $resetUrl }}
</x-mail::panel>

**Didn't request this?** You can safely ignore this email. Your password won't change unless you click the link above and choose a new one.

Thanks,<br>
The {{ config('app.name') }} Team

<x-slot:subcopy>
For your security, never share this link with anyone. If you didn't request a password reset, please contact support immediately.
</x-slot:subcopy>
</x-mail::message>