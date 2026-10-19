<?php

namespace App\Support;

class SimpleCsvWriter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|bool|null>>  $rows
     */
    public static function toCsv(array $headers, iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        // UTF-8 BOM for Excel-friendly CSV
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ',', '"', '\\');

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
            fputcsv($handle, $cells, ',', '"', '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }
}
