<?php

namespace App\Mail;

use App\Models\HelpdeskTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketResolutionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public HelpdeskTicket $ticket,
        public string $ticketUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'IT Service Desk — '.$this->ticket->ticket_number.' closed',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.helpdesk.ticket-resolution',
        );
    }
}
