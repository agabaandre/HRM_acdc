<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Leave\Http\Resources\Api\V1\LeaveTypeResource;
use Modules\Leave\Models\LeaveType;

/**
 * Redis (or default) cache for dropdown / reference payloads.
 * Mirrors helpdesk ReferenceDataController + ReferenceDataSyncService.
 */
final class PortalReferenceCache
{
    public const FORM_LOOKUPS_KEY = 'staff_portal:form_lookups_v2';

    public const LEAVE_TYPES_KEY = 'staff_portal:leave_types_v1';

    public const PERMISSIONS_CATALOG_KEY = 'staff_portal:permissions_catalog_v1';

    /**
     * Lookup tables that feed staff form dropdowns.
     *
     * @var list<string>
     */
    public const FORM_LOOKUP_TABLES = [
        'jobs',
        'jobs_acting',
        'grades',
        'contracting_institutions',
        'funders',
        'contract_types',
        'duty_stations',
        'divisions',
        'units',
        'status',
        'nationalities',
    ];

    public static function ttl(): int
    {
        return max(30, (int) config('staff-portal.reference_data_cache_ttl', 300));
    }

    public static function remember(string $key, callable $callback): mixed
    {
        return Cache::remember($key, self::ttl(), $callback);
    }

    public static function forget(string $key): void
    {
        Cache::forget($key);
    }

    public static function formLookupsKey(int $excludeStaffId = 0): string
    {
        return self::FORM_LOOKUPS_KEY.($excludeStaffId > 0 ? ':x'.$excludeStaffId : '');
    }

    public static function leaveTypesKey(): string
    {
        return self::LEAVE_TYPES_KEY;
    }

    public static function lookupListKey(string $table): string
    {
        return 'staff_portal:lookup:'.$table.':v1';
    }

    public static function bustFormLookups(): void
    {
        Cache::forget(self::FORM_LOOKUPS_KEY);
        // Best-effort: common exclude variants are rare; warm command rebuilds the base key.
    }

    public static function bustLeaveTypes(): void
    {
        Cache::forget(self::LEAVE_TYPES_KEY);
    }

    public static function bustPermissionsCatalog(): void
    {
        Cache::forget(self::PERMISSIONS_CATALOG_KEY);
    }

    public static function bustLookup(string $table): void
    {
        Cache::forget(self::lookupListKey($table));
        if (in_array($table, self::FORM_LOOKUP_TABLES, true) || $table === 'staff' || $table === 'regions') {
            self::bustFormLookups();
            Cache::forget('staff_portal:staff_filter_options_v1');
        }
        if ($table === 'leave_types') {
            self::bustLeaveTypes();
        }
    }

    public static function flush(): void
    {
        self::bustFormLookups();
        self::bustLeaveTypes();
        self::bustPermissionsCatalog();
        foreach (self::FORM_LOOKUP_TABLES as $table) {
            Cache::forget(self::lookupListKey($table));
        }
    }

