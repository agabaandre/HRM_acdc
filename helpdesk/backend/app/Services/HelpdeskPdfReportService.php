<?php

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Africa CDC branded PDF reports (mirrors APM memo PDF header/footer).
 */
class HelpdeskPdfReportService
{
    /**
     * @param  array{document_url?:string,title?:string,generated_by?:?string}  $options
     */
    public function make(string $htmlBody, array $options = []): Mpdf
    {
        $arialFontDir = public_path('assets/fonts/arial');
        $haveArial = is_dir($arialFontDir) && is_file($arialFontDir.DIRECTORY_SEPARATOR.'ARIAL.TTF');

        $defaultConfig = (new \Mpdf\Config\ConfigVariables)->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables)->getDefaults()['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => storage_path('app/mpdf_tmp'),
            'fontDir' => $haveArial ? array_merge($fontDirs, [$arialFontDir]) : $fontDirs,
            'fontdata' => $haveArial
                ? $fontData + [
                    'arial' => [
                        'R' => 'ARIAL.TTF',
                        'B' => 'ARIALBD.TTF',
                        'I' => 'ARIALI.TTF',
                        'BI' => 'ARIALBI.TTF',
                    ],
                ]
                : $fontData,
            'default_font' => $haveArial ? 'arial' : 'freesans',
            'default_font_size' => 10,
        ]);

        $mpdf->SetMargins(10, 10, 35);
        $pdfContentFooterGapMm = 4 * 25.4 / 96;
        $mpdf->SetAutoPageBreak(true, 30 + $pdfContentFooterGapMm);

        $logoPath = public_path('assets/images/logo.png');
        $logoSrc = is_file($logoPath) ? $logoPath : '';
        $logoHtml = $logoSrc !== ''
            ? '<img src="'.$logoSrc.'" alt="Africa CDC Logo" style="height: 80px;">'
            : '<strong style="font-size:18px;color:#911C39;">Africa CDC</strong>';

        $header = '<div style="width: 100%; text-align: center; padding-bottom: 5px;">
            <div style="width: 100%; padding-bottom: 5px;">
                <div style="width: 100%; padding: 10px 0;">
                    <div style="display:flex; justify-content: space-between; align-items: center;">
                        <div style="width: 60%; text-align: left; float:left;">
                            '.$logoHtml.'
                        </div>
                        <div style="text-align: right; width: 35%; float:right; margin-top:10px;">
                            <span style="font-size: 14px; color: #911C39;">Safeguarding Africa\'s Health</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        $mpdf->SetHTMLHeader($header);

        $documentUrl = (string) ($options['document_url'] ?? config('app.url', ''));
        $generatedBy = trim((string) ($options['generated_by'] ?? ''));
        $footerMetaAndQrHtml = $this->footerMetaHtml($documentUrl, $generatedBy);

        $footer = ' <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 8pt; color: #911C39; border:none; margin-top: 4px; border-collapse: collapse;">
            <tr>
                <td align="left" valign="top" style="border: none; width: 50%; padding: 0 18px 0 0;">
                    Africa CDC Headquarters, Ring Road, 16/17,<br>
                    Haile Garment Lafto Square, Nifas Silk-Lafto Sub City,<br>
                    P.O Box: 200050 Addis Ababa<br>
                    Email: <a href="mailto:registry@africacdc.org" style="color: #911C39;">registry@africacdc.org</a>
                </td>
                <td align="right" valign="top" style="border: none; padding: 0 0 0 12px;">'.$footerMetaAndQrHtml.'</td>
            </tr>
        </table><p style="text-align:right; font-size: 8pt;">Page {PAGENO} of {nbpg}</p>';
        $mpdf->SetHTMLFooter($footer);

        try {
            $css = '';
            if (preg_match_all('#<style\b[^>]*>(.*?)</style>#is', $htmlBody, $cssMatches)) {
                $css = trim(implode("\n", $cssMatches[1] ?? []));
                $htmlBody = (string) preg_replace('#<style\b[^>]*>.*?</style>#is', '', $htmlBody);
            }
            if ($css !== '') {
                $mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
            }
            $mpdf->WriteHTML($htmlBody, HTMLParserMode::HTML_BODY);
        } catch (Throwable $e) {
            Log::error('Helpdesk mPDF WriteHTML failed', ['message' => $e->getMessage()]);
            throw $e;
        }

        if (! empty($options['title'])) {
            $mpdf->SetTitle((string) $options['title']);
        }

        return $mpdf;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download(string $htmlBody, string $filename, array $options = [])
    {
        $mpdf = $this->make($htmlBody, $options);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function inline(string $htmlBody, string $filename, array $options = [])
    {
        $mpdf = $this->make($htmlBody, $options);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function footerMetaHtml(string $documentUrl, string $generatedBy): string
    {
        $qrHtml = '';
        if ($documentUrl !== '' && class_exists(QrCode::class)) {
            try {
                $qr = new QrCode(
                    data: $documentUrl,
                    errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Medium,
                    size: 120,
                    margin: 2,
                );
                $writer = new PngWriter;
                $result = $writer->write($qr);
                $dataUri = htmlspecialchars($result->getDataUri(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $qrHtml = '<img src="'.$dataUri.'" alt="QR" style="width:15.4mm;height:15.4mm;display:block;"><br>';
            } catch (Throwable) {
                $qrHtml = '';
            }
        }

        $by = $generatedBy !== '' ? e($generatedBy) : 'Service Desk';

        return $qrHtml
            .'Source: Africa CDC Central Business Platform<br>'
            .'Generated: '.now()->format('Y-m-d H:i').'<br>'
            .'By: '.$by;
    }
}
