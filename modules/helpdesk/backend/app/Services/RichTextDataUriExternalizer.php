<?php

namespace App\Services;

use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketAttachment;
use App\Models\User;
use App\Support\HelpdeskAttachmentUrl;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Replace inline base64 images in Quill HTML with uploaded file URLs.
 *
 * Pasted screenshots are often embedded as data: URIs (tens of thousands of
 * characters). Validation caps HTML at 65k, so we externalize before validate.
 */
class RichTextDataUriExternalizer
{
    public static function externalize(?string $html, ?HelpdeskTicket $ticket = null, ?User $user = null): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        if (! str_contains($html, 'data:image')) {
            return $html;
        }

        $wrapped = '<?xml encoding="UTF-8"?><div id="__cbp_root__">'.$html.'</div>';

        $libxmlPrev = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlPrev);

        $root = $dom->getElementById('__cbp_root__');
        if (! $root instanceof DOMElement) {
            return $html;
        }

        $xpath = new DOMXPath($dom);
        /** @var \DOMNodeList<DOMElement> $images */
        $images = $xpath->query('.//img[@src]');
        if ($images === false || $images->length === 0) {
            return $html;
        }

        $changed = false;
        foreach ($images as $img) {
            if (! $img instanceof DOMElement) {
                continue;
            }
            $src = trim($img->getAttribute('src'));
            if ($src === '' || ! preg_match('#^data:image/(png|jpe?g|gif|webp);base64,(.+)$#i', $src, $match)) {
                continue;
            }

            $binary = base64_decode($match[2], true);
            if ($binary === false) {
                $img->parentNode?->removeChild($img);
                $changed = true;
                continue;
            }

            $ext = self::extensionForMime($match[1]);
            $url = self::storeBinary($binary, $ext, $ticket, $user);
            $img->setAttribute('src', $url);
            $changed = true;
        }

        if (! $changed) {
            return $html;
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function extensionForMime(string $mime): string
    {
        return match (strtolower($mime)) {
            'jpeg', 'jpg' => 'jpg',
            'gif' => 'gif',
            'webp' => 'webp',
            default => 'png',
        };
    }

    private static function storeBinary(string $binary, string $ext, ?HelpdeskTicket $ticket, ?User $user): string
    {
        $name = Str::uuid()->toString().'.'.$ext;

        if ($ticket !== null && $user !== null) {
            $path = 'helpdesk/'.$ticket->id.'/inline/'.$name;
            Storage::disk('public')->put($path, $binary);

            $row = HelpdeskTicketAttachment::query()->create([
                'ticket_id' => $ticket->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => 'pasted-image.'.$ext,
                'size_bytes' => strlen($binary),
                'mime_type' => $ext === 'jpg' ? 'image/jpeg' : 'image/'.$ext,
                'uploaded_by' => $user->id,
            ]);

            return HelpdeskAttachmentUrl::forAttachment($row);
        }

        abort_unless($user !== null, 422, 'You must be signed in to upload images.');

        $path = 'helpdesk/rich-text/'.$user->id.'/'.$name;
        Storage::disk('public')->put($path, $binary);

        return Storage::disk('public')->url($path);
    }
}
