<?php

namespace App\Mail;

use App\Models\HelpdeskLicense;
use App\Support\HelpdeskMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LicenseExpiryAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{days_until_expiry:int|null,expiry_status:string,is_expiring_soon:bool,is_expired:bool}  $expiry
     */
    public function __construct(
        public HelpdeskLicense $license,
        public array $expiry,
        public string $licensesUrl,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->license->name;
        $status = ($this->expiry['is_expired'] ?? false) ? 'expired' : 'expiring soon';

        return new Envelope(
            subject: HelpdeskMailBranding::brandName().' — License '.$status.': '.$name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.helpdesk.license-expiry-alert',
        );
    }
}
