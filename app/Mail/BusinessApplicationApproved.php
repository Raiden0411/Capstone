<?php

namespace App\Mail;

use App\Models\BusinessApplication;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessApplicationApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BusinessApplication $application,
        public Tenant $tenant,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your business is now live — {$this->application->business_name}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.business-application-approved');
    }
}