<?php

namespace App\Services;

use App\Models\Directorate;
use App\Models\Division;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Combined weekly briefing PDF for a directorate / division director.
 *
 * Recipients come from:
 * - {@see Directorate::director_id} (preferred when populated), and/or
 * - {@see Division::director_id} / active director OIC (legacy / common production path).
 *
 * Scope for a directorate id includes `dr-{id}` plus division `d-*` briefs under that directorate
 * ({@see DirectorateDivisionLink}). When a director only oversights divisions with no resolvable
 * directorate, packs use {@code directorate_id = 0}.
 */
final class WeeklyBriefingDirectorateCombined
{
    /**
     * Whether this staff may receive / download the director pack for the given directorate scope.
     */
    public static function staffIsAuthorizedForDirectorDirectorateScope(int $directorStaffId, int $directorateId): bool
    {
        if ($directorStaffId <= 0) {
            return false;
        }

        if ($directorateId > 0) {
            $dir = Directorate::query()->find($directorateId);
            if ($dir && (int) ($dir->director_id ?? 0) === $directorStaffId) {
                return true;
            }
        }

        return self::divisionIdsInDirectorDirectorateScope($directorStaffId, $directorateId) !== [];
    }

    /**
     * Division ids in this director's scope for the given directorate bucket (0 = no directorate).
     *
     * @return list<int>
     */
    public static function divisionIdsInDirectorDirectorateScope(int $directorStaffId, int $directorateId): array
    {
        if ($directorStaffId <= 0) {
            return [];
        }

        $out = [];

        foreach (DivisionWeeklyBriefGate::divisionIdsUnderDirectorOversight($directorStaffId) as $divId) {
            $div = Division::query()->find($divId);
            if (! $div) {
                continue;
            }

            $resolved = DirectorateDivisionLink::resolveDirectorateIdForDivision($div);
            if ($directorateId > 0) {
                if (
                    $resolved === $directorateId
                    || DirectorateDivisionLink::divisionBelongsToDirectorate($div, $directorateId)
                ) {
                    $out[] = (int) $divId;
                }
            } elseif ($resolved === 0) {
                $out[] = (int) $divId;
            }
        }

        // Named directorate director: always include FK-linked divisions even if oversight list is incomplete.
        if ($directorateId > 0) {
            $dir = Directorate::query()->find($directorateId);
            if ($dir && (int) ($dir->director_id ?? 0) === $directorStaffId) {
                foreach (Division::query()->where('directorate_id', $directorateId)->pluck('id') as $divId) {
                    $out[] = (int) $divId;
                }
            }
        }

        return array_values(array_unique(array_filter($out, fn (int $id) => $id > 0)));
    }

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
        if (! self::staffIsAuthorizedForDirectorDirectorateScope($directorStaffId, $directorateId)) {
            return collect();
        }

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

        if ($directorateId > 0) {
            $keyDr = WeeklyBriefingContributor::contributionKeyForDirectorate($directorateId);
            if ($rep = $reportsByKey->get($keyDr)) {
                $picked->push($rep);
            }
        }

        foreach (self::divisionIdsInDirectorDirectorateScope($directorStaffId, $directorateId) as $divId) {
            $key = WeeklyBriefingContributor::contributionKeyForDivision((int) $divId);
            if ($rep = $reportsByKey->get($key)) {
                $picked->push($rep);
            }
        }

