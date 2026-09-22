<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantAdminWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $adminName,
        public string $adminEmail,
        public string $adminPassword,
        public string $businessName,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome — {$this->businessName} is live on " . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.tenant-admin-welcome');
    }
}