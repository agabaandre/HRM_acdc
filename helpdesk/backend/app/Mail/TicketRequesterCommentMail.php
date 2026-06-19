<?php

namespace App\Mail;

use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketRequesterCommentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public HelpdeskTicket $ticket,
        public HelpdeskTicketComment $comment,
        public User $requester,
        public bool $ticketReopened,
    ) {}

    public function envelope(): Envelope
    {
        $suffix = $this->ticketReopened ? ' (reopened)' : '';

        return new Envelope(
            subject: 'IT Service Desk — New requester comment'.$suffix.': '.$this->ticket->ticket_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.helpdesk.ticket-requester-comment',
        );
    }
}
