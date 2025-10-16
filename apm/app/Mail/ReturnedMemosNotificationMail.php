<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturnedMemosNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $staff;
    public $summaryStats;
    public $returnedMemos;

    /**
     * Create a new message instance.
     */
    public function __construct($staff, $summaryStats, $returnedMemos)
    {
        $this->staff = $staff;
        $this->summaryStats = $summaryStats;
        $this->returnedMemos = $returnedMemos;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Returned Memos Notification - Action Required',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.returned-memos-notification',
            with: [
                'staff' => $this->staff,
                'summaryStats' => $this->summaryStats,
                'returnedMemos' => $this->returnedMemos,
                'returnedMemosUrl' => config('app.url') . '/returned-memos'
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
