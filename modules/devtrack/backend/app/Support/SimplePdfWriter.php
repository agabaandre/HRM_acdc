<?php

namespace App\Support;

/**
 * Minimal multi-page PDF table writer (Helvetica, no external PDF library).
 */
class SimplePdfWriter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|bool|null>>  $rows
     */
    public static function toPdf(string $title, array $headers, iterable $rows, string $subtitle = ''): string
    {
        $pageWidth = 841.89; // A4 landscape
        $pageHeight = 595.28;
        $margin = 36.0;
        $lineHeight = 12.0;
        $usableWidth = $pageWidth - ($margin * 2);
        $colCount = max(1, count($headers));
        $colWidth = $usableWidth / $colCount;

        $normalizedRows = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach (array_values($row) as $value) {
                if (is_bool($value)) {
                    $cells[] = $value ? 'Yes' : 'No';
                } elseif ($value === null) {
                    $cells[] = '';
                } else {
                    $cells[] = (string) $value;
                }
            }
            while (count($cells) < $colCount) {
                $cells[] = '';
            }
            $normalizedRows[] = array_slice($cells, 0, $colCount);
        }

        $pages = [];
        $content = '';
        $y = 0.0;

        $startPage = function () use (
            &$content,
            &$y,
            $pageHeight,
            $margin,
            $title,
            $subtitle,
            $headers,
            $colWidth,
            $lineHeight
        ): void {
            $content = '';
            $y = $pageHeight - $margin;
            $content .= sprintf("BT /F1 12 Tf %.2F %.2F Td (%s) Tj ET\n", $margin, $y, self::escape($title));
            $y -= 16;
            if ($subtitle !== '') {
                $content .= sprintf("BT /F1 9 Tf %.2F %.2F Td (%s) Tj ET\n", $margin, $y, self::escape($subtitle));
                $y -= 14;
            }
            $y -= 4;
            $x = $margin;
            foreach ($headers as $header) {
                $content .= sprintf(
                    "BT /F1 8 Tf %.2F %.2F Td (%s) Tj ET\n",
                    $x,
                    $y,
                    self::escape(self::truncate((string) $header, 28)),
                );
                $x += $colWidth;
            }
            $y -= ($lineHeight + 2);
            $content .= sprintf(
                "%.2F %.2F m %.2F %.2F l S\n",
                $margin,
                $y + 6,
                $margin + ($colWidth * count($headers)),
                $y + 6,
            );
        };

        $startPage();

        foreach ($normalizedRows as $row) {
            if ($y < $margin + $lineHeight) {
                $pages[] = $content;
                $startPage();
            }
            $x = $margin;
            foreach ($row as $cell) {
                $content .= sprintf(
                    "BT /F1 8 Tf %.2F %.2F Td (%s) Tj ET\n",
                    $x,
                    $y,
                    self::escape(self::truncate($cell, 28)),
                );
                $x += $colWidth;
            }
            $y -= $lineHeight;
        }

        $pages[] = $content;

        return self::assemble($pages, $pageWidth, $pageHeight);
    }

    /**
     * @param  list<string>  $pageContents
     */
    private static function assemble(array $pageContents, float $pageWidth, float $pageHeight): string
    {
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>'; // 1
        $objects[] = ''; // 2 pages placeholder
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'; // 3

        $kids = [];
        foreach ($pageContents as $pageContent) {
            $contentNumber = count($objects) + 1;
            $objects[] = '<< /Length '.strlen($pageContent)." >>\nstream\n".$pageContent.'endstream';
            $pageNumber = count($objects) + 1;
            $objects[] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>',
                $pageWidth,
                $pageHeight,
                $contentNumber,
            );
            $kids[] = $pageNumber.' 0 R';
        }

        $objects[1] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= 'xref\n0 '.$count."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= 'trailer<< /Size '.$count.' /Root 1 0 R >>'."\n";
        $pdf .= "startxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private static function truncate(string $value, int $max): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1).'…';
    }

    private static function escape(string $value): string
    {
        // PDF literal strings are Latin-ish; strip non-ASCII for reliability.
        $value = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
