<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskTicketAttachment;
use App\Support\HelpdeskAttachmentUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentDownloadController extends Controller
{
    public function file(Request $request, HelpdeskTicketAttachment $attachment): BinaryFileResponse|StreamedResponse
    {
        HelpdeskAttachmentUrl::verify(
            $attachment,
            (int) $request->query('exp', 0),
            (string) $request->query('sig', ''),
        );

        if ($attachment->disk !== 'public') {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        $full = $disk->path($attachment->path);
        $real = realpath($full);
        $root = realpath($disk->path(''));
        if ($real === false || $root === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $mime = $attachment->mime_type ?: 'application/octet-stream';

        return response()->file($real, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($attachment->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
