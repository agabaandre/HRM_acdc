<?php

namespace App\Services;

use App\Models\Directorate;
use App\Models\Division;
use Illuminate\Support\Facades\Schema;

/**
 * How a division relates to a directorate in weekly briefing and settings.
 *
 * Many legacy rows have {@code divisions.director_id} aligned with {@code directorates.director_id}
 * while {@code divisions.directorate_id} is null or stale — settings must accept both links.
 */
final class DirectorateDivisionLink
{
    /**
     * Pure membership check (easy to unit test).
     */
    public static function belongs(
        int $divisionDirectorateId,
        int $divisionDirectorId,
        int $selectedDirectorateId,
        int $selectedDirectorateDirectorId,
    ): bool {
        if ($selectedDirectorateId <= 0) {
            return false;
        }

        if ($divisionDirectorateId > 0 && $divisionDirectorateId === $selectedDirectorateId) {
            return true;
        }

        if (
            $divisionDirectorId > 0
            && $selectedDirectorateDirectorId > 0
            && $divisionDirectorId === $selectedDirectorateDirectorId
        ) {
            return true;
        }

        return false;
    }

    public static function divisionBelongsToDirectorate(Division $division, int $directorateId): bool
    {
        if ($directorateId <= 0) {
            return false;
        }

        $directorate = Directorate::query()->find($directorateId);
        if (! $directorate) {
            return false;
        }

        return self::belongs(
            (int) ($division->directorate_id ?? 0),
            (int) ($division->director_id ?? 0),
            $directorateId,
            (int) ($directorate->director_id ?? 0),
        );
    }

    /**
     * Best directorate id for a division (FK first, else directorate whose director matches division director).
     */
    public static function resolveDirectorateIdForDivision(Division $division): int
    {
        $fromMap = (int) (self::buildDivisionDirectorateMap()[(int) $division->id] ?? 0);
        if ($fromMap > 0) {
            return $fromMap;
        }

        $dirId = (int) ($division->directorate_id ?? 0);
        if ($dirId > 0) {
            return $dirId;
        }

        $divDirector = (int) ($division->director_id ?? 0);
        if ($divDirector <= 0 || ! Schema::hasTable('directorates')) {
            return 0;
        }

        if (! Schema::hasColumn('directorates', 'director_id')) {
            return 0;
        }

        $resolved = self::directorateIdForDirectorStaffId($divDirector);

        return $resolved > 0 ? $resolved : 0;
    }

    /**
     * Same resolution as the settings UI map + POST (for save).
     *
     * @param  array<string, mixed>  $row
     */
    public static function directorateIdForSettingsRow(int $divisionId, array $row = []): int
    {
        if ($divisionId <= 0) {
            return 0;
        }

        $map = self::buildDivisionDirectorateMap();
        $fromMap = (int) ($map[$divisionId] ?? 0);
        if ($fromMap > 0) {
            return $fromMap;
        }

        $posted = (int) ($row['contribution_directorate_id'] ?? 0);
        if ($posted > 0) {
            return $posted;
        }

        $division = Division::query()->find($divisionId);

        return $division ? self::resolveDirectorateIdForDivision($division) : 0;
    }

    private static function directorateIdForDirectorStaffId(int $staffId): int
    {
        if ($staffId <= 0) {
            return 0;
        }

        $q = Directorate::query()->where('director_id', $staffId)->orderBy('id');
        if (Schema::hasColumn('directorates', 'is_active')) {
            $activeId = (int) ((clone $q)->where('is_active', true)->value('id') ?? 0);
            if ($activeId > 0) {
                return $activeId;
            }
        }

        return (int) ($q->value('id') ?? 0);
    }

    /**
     * @return list<int>
     */
    public static function directorateIdsForDivision(Division $division): array
    {
        $ids = [];
        $fk = (int) ($division->directorate_id ?? 0);
        if ($fk > 0) {
            $ids[$fk] = $fk;
        }

        $resolved = self::resolveDirectorateIdForDivision($division);
        if ($resolved > 0) {
            $ids[$resolved] = $resolved;
        }

        return array_values($ids);
    }

    /**
     * Map division id → suggested directorate id for settings UI (FK, else director match).
     *
     * @return array<int, int>
     */
    public static function buildDivisionDirectorateMap(): array
    {
        if (! Schema::hasTable('divisions')) {
            return [];
        }

        $map = [];

        $directorToDirectorate = [];
        if (Schema::hasTable('directorates') && Schema::hasColumn('directorates', 'director_id')) {
            $q = Directorate::query()->orderBy('id');
            if (Schema::hasColumn('directorates', 'is_active')) {
                $q->where('is_active', true);
            }
            foreach ($q->get(['id', 'director_id']) as $directorate) {
                $directorStaffId = (int) ($directorate->director_id ?? 0);
                if ($directorStaffId > 0 && ! isset($directorToDirectorate[$directorStaffId])) {
                    $directorToDirectorate[$directorStaffId] = (int) $directorate->id;
                }
            }
        }

        $columns = ['id', 'directorate_id'];
        if (Schema::hasColumn((new Division)->getTable(), 'director_id')) {
            $columns[] = 'director_id';
        }

        foreach (Division::query()->get($columns) as $division) {
            $divisionId = (int) $division->id;
            $fk = (int) ($division->directorate_id ?? 0);
            if ($fk > 0) {
                $map[$divisionId] = $fk;

                continue;
            }

            $divDirector = (int) ($division->director_id ?? 0);
            if ($divDirector > 0 && isset($directorToDirectorate[$divDirector])) {
                $map[$divisionId] = $directorToDirectorate[$divDirector];
            }
        }

        return $map;
    }
}
