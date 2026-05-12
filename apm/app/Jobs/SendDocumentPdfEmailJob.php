<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Throwable;

class SendDocumentPdfEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public string $recipientEmail,
        public string $subjectLine,
        public string $attachmentFilename,
        public string $absolutePathToPdf,
    ) {}

    public function handle(): void
    {
        if (! is_file($this->absolutePathToPdf) || ! is_readable($this->absolutePathToPdf)) {
            Log::error('SendDocumentPdfEmailJob: PDF temp file missing', [
                'path' => $this->absolutePathToPdf,
                'recipient' => $this->recipientEmail,
            ]);

            throw new \RuntimeException('PDF temp file is missing or not readable.');
        }

        $binary = file_get_contents($this->absolutePathToPdf);
        if ($binary === false || $binary === '') {
            throw new \RuntimeException('Could not read PDF file for emailing.');
        }

        $htmlBody = trim(View::make('emails.document-pdf')->render());
        if ($htmlBody === '') {
            $htmlBody = '<p>Your requested PDF is attached.</p>';
        }

        $attachments = [[
            'name' => $this->attachmentFilename,
            'content' => $binary,
            'content_type' => 'application/pdf',
        ]];

        if (! sendEmail($this->recipientEmail, $this->subjectLine, $htmlBody, null, null, [], [], $attachments)) {
            throw new \RuntimeException('sendEmail returned false (mail transport rejected send).');
        }

        $this->deleteTempFile();

        Log::info('SendDocumentPdfEmailJob: PDF email sent', [
            'recipient' => $this->recipientEmail,
            'subject' => $this->subjectLine,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->deleteTempFile();

        Log::error('SendDocumentPdfEmailJob failed permanently', [
            'recipient' => $this->recipientEmail,
            'subject' => $this->subjectLine,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function deleteTempFile(): void
    {
        if ($this->absolutePathToPdf !== '' && is_file($this->absolutePathToPdf)) {
            @unlink($this->absolutePathToPdf);
        }
    }
}
