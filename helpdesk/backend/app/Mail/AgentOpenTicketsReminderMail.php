<?php

namespace App\Mail;

use App\Models\User;
use App\Support\HelpdeskMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgentOpenTicketsReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{ticket_number:string,subject:string,status:string,priority:string}>  $tickets
     */
    public function __construct(
        public User $agent,
        public array $tickets,
        public string $deskUrl,
    ) {}

    public function envelope(): Envelope
    {
        $count = count($this->tickets);

        return new Envelope(
            subject: HelpdeskMailBranding::brandName().' — '.$count.' open ticket'.($count === 1 ? '' : 's').' need attention',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.helpdesk.agent-open-tickets-reminder',
        );
    }
}
