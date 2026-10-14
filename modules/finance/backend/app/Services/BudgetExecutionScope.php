<?php

namespace App\Services;

use App\Support\FinancePermissions;
use App\Support\StaffDivisionContext;

/**
 * Resolve budget-execution visibility: org-wide, directorate, or own division(s).
 */
final class BudgetExecutionScope
{
    public function __construct(private readonly StaffPortalOrgClient $orgClient = new StaffPortalOrgClient) {}

    /**
     * @param  list<int|string>  $permissions
     * @return array{
     *   mode: 'all'|'directorate'|'division'|'none',
     *   can_view_all: bool,
     *   division_ids: list<int>,
     *   directorate_ids: list<int>,
     *   show_division_charts: bool
     * }
     */
    public function resolve(array $permissions, int $roleId, int $staffId, int $sessionDivisionId): array
    {
        if (FinancePermissions::canViewAllBudgetExecution($permissions, $roleId)
            || $this->isFinanceOfficer($staffId)) {
            return [
                'mode' => 'all',
                'can_view_all' => true,
                'division_ids' => [],
                'directorate_ids' => [],
                'show_division_charts' => true,
            ];
        }

        $ids = [];
        foreach (StaffDivisionContext::switchableIds($staffId) as $id) {
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($sessionDivisionId > 0) {
            $ids[$sessionDivisionId] = true;
        }

        $directorateIds = [];
        $isDirectorateLead = false;

        if ($staffId > 0) {
            try {
                $org = $this->orgClient->fetchOrg();
            } catch (\Throwable) {
                $org = ['divisions' => [], 'directorates' => []];
            }

            $directedDirectorateIds = [];
            foreach ($org['directorates'] ?? [] as $dir) {
                $directorId = (int) ($dir['director_id'] ?? 0);
                $xid = (int) ($dir['id'] ?? 0);
                if ($xid > 0 && $directorId === $staffId) {
                    $directedDirectorateIds[$xid] = true;
                    $directorateIds[$xid] = true;
                    $isDirectorateLead = true;
                }
            }

            foreach ($org['divisions'] ?? [] as $div) {
                $did = (int) ($div['division_id'] ?? 0);
                if ($did < 1) {
                    continue;
                }
                $divDirector = (int) ($div['director_id'] ?? 0);
                $dirId = (int) ($div['directorate_id'] ?? 0);
                if ($divDirector === $staffId) {
                    $ids[$did] = true;
                    if ($dirId > 0) {
                        $directorateIds[$dirId] = true;
                        $isDirectorateLead = true;
                    }
                    continue;
                }
                if ($dirId > 0 && isset($directedDirectorateIds[$dirId])) {
                    $ids[$did] = true;
                    $directorateIds[$dirId] = true;
                }
            }

            // Regular staff: expand to sibling divisions in the same directorate(s)
            // so directors/heads see the directorate view; employees still get peers
            // when they lead the directorate. For non-leads, keep own division only
            // unless local DB marks them as directorate head via divisions.director_id.
            $this->expandFromLocalDivisions($staffId, $ids, $directorateIds, $isDirectorateLead);
        }

        $list = array_values(array_unique(array_filter(array_map('intval', array_keys($ids)), static fn (int $id) => $id > 0)));
        sort($list);
        $dirList = array_values(array_unique(array_filter(array_map('intval', array_keys($directorateIds)), static fn (int $id) => $id > 0)));
        sort($dirList);

        if ($list === []) {
            return [
                'mode' => 'none',
                'can_view_all' => false,
                'division_ids' => [],
                'directorate_ids' => [],
                'show_division_charts' => false,
            ];
        }

        $mode = ($isDirectorateLead || count($list) > 1) ? 'directorate' : 'division';

        return [
            'mode' => $mode,
            'can_view_all' => false,
            'division_ids' => $list,
            'directorate_ids' => $dirList,
            'show_division_charts' => $mode === 'directorate',
        ];
    }

    /**
     * @param  array<int, true>  $ids
     * @param  array<int, true>  $directorateIds
     */
    private function expandFromLocalDivisions(int $staffId, array &$ids, array &$directorateIds, bool &$isDirectorateLead): void
    {
        $schema = StaffDivisionContext::orgSchema();
        $db = StaffDivisionContext::orgDb();

        if (! $schema->hasTable('divisions')) {
            return;
        }
        $idCol = $schema->hasColumn('divisions', 'division_id') ? 'division_id' : 'id';
        $hasDirCol = $schema->hasColumn('divisions', 'directorate_id');
        $hasDirectorCol = $schema->hasColumn('divisions', 'director_id');

        if ($hasDirectorCol) {
            foreach ($db->table('divisions')->where('director_id', $staffId)->pluck($idCol) as $id) {
                $n = (int) $id;
                if ($n > 0) {
                    $ids[$n] = true;
                    $isDirectorateLead = true;
                }
            }
        }

        if (! $hasDirCol || $ids === []) {
            return;
        }

        $seedDirIds = $db->table('divisions')
            ->whereIn($idCol, array_keys($ids))
            ->whereNotNull('directorate_id')
            ->pluck('directorate_id')
            ->map(static fn ($v) => (int) $v)
            ->filter(static fn (int $v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        foreach ($seedDirIds as $dirId) {
            $directorateIds[$dirId] = true;
        }

        // Directors / directorate leads see every division under their directorate(s).
        // Other staff keep only attached / switchable divisions (no peer expansion).
        if (! $isDirectorateLead || $seedDirIds === []) {
            return;
        }

        foreach ($db->table('divisions')->whereIn('directorate_id', $seedDirIds)->pluck($idCol) as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[$n] = true;
            }
        }
    }

    private function isFinanceOfficer(int $staffId): bool
    {
        if ($staffId < 1) {
            return false;
        }

        $schema = StaffDivisionContext::orgSchema();
        $db = StaffDivisionContext::orgDb();
        if (! $schema->hasTable('divisions') || ! $schema->hasColumn('divisions', 'finance_officer')) {
            return false;
        }

        if ($db->table('divisions')->where('finance_officer', $staffId)->exists()) {
            return true;
        }

        if (! $schema->hasColumn('divisions', 'finance_officer_oic_id')) {
            return false;
        }

        $today = now()->toDateString();
        $q = $db->table('divisions')->where('finance_officer_oic_id', $staffId);
        if ($schema->hasColumn('divisions', 'finance_officer_oic_start_date')) {
            $q->where(function ($inner) use ($today) {
                $inner->whereNull('finance_officer_oic_start_date')
                    ->orWhereDate('finance_officer_oic_start_date', '<=', $today);
            });
        }
        if ($schema->hasColumn('divisions', 'finance_officer_oic_end_date')) {
            $q->where(function ($inner) use ($today) {
                $inner->whereNull('finance_officer_oic_end_date')
                    ->orWhereDate('finance_officer_oic_end_date', '>=', $today);
            });
        }

        return $q->exists();
    }
}
