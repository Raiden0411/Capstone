<?php

namespace App\Mail;

use App\Models\BusinessApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessApplicationNeedsRevision extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BusinessApplication $application,
        public string $notes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Action needed — {$this->application->business_name}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.business-application-needs-revision');
    }
}