<?php

namespace Modules\Performance\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class PerformanceRichText
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        '*' => ['class', 'style', 'dir'],
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'strike' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'span' => [],
    ];

    /**
     * @var list<string>
     */
    private const SAFE_CSS_PROPS = [
        'color',
        'background-color',
        'text-align',
        'font-weight',
        'font-style',
        'text-decoration',
        'padding-left',
        'margin-left',
    ];

    /**
     * @var list<string>
     */
    private const FORM_FIELDS = [
        'training_contributions',
        'recommended_trainings',
        'recommended_trainings_details',
        'comments',
        'midterm_comments',
        'midterm_training_review',
        'midterm_achievements',
        'midterm_non_achievements',
        'midterm_training_contributions',
        'midterm_recommended_trainings',
        'midterm_recommended_trainings_details',
        'endterm_comments',
        'endterm_training_review',
        'endterm_achievements',
        'endterm_non_achievements',
        'endterm_training_contributions',
        'endterm_recommended_trainings',
        'endterm_recommended_trainings_details',
    ];

    public static function isEmpty(?string $html): bool
    {
        if ($html === null) {
            return true;
        }

        $trimmed = trim($html);
        if ($trimmed === '') {
            return true;
        }

        $normalised = preg_replace('/\s+/', '', $trimmed) ?? '';
        if ($normalised === '<p><br></p>' || $normalised === '<p><br/></p>') {
            return true;
        }

        $plain = trim(html_entity_decode(strip_tags($trimmed), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $plain === '';
    }

    public static function looksLikeHtml(?string $value): bool
    {
        return is_string($value) && (bool) preg_match('/<[a-z][\s\S]*>/i', $value);
    }

    public static function sanitize(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $trimmed = trim($html);
        if ($trimmed === '' || self::isEmpty($trimmed)) {
            return '';
        }

        if (! self::looksLikeHtml($trimmed)) {
            return $trimmed;
        }

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
            $body = $dom->getElementsByTagName('body')->item(0);
            if (! $body) {
                return '';
            }
            $root = $body;
        }

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        $out = trim($out);

        return self::isEmpty($out) ? '' : $out;
    }

    public static function toSafeHtml(?string $value): string
    {
        if (self::isEmpty($value)) {
            return '';
        }

        if (! self::looksLikeHtml($value)) {
            return nl2br(e($value), false);
        }

        return self::sanitize($value);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitizeFormPayload(array $payload): array
    {
        foreach (self::FORM_FIELDS as $key) {
            if (array_key_exists($key, $payload) && is_string($payload[$key])) {
                $payload[$key] = self::sanitize($payload[$key]);
            }
        }

        if (isset($payload['objectives']) && is_array($payload['objectives'])) {
            $payload['objectives'] = self::sanitizeObjectives($payload['objectives']);
        }

        return $payload;
    }

    /**
     * @param  array<int|string, mixed>  $objectives
     * @return array<int|string, mixed>
     */
    public static function sanitizeObjectives(array $objectives): array
    {
        foreach ($objectives as $key => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (['objective', 'indicator', 'self_appraisal'] as $field) {
                if (isset($row[$field]) && is_string($row[$field])) {
                    $objectives[$key][$field] = self::sanitize($row[$field]);
                }
            }
        }

        return $objectives;
    }

    private static function walk(DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);
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
        foreach ($el->attributes as $attribute) {
            $names[] = $attribute->nodeName;
        }

        foreach ($names as $name) {
            $lower = strtolower($name);
            if (str_starts_with($lower, 'on') || ! isset($allowedSet[$lower])) {
                $el->removeAttribute($name);

                continue;
            }

            $value = $el->getAttribute($name);
            if ($lower === 'href') {
                if (! self::isSafeHref($value)) {
                    $el->removeAttribute($name);
                }

                continue;
            }

            if ($lower === 'style') {
                $safe = self::sanitizeStyle($value);
                if ($safe === '') {
                    $el->removeAttribute($name);
                } else {
                    $el->setAttribute('style', $safe);
                }

                continue;
            }

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
        if (preg_match('#^(javascript|vbscript|data|file):#i', $v)) {
            return false;
        }
        if ($v[0] === '#' || $v[0] === '/' || str_starts_with($v, './') || str_starts_with($v, '../') || str_starts_with($v, '//')) {
            return true;
        }

        return (bool) preg_match('#^(https?|mailto|tel):#i', $v);
    }

    private static function sanitizeStyle(string $value): string
    {
        $kept = [];
        foreach (explode(';', $value) as $decl) {
            $parts = explode(':', $decl, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $prop = strtolower(trim($parts[0]));
            $val = trim($parts[1]);
            if ($prop === '' || $val === '' || ! in_array($prop, self::SAFE_CSS_PROPS, true)) {
                continue;
            }
            if (preg_match('/expression|javascript|url\s*\(/i', $val)) {
                continue;
            }
            $kept[] = $prop.': '.$val;
        }

        return implode('; ', $kept);
    }
}
