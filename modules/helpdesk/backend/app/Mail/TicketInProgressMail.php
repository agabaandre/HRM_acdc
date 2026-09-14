<?php

namespace App\Mail;

use App\Models\HelpdeskTicket;
use App\Models\User;
use App\Support\HelpdeskMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketInProgressMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public HelpdeskTicket $ticket,
        public ?User $agent,
        public string $ticketUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: HelpdeskMailBranding::brandName().' — '.$this->ticket->ticket_number.' is in progress',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.helpdesk.ticket-in-progress',
        );
    }
}
