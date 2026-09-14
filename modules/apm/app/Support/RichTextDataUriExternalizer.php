<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Replace inline base64 images in Quill/Summernote HTML with uploaded file URLs.
 *
 * Pasted screenshots are often embedded as data: URIs (very large strings).
 * Memos should store short /storage/... URLs instead.
 */
final class RichTextDataUriExternalizer
{
    public static function externalize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        if (! str_contains($html, 'data:image')) {
            return $html;
        }

        $wrapped = '<?xml encoding="UTF-8"?><div id="__apm_root__">'.$html.'</div>';

        $libxmlPrev = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlPrev);

        $root = $dom->getElementById('__apm_root__');
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
            $img->setAttribute('src', self::storeBinary($binary, $ext));
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

    private static function storeBinary(string $binary, string $ext): string
    {
        $filename = time().'_'.Str::random(10).'.'.$ext;
        $path = 'uploads/summernote/'.$filename;
        Storage::disk('public')->put($path, $binary);

        return asset('storage/'.$path);
    }
}
