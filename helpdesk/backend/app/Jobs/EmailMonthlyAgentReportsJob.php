<?php

namespace App\Jobs;

use App\Mail\AgentMonthlyReportMail;
use App\Models\HelpdeskAgentMonthlyReport;
use App\Models\HelpdeskSetting;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailMonthlyAgentReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $year = null,
        public ?int $month = null,
    ) {}

    public function handle(): void
    {
        if (! HelpdeskSetting::agentMonthlyReportEnabled() || ! HelpdeskSetting::agentMonthlyReportEmailEnabled()) {
            return;
        }

        $period = $this->resolvePeriod();
        $reports = HelpdeskAgentMonthlyReport::query()
            ->with('user')
            ->where('period_year', $period->year)
            ->where('period_month', $period->month)
            ->whereNull('emailed_at')
            ->get();

        foreach ($reports as $report) {
            $email = $report->user?->email;
            if (! $email) {
                continue;
            }

            try {
                Mail::to($email)->send(new AgentMonthlyReportMail($report));
                $report->emailed_at = now();
                $report->save();
            } catch (\Throwable $e) {
                Log::error('Failed to email monthly agent report', [
                    'report_id' => $report->id,
                    'user_id' => $report->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function resolvePeriod(): Carbon
    {
        if ($this->year !== null && $this->month !== null) {
            return Carbon::create($this->year, $this->month, 1);
        }

        return now()->subMonth()->startOfMonth();
    }
}
