<?php

namespace Modules\Core\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UTF-8 CSV stream export (APM / CI3 render_csv_data style).
 */
class CsvExportService
{
    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function stream(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // UTF-8 BOM for Excel
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, array_map(static fn ($v) => $v === null ? '' : (string) $v, $row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
