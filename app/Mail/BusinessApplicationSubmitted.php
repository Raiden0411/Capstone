<?php

namespace App\Mail;

use App\Models\BusinessApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessApplicationSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BusinessApplication $application,
        public bool $forAdmin = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->forAdmin
                ? "New KYB Application: {$this->application->business_name}"
                : "We received your application — {$this->application->business_name}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.business-application-submitted');
    }
}