<?php

namespace App\Jobs;

use App\Mail\DocumentPdfMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        Mail::to($this->recipientEmail)->send(
            new DocumentPdfMail(
                $this->subjectLine,
                $this->attachmentFilename,
                $this->absolutePathToPdf,
            )
        );

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
