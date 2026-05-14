<?php

namespace App\Mail;

use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketAssignedToAgentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public HelpdeskTicket $ticket,
        public User $assignee,
        public bool $isReassignment,
    ) {}

    public function envelope(): Envelope
    {
        $verb = $this->isReassignment ? 'Reassigned to you' : 'Assigned to you';

        return new Envelope(
            subject: 'IT Service Desk — '.$verb.': '.$this->ticket->ticket_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.helpdesk.ticket-assigned-to-agent',
        );
    }
}
