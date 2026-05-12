<?php

namespace App\Services;

use App\Models\Division;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use Illuminate\Support\Collection;

/**
 * Combined weekly briefing PDF for a division director: submitted division briefs (d-* only)
 * for divisions where they are the named director or active director OIC (see Division::queryForStaffActingAsDirector),
 * scoped by directorate_id (null directorates use id 0). Does not include directorate-level (dr-*) reports — those stay
 * on the organisation-wide compiled pack for central recipients only.
 */
final class WeeklyBriefingDirectorateCombined
{
    /**
     * @param  Collection<string, WeeklyBriefingReport>|null  $reportsByKey  contribution_key => report; if null, loads from DB
     * @return Collection<int, WeeklyBriefingReport>
     */
    public static function submittedReportsForDirectorDirectorate(
        int $directorStaffId,
        int $directorateId,
        int $isoYear,
        int $isoWeek,
        ?Collection $reportsByKey = null,
    ): Collection {
        if ($reportsByKey === null) {
            $reportsByKey = WeeklyBriefingReport::query()
                ->where('report_iso_week_year', $isoYear)
                ->where('report_iso_week', $isoWeek)
                ->where('status', WeeklyBriefingReport::STATUS_SUBMITTED)
                ->with(['division', 'directorate', 'submittedBy'])
                ->get()
                ->keyBy(fn (WeeklyBriefingReport $r) => (string) $r->contribution_key);
        }

        $picked = collect();

        $divisions = Division::queryForStaffActingAsDirector($directorStaffId)
            ->where(function ($q) use ($directorateId) {
                if ($directorateId === 0) {
                    $q->whereNull('directorate_id');
                } else {
                    $q->where('directorate_id', $directorateId);
                }
            })
            ->get();

        foreach ($divisions as $div) {
            $key = WeeklyBriefingContributor::contributionKeyForDivision((int) $div->id);
            $rep = $reportsByKey->get($key);
            if ($rep) {
                $picked->push($rep);
            }
        }

        return WeeklyBriefingCompletionSummary::sortReportsForCompiled($picked->unique('id')->values());
    }

    /**
     * Configured contributor keys (from settings) for divisions this director directs in this directorate scope
     * (division d-* keys only; directorate dr-* rows are excluded from the director-scoped summary).
     *
     * @return list<string>
     */
    public static function contributionKeysForDirectorDirectorateScope(
        int $directorStaffId,
        int $directorateId,
        WeeklyBriefingSetting $settings,
    ): array {
        $configured = $settings->contributors()->distinct()->pluck('contribution_key')->filter()->values();
        $out = [];
        foreach ($configured as $key) {
            $k = trim((string) $key);
            if ($k === '' || ! str_starts_with($k, 'd-')) {
                continue;
            }
            $divId = (int) substr($k, 2);
            $div = Division::query()->find($divId);
            if (! $div || ! $div->staffActsAsDivisionDirector($directorStaffId)) {
                continue;
            }
            $divDir = (int) ($div->directorate_id ?? 0);
            if ($divDir !== $directorateId) {
                continue;
            }
            $out[] = $k;
        }

        return array_values(array_unique($out));
    }

    /**
     * One entry per distinct (director_id, directorate_id) that has at least one submitted division brief.
     *
     * @param  Collection<int, WeeklyBriefingReport>  $submittedReports
     * @return list<array{director_id: int, directorate_id: int, reports: Collection<int, WeeklyBriefingReport>}>
     */
    public static function directorCombinedMailGroups(Collection $submittedReports, int $isoYear, int $isoWeek): array
    {
        $reportsByKey = $submittedReports->keyBy(fn (WeeklyBriefingReport $r) => (string) $r->contribution_key);

        $pairSeen = [];
        foreach (Division::query()->orderBy('id')->get() as $div) {
            $recipientId = $div->primaryOrActiveDirectorStaffIdForWeeklyBrief();
            if (! $recipientId) {
                continue;
            }
            $dKey = WeeklyBriefingContributor::contributionKeyForDivision((int) $div->id);
            if (! $reportsByKey->has($dKey)) {
                continue;
            }
            $dirTorateId = (int) ($div->directorate_id ?? 0);
            $pairSeen[$recipientId.':'.$dirTorateId] = [
                'director_id' => $recipientId,
                'directorate_id' => $dirTorateId,
            ];
        }

        $out = [];
        foreach ($pairSeen as $pair) {
            $coll = self::submittedReportsForDirectorDirectorate(
                $pair['director_id'],
                $pair['directorate_id'],
                $isoYear,
                $isoWeek,
                $reportsByKey
            );
            if ($coll->isEmpty()) {
                continue;
            }
            $out[] = [
                'director_id' => $pair['director_id'],
                'directorate_id' => $pair['directorate_id'],
                'reports' => $coll,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{directorate_id: int, label: string}>
     */
    public static function directorCombinedDownloadOptionsForStaff(int $staffId, int $isoYear, int $isoWeek): array
    {
        if ($staffId <= 0) {
            return [];
        }

        $reportsByKey = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $isoYear)
            ->where('report_iso_week', $isoWeek)
            ->where('status', WeeklyBriefingReport::STATUS_SUBMITTED)
            ->get()
            ->keyBy(fn (WeeklyBriefingReport $r) => (string) $r->contribution_key);

        $directorateIds = [];
        foreach (Division::queryForStaffActingAsDirector($staffId)->get() as $div) {
            $dKey = WeeklyBriefingContributor::contributionKeyForDivision((int) $div->id);
            if (! $reportsByKey->has($dKey)) {
                continue;
            }
            $directorateIds[(int) ($div->directorate_id ?? 0)] = true;
        }

        $options = [];
        foreach (array_keys($directorateIds) as $dirId) {
            $coll = self::submittedReportsForDirectorDirectorate($staffId, (int) $dirId, $isoYear, $isoWeek, $reportsByKey);
            if ($coll->isEmpty()) {
                continue;
            }
            if ($dirId > 0) {
                $first = Division::query()->where('directorate_id', $dirId)->with('directorate')->orderBy('id')->first();
                $label = $first?->directorate?->name ?? ('Directorate #'.$dirId);
            } else {
                $label = 'Directed divisions (no directorate)';
            }
            $options[] = [
                'directorate_id' => (int) $dirId,
                'label' => $label,
            ];
        }

        return $options;
    }
}
