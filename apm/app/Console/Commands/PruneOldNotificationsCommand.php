<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneOldNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune-old
                            {--months=4 : Delete rows with created_at older than this many months}';

    protected $description = 'Delete in-app APM notifications (notifications table) older than the retention window';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $cutoff = Carbon::now()->subMonths($months);

        $this->info("Deleting notifications with created_at before {$cutoff->toDateTimeString()}…");

        $deleted = Notification::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} row(s).");

        Log::info('notifications:prune-old completed', [
            'months' => $months,
            'deleted' => $deleted,
            'cutoff' => $cutoff->toIso8601String(),
        ]);

        return self::SUCCESS;
    }
}
