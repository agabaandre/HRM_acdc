<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyPendingApprovalsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    /**
     * Create a new message instance.
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $prefix = env('MAIL_SUBJECT_PREFIX', 'APM') . ": ";
        $currentHour = (int) date('H');
        $timeOfDay = $currentHour < 12 ? 'Morning' : 'Evening';
        $totalPending = $this->data['summaryStats']['total_pending'] ?? 0;
        
        return new Envelope(
            subject: $prefix . "{$timeOfDay} Pending Approvals Reminder - {$totalPending} items",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-pending-approvals-notification',
            with: $this->data
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