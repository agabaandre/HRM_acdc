<?php

namespace App\Services;

/**
 * Normalises inbound email HTML/text for ticket descriptions:
 * keep the latest message, strip quoted reply threads, fix over-escaped entities.
 */
class EmailBodyNormalizer
{
    public function toTicketHtml(string $raw, string $contentType = 'html'): string
    {
        $type = strtolower(trim($contentType));
        $content = trim($raw);
        if ($content === '') {
            return '<p>Email body was empty.</p>';
        }

        if ($type === 'text' || $type === 'text/plain') {
            $content = $this->stripPlainReplies($content);
            $content = $this->decodeEntitiesLightly($content);

            return '<p>'.nl2br(e($content), false).'</p>';
        }

        $content = $this->decodeOverEscapedHtml($content);
        $content = $this->stripHtmlReplies($content);
        $sanitized = HtmlSanitizer::sanitize($content);
        if ($sanitized !== null && trim(HtmlSanitizer::toPlainText($sanitized) ?? '') !== '') {
            return $sanitized;
        }

        $plain = trim(HtmlSanitizer::toPlainText($content) ?? '');
        if ($plain === '') {
            $plain = trim(strip_tags($this->stripPlainReplies(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        }

        return $plain !== ''
            ? '<p>'.nl2br(e($plain), false).'</p>'
            : '<p>Email body was empty.</p>';
    }

    public function decodeOverEscapedHtml(string $html): string
    {
        $current = $html;
        // Up to 2 passes for &amp;nbsp; → &nbsp; → nbsp char / space in rendering.
        for ($i = 0; $i < 2; $i++) {
            if (! str_contains($current, '&amp;') && ! str_contains($current, '&lt;')) {
                break;
            }
            $decoded = html_entity_decode($current, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $current) {
                break;
            }
            $current = $decoded;
        }

        return $current;
    }

    public function stripHtmlReplies(string $html): string
    {
        $patterns = [
            // Outlook reply/forward chrome
            '/<div[^>]+id=["\']?divRplyFwdMsg["\']?[^>]*>.*$/is',
            '/<div[^>]+id=["\']?mail-editor-reference-message-container["\']?[^>]*>.*$/is',
            // Gmail quote
            '/<div[^>]+class=["\'][^"\']*gmail_quote[^"\']*["\'][^>]*>.*$/is',
            '/<blockquote[^>]+class=["\'][^"\']*gmail_quote[^"\']*["\'][^>]*>.*$/is',
            // Generic blockquote reply
            '/<blockquote[^>]*type=["\']cite["\'][^>]*>.*$/is',
            // Horizontal rule then From: (common in HTML replies)
            '/<hr[^>]*>\s*(?:<[^>]+>\s*)*From:\s*.+$/is',
            '/<hr[^>]*>\s*(?:<[^>]+>\s*)*De\s*:\s*.+$/is',
            // "-----Original Message-----"
            '/-{5,}\s*Original Message\s*-{5,}.*$/is',
            '/_{10,}.*$/s',
        ];

        $out = $html;
        foreach ($patterns as $pattern) {
            $stripped = preg_replace($pattern, '', $out);
            if (is_string($stripped) && trim(strip_tags($stripped)) !== '') {
                $out = $stripped;
            }
        }

        // Cut before a standalone "From:" / "Sent:" header block in remaining text.
        $plainCut = $this->stripPlainReplies(HtmlSanitizer::toPlainText($out) ?? strip_tags($out));
        if ($plainCut !== '' && mb_strlen($plainCut) < mb_strlen(HtmlSanitizer::toPlainText($out) ?? '')) {
            // Prefer structured HTML when the cut removed most of the thread; rebuild from plain.
            $ratio = mb_strlen($plainCut) / max(1, mb_strlen(HtmlSanitizer::toPlainText($out) ?? 'x'));
            if ($ratio < 0.85) {
                return '<p>'.nl2br(e($plainCut), false).'</p>';
            }
        }

        return trim($out) !== '' ? $out : $html;
    }

    public function stripPlainReplies(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $patterns = [
            '/\nOn .+wrote:\n.*$/s',
            '/\nLe .+a écrit\s*:\n.*$/siu',
            '/\nFrom:\s.+\nSent:\s.+$/si',
            '/\nDe\s*:\s.+\nEnvoyé\s*:\s.+$/siu',
            '/\n-{5,}\s*Original Message\s*-{5,}.*$/si',
            '/\n_{10,}.*$/s',
            '/\n>+ .*$/s',
        ];

        $out = $text;
        foreach ($patterns as $pattern) {
            $stripped = preg_replace($pattern, '', $out);
            if (is_string($stripped) && trim($stripped) !== '') {
                $out = $stripped;
            }
        }

        return trim($out);
    }

    private function decodeEntitiesLightly(string $text): string
    {
        if (! str_contains($text, '&')) {
            return $text;
        }

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
