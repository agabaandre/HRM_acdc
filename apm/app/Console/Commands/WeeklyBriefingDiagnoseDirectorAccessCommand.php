<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\WeeklyBriefingSetting;
use App\Services\DivisionWeeklyBriefGate;
use App\Services\WeeklyBriefingContributionKeyResolver;
use Illuminate\Console\Command;

class WeeklyBriefingDiagnoseDirectorAccessCommand extends Command
{
    protected $signature = 'weekly-briefing:diagnose-director-access {staff_id : Session staff_id to diagnose}';

    protected $description = 'Show which weekly-brief contributor rows a director should see and why.';

    public function handle(): int
    {
        $staffId = (int) $this->argument('staff_id');
        if ($staffId <= 0) {
            $this->error('Invalid staff_id.');

            return self::FAILURE;
        }

        $this->info("Staff #{$staffId}");
        $this->line('directorates.director_id column: '.(DivisionWeeklyBriefGate::directoratesTableHasDirectorIdColumn() ? 'yes' : 'no'));
        $this->line('mayActAsDivisionDirector: '.(DivisionWeeklyBriefGate::mayActAsDivisionDirector($staffId) ? 'yes' : 'no'));
        $this->line('Directorate ids (directorates.director_id): '.json_encode(DivisionWeeklyBriefGate::directorateIdsForStaffDirector($staffId)));
        $this->line('Division ids (divisions.director_id / OIC): '.json_encode(DivisionWeeklyBriefGate::divisionIdsForStaffActingAsDirector($staffId)));
        $this->line('Oversight division ids (union): '.json_encode(DivisionWeeklyBriefGate::divisionIdsUnderDirectorOversight($staffId)));

        $rows = WeeklyBriefingSetting::current()->contributors()->with('staff')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $this->warn('No contributor rows configured in weekly briefing settings.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Contributor id', 'Key', 'Filing division', 'Visible to director', 'HoD staff'],
            $rows->map(function ($c) use ($staffId) {
                $divId = WeeklyBriefingContributionKeyResolver::divisionIdForContributor($c);
                $div = $divId > 0 ? Division::query()->find($divId) : null;

                return [
                    $c->id,
                    $c->contribution_key,
                    $div ? "{$div->id} {$div->division_name}" : '—',
                    DivisionWeeklyBriefGate::contributorRowVisibleToDirector($c, $staffId) ? 'yes' : 'no',
                    $c->staff?->name ?? $c->staff_id,
                ];
            })->all()
        );

        $visible = $rows->filter(fn ($c) => DivisionWeeklyBriefGate::contributorRowVisibleToDirector($c, $staffId))->count();
        $this->newLine();
        $this->info("Visible on hub: {$visible} / {$rows->count()} contributor row(s).");

        if ($visible === 0 && DivisionWeeklyBriefGate::mayActAsDivisionDirector($staffId)) {
            $this->warn('Director access is enabled but no rows match. Check: divisions.director_id / directorates.director_id, divisions.directorate_id, and contributor rows for each division under the directorate.');
        }

        return self::SUCCESS;
    }
}
