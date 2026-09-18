<?php

namespace Modules\Payroll\Jobs;

use App\Services\PortalMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Payroll\Models\PayrollPayslip;

class SendPayslipEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $payslipId) {}

    public function handle(PortalMailer $mailer): void
    {
        $payslip = PayrollPayslip::query()->with('period')->find($this->payslipId);
        if (! $payslip) {
            return;
        }

        $staff = DB::table('staff')
            ->where('staff_id', $payslip->staff_id)
            ->select([
                'work_email',
                'fname',
                'lname',
                DB::raw("TRIM(CONCAT(COALESCE(fname,''), ' ', COALESCE(lname,''))) as staff_name"),
            ])
            ->first();

        $email = trim((string) ($staff->work_email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Payslip email skipped: missing work_email', [
                'payslip_id' => $payslip->id,
                'staff_id' => $payslip->staff_id,
            ]);

            return;
        }

        if (! $payslip->pdf_path || ! Storage::disk('local')->exists($payslip->pdf_path)) {
            Log::warning('Payslip email skipped: PDF missing', ['payslip_id' => $payslip->id]);

            return;
        }

        $periodLabel = $payslip->period?->label ?? 'pay period';
        $name = trim((string) ($staff->staff_name ?? '')) ?: 'colleague';
        $pdf = Storage::disk('local')->get($payslip->pdf_path);
        $filename = basename((string) $payslip->pdf_path);

        $html = '<p>Dear '.e($name).',</p>'
            .'<p>Your payslip for <strong>'.e($periodLabel).'</strong> is attached.</p>'
            .'<p>This message was sent automatically by Staff Portal Payroll.</p>';

        $mailer->send($email, 'Payslip — '.$periodLabel, $html, [
            [
                'name' => $filename,
                'content' => $pdf,
                'content_type' => 'application/pdf',
            ],
        ]);

        $payslip->update(['emailed_at' => now()]);
    }
}
