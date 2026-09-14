<?php

namespace App\Mail;

use App\Models\HelpdeskAgentMonthlyReport;
use App\Support\HelpdeskMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgentMonthlyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public HelpdeskAgentMonthlyReport $report,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: HelpdeskMailBranding::brandName().' — Monthly agent report '.$this->report->periodLabel(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.helpdesk.agent-monthly-report',
        );
    }
}
