<?php

namespace Tests\Support;

use ZipArchive;

trait BuildsMinimalXlsx
{
    /**
     * @param  list<list<string>>  $rows
     */
    protected function makeMinimalXlsx(array $rows, string $sheetName = 'Sheet1'): string
    {
        $path = sys_get_temp_dir().'/fin_xlsx_fixture_'.uniqid('', true).'.xlsx';
        $shared = [];
        $sharedIndex = [];
        $sheetRowsXml = '';
        foreach ($rows as $rIdx => $cols) {
            $rowNum = $rIdx + 1;
            $cellsXml = '';
            foreach ($cols as $cIdx => $value) {
                $colLetters = $this->xlsxColLetters($cIdx + 1);
                $ref = $colLetters.$rowNum;
                if (! isset($sharedIndex[$value])) {
                    $sharedIndex[$value] = count($shared);
                    $shared[] = $value;
                }
                $si = $sharedIndex[$value];
                $cellsXml .= '<c r="'.$ref.'" t="s"><v>'.$si.'</v></c>';
            }
            $sheetRowsXml .= '<row r="'.$rowNum.'">'.$cellsXml.'</row>';
        }

        $sharedXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($shared).'" uniqueCount="'.count($shared).'">';
        foreach ($shared as $s) {
            $sharedXml .= '<si><t>'.htmlspecialchars($s, ENT_XML1).'</t></si>';
        }
        $sharedXml .= '</sst>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$sheetRowsXml.'</sheetData></worksheet>';

        $safeName = htmlspecialchars($sheetName, ENT_XML1);
        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$safeName.'" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>';

        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rootRels);
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $sharedXml);
        $zip->close();

        return $path;
    }

    private function xlsxColLetters(int $index): string
    {
        $s = '';
        while ($index > 0) {
            $index--;
            $s = chr(65 + ($index % 26)).$s;
            $index = intdiv($index, 26);
        }

        return $s;
    }
}
