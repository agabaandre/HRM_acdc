<?php

namespace App\Http\Controllers\Concerns;

use App\Services\HelpdeskPdfReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait DownloadsPdfReports
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int|float|null>>  $rows
     * @param  list<string>  $summaryLines
     */
    protected function pdfTableDownload(
        Request $request,
        HelpdeskPdfReportService $pdf,
        string $title,
        array $headings,
        array $rows,
        string $filename,
        array $summaryLines = [],
    ): Response {
        $html = view('pdf.report-table', [
            'title' => $title,
            'headings' => $headings,
            'rows' => $rows,
            'summary_lines' => $summaryLines,
        ])->render();

        return $pdf->inline($html, $filename, [
            'title' => $title,
            'generated_by' => $request->user()?->name,
            'document_url' => config('app.url').'/reports',
        ]);
    }
}
