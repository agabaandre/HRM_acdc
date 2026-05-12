<?php

namespace App\Http\Controllers\Concerns;

use App\Jobs\SendDocumentPdfEmailJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait SendsSelfDocumentPdfEmail
{
    /**
     * Write PDF bytes to a temp file and queue {@see SendDocumentPdfEmailJob} (Exchange via sendEmail in job).
     *
     * @throws \RuntimeException
     */
    protected function queueDocumentPdfEmailFromBinary(string $recipientEmail, string $subject, string $attachmentFilename, string $binaryPdf): void
    {
        if (! is_string($binaryPdf) || $binaryPdf === '') {
            throw new \RuntimeException('PDF generation returned empty output.');
        }

        $relative = 'tmp/email-pdf/'.Str::uuid()->toString().'.pdf';
        Storage::disk('local')->makeDirectory('tmp/email-pdf');
        if (! Storage::disk('local')->put($relative, $binaryPdf)) {
            throw new \RuntimeException('Could not store the PDF for emailing.');
        }
        $absolutePath = Storage::disk('local')->path($relative);

        try {
            SendDocumentPdfEmailJob::dispatch(
                $recipientEmail,
                $subject,
                $attachmentFilename,
                $absolutePath
            )->afterResponse();
        } catch (\Throwable $e) {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            throw $e;
        }
    }
}
