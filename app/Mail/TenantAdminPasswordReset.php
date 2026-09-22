<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantAdminPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $adminName,
        public string $adminEmail,
        public string $newPassword,
        public string $businessName,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your password was reset — {$this->businessName}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.tenant-admin-password-reset');
    }
}