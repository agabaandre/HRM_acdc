<?php

namespace App\Services;

/**
 * Resolves which divisions a user may view on the budget execution dashboard.
 */
final class BudgetExecutionScope
{
    public const ACCESS_ALL = 'all';

    public const ACCESS_DIRECTORATE = 'directorate';

    public const ACCESS_DIVISION = 'division';

    /**
     * @return array{
     *   access: string,
     *   allowed_division_ids: list<int>|null,
     *   default_division_id: int|null,
     *   is_director: bool,
     * }
     */
    public static function resolve(?int $staffId = null): array
    {
        $staffId = $staffId ?? DivisionWeeklyBriefGate::sessionStaffId();
        $sessionDivisionId = (int) (user_session('division_id') ?? 0);
        $role = (int) (user_session('role') ?? user_session('user_role') ?? 0);
        $permissions = user_session('permissions', []) ?? [];

        if ($role === 10 || in_array(88, $permissions, true) || approver_timing_report_can_view_all()) {
            return [
                'access' => self::ACCESS_ALL,
                'allowed_division_ids' => null,
                'default_division_id' => $sessionDivisionId > 0 ? $sessionDivisionId : null,
                'is_director' => false,
            ];
        }

        $directorDivisionIds = DivisionWeeklyBriefGate::divisionIdsUnderDirectorOversight($staffId);
        if ($directorDivisionIds !== []) {
            return [
                'access' => self::ACCESS_DIRECTORATE,
                'allowed_division_ids' => $directorDivisionIds,
                'default_division_id' => $sessionDivisionId > 0 && in_array($sessionDivisionId, $directorDivisionIds, true)
                    ? $sessionDivisionId
                    : $directorDivisionIds[0],
                'is_director' => true,
            ];
        }

        return [
            'access' => self::ACCESS_DIVISION,
            'allowed_division_ids' => $sessionDivisionId > 0 ? [$sessionDivisionId] : [],
            'default_division_id' => $sessionDivisionId > 0 ? $sessionDivisionId : null,
            'is_director' => false,
        ];
    }

    /**
     * @param  array{
     *   access: string,
     *   allowed_division_ids: list<int>|null,
     * }  $scope
     */
    public static function assertDivisionAllowed(array $scope, ?int $divisionId): void
    {
        if ($divisionId === null || $divisionId <= 0) {
            return;
        }

        $allowed = $scope['allowed_division_ids'] ?? null;
        if ($allowed === null) {
            return;
        }

        if (! in_array($divisionId, $allowed, true)) {
            abort(403, 'You are not allowed to view budget execution for this division.');
        }
    }

    /**
     * @param  array{
     *   access: string,
     *   allowed_division_ids: list<int>|null,
     * }  $scope
     */
    public static function filterDivisionQuery($query, array $scope, string $divisionColumn = 'division_id'): void
    {
        $allowed = $scope['allowed_division_ids'] ?? null;
        if ($allowed === null) {
            return;
        }

        if ($allowed === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($divisionColumn, $allowed);
    }
}
