<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-division context for Finance / Risk Register SPAs.
 *
 * Switchable = primary contract division + associated_divisions
 * + focal / risk focal / head / head OIC / director / director OIC / admin assistant.
 * Finance officer slots are intentionally excluded.
 */
final class StaffDivisionContext
{
    public const SESSION_ACTIVE_ID = 'active_division_id';

    /**
     * @return list<int>
     */
    public static function switchableIds(int $staffId): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['id'],
            self::switchableDivisions($staffId)
        ));
    }

    /**
     * @return list<array{id:int,name:string,is_primary:bool,sources:list<string>}>
     */
    public static function switchableDivisions(int $staffId): array
    {
        if ($staffId <= 0 || ! Schema::hasTable('divisions')) {
            return [];
        }

        $meta = self::staffDivisionMeta($staffId);
        $primary = $meta['primary'];

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

        $add($primary, 'primary');
        foreach ($meta['associated'] as $raw) {
            $add((int) $raw, 'associated');
        }

        $idCol = Schema::hasColumn('divisions', 'division_id') ? 'division_id' : 'id';
        $nameCol = Schema::hasColumn('divisions', 'division_name') ? 'division_name' : 'name';
        $today = Carbon::now()->toDateString();

        $roleColumns = [
            'focal_person' => 'focal',
            'risk_focal_person' => 'risk_focal',
            'division_head' => 'head',
            'admin_assistant' => 'admin_assistant',
            'director_id' => 'director',
        ];
        foreach ($roleColumns as $column => $source) {
            if (! Schema::hasColumn('divisions', $column)) {
                continue;
            }
            foreach (DB::table('divisions')->where($column, $staffId)->pluck($idCol) as $id) {
                $add((int) $id, $source);
            }
        }

        if (Schema::hasColumn('divisions', 'head_oic_id')) {
            $q = DB::table('divisions')->where('head_oic_id', $staffId);
            if (Schema::hasColumn('divisions', 'head_oic_start_date')) {
                $q->where(function ($w) use ($today): void {
                    $w->whereNull('head_oic_start_date')->orWhereDate('head_oic_start_date', '<=', $today);
                });
            }
            if (Schema::hasColumn('divisions', 'head_oic_end_date')) {
                $q->where(function ($w) use ($today): void {
                    $w->whereNull('head_oic_end_date')->orWhereDate('head_oic_end_date', '>=', $today);
                });
            }
            foreach ($q->pluck($idCol) as $id) {
                $add((int) $id, 'head_oic');
            }
        }

        if (Schema::hasColumn('divisions', 'director_oic_id')) {
            $q = DB::table('divisions')->where('director_oic_id', $staffId);
            if (Schema::hasColumn('divisions', 'director_oic_start_date')) {
                $q->where(function ($w) use ($today): void {
                    $w->whereNull('director_oic_start_date')->orWhereDate('director_oic_start_date', '<=', $today);
                });
            }
            if (Schema::hasColumn('divisions', 'director_oic_end_date')) {
                $q->where(function ($w) use ($today): void {
                    $w->whereNull('director_oic_end_date')->orWhereDate('director_oic_end_date', '>=', $today);
                });
            }
            foreach ($q->pluck($idCol) as $id) {
                $add((int) $id, 'director_oic');
            }
        }

        // Intentionally omit finance_officer / finance_officer_oic_id

        if ($sourcesById === []) {
            return [];
        }

        $names = DB::table('divisions')
            ->whereIn($idCol, array_keys($sourcesById))
            ->pluck($nameCol, $idCol);

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

    /**
     * @param  array<string, mixed>  $session
     */
    public static function resolveDivisionId(array $session, int $staffId): int
    {
        $primary = (int) ($session['division_id'] ?? 0);
        $active = (int) ($session[self::SESSION_ACTIVE_ID] ?? 0);
        if ($active > 0 && $staffId > 0 && in_array($active, self::switchableIds($staffId), true)) {
            return $active;
        }

        return $primary > 0 ? $primary : 0;
    }

    public static function setActiveOnSession(Request $request, int $divisionId): bool
    {
        $staffId = (int) $request->attributes->get('risk_staff_id', 0);
        if ($staffId <= 0 || ! in_array($divisionId, self::switchableIds($staffId), true)) {
            return false;
        }

        self::mutateSessionPayload($request, static function (array $payload) use ($divisionId): array {
            $payload[self::SESSION_ACTIVE_ID] = $divisionId;

            return $payload;
        });

        return true;
    }

    public static function clearActiveOnSession(Request $request): void
    {
        self::mutateSessionPayload($request, static function (array $payload): array {
            unset($payload[self::SESSION_ACTIVE_ID]);

            return $payload;
        });
    }

    /**
     * @return array{enabled:bool,active_id:int|null,divisions:list<array{id:int,name:string,is_primary:bool,sources:list<string>}>}
     */
    public static function payloadFor(int $staffId, int $activeDivisionId): array
    {
        $divisions = self::switchableDivisions($staffId);

        return [
            'enabled' => count($divisions) >= 2,
            'active_id' => $activeDivisionId > 0 ? $activeDivisionId : null,
            'divisions' => $divisions,
        ];
    }

    /**
     * @return array{primary:int,associated:list<int>}
     */
    private static function staffDivisionMeta(int $staffId): array
    {
        $primary = 0;
        $associated = [];

        if (Schema::hasTable('staff_contracts')) {
            $q = DB::table('staff_contracts')->where('staff_id', $staffId);
            if (Schema::hasColumn('staff_contracts', 'status_id')) {
                $q->whereIn('status_id', [1, 2, 3, 7]);
            }
            $row = $q->orderByDesc('staff_contract_id')->first();
            if ($row) {
                $primary = (int) ($row->division_id ?? 0);
                if (Schema::hasColumn('staff_contracts', 'other_associated_divisions')) {
                    $raw = $row->other_associated_divisions ?? null;
                    $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                    if (is_array($decoded)) {
                        foreach ($decoded as $id) {
                            $n = (int) $id;
                            if ($n > 0) {
                                $associated[] = $n;
                            }
                        }
                    }
                }
            }
        }

        if ($primary <= 0 && Schema::hasTable('staff') && Schema::hasColumn('staff', 'division_id')) {
            $primary = (int) (DB::table('staff')->where('staff_id', $staffId)->value('division_id') ?? 0);
        }

        return [
            'primary' => $primary,
            'associated' => array_values(array_unique($associated)),
        ];
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutator
     */
    private static function mutateSessionPayload(Request $request, callable $mutator): void
    {
        try {
            $session = $request->session()->get('risk_register');
            if (is_array($session)) {
                $request->session()->put('risk_register', $mutator($session));
            }
        } catch (\Throwable) {
            // Session may be unavailable in some test / API-only contexts.
        }

        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return;
        }

        try {
            $cached = Cache::get('risk_api_token:'.$bearer);
            if (! is_array($cached)) {
                return;
            }

            Cache::put('risk_api_token:'.$bearer, $mutator($cached), now()->addHours(12));
        } catch (\Throwable) {
            // Redis/cache optional for bearer overlay; web session already updated above.
        }
    }
}
