<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlBody;

    public function __construct(
        public string $subjectLine,
        string $htmlBody,
        public string $pdfFilename,
        public string $pdfBinary,
    ) {
        $this->htmlBody = self::sanitizeRichHtml($htmlBody);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-pdf',
            with: [
                'htmlBody' => $this->htmlBody,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdfBinary = $this->pdfBinary;

        return [
            Attachment::fromData(static function () use ($pdfBinary) {
                return $pdfBinary;
            }, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }

    private static function sanitizeRichHtml(string $html): string
    {
        $allowed = '<p><br><br/><strong><b><em><i><u><s><strike><sub><sup><ul><ol><li><h1><h2><h3><h4><blockquote><a><span><div><table><thead><tbody><tr><th><td><hr><pre><code>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\s*on\w+\s*=\s*("|\').*?\1/is', '', $clean) ?? $clean;
        $clean = preg_replace('/href\s*=\s*("|\')\s*javascript:[^"\']*\1/is', 'href="#"', $clean) ?? $clean;

        return trim($clean) === '' ? '<p>Please find the attached PDF.</p>' : $clean;
    }
}
