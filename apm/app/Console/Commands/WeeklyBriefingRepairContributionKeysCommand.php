<?php

namespace App\Console\Commands;

use App\Services\WeeklyBriefingContributionKeyResolver;
use Illuminate\Console\Command;

class WeeklyBriefingRepairContributionKeysCommand extends Command
{
    protected $signature = 'weekly-briefing:repair-contribution-keys';

    protected $description = 'Give each contributor row its own division-scoped brief (d-{division_id}) and split legacy shared directorate (dr-*) reports.';

    public function handle(): int
    {
        $updated = WeeklyBriefingContributionKeyResolver::repairContributorKeysInDatabase();
        $this->info("Updated {$updated} contributor row(s) to division-scoped keys.");

        return self::SUCCESS;
    }
}
