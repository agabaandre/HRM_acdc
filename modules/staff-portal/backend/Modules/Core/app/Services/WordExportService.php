<?php

namespace Modules\Core\Services;

use Illuminate\Http\Response;

/**
 * Word-compatible HTML (.doc) download — opens in Microsoft Word / LibreOffice
 * without adding PhpWord. Uses the same printable HTML bodies as PDF export.
 */
class WordExportService
{
    /**
     * @param  array{
     *     title?: string,
     *     font_size_pt?: int,
     *     watermark_text?: string
     * }  $options
     */
    public function download(string $html, string $filename, array $options = []): Response
    {
        $filename = $this->ensureDocExtension($filename);
        $doc = $this->wrapAsWordDocument($html, $options);

        return response($doc, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * @param  array{title?: string, font_size_pt?: int, watermark_text?: string}  $options
     */
    public function wrapAsWordDocument(string $html, array $options = []): string
    {
        $title = htmlspecialchars((string) ($options['title'] ?? 'Document'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fontSize = (int) ($options['font_size_pt'] ?? 13);
        if ($fontSize < 10) {
            $fontSize = 10;
        }
        if ($fontSize > 18) {
            $fontSize = 18;
        }

        $body = $this->extractBody($html);
        $body = $this->embedLocalImagesAsDataUri($body);

        $watermark = trim((string) ($options['watermark_text'] ?? ''));
        $watermarkHtml = '';
        if ($watermark !== '') {
            $safe = htmlspecialchars($watermark, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $watermarkHtml = '<div style="color:#94a3b8;font-size:'.($fontSize + 5).'pt;font-weight:700;letter-spacing:0.12em;text-align:center;margin:0 0 12pt;border:1px dashed #cbd5e1;padding:6pt 0;">'
                .$safe
                .'</div>';
        }

        return '<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:w="urn:schemas-microsoft-com:office:word"
 xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="ProgId" content="Word.Document">
<meta name="Generator" content="Africa CDC Staff Portal">
<title>'.$title.'</title>
<!--[if gte mso 9]>
<xml>
 <w:WordDocument>
  <w:View>Print</w:View>
  <w:Zoom>100</w:Zoom>
  <w:DoNotOptimizeForBrowser/>
 </w:WordDocument>
</xml>
<![endif]-->
<style>
  @page Section1 {
    size: 595.3pt 841.9pt;
    margin: 56.7pt 56.7pt 56.7pt 56.7pt;
  }
  div.Section1 { page: Section1; }
  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: '.$fontSize.'pt;
    color: #1f2933;
    line-height: 1.45;
  }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #cbd5e1; padding: 6pt 8pt; vertical-align: top; }
  th { background: #e4e7eb; font-weight: 700; }
  img { max-width: 100%; height: auto; }
  .page-break { page-break-before: always; }
</style>
</head>
<body>
<div class="Section1">
'.$watermarkHtml.$body.'
</div>
</body>
</html>';
    }

    protected function ensureDocExtension(string $filename): string
    {
        $filename = preg_replace('/[^\w.\-]+/', '_', $filename) ?: 'export.doc';
        if (! preg_match('/\.docx?$/i', $filename)) {
            $filename .= '.doc';
        }

        return $filename;
    }

    protected function extractBody(string $html): string
    {
        if (preg_match('/<body\b[^>]*>(.*)<\/body>/is', $html, $m)) {
            return $m[1];
        }

        // Drop a leading <!DOCTYPE>…</head> if present so we do not nest documents.
        $html = preg_replace('/^.*?<body\b[^>]*>/is', '', $html) ?? $html;
        $html = preg_replace('/<\/body>.*$/is', '', $html) ?? $html;

        return $html;
    }

    /**
     * Word cannot resolve server filesystem paths — embed local image files as data URIs.
     */
    protected function embedLocalImagesAsDataUri(string $html): string
    {
        return (string) preg_replace_callback(
            '/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'][^>]*>)/i',
            function (array $m): string {
                $src = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (str_starts_with($src, 'data:') || preg_match('#^https?://#i', $src)) {
                    return $m[0];
                }
                $path = $src;
                if (str_starts_with($path, 'file://')) {
                    $path = substr($path, 7);
                }
                if (! is_file($path) || ! is_readable($path)) {
                    return $m[0];
                }
                $bytes = @file_get_contents($path);
                if ($bytes === false || $bytes === '') {
                    return $m[0];
                }
                $mime = 'image/png';
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg'], true)) {
                    $mime = 'image/jpeg';
                } elseif ($ext === 'gif') {
                    $mime = 'image/gif';
                } elseif ($ext === 'webp') {
                    $mime = 'image/webp';
                }

                return $m[1].'data:'.$mime.';base64,'.base64_encode($bytes).$m[3];
            },
            $html
        );
    }
}
