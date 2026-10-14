<?php

namespace App\Support;

use RuntimeException;

final class SapExportParser
{
    public function __construct(
        private readonly ?XlsxSheetReader $reader = null,
    ) {}

    /**
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   totals: list<array{
     *     fund_center: string,
     *     budget_code: string|null,
     *     fund_center_text: string|null,
     *     total_released_budget: float,
     *     released_budget_balance: float,
     *     commitment: float,
     *     actual_expenditure: float,
     *     raw: array<string, mixed>
     *   }>
     * }
     */
    public function parse(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'], true) || $this->looksLikeCsv($path)) {
            $raw = $this->readCsvRows($path);
        } else {
            $reader = $this->reader ?? new XlsxSheetReader;
            $raw = $reader->readFirstSheetRows($path);
        }
        if ($raw === []) {
            throw new RuntimeException('SAP export is empty.');
        }

        $headerEntry = $raw[0];
        $headerCells = $headerEntry['cells'] ?? [];
        $headers = [];
        foreach ($headerCells as $i => $label) {
            $headers[(int) $i] = $this->normalizeHeader((string) $label);
        }

        $required = [
            'fund center',
            'gl account',
            'total released budget',
            'released budget balance',
        ];
        foreach ($required as $h) {
            if (! in_array($h, $headers, true)) {
                throw new RuntimeException('Missing required header: '.$h);
            }
        }

        $rows = [];
        // Last Total row per fund center wins (SAP exports often repeat a code).
        $totalsByFundCenter = [];
        /** @var array<string, array<string, true>> */
        $budgetCodesByFc = [];
        /** @var array<string, string> */
        $textByFc = [];

        foreach ($raw as $idx => $entry) {
            if ($idx === 0) {
                continue;
            }
            $cells = $entry['cells'] ?? [];
            $assoc = [];
            foreach ($headers as $i => $key) {
                $assoc[$key] = $cells[$i] ?? null;
            }
            $rows[] = $assoc;

            $gl = strtolower(trim((string) ($assoc['gl account'] ?? '')));
            $fc = trim((string) ($assoc['fund center'] ?? ''));
            $fund = trim((string) ($assoc['fund'] ?? ''));
            $text = trim((string) ($assoc['fund center text'] ?? ''));

            if ($fc !== '' && $fund !== '') {
                $budgetCodesByFc[$fc][$fund] = true;
            }
            if ($fc !== '' && $text !== '') {
                $textByFc[$fc] = $text;
            }

            if ($fc === '' || $gl !== 'total') {
                continue;
            }

            $totalsByFundCenter[$fc] = [
                'fund_center' => $fc,
                'budget_code' => null,
                'fund_center_text' => $textByFc[$fc] ?? ($text !== '' ? $text : null),
                'total_released_budget' => $this->decimal($assoc['total released budget'] ?? 0),
                'released_budget_balance' => $this->decimal($assoc['released budget balance'] ?? 0),
                'commitment' => $this->decimal($assoc['commitment'] ?? 0),
                'actual_expenditure' => $this->decimal($assoc['actual expenditure'] ?? 0),
                'raw' => $assoc,
            ];
        }

        foreach ($totalsByFundCenter as $fc => &$total) {
            $codes = array_keys($budgetCodesByFc[$fc] ?? []);
            sort($codes);
            $total['budget_code'] = $codes !== [] ? implode(', ', $codes) : null;
            if (($total['fund_center_text'] ?? null) === null && isset($textByFc[$fc])) {
                $total['fund_center_text'] = $textByFc[$fc];
            }
        }
        unset($total);

        return ['rows' => $rows, 'totals' => array_values($totalsByFundCenter)];
    }

    private function normalizeHeader(string $header): string
    {
        $header = str_replace("\xEF\xBB\xBF", '', $header);
        $header = trim($header);
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;
        $header = strtolower($header);

        return match ($header) {
            'g/l account', 'gl account.', 'g l account' => 'gl account',
            'fund center description', 'fund center desc.', 'fund center desc' => 'fund center text',
            'commitment amount', 'commitments' => 'commitment',
            'cy expediture', 'cy expenditure' => 'actual expenditure',
            default => $header,
        };
    }

    private function looksLikeCsv(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $line = fgets($fh) ?: '';
        fclose($fh);

        return str_contains($line, ',') && (stripos($line, 'Fund Center') !== false || stripos($line, 'G/L') !== false);
    }

    /**
     * @return list<array{row:int,cells:array<int,string>}>
     */
    private function readCsvRows(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('CSV file not found: '.$path);
        }
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new RuntimeException('Unable to open CSV: '.$path);
        }
        $out = [];
        $rowNum = 0;
        try {
            while (($cells = fgetcsv($fh)) !== false) {
                if ($cells === [null] || $cells === false) {
                    continue;
                }
                $rowNum++;
                $normalized = [];
                foreach ($cells as $i => $cell) {
                    $normalized[(int) $i] = (string) $cell;
                }
                $out[] = ['row' => $rowNum, 'cells' => $normalized];
            }
        } finally {
            fclose($fh);
        }

        return $out;
    }

    private function decimal(mixed $value): float
    {
        $clean = str_replace([',', ' '], '', (string) $value);
        if ($clean === '' || ! is_numeric($clean)) {
            return 0.0;
        }

        return (float) $clean;
    }
}
