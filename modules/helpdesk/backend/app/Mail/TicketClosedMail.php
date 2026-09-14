<?php

namespace App\Mail;

use App\Models\HelpdeskTicket;
use App\Support\HelpdeskMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketClosedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public HelpdeskTicket $ticket,
        public string $ticketUrl,
        public bool $autoClosed = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: HelpdeskMailBranding::brandName().' — '.$this->ticket->ticket_number.' closed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.helpdesk.ticket-closed',
        );
    }
}
