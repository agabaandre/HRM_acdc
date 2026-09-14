<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Services\PdfService;
use Modules\Payroll\Jobs\SendPayslipEmailJob;
use Modules\Payroll\Models\PayrollPayslip;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\PayrollRunLine;

class PayslipService
{
    public function __construct(private PdfService $pdf) {}

    public function generateForRun(PayrollRun $run, bool $queueEmails = true): void
    {
        $period = $run->period()->firstOrFail();
        $dir = 'payroll/payslips/'.$period->year.'/'.$period->month;
        Storage::disk('local')->makeDirectory($dir);

        foreach ($run->lines()->with('items.wageType')->get() as $line) {
            $ytd = $this->ytdForStaff((int) $line->staff_id, (int) $period->year, (int) $period->month, $line);
            $html = $this->renderHtml($line, $period->label, $ytd);
            $filename = sprintf('payslip-%d-%s.pdf', $line->staff_id, $period->label);
            $relative = $dir.'/'.$filename;

            $mpdf = $this->pdf->make($html, [
                'title' => 'Payslip '.$period->label,
            ]);
            Storage::disk('local')->put($relative, $mpdf->Output($filename, 'S'));

            $payslip = PayrollPayslip::query()->updateOrCreate(
                ['run_line_id' => $line->id],
                [
                    'staff_id' => $line->staff_id,
                    'period_id' => $period->id,
                    'run_id' => $run->id,
                    'pdf_path' => $relative,
                    'ytd' => $ytd,
                    'generated_at' => now(),
                ],
            );

            if ($queueEmails) {
                SendPayslipEmailJob::dispatch((int) $payslip->id);
            }
        }
    }

    /**
     * @return array{gross: float, tax: float, deductions: float, net: float, benefits: float}
     */
    public function ytdForStaff(int $staffId, int $year, int $month, PayrollRunLine $current): array
    {
        $prior = DB::table('payroll_payslips as p')
            ->join('payroll_run_lines as l', 'l.id', '=', 'p.run_line_id')
            ->join('payroll_periods as per', 'per.id', '=', 'p.period_id')
            ->where('p.staff_id', $staffId)
            ->where('per.year', $year)
            ->where(function ($q) use ($month): void {
                $q->where('per.month', '<', $month);
            })
            ->selectRaw('COALESCE(SUM(l.gross),0) as gross, COALESCE(SUM(l.tax),0) as tax, COALESCE(SUM(l.deductions),0) as deductions, COALESCE(SUM(l.net),0) as net, COALESCE(SUM(l.benefits),0) as benefits')
            ->first();

        return [
            'gross' => round((float) ($prior->gross ?? 0) + (float) $current->gross, 2),
            'tax' => round((float) ($prior->tax ?? 0) + (float) $current->tax, 2),
            'deductions' => round((float) ($prior->deductions ?? 0) + (float) $current->deductions, 2),
            'net' => round((float) ($prior->net ?? 0) + (float) $current->net, 2),
            'benefits' => round((float) ($prior->benefits ?? 0) + (float) $current->benefits, 2),
        ];
    }

    /**
     * @param  array{gross: float, tax: float, deductions: float, net: float, benefits: float}  $ytd
     */
    public function renderHtml(PayrollRunLine $line, string $periodLabel, array $ytd): string
    {
        $rows = '';
        foreach ($line->items as $item) {
            $name = e($item->wageType?->name ?? ($item->meta['code'] ?? $item->category));
            $cat = e($item->category);
            $amt = number_format((float) $item->amount, 2);
            $rows .= "<tr><td>{$name}</td><td>{$cat}</td><td style=\"text-align:right\">{$amt}</td></tr>";
        }

        $staff = (int) $line->staff_id;
        $currency = e($line->currency);
        $net = number_format((float) $line->net, 2);
        $gross = number_format((float) $line->gross, 2);
        $tax = number_format((float) $line->tax, 2);
        $ytdNet = number_format($ytd['net'], 2);
        $ytdGross = number_format($ytd['gross'], 2);
        $ytdTax = number_format($ytd['tax'], 2);

        return <<<HTML
        <h2 style="color:#119a48;margin:0 0 8px;">Payslip — {$periodLabel}</h2>
        <p style="margin:0 0 12px;">Staff ID: <strong>{$staff}</strong> · Currency: <strong>{$currency}</strong></p>
        <table width="100%" cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-size:10pt;">
            <thead>
                <tr style="background:#f1f5f9;">
                    <th align="left">Description</th>
                    <th align="left">Category</th>
                    <th align="right">Amount</th>
                </tr>
            </thead>
            <tbody>{$rows}</tbody>
        </table>
        <table width="100%" style="margin-top:16px;font-size:10pt;">
            <tr><td>Gross</td><td align="right"><strong>{$gross}</strong></td></tr>
            <tr><td>Tax</td><td align="right">{$tax}</td></tr>
            <tr><td>Net pay</td><td align="right" style="color:#119a48;font-size:12pt;"><strong>{$net}</strong></td></tr>
        </table>
        <h3 style="margin-top:18px;color:#334155;">Year to date</h3>
        <table width="100%" style="font-size:10pt;">
            <tr><td>YTD Gross</td><td align="right">{$ytdGross}</td></tr>
            <tr><td>YTD Tax</td><td align="right">{$ytdTax}</td></tr>
            <tr><td>YTD Net</td><td align="right"><strong>{$ytdNet}</strong></td></tr>
        </table>
        HTML;
    }

    public function pdfResponse(PayrollPayslip $payslip)
    {
        if ($payslip->pdf_path && Storage::disk('local')->exists($payslip->pdf_path)) {
            $binary = Storage::disk('local')->get($payslip->pdf_path);
            $name = basename($payslip->pdf_path);

            return response($binary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$name.'"',
            ]);
        }

        $line = $payslip->line()->with('items.wageType')->firstOrFail();
        $period = $payslip->period()->firstOrFail();
        $ytd = $payslip->ytd ?? $this->ytdForStaff((int) $payslip->staff_id, (int) $period->year, (int) $period->month, $line);

        return $this->pdf->inline(
            $this->renderHtml($line, $period->label, $ytd),
            'payslip-'.$payslip->staff_id.'.pdf',
            ['title' => 'Payslip '.$period->label],
        );
    }
}
