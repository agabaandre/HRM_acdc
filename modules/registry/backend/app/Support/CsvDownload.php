<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvDownload
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function make(string $filename, array $headings, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
