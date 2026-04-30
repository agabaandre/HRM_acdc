<?php

namespace App\Console\Commands;

use App\Models\ServiceRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ListPrematureApprovedServiceRequestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service-requests:list-premature-approved';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List current-quarter service requests approved at level 31 before level 32';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $start = $now->copy()->startOfQuarter()->startOfDay();
        $end = $now->copy()->endOfQuarter()->endOfDay();

        $rows = ServiceRequest::query()
            ->select(['id', 'request_number', 'staff_id', 'division_id', 'approval_level', 'next_approval_level', 'overall_status', 'created_at'])
            ->where('approval_level', 31)
            ->where('overall_status', 'approved')
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->info(sprintf(
            'Quarter window: %s to %s',
            $start->toDateTimeString(),
            $end->toDateTimeString()
        ));
        $this->info('Found ' . $rows->count() . ' service request(s).');

        if ($rows->isEmpty()) {
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Request #', 'Staff', 'Division', 'Approval Level', 'Next Level', 'Status', 'Created At'],
            $rows->map(fn ($r) => [
                $r->id,
                $r->request_number,
                $r->staff_id,
                $r->division_id,
                $r->approval_level,
                $r->next_approval_level,
                $r->overall_status,
                optional($r->created_at)->toDateTimeString(),
            ])->all()
        );

        return self::SUCCESS;
    }
}

