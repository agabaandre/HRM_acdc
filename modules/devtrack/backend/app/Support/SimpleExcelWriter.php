<?php

namespace App\Support;

/**
 * Excel-compatible workbook writer (SpreadsheetML XML).
 * Opens in Microsoft Excel / LibreOffice without ZipArchive or PhpSpreadsheet.
 */
class SimpleExcelWriter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|bool|null>>  $rows
     */
    public static function toXls(array $headers, iterable $rows, string $sheetName = 'Sheet1'): string
    {
        $sheetName = self::sanitizeSheetName($sheetName);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<?mso-application progid="Excel.Sheet"?>'."\n"
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:o="urn:schemas-microsoft-com:office:office"'
            .' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            .' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:html="http://www.w3.org/TR/REC-html40">'
            .'<Styles>'
            .'<Style ss:ID="Header"><Font ss:Bold="1"/></Style>'
            .'</Styles>'
            .'<Worksheet ss:Name="'.self::xml($sheetName).'"><Table>';

        $xml .= '<Row>';
        foreach ($headers as $header) {
            $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">'.self::xml((string) $header).'</Data></Cell>';
        }
        $xml .= '</Row>';

        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach (array_values($row) as $value) {
                if (is_int($value) || is_float($value)) {
                    $xml .= '<Cell><Data ss:Type="Number">'.$value.'</Data></Cell>';
                } elseif (is_bool($value)) {
                    $xml .= '<Cell><Data ss:Type="String">'.self::xml($value ? 'Yes' : 'No').'</Data></Cell>';
                } else {
                    $text = $value === null ? '' : (string) $value;
                    $xml .= '<Cell><Data ss:Type="String">'.self::xml($text).'</Data></Cell>';
                }
            }
            $xml .= '</Row>';
        }

        $xml .= '</Table></Worksheet></Workbook>';

        return $xml;
    }

    private static function sanitizeSheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/', '', $name) ?? 'Sheet1';
        $name = trim($name);
        if ($name === '') {
            return 'Sheet1';
        }

        return mb_substr($name, 0, 31);
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