        return WeeklyBriefingCompletionSummary::sortReportsForCompiled($picked->unique('id')->values());
    }

    /**
     * Configured contributor keys for this directorate scope (dr-* for the id, and d-* for divisions in scope).
     *
     * @return list<string>
     */
    public static function contributionKeysForDirectorDirectorateScope(
        int $directorStaffId,
        int $directorateId,
        WeeklyBriefingSetting $settings,
    ): array {
        if (! self::staffIsAuthorizedForDirectorDirectorateScope($directorStaffId, $directorateId)) {
            return [];
        }

        $allowedDivIds = array_fill_keys(
            self::divisionIdsInDirectorDirectorateScope($directorStaffId, $directorateId),
            true
        );

        $configured = $settings->contributors()->distinct()->pluck('contribution_key')->filter()->values();
        $out = [];
        foreach ($configured as $key) {
            $k = trim((string) $key);
            if ($k === '') {
                continue;
            }
            if (str_starts_with($k, 'dr-')) {
                $id = (int) substr($k, 3);
                if ($directorateId > 0 && $id === $directorateId) {
                    $out[] = $k;
                }

                continue;
            }
            if (str_starts_with($k, 'd-')) {
                $divId = (int) substr($k, 2);
                if (isset($allowedDivIds[$divId])) {
                    $out[] = $k;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * One entry per distinct (director, directorate-or-0) that has at least one submitted report in scope.
     *
     * @param  Collection<int, WeeklyBriefingReport>  $submittedReports
     * @return list<array{director_id: int, directorate_id: int, reports: Collection<int, WeeklyBriefingReport>}>
     */
    public static function directorCombinedMailGroups(Collection $submittedReports, int $isoYear, int $isoWeek): array
    {
        $reportsByKey = $submittedReports->keyBy(fn (WeeklyBriefingReport $r) => (string) $r->contribution_key);

        $pairSeen = [];
        foreach (self::candidateDirectorStaffIds() as $directorStaffId) {
            foreach (self::directorateScopeIdsForDirector($directorStaffId) as $directorateId) {
                $pairSeen[$directorStaffId.':'.$directorateId] = [
                    'director_id' => $directorStaffId,
                    'directorate_id' => $directorateId,
                ];
            }
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
        foreach (self::directorateScopeIdsForDirector($staffId) as $dirId) {
            $coll = self::submittedReportsForDirectorDirectorate($staffId, $dirId, $isoYear, $isoWeek, $reportsByKey);
            if ($coll->isEmpty()) {
                continue;
            }
            if ($dirId > 0) {
                $dir = Directorate::query()->find($dirId);
                $label = $dir && $dir->name !== '' ? (string) $dir->name : ('Directorate #'.$dirId);
            } else {
                $label = 'Directed divisions (no directorate)';
            }
            $options[] = [
                'directorate_id' => $dirId,
                'label' => $label,
            ];
        }

        usort($options, fn (array $a, array $b) => strcasecmp($a['label'], $b['label']));

        return $options;
    }

    /**
     * @return list<int>
     */
    private static function candidateDirectorStaffIds(): array
    {
        $ids = [];

        if (Schema::hasTable('directorates') && Schema::hasColumn('directorates', 'director_id')) {
            $q = Directorate::query()->where('director_id', '>', 0);
            if (Schema::hasColumn('directorates', 'is_active')) {
                $q->where('is_active', true);
            }
            foreach ($q->pluck('director_id') as $id) {
                $ids[(int) $id] = true;
            }
        }

        if (Schema::hasTable('divisions') && Schema::hasColumn((new Division)->getTable(), 'director_id')) {
            foreach (Division::query()->where('director_id', '>', 0)->pluck('director_id') as $id) {
                $ids[(int) $id] = true;
            }
        }

        if (Schema::hasTable('divisions') && Schema::hasColumn((new Division)->getTable(), 'director_oic_id')) {
            foreach (Division::query()->where('director_oic_id', '>', 0)->get(['director_oic_id', 'director_oic_start_date', 'director_oic_end_date', 'director_id']) as $div) {
                $oic = (int) ($div->director_oic_id ?? 0);
                if ($oic > 0 && $div->staffActsAsDivisionDirector($oic)) {
                    $ids[$oic] = true;
                }
            }
        }

        return array_values(array_filter(array_map('intval', array_keys($ids)), fn (int $id) => $id > 0));
    }

    /**
     * Directorate buckets (including 0) this director should receive packs for.
     *
     * @return list<int>
     */
    private static function directorateScopeIdsForDirector(int $directorStaffId): array
    {
        if ($directorStaffId <= 0) {
            return [];
        }

        $scopes = [];

        foreach (DivisionWeeklyBriefGate::directorateIdsForStaffDirector($directorStaffId) as $dirId) {
            $scopes[(int) $dirId] = true;
        }

        foreach (DivisionWeeklyBriefGate::divisionIdsUnderDirectorOversight($directorStaffId) as $divId) {
            $div = Division::query()->find($divId);
            if (! $div) {
                continue;
            }
            $resolved = DirectorateDivisionLink::resolveDirectorateIdForDivision($div);
            $scopes[$resolved] = true;
        }

        return array_values(array_map('intval', array_keys($scopes)));
    }
}
