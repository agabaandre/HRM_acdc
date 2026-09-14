<?php

namespace App\Jobs;

use App\Services\AgentMonthlyReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeOldAgentReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('helpdesk');
    }

    public function handle(AgentMonthlyReportService $service): void
    {
        $service->purgeExpiredReports();
    }
}
