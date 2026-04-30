<?php

namespace App\Console\Commands;

use App\Models\ServiceRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixPrematureApprovedServiceRequestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service-requests:fix-premature-approved';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix current-quarter service requests prematurely approved at level 31';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $start = $now->copy()->startOfQuarter()->startOfDay();
        $end = $now->copy()->endOfQuarter()->endOfDay();

        $query = ServiceRequest::query()
            ->where('approval_level', 31)
            ->where('overall_status', 'approved')
            ->whereBetween('created_at', [$start, $end]);

        $ids = $query->pluck('id')->all();

        $this->info(sprintf(
            'Quarter window: %s to %s',
            $start->toDateTimeString(),
            $end->toDateTimeString()
        ));
        $this->info('Matching records: ' . count($ids));

        if (empty($ids)) {
            return self::SUCCESS;
        }

        $updated = ServiceRequest::query()
            ->whereIn('id', $ids)
            ->update([
                'overall_status' => 'pending',
                'approval_level' => 32,
                'next_approval_level' => 32,
                'updated_at' => now(),
            ]);

        $this->info("Updated {$updated} service request(s) to pending at level 32.");

        return self::SUCCESS;
    }
}

