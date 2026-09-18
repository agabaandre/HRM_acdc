<?php

namespace App\Services;

use App\Support\RiskPermissions;

/**
 * Resolve which division IDs a Risk Register user may view.
 */
final class RiskAccessScope
{
    public function __construct(private readonly StaffPortalOrgClient $orgClient) {}

    /**
     * @param  list<int|string>  $permissions
     * @return array{mode: 'all'|'divisions'|'none', division_ids: list<int>}
     */
    public function resolve(array $permissions, int $staffId, int $sessionDivisionId): array
    {
        if (RiskPermissions::canViewAll($permissions)) {
            return ['mode' => 'all', 'division_ids' => []];
        }

        if (! RiskPermissions::canViewDivision($permissions)) {
            return ['mode' => 'none', 'division_ids' => []];
        }

        $ids = [];
        if ($sessionDivisionId > 0) {
            $ids[$sessionDivisionId] = true;
        }

        if ($staffId > 0) {
            try {
                $org = $this->orgClient->fetchOrg();
            } catch (\Throwable) {
                $org = ['divisions' => [], 'directorates' => []];
            }

            $directedDirectorateIds = [];
            foreach ($org['directorates'] ?? [] as $dir) {
                $directorId = isset($dir['director_id']) && $dir['director_id'] !== null && $dir['director_id'] !== ''
                    ? (int) $dir['director_id']
                    : 0;
                if ($directorId === $staffId) {
                    $xid = (int) ($dir['id'] ?? 0);
                    if ($xid > 0) {
                        $directedDirectorateIds[$xid] = true;
                    }
                }
            }

            foreach ($org['divisions'] ?? [] as $div) {
                $did = (int) ($div['division_id'] ?? 0);
                if ($did < 1) {
                    continue;
                }
                $divDirector = isset($div['director_id']) && $div['director_id'] !== null && $div['director_id'] !== ''
                    ? (int) $div['director_id']
                    : 0;
                if ($divDirector === $staffId) {
                    $ids[$did] = true;
                    continue;
                }
                $dirId = isset($div['directorate_id']) && $div['directorate_id'] !== null && $div['directorate_id'] !== ''
                    ? (int) $div['directorate_id']
                    : 0;
                if ($dirId > 0 && isset($directedDirectorateIds[$dirId])) {
                    $ids[$did] = true;
                }
            }
        }

        $list = array_map('intval', array_keys($ids));
        sort($list);

        if ($list === []) {
            return ['mode' => 'none', 'division_ids' => []];
        }

        return ['mode' => 'divisions', 'division_ids' => $list];
    }

    /**
     * @param  array{mode: string, division_ids: list<int>}  $scope
     */
    public function allowsDivision(array $scope, ?int $divisionId): bool
    {
        if ($scope['mode'] === 'all') {
            return true;
        }
        if ($scope['mode'] !== 'divisions' || $divisionId === null || $divisionId < 1) {
            return false;
        }

        return in_array($divisionId, $scope['division_ids'], true);
    }
}
