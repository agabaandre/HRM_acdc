<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Minimal allow-list HTML sanitizer for staff-authored rich text (Quill).
 *
 * Why: ticket resolution HTML is shown to authenticated agents AND emailed to
 * external requesters AND rendered on the public confirmation page. We trust
 * the actor (they are signed in as staff), but a single compromised agent
 * session could otherwise inject persistent XSS into outbound emails. Defense
 * in depth is cheap here.
 *
 * Strategy:
 *  - Parse with DOMDocument (UTF-8).
 *  - Walk the tree; drop any element that isn't in the tag allow-list.
 *  - For surviving elements, drop any attribute not in the per-tag allow-list.
 *  - Normalise href/src to safe schemes (http, https, mailto, data:image/* for images).
 *  - Strip every `on*` event attribute and any `javascript:`/`vbscript:` URI.
 */
class HtmlSanitizer
{
    /**
     * Per-tag attribute allow-list. The `*` key applies to every tag.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        '*' => ['class', 'style', 'dir'],
        'p' => [],
        'br' => [],
        'hr' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'strike' => [],
        'sub' => [],
        'sup' => [],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'pre' => [],
        'code' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'span' => [],
        'div' => [],
        'figure' => [],
        'figcaption' => [],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => [],
        'td' => [],
        // Quill video embed renders as <iframe class="ql-video"> — we keep iframes
        // but pin their src to https://www.youtube.com/, https://www.youtu.be/, or
        // https://player.vimeo.com/ (see isSafeIframeUrl()).
        'iframe' => ['src', 'width', 'height', 'frameborder', 'allowfullscreen', 'class'],
    ];

    /**
     * CSS properties we tolerate inside an inline `style` attribute.
     *
     * @var list<string>
     */
    private const SAFE_CSS_PROPS = [
        'color', 'background-color', 'background', 'text-align',
        'font-weight', 'font-style', 'text-decoration', 'font-size', 'line-height',
        'padding-left', 'padding-right', 'margin-left', 'margin-right',
    ];

    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        $trimmed = trim($html);
        if ($trimmed === '' || self::isQuillEmpty($trimmed)) {
            return null;
        }

        // DOMDocument expects a single root; wrap in a sentinel <div> so we can
        // unwrap cleanly afterwards. Also ensure UTF-8 parsing.
        $wrapped = '<?xml encoding="UTF-8"?><div id="__cbp_root__">'.$trimmed.'</div>';

        $libxmlPrev = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlPrev);

        $root = $dom->getElementById('__cbp_root__');
        if (! $root instanceof DOMElement) {
            // Fallback: pull the first element under <body>.
            $body = $dom->getElementsByTagName('body')->item(0);
            if (! $body) {
                return null;
            }
            $root = $body;
        }

        self::walk($root);

        // Serialise children of the sentinel only, never the sentinel itself.
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        $out = trim($out);

        return $out === '' ? null : $out;
    }

    /** Quill emits `<p><br></p>` as its "empty" content. */
    private static function isQuillEmpty(string $html): bool
    {
        $normalised = preg_replace('/\s+/', '', $html) ?? '';

        return $normalised === '<p><br></p>' || $normalised === '<p><br/></p>';
    }

    private static function walk(DOMNode $node): void
    {
        // Snapshot the child list because we mutate as we go.
        $children = [];
        foreach ($node->childNodes as $c) {
            $children[] = $c;
        }
        foreach ($children as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->nodeName);

            // Disallowed tag → drop the element (and everything inside it).
            if (! array_key_exists($tag, self::ALLOWED)) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            self::sanitizeAttributes($child, $tag);
            self::walk($child);
        }
    }

    private static function sanitizeAttributes(DOMElement $el, string $tag): void
    {
        $allowed = array_merge(self::ALLOWED['*'] ?? [], self::ALLOWED[$tag] ?? []);
        $allowedSet = array_flip($allowed);

        $names = [];
        foreach ($el->attributes as $a) {
            $names[] = $a->nodeName;
        }

        foreach ($names as $name) {
            $lower = strtolower($name);

            // 1. Always strip event handlers.
            if (str_starts_with($lower, 'on')) {
                $el->removeAttribute($name);
                continue;
            }

            // 2. Strip any attribute not in the allow-list.
            if (! isset($allowedSet[$lower])) {
                $el->removeAttribute($name);
                continue;
            }

            $value = $el->getAttribute($name);

            // 3. URL attributes — pin to safe schemes.
            if ($lower === 'href') {
                if (! self::isSafeHref($value)) {
                    $el->removeAttribute($name);
                }
                continue;
            }
            if ($lower === 'src') {
                $ok = $tag === 'img'
                    ? self::isSafeImgSrc($value)
                    : ($tag === 'iframe' ? self::isSafeIframeUrl($value) : self::isSafeHref($value));
                if (! $ok) {
                    $el->removeAttribute($name);
                }
                continue;
            }

            // 4. style="…" — keep only an allow-list of properties.
            if ($lower === 'style') {
                $safe = self::sanitizeStyle($value);
                if ($safe === '') {
                    $el->removeAttribute($name);
                } else {
                    $el->setAttribute('style', $safe);
                }
                continue;
            }

            // 5. target=_blank gets rel=noopener for safety.
            if ($lower === 'target' && $tag === 'a' && strtolower($value) === '_blank') {
                $existingRel = strtolower($el->getAttribute('rel'));
                if (! str_contains($existingRel, 'noopener')) {
                    $el->setAttribute('rel', trim($existingRel.' noopener noreferrer'));
                }
            }
        }
    }

    private static function isSafeHref(string $value): bool
    {
        $v = ltrim($value);
        if ($v === '') {
            return false;
        }
        // Block javascript:, vbscript:, data: (except for images handled separately).
        if (preg_match('#^(javascript|vbscript|data|file):#i', $v)) {
            return false;
        }

        // Allow relative/anchor/scheme-relative + http/https/mailto/tel.
        if ($v[0] === '#' || $v[0] === '/' || str_starts_with($v, './') || str_starts_with($v, '../')) {
            return true;
        }
        if (str_starts_with($v, '//')) {
            return true;
        }

        return (bool) preg_match('#^(https?|mailto|tel):#i', $v);
    }

    private static function isSafeImgSrc(string $value): bool
    {
        $v = ltrim($value);
        if ($v === '') {
            return false;
        }
        // Inline base64 images are fine — but only for image/* MIMEs.
        if (preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $v)) {
            return true;
        }

        return self::isSafeHref($value);
    }

    private static function isSafeIframeUrl(string $value): bool
    {
        $v = ltrim($value);

        return (bool) preg_match(
            '#^https://(www\.)?(youtube\.com|youtu\.be|player\.vimeo\.com)/#i',
            $v,
        );
    }

    private static function sanitizeStyle(string $value): string
    {
        $kept = [];
        foreach (explode(';', $value) as $decl) {
            $decl = trim($decl);
            if ($decl === '') {
                continue;
            }
            $parts = explode(':', $decl, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $prop = strtolower(trim($parts[0]));
            $val = trim($parts[1]);

            if (! in_array($prop, self::SAFE_CSS_PROPS, true)) {
                continue;
            }
            // Block url() and expression() entirely.
            if (preg_match('#(url\s*\(|expression\s*\(|javascript\s*:)#i', $val)) {
                continue;
            }
            $kept[] = $prop.': '.$val;
        }

        return implode('; ', $kept);
    }
}
