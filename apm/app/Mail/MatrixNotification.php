<?php

namespace App\Mail;

use App\Models\Matrix;
use App\Models\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MatrixNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $matrix;
    public $recipient;
    public $type;
    public $message;

    /**
     * Create a new message instance.
     */
    public function __construct(Matrix $matrix, Staff $recipient, string $type, string $message)
    {
        $this->matrix = $matrix;
        $this->recipient = $recipient;
        $this->type = $type;
        $this->message = $message;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = '';
        switch($this->type) {
            case 'matrix_approval':
                $subject = 'Matrix Approval Required';
                break;
            case 'matrix_returned':
                $subject = 'Matrix Returned for Revision';
                break;
            default:
                $subject = 'Matrix Notification';
        }

        return new Envelope( $subject );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            'emails.matrix-notification',
            null,
            null,
            null,
             [
                'matrix' => $this->matrix,
                'recipient' => $this->recipient,
                'message' => $this->message,
                'type' => $this->type,
            ]
        );
    }
} 