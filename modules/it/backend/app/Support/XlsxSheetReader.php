<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Lightweight OOXML reader for the Risk Register sheet (no PhpSpreadsheet).
 */
final class XlsxSheetReader
{
    /**
     * @return list<array{row:int,cells:array<int,string>}>
     */
    public function readSheetRows(string $path, string $sheetNameContains = 'Risk Register'): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Excel file not found: '.$path);
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open xlsx: '.$path);
        }

        try {
            $shared = $this->parseSharedStrings($zip);
            $sheetPath = $this->resolveSheetPath($zip, $sheetNameContains);
            $xml = $zip->getFromName($sheetPath);
            if ($xml === false) {
                throw new RuntimeException('Sheet XML missing: '.$sheetPath);
            }

            return $this->parseSheetXml($xml, $shared);
        } finally {
            $zip->close();
        }
    }

    /**
     * Read the first worksheet in the workbook (SAP exports typically use Sheet1).
     *
     * @return list<array{row:int,cells:array<int,string>}>
     */
    public function readFirstSheetRows(string $path): array
    {
        return $this->readSheetRows($path, '');
    }

    /**
     * @return list<string>
     */
    private function parseSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $ss = [];
        $root = new \SimpleXMLElement($xml);
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $root->registerXPathNamespace('m', $ns);
        foreach ($root->xpath('//m:si') ?: [] as $si) {
            $texts = [];
            // Child xpath needs the namespace re-registered on the element.
            $si->registerXPathNamespace('m', $ns);
            foreach ($si->xpath('.//m:t') ?: [] as $t) {
                $texts[] = (string) $t;
            }
            if ($texts === [] && isset($si->t)) {
                $texts[] = (string) $si->t;
            }
            $ss[] = implode('', $texts);
        }

        return $ss;
    }

    private function resolveSheetPath(ZipArchive $zip, string $sheetNameContains): string
    {
        $wbXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wbXml === false || $relsXml === false) {
            throw new RuntimeException('Invalid xlsx workbook structure.');
        }

        $wb = new \SimpleXMLElement($wbXml);
        $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $rid = null;
        $chosenName = null;
        $sheets = $wb->xpath('//m:sheets/m:sheet') ?: [];

        // Empty filter => first worksheet (SAP Sheet1 exports).
        if ($sheetNameContains === '') {
            $sheet = $sheets[0] ?? null;
            if ($sheet === null) {
                throw new RuntimeException('Workbook has no worksheets.');
            }
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        } else {
            foreach ($sheets as $sheet) {
                $name = (string) ($sheet['name'] ?? '');
                if (stripos($name, $sheetNameContains) === false) {
                    continue;
                }
                // Prefer exact "Risk Register" over "Risk Register Dashboard" etc.
                if ($chosenName !== null && strcasecmp($chosenName, $sheetNameContains) === 0) {
                    continue;
                }
                if ($chosenName !== null
                    && stripos($name, 'Dashboard') !== false
                    && stripos($chosenName, 'Dashboard') === false) {
                    continue;
                }
                $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $chosenName = $name;
                if (strcasecmp($name, $sheetNameContains) === 0) {
                    break;
                }
            }
        }

        if ($rid === null || $rid === '') {
            throw new RuntimeException('Sheet not found containing: '.$sheetNameContains);
        }

        $rels = new \SimpleXMLElement($relsXml);
        $target = null;
        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $rid) {
                $target = (string) $rel['Target'];
                break;
            }
        }
        if ($target === null || $target === '') {
            throw new RuntimeException('Sheet relationship missing for '.$rid);
        }

        $target = ltrim($target, '/');
        if (! str_starts_with($target, 'xl/')) {
            $target = 'xl/'.$target;
        }

        return $target;
    }

    /**
     * @param  list<string>  $shared
     * @return list<array{row:int,cells:array<int,string>}>
     */
    private function parseSheetXml(string $xml, array $shared): array
    {
        $root = new \SimpleXMLElement($xml);
        $root->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $byRow = [];
        foreach ($root->xpath('//m:sheetData/m:row') ?: [] as $rowEl) {
            $rowNum = (int) ($rowEl['r'] ?? 0);
            if ($rowNum < 1) {
                continue;
            }
            $cells = [];
            foreach ($rowEl->c as $c) {
                $ref = (string) ($c['r'] ?? '');
                if ($ref === '' || ! preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
                    continue;
                }
                $col = $this->columnIndex($m[1]);
                $type = (string) ($c['t'] ?? '');
                $v = isset($c->v) ? (string) $c->v : '';
                if ($type === 's' && $v !== '') {
                    $idx = (int) $v;
                    $val = $shared[$idx] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = isset($c->is->t) ? (string) $c->is->t : '';
                } else {
                    $val = $v;
                }
                $cells[$col] = $val;
            }
            if ($cells !== []) {
                $byRow[] = ['row' => $rowNum, 'cells' => $cells];
            }
        }

        return $byRow;
    }

    private function columnIndex(string $letters): int
    {
        $n = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }

        return $n;
    }
}
