<?php

namespace Modules\Core\Services;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

/**
 * Africa CDC branded PDF (mirrors APM / Helpdesk mPDF printouts).
 */
class PdfService
{
    /**
     * @param  array{
     *     document_url?: string,
     *     title?: string,
     *     generated_by?: ?string,
     *     landscape?: bool,
     *     header?: bool,
     *     watermark_text?: string,
     *     watermark_alpha?: float
     * }  $options
     */
    public function make(string $htmlBody, array $options = []): Mpdf
    {
        @mkdir(storage_path('app/mpdf_tmp'), 0775, true);

        $arialFontDir = $this->resolveArialFontDir();
        $haveArial = $arialFontDir !== null;

        $defaultConfig = (new \Mpdf\Config\ConfigVariables)->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables)->getDefaults()['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => ! empty($options['landscape']) ? 'A4-L' : 'A4',
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

        $useHeader = ($options['header'] ?? true) !== false;
        $mpdf->SetMargins(10, 10, $useHeader ? 32 : 12);
        $mpdf->SetAutoPageBreak(true, 36);

        if ($useHeader) {
            $logoPath = $this->resolveLogoPath();
            $logoHtml = $logoPath !== null
                ? '<img src="'.$logoPath.'" alt="Africa CDC Logo" style="height: 64px;">'
                : '<strong style="font-size:18px;color:#911C39;">Africa CDC</strong>';

            $title = htmlspecialchars((string) ($options['title'] ?? 'Staff Portal Report'), ENT_QUOTES, 'UTF-8');
            $header = '<div style="width:100%;padding-bottom:6px;border-bottom:2px solid #119a48;">
                <table width="100%" cellpadding="0" cellspacing="0"><tr>
                    <td width="55%" align="left">'.$logoHtml.'</td>
                    <td width="45%" align="right" style="color:#911C39;font-size:12px;">
                        Safeguarding Africa\'s Health<br><span style="color:#2c3e50;font-size:11px;">'.$title.'</span>
                    </td>
                </tr></table>
            </div>';
            $mpdf->SetHTMLHeader($header);
        }

        $mpdf->SetHTMLFooter($this->buildFooter($options));

        $watermarkText = trim((string) ($options['watermark_text'] ?? ''));
        if ($watermarkText !== '') {
            $alpha = isset($options['watermark_alpha']) ? (float) $options['watermark_alpha'] : 0.14;
            $mpdf->SetWatermarkText($watermarkText, $alpha);
            $mpdf->showWatermarkText = true;
        }

        $mpdf->WriteHTML($htmlBody);

        return $mpdf;
    }

    /**
     * @param  array{
     *     document_url?: string,
     *     title?: string,
     *     generated_by?: ?string,
     *     landscape?: bool,
     *     header?: bool,
     *     watermark_text?: string,
     *     watermark_alpha?: float
     * }  $options
     */
    public function inline(string $htmlBody, string $filename, array $options = []): Response
    {
        $mpdf = $this->make($htmlBody, $options);
        $binary = $mpdf->Output($filename, 'S');

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * APM-style footer: HQ address + QR (document_url) + Source / Generated / By.
     *
     * @param  array{document_url?: string, generated_by?: ?string}  $options
     */
    protected function buildFooter(array $options): string
    {
        $documentUrl = trim((string) ($options['document_url'] ?? ''));
        if ($documentUrl === '') {
            try {
                $documentUrl = (string) (request()?->fullUrl() ?? '');
            } catch (\Throwable) {
                $documentUrl = '';
            }
        }
        if ($documentUrl === '') {
            $documentUrl = (string) config('app.url');
        }

        $generatedBy = trim((string) ($options['generated_by'] ?? ''));
        if ($generatedBy === '') {
            $generatedBy = trim((string) (session('user.name') ?? session('user.full_name') ?? ''));
        }

        $qrHtml = $this->qrImageHtml($documentUrl);
        if ($qrHtml === '') {
            $qrHtml = '<span style="word-break: break-all; font-size: 6pt; display: inline-block; max-width: 18mm;">'
                .htmlspecialchars($documentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                .'</span>';
        }

        $metaHtml = 'Source: Africa CDC  Central Business Platform<br>'
            .'Generated on: '.now()->timezone(config('app.timezone', 'Africa/Nairobi'))->format('d F, Y h:i A').'<br>'
            .'By: '.htmlspecialchars($generatedBy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $metaAndQr = '<div style="display: inline-block; text-align: left;">'
            .'<table cellpadding="0" cellspacing="0" style="border-collapse: collapse; border: none; width: auto;">'
            .'<tr>'
            .'<td valign="top" align="left" style="border: none; width: 18mm; padding: 0 12px 0 0;">'.$qrHtml.'</td>'
            .'<td valign="top" align="left" style="border: none; padding: 0;">'.$metaHtml.'</td>'
            .'</tr>'
            .'</table>'
            .'</div>';

        return '<table width="100%" cellpadding="0" cellspacing="0" style="font-size: 8pt; color: #911C39; border:none; margin-top: 4px; border-collapse: collapse;">
            <tr>
                <td align="left" valign="top" style="border: none; width: 50%; padding: 0 18px 0 0;">
                    Africa CDC Headquarters, Ring Road, 16/17,<br>
                    Haile Garment Lafto Square, Nifas Silk-Lafto Sub City,<br>
                    P.O Box: 200050 Addis Ababa<br>
                    Email: <a href="mailto:registry@africacdc.org" style="color: #911C39;">registry@africacdc.org</a>
                </td>
                <td align="right" valign="top" style="border: none; padding: 0 0 0 12px;">'.$metaAndQr.'</td>
            </tr>
        </table><p style="text-align:right; font-size: 8pt;">Page {PAGENO} of {nbpg}</p>';
    }

    protected function qrImageHtml(string $data): string
    {
        try {
            $qrCode = new QrCode(
                data: $data,
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 121,
                margin: 2,
            );
            $dataUri = (new PngWriter)->write($qrCode)->getDataUri();

            return '<img src="'.htmlspecialchars($dataUri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" alt="" style="width: 15.4mm; height: 15.4mm; display: block;" />';
        } catch (\Throwable $e) {
            Log::warning('mPDF footer QR code generation failed', [
                'message' => $e->getMessage(),
            ]);

            return '';
        }
    }

    protected function resolveArialFontDir(): ?string
    {
        $candidates = [
            public_path('assets/fonts/arial'),
            base_path('../helpdesk/backend/public/assets/fonts/arial'),
            '/opt/homebrew/var/www/staff/helpdesk/backend/public/assets/fonts/arial',
        ];
        foreach ($candidates as $dir) {
            if (is_dir($dir) && is_file($dir.DIRECTORY_SEPARATOR.'ARIAL.TTF')) {
                return $dir;
            }
        }

        return null;
    }

    protected function resolveLogoPath(): ?string
    {
        $candidates = [
            public_path('assets/images/logo.png'),
            public_path('assets/images/AU_CDC_Logo-800.png'),
            '/opt/homebrew/var/www/staff/apm/public/assets/images/logo.png',
            '/opt/homebrew/var/www/staff/assets/images/AU_CDC_Logo-800.png',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