    /**
     * @return array{form_lookups: int, leave_types: int}
     */
    public static function warm(): array
    {
        $lookups = self::remember(self::FORM_LOOKUPS_KEY, fn () => self::buildFormLookups(0));
        $types = self::remember(self::LEAVE_TYPES_KEY, fn () => self::buildLeaveTypes());

        return [
            'form_lookups' => is_array($lookups) ? count($lookups) : 0,
            'leave_types' => is_array($types) ? count($types) : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildFormLookups(int $excludeStaffId = 0): array
    {
        $supervisors = DB::table('staff')
            ->when($excludeStaffId > 0, fn ($q) => $q->where('staff_id', '!=', $excludeStaffId))
            ->orderBy('lname')
            ->orderBy('fname')
            ->select('staff_id', 'fname', 'lname')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $table = static fn (string $name, string $order) => DB::table($name)
            ->orderBy($order)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return [
            'jobs' => self::jobsWithCategoryLabels(),
            'jobsActing' => self::jobsActingWithCategoryLabels(),
            'grades' => $table('grades', 'grade'),
            'institutions' => $table('contracting_institutions', 'contracting_institution'),
            'funders' => $table('funders', 'funder'),
            'contractTypes' => self::contractTypesWithCategoryLabels(),
            'dutyStations' => $table('duty_stations', 'duty_station_name'),
            'divisions' => $table('divisions', 'division_name'),
            'units' => DB::getSchemaBuilder()->hasTable('units')
                ? $table('units', 'unit_name')
                : [],
            'statuses' => $table('status', 'status_id'),
            'nationalities' => $table('nationalities', 'nationality'),
            'supervisors' => $supervisors,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function jobsWithCategoryLabels(): array
    {
        $gradeByJob = self::mostCommonGradeByColumn('job_id');

        return DB::table('jobs')
            ->orderBy('job_name')
            ->get(['job_id', 'job_name'])
            ->map(function ($row) use ($gradeByJob) {
                $jobId = (int) $row->job_id;
                $name = (string) $row->job_name;
                $category = self::jobCategoryLabel($gradeByJob[$jobId] ?? null);

                return [
                    'job_id' => $jobId,
                    'job_name' => $name,
                    'job_category' => $category,
                    'label' => $category !== null ? "{$name} ({$category})" : $name,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function jobsActingWithCategoryLabels(): array
    {
        $gradeByActing = self::mostCommonGradeByColumn('job_acting_id');

        return DB::table('jobs_acting')
            ->orderBy('job_acting')
            ->get(['job_acting_id', 'job_acting'])
            ->map(function ($row) use ($gradeByActing) {
                $id = (int) $row->job_acting_id;
                $name = (string) $row->job_acting;
                $category = self::jobCategoryLabel($gradeByActing[$id] ?? null);

                return [
                    'job_acting_id' => $id,
                    'job_acting' => $name,
                    'job_category' => $category,
                    'label' => $category !== null ? "{$name} ({$category})" : $name,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function contractTypesWithCategoryLabels(): array
    {
        return DB::table('contract_types')
            ->orderBy('contract_type')
            ->get(['contract_type_id', 'contract_type', 'category'])
            ->map(function ($row) {
                $name = trim((string) $row->contract_type);
                $category = self::humanizeStaffCategory((string) ($row->category ?? ''));

                return [
                    'contract_type_id' => (int) $row->contract_type_id,
                    'contract_type' => $name,
                    'category' => (string) ($row->category ?? ''),
                    'label' => $category !== '' ? "{$name} ({$category})" : $name,
                ];
            })
            ->all();
    }

    /**
     * Most common grade code for each job / acting job from active contract history.
     *
     * @return array<int, string>
     */
    private static function mostCommonGradeByColumn(string $column): array
    {
        if (! in_array($column, ['job_id', 'job_acting_id'], true)) {
            return [];
        }

        $rows = DB::table('staff_contracts as sc')
            ->join('grades as g', 'g.grade_id', '=', 'sc.grade_id')
            ->whereNotNull("sc.{$column}")
            ->where("sc.{$column}", '>', 0)
            ->whereNotNull('sc.grade_id')
            ->where('sc.grade_id', '!=', '')
            ->groupBy("sc.{$column}", 'g.grade')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get([
                DB::raw("sc.{$column} as ref_id"),
                'g.grade',
                DB::raw('COUNT(*) as usage_count'),
            ]);

        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row->ref_id;
            if ($id < 1 || isset($map[$id])) {
                continue;
            }
            $map[$id] = (string) $row->grade;
        }

        return $map;
    }

    private static function jobCategoryLabel(?string $grade): ?string
    {
        if ($grade === null || trim($grade) === '') {
            return null;
        }

        $g = strtoupper(trim($grade));
        $family = match (true) {
            str_starts_with($g, 'D') => 'Director',
            str_starts_with($g, 'P') => 'Professional',
            str_starts_with($g, 'G') => 'General Service',
            str_starts_with($g, 'NO') => 'National Officer',
            str_starts_with($g, 'FS') => 'Field Service',
            default => null,
        };

        return $family !== null ? "{$family} · {$grade}" : $grade;
    }

    private static function humanizeStaffCategory(string $category): string
    {
        return match ($category) {
            'main_staff' => 'Main staff',
            'other_staff' => 'Other staff',
            default => $category !== ''
                ? ucwords(str_replace('_', ' ', $category))
                : '',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function buildLeaveTypes(): array
    {
        $types = LeaveType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('leave_name')
            ->get();

        return LeaveTypeResource::collection($types)->resolve();
    }
}
