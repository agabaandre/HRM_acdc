<?php

namespace App\Support;

use App\Models\Division;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-division context for APM: switchable divisions + session active overlay.
 *
 * Switchable = primary contract division + associated_divisions JSON
 * + focal / head / head OIC / director / director OIC / admin assistant.
 * Finance officer / finance OIC slots are intentionally excluded.
 */
final class StaffDivisionContext
{
    public const SESSION_ACTIVE_ID = 'active_division_id';

    public const SESSION_ACTIVE_NAME = 'active_division_name';

    public static function primaryDivisionId(?int $staffId = null): ?int
    {
        $staffId = $staffId ?? (function_exists('resolved_session_staff_id') ? resolved_session_staff_id() : null);
        if ($staffId === null || $staffId <= 0) {
            return null;
        }

        $staff = Staff::query()->where('staff_id', $staffId)->first();
        $id = (int) ($staff->division_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @return list<int>
     */
    public static function switchableIds(?int $staffId = null): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['id'],
            self::switchableDivisions($staffId)
        ));
    }

    /**
     * Division IDs for "My Division" style lists (union of switchable set).
     *
     * @return list<int>
     */
    public static function listDivisionIds(?int $staffId = null): array
    {
        $ids = self::switchableIds($staffId);
        if ($ids !== []) {
            return $ids;
        }

        $primary = self::primaryDivisionId($staffId);

        return $primary !== null ? [$primary] : [];
    }

    /**
     * @return list<array{id:int,name:string,is_primary:bool,sources:list<string>}>
     */
    public static function switchableDivisions(?int $staffId = null): array
    {
        $staffId = $staffId ?? (function_exists('resolved_session_staff_id') ? resolved_session_staff_id() : null);
        if ($staffId === null || $staffId <= 0) {
            return [];
        }

        $staff = Staff::query()->where('staff_id', $staffId)->first();
        if (! $staff) {
            return [];
        }

        /** @var array<int, list<string>> $sourcesById */
        $sourcesById = [];
        $add = static function (int $id, string $source) use (&$sourcesById): void {
            if ($id <= 0) {
                return;
            }
            $sourcesById[$id] ??= [];
            if (! in_array($source, $sourcesById[$id], true)) {
                $sourcesById[$id][] = $source;
            }
        };

        $primary = (int) ($staff->division_id ?? 0);
        $add($primary, 'primary');

        foreach ((array) ($staff->associated_divisions ?? []) as $raw) {
            $add((int) $raw, 'associated');
        }

        $divisionsTable = (new Division)->getTable();
        if (Schema::hasTable($divisionsTable)) {
            foreach (Division::query()->where('focal_person', $staffId)->pluck('id') as $id) {
                $add((int) $id, 'focal');
            }
            foreach (Division::query()->where('division_head', $staffId)->pluck('id') as $id) {
                $add((int) $id, 'head');
            }
            foreach (Division::query()->where('admin_assistant', $staffId)->pluck('id') as $id) {
                $add((int) $id, 'admin_assistant');
            }
            foreach (Division::queryForStaffActingAsDirector($staffId)->pluck('id') as $id) {
                $add((int) $id, 'director');
            }

            $today = Carbon::now()->toDateString();
            $headOic = Division::query()
                ->where('head_oic_id', $staffId)
                ->where(function ($q) use ($today) {
                    $q->whereNull('head_oic_start_date')->orWhereDate('head_oic_start_date', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('head_oic_end_date')->orWhereDate('head_oic_end_date', '>=', $today);
                })
                ->pluck('id');
            foreach ($headOic as $id) {
                $add((int) $id, 'head_oic');
            }
            // Intentionally omit finance_officer / finance_officer_oic_id
        }

        if ($sourcesById === []) {
            return [];
        }

        $names = Division::query()
            ->whereIn('id', array_keys($sourcesById))
            ->pluck('division_name', 'id');

        $out = [];
        foreach ($sourcesById as $id => $sources) {
            $out[] = [
                'id' => (int) $id,
                'name' => (string) ($names[$id] ?? ('Division '.$id)),
                'is_primary' => (int) $id === $primary,
                'sources' => $sources,
            ];
        }

        usort($out, static function (array $a, array $b): int {
            if ($a['is_primary'] !== $b['is_primary']) {
                return $a['is_primary'] ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $out;
    }

    public static function clearActive(): void
    {
        if (! function_exists('session')) {
            return;
        }
        session()->forget([self::SESSION_ACTIVE_ID, self::SESSION_ACTIVE_NAME]);
    }

    public static function setActive(int $divisionId, ?int $staffId = null): bool
    {
        $ids = self::switchableIds($staffId);
        if (! in_array($divisionId, $ids, true)) {
            return false;
        }
        $name = (string) (Division::query()->where('id', $divisionId)->value('division_name') ?? '');
        session([
            self::SESSION_ACTIVE_ID => $divisionId,
            self::SESSION_ACTIVE_NAME => $name,
        ]);

        return true;
    }

    public static function activeDivisionId(?int $staffId = null): ?int
    {
        $active = (int) session(self::SESSION_ACTIVE_ID, 0);
        if ($active <= 0) {
            return self::primaryDivisionId($staffId);
        }
        if (! in_array($active, self::switchableIds($staffId), true)) {
            self::clearActive();

            return self::primaryDivisionId($staffId);
        }

        return $active;
    }

    public static function activeDivisionName(?int $staffId = null): ?string
    {
        $id = self::activeDivisionId($staffId);
        if ($id === null) {
            return null;
        }
        $cachedId = (int) session(self::SESSION_ACTIVE_ID, 0);
        $cachedName = session(self::SESSION_ACTIVE_NAME);
        if ($cachedId === $id && is_string($cachedName) && $cachedName !== '') {
            return $cachedName;
        }

        return (string) (Division::query()->where('id', $id)->value('division_name') ?? '');
    }
}
