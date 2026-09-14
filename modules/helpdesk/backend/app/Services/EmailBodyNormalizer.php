<?php

namespace App\Services;

/**
 * Normalises inbound email HTML/text for ticket descriptions:
 * keep the latest message, strip quoted reply threads and email signatures,
 * fix over-escaped entities.
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
            $content = $this->stripPlainSignatures($content);
            $content = $this->decodeEntitiesLightly($content);

            return '<p>'.nl2br(e($content), false).'</p>';
        }

        $content = $this->decodeOverEscapedHtml($content);
        $content = $this->stripHtmlReplies($content);
        $content = $this->stripHtmlSignatures($content);
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

    public function stripHtmlSignatures(string $html): string
    {
        $patterns = [
            '/<div[^>]+id=["\']?(?:x_)?Signature["\']?[^>]*>.*$/is',
            '/<div[^>]+name=["\']?(?:x_)?Signature["\']?[^>]*>.*$/is',
            '/<div[^>]+class=["\'][^"\']*gmail_signature[^"\']*["\'][^>]*>.*$/is',
            '/<div[^>]+class=["\'][^"\']*(?:moz-signature|apple-mail-signature)[^"\']*["\'][^>]*>.*$/is',
            '/<div[^>]+class=["\'][^"\']*c2[-_]?sig[^"\']*["\'][^>]*>.*$/is',
            '/<table[^>]+class=["\'][^"\']*c2[-_]?sig[^"\']*["\'][^>]*>.*$/is',
            '/<!--\s*(?:CodeTwo|C2(?:TABLE|SIG|Signature)).*$/is',
        ];

        $out = $html;
        foreach ($patterns as $pattern) {
            $stripped = preg_replace($pattern, '', $out);
            if (is_string($stripped) && trim(strip_tags($stripped)) !== '') {
                $out = $stripped;
            }
        }

        $withoutImgs = preg_replace('/<img\b[^>]*c2_signature_[^>]*>/i', '', $out);
        if (is_string($withoutImgs) && trim(strip_tags($withoutImgs)) !== '') {
            $out = $withoutImgs;
        }

        $plain = trim(HtmlSanitizer::toPlainText($out) ?? strip_tags($out));
        $plainCut = $this->stripPlainSignatures($plain);
        if ($plainCut !== '' && $plainCut !== $plain && mb_strlen($plainCut) < mb_strlen($plain)) {
            return '<p>'.nl2br(e($plainCut), false).'</p>';
        }

        return trim($out) !== '' ? $out : $html;
    }

    public function stripPlainSignatures(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);
        if ($text === '') {
            return $text;
        }

        if (preg_match('/(?:\A|\n)--[ \t]*\n/u', $text, $match, PREG_OFFSET_CAPTURE) === 1) {
            $cut = trim(substr($text, 0, (int) $match[0][1]));
            if ($cut !== '') {
                $text = $cut;
            }
        }

        $org = 'Africa Centres for Disease Control and Prevention';
        $pos = mb_stripos($text, $org);
        if ($pos === false) {
            return trim($text);
        }

        $after = mb_substr($text, $pos);
        if (! preg_match('/haile garment|www\.africacdc\.org|p\.?\s*o\.?\s*box\s*3243|addis ababa/i', $after)) {
            return trim($text);
        }

        $before = $this->dropTrailingNameTitle(mb_substr($text, 0, $pos));

        return $before !== '' ? $before : trim($text);
    }

    private function dropTrailingNameTitle(string $before): string
    {
        $before = rtrim($before);
        if ($before === '') {
            return '';
        }

        $lines = preg_split("/\n/", $before) ?: [$before];
        $dropped = 0;
        while ($lines !== [] && $dropped < 2) {
            $line = trim(html_entity_decode((string) array_pop($lines), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $line = trim(strip_tags($line));
            if ($line === '') {
                continue;
            }
            if (
                mb_strlen($line) > 80
                || str_ends_with($line, '.')
                || str_ends_with($line, '?')
                || str_ends_with($line, '!')
            ) {
                $lines[] = $line;
                break;
            }
            $dropped++;
        }

        return trim(implode("\n", $lines));
    }

    private function decodeEntitiesLightly(string $text): string
    {
        if (! str_contains($text, '&')) {
            return $text;
        }

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
