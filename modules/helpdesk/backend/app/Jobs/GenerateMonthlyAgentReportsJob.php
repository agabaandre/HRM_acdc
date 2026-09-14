<?php

namespace App\Jobs;

use App\Models\HelpdeskSetting;
use App\Services\AgentMonthlyReportService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyAgentReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $year = null,
        public ?int $month = null,
        public bool $force = false,
    ) {
        $this->onQueue('helpdesk');
    }

    public function handle(AgentMonthlyReportService $service): void
    {
        if (! HelpdeskSetting::agentMonthlyReportEnabled()) {
            return;
        }

        $period = $this->resolvePeriod();
        $year = $period->year;
        $month = $period->month;

        Log::info('Generating monthly agent reports', ['year' => $year, 'month' => $month]);

        foreach ($service->eligibleAgents() as $agent) {
            try {
                $service->generateForAgent($agent, $year, $month, $this->force);
            } catch (\Throwable $e) {
                Log::error('Failed monthly agent report', [
                    'user_id' => $agent->id,
                    'year' => $year,
                    'month' => $month,
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
