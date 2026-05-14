<?php

namespace App\Services;

use App\Models\Directorate;
use App\Models\Division;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use Illuminate\Support\Collection;

/**
 * Combined weekly briefing PDF for a **directorate director** ({@see Directorate::director_id}): submitted
 * reports for that directorate’s `dr-*` key plus legacy `d-*` division briefs for divisions under the same directorate.
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

        $dir = Directorate::query()->find($directorateId);
        if (! $dir || (int) ($dir->director_id ?? 0) !== $directorStaffId) {
            return collect();
        }

        $picked = collect();

        $keyDr = WeeklyBriefingContributor::contributionKeyForDirectorate($directorateId);
        if ($rep = $reportsByKey->get($keyDr)) {
            $picked->push($rep);
        }

        foreach (Division::query()->where('directorate_id', $directorateId)->pluck('id') as $divId) {
            $key = WeeklyBriefingContributor::contributionKeyForDivision((int) $divId);
            if ($rep = $reportsByKey->get($key)) {
                $picked->push($rep);
            }
        }

        return WeeklyBriefingCompletionSummary::sortReportsForCompiled($picked->unique('id')->values());
    }

    /**
     * Configured contributor keys for this directorate scope (dr-* for the id, and d-* for divisions in that directorate).
     *
     * @return list<string>
     */
    public static function contributionKeysForDirectorDirectorateScope(
        int $directorStaffId,
        int $directorateId,
        WeeklyBriefingSetting $settings,
    ): array {
        $dir = Directorate::query()->find($directorateId);
        if (! $dir || (int) ($dir->director_id ?? 0) !== $directorStaffId) {
            return [];
        }

        $configured = $settings->contributors()->distinct()->pluck('contribution_key')->filter()->values();
        $out = [];
        foreach ($configured as $key) {
            $k = trim((string) $key);
            if ($k === '') {
                continue;
            }
            if (str_starts_with($k, 'dr-')) {
                $id = (int) substr($k, 3);
                if ($id === $directorateId) {
                    $out[] = $k;
                }

                continue;
            }
            if (str_starts_with($k, 'd-')) {
                $divId = (int) substr($k, 2);
                $div = Division::query()->find($divId);
                if ($div && (int) ($div->directorate_id ?? 0) === $directorateId) {
                    $out[] = $k;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * One entry per distinct (directorate director, directorate) that has at least one submitted report in scope.
     *
     * @param  Collection<int, WeeklyBriefingReport>  $submittedReports
     * @return list<array{director_id: int, directorate_id: int, reports: Collection<int, WeeklyBriefingReport>}>
     */
    public static function directorCombinedMailGroups(Collection $submittedReports, int $isoYear, int $isoWeek): array
    {
        $reportsByKey = $submittedReports->keyBy(fn (WeeklyBriefingReport $r) => (string) $r->contribution_key);

        $pairSeen = [];
        foreach (Directorate::query()->whereNotNull('director_id')->where('director_id', '>', 0)->get() as $dirRow) {
            $dirTorateId = (int) $dirRow->id;
            $recipientId = (int) $dirRow->director_id;
            $has = $reportsByKey->has(WeeklyBriefingContributor::contributionKeyForDirectorate($dirTorateId));
            if (! $has) {
                foreach (Division::query()->where('directorate_id', $dirTorateId)->pluck('id') as $divId) {
                    if ($reportsByKey->has(WeeklyBriefingContributor::contributionKeyForDivision((int) $divId))) {
                        $has = true;
                        break;
                    }
                }
            }
            if (! $has) {
                continue;
            }
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

        $options = [];
        foreach (Directorate::query()->where('director_id', $staffId)->where('is_active', true)->orderBy('name')->get() as $dir) {
            $dirId = (int) $dir->id;
            $coll = self::submittedReportsForDirectorDirectorate($staffId, $dirId, $isoYear, $isoWeek, $reportsByKey);
            if ($coll->isEmpty()) {
                continue;
            }
            $options[] = [
                'directorate_id' => $dirId,
                'label' => $dir->name !== '' ? (string) $dir->name : ('Directorate #'.$dirId),
            ];
        }

        return $options;
    }
}
