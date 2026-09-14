<?php

namespace App\Mail;

use App\Models\HelpdeskSoftwareRequest;
use App\Support\HelpdeskMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SoftwareRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public HelpdeskSoftwareRequest $softwareRequest,
        public string $recipientName,
        public string $requestsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: HelpdeskMailBranding::brandName().' — New software request '.$this->softwareRequest->request_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.helpdesk.software-request-submitted',
        );
    }
}
