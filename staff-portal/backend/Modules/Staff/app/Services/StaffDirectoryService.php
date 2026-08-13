<?php

namespace Modules\Staff\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\PortalTable;

class StaffDirectoryService
{
    /**
     * @param  int|list<int>|null  $statusId
     * @param  array<string, mixed>  $filters
     */
    public function paginate(
        string $search = '',
        int|array|null $statusId = null,
        int $page = 1,
        int $perPage = 20,
        string $category = 'main_staff',
        array $filters = [],
    ): LengthAwarePaginator {
        $perPage = min(100, max(10, $perPage));
        $page = max(1, $page);
        $category = $this->normalizeCategory($category);
        $filters = $this->normalizeFilters($filters);

        $light = $this->lightQuery($search, $statusId, $category, $filters);
        $total = (int) (clone $light)->count(DB::raw('DISTINCT s.staff_id'));

        if ($total === 0) {
            return new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                $page,
                PortalTable::paginationOptions($pageName = 'page')
            );
        }

        $ids = (clone $light)
            ->select('s.staff_id', 's.lname', 's.fname')
            ->groupBy('s.staff_id', 's.lname', 's.fname')
            ->orderBy('s.lname')
            ->orderBy('s.fname')
            ->forPage($page, $perPage)
            ->pluck('s.staff_id');

        if ($ids->isEmpty()) {
            return new LengthAwarePaginator(
                collect(),
                $total,
                $perPage,
                $page,
                PortalTable::paginationOptions()
            );
        }

        $items = $this->detailQuery($category)
            ->whereIn('s.staff_id', $ids->all())
            ->orderBy('s.lname')
            ->orderBy('s.fname')
            ->get();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            PortalTable::paginationOptions()
        );
    }

    /**
     * @param  int|list<int>|null  $statusId
     * @param  array<string, mixed>  $filters
     */
    public function exportRows(
        string $search = '',
        int|array|null $statusId = null,
        string $category = 'main_staff',
        int $limit = 5000,
        array $filters = [],
    ): Collection {
        $limit = min(5000, max(1, $limit));
        $category = $this->normalizeCategory($category);
        $filters = $this->normalizeFilters($filters);

        $ids = $this->lightQuery($search, $statusId, $category, $filters)
            ->select('s.staff_id', 's.lname', 's.fname')
            ->groupBy('s.staff_id', 's.lname', 's.fname')
            ->orderBy('s.lname')
            ->orderBy('s.fname')
            ->limit($limit)
            ->pluck('s.staff_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return $this->detailQuery($category)
            ->whereIn('s.staff_id', $ids->all())
            ->orderBy('s.lname')
            ->orderBy('s.fname')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function filterCounts(string $search = '', string $category = 'main_staff', array $filters = []): array
    {
        $category = $this->normalizeCategory($category);
        $filters = $this->normalizeFilters($filters);
        $cacheKey = 'staff_directory_filter_counts:'.md5($search.'|'.$category.'|'.json_encode($filters));

        return Cache::remember($cacheKey, 60, function () use ($search, $category, $filters): array {
            $row = $this->lightQuery($search, null, $category, $filters)
                ->selectRaw('
                    COUNT(DISTINCT CASE WHEN sc.status_id IN (1, 2) THEN s.staff_id END) as active_count,
                    COUNT(DISTINCT CASE WHEN sc.status_id = 2 THEN s.staff_id END) as due_count,
                    COUNT(DISTINCT CASE WHEN sc.status_id = 3 THEN s.staff_id END) as expired_count,
                    COUNT(DISTINCT CASE WHEN sc.status_id = 4 THEN s.staff_id END) as former_count,
                    COUNT(DISTINCT CASE WHEN sc.status_id = 7 THEN s.staff_id END) as renewal_count,
                    COUNT(DISTINCT s.staff_id) as all_count
                ')
                ->first();

            return [
                'active' => (int) ($row->active_count ?? 0),
                'due' => (int) ($row->due_count ?? 0),
                'expired' => (int) ($row->expired_count ?? 0),
                'former' => (int) ($row->former_count ?? 0),
                'renewal' => (int) ($row->renewal_count ?? 0),
                'all' => (int) ($row->all_count ?? 0),
            ];
        });
    }

    /**
     * Dropdown options for CI3-parity staff filters (Redis-cached).
     *
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return Cache::remember('staff_portal:staff_filter_options_v1', 300, function (): array {
            $regions = DB::table('regions')->orderBy('region_name')->get(['id', 'region_name'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->region_name])
                ->all();

            $nationalities = DB::table('nationalities')->orderBy('nationality')
                ->get(['nationality_id', 'nationality', 'region_id'])
                ->map(fn ($r) => [
                    'id' => (int) $r->nationality_id,
                    'name' => (string) $r->nationality,
                    'region_id' => $r->region_id === null ? null : (int) $r->region_id,
                ])
                ->all();

            return [
                'regions' => $regions,
                'nationalities' => $nationalities,
                'divisions' => DB::table('divisions')->orderBy('division_name')->get(['division_id', 'division_name'])
                    ->map(fn ($r) => ['id' => (int) $r->division_id, 'name' => (string) $r->division_name])->all(),
                'duty_stations' => DB::table('duty_stations')->orderBy('duty_station_name')->get(['duty_station_id', 'duty_station_name'])
                    ->map(fn ($r) => ['id' => (int) $r->duty_station_id, 'name' => (string) $r->duty_station_name])->all(),
                'funders' => DB::table('funders')->orderBy('funder')->get(['funder_id', 'funder'])
                    ->map(fn ($r) => ['id' => (int) $r->funder_id, 'name' => (string) $r->funder])->all(),
                'jobs' => DB::table('jobs')->orderBy('job_name')->get(['job_id', 'job_name'])
                    ->map(fn ($r) => ['id' => (int) $r->job_id, 'name' => (string) $r->job_name])->all(),
                'grades' => DB::table('grades')->orderBy('grade')->get(['grade_id', 'grade'])
                    ->map(fn ($r) => ['id' => (int) $r->grade_id, 'name' => (string) $r->grade])->all(),
                'genders' => [
                    ['id' => 'Male', 'name' => 'Male'],
                    ['id' => 'Female', 'name' => 'Female'],
                ],
            ];
        });
    }

    /**
     * Minimal joins for counts and ID pagination.
     *
     * @param  int|list<int>|null  $statusId
     * @param  array<string, mixed>  $filters
     */
    protected function lightQuery(string $search, int|array|null $statusId, string $category, array $filters = []): Builder
    {
        $q = DB::table('staff as s')
            ->joinSub($this->selectedContractSubquery(), 'lc', 'lc.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
            ->leftJoin('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id');

        $this->applyStatusFilter($q, $statusId);
        $this->applyCategoryFilter($q, $category);
        $this->applySearchFilter($q, $search);
        $this->applyAdvancedFilters($q, $filters);

        return $q;
    }

    /** Full row payload — only for the current page of staff IDs. */
    protected function detailQuery(string $category): Builder
    {
        return DB::table('staff as s')
            ->joinSub($this->selectedContractSubquery(), 'lc', 'lc.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
            ->leftJoin('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id')
            ->leftJoin('grades as g', 'g.grade_id', '=', 'sc.grade_id')
            ->leftJoin('nationalities as n', 'n.nationality_id', '=', 's.nationality_id')
            ->leftJoin('regions as reg', 'reg.id', '=', 'n.region_id')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->leftJoin('duty_stations as ds', 'ds.duty_station_id', '=', 'sc.duty_station_id')
            ->leftJoin('jobs as j', 'j.job_id', '=', 'sc.job_id')
            ->leftJoin('jobs_acting as ja', 'ja.job_acting_id', '=', 'sc.job_acting_id')
            ->leftJoin('funders as f', 'f.funder_id', '=', 'sc.funder_id')
            ->leftJoin('status as st', 'st.status_id', '=', 'sc.status_id')
            ->leftJoin('staff as sup1', 'sup1.staff_id', '=', 'sc.first_supervisor')
            ->leftJoin('staff as sup2', 'sup2.staff_id', '=', 'sc.second_supervisor')
            ->select([
                's.staff_id',
                's.SAPNO',
                's.title',
                's.fname',
                's.lname',
                's.oname',
                's.photo',
                's.gender',
                's.date_of_birth',
                's.initiation_date',
                's.work_email',
                's.tel_1',
                's.tel_2',
                's.whatsapp',
                'n.nationality',
                'reg.region_name',
                'ct.contract_type',
                'ct.category',
                'g.grade',
                'j.job_name',
                'ja.job_acting',
                'ds.duty_station_name',
                'd.division_name',
                'sc.start_date',
                'sc.end_date',
                'sc.status_id',
                'st.status as contract_status',
                'f.funder',
                DB::raw("TRIM(CONCAT(COALESCE(sup1.fname,''), ' ', COALESCE(sup1.lname,''))) as first_supervisor_name"),
                DB::raw("TRIM(CONCAT(COALESCE(sup2.fname,''), ' ', COALESCE(sup2.lname,''))) as second_supervisor_name"),
            ])
            ->when($category !== 'all', fn (Builder $query) => $query->where('ct.category', $category));
    }

    protected function selectedContractSubquery(): Builder
    {
        return DB::table('staff_contracts')
            ->selectRaw(
                'staff_id, COALESCE(MAX(CASE WHEN status_id IN (1, 2, 7) THEN staff_contract_id END), MAX(staff_contract_id)) as cid'
            )
            ->groupBy('staff_id');
    }

    /**
     * @param  int|list<int>|null  $statusId
     */
    protected function applyStatusFilter(Builder $q, int|array|null $statusId): void
    {
        if (is_array($statusId)) {
            $q->whereIn('sc.status_id', $statusId);
        } elseif ($statusId !== null) {
            $q->where('sc.status_id', $statusId);
        } else {
            $q->whereIn('sc.status_id', [1, 2, 3, 4, 7]);
        }
    }

    protected function applySearchFilter(Builder $q, string $search): void
    {
        if ($search === '') {
            return;
        }

        $term = '%'.$search.'%';
        $q->where(function ($w) use ($term): void {
            $w->where('s.fname', 'like', $term)
                ->orWhere('s.lname', 'like', $term)
                ->orWhere('s.oname', 'like', $term)
                ->orWhere('s.work_email', 'like', $term)
                ->orWhere('s.SAPNO', 'like', $term);
        });
    }

    protected function applyCategoryFilter(Builder $q, string $category): void
    {
        if ($category === 'all') {
            return;
        }

        $q->where('ct.category', $category);
    }

    /**
     * CI3 staff_filters.php parity.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyAdvancedFilters(Builder $q, array $filters): void
    {
        if (($filters['name'] ?? '') !== '') {
            $term = '%'.$filters['name'].'%';
            $q->where(function ($w) use ($term): void {
                $w->where('s.lname', 'like', $term)
                    ->orWhere('s.fname', 'like', $term)
                    ->orWhere('s.oname', 'like', $term);
            });
        }

        if (($filters['sapno'] ?? '') !== '') {
            $q->where('s.SAPNO', 'like', '%'.$filters['sapno'].'%');
        }

        if (($filters['gender'] ?? '') !== '') {
            $q->where('s.gender', $filters['gender']);
        }

        if (($filters['nationality_id'] ?? null) !== null) {
            $q->where('s.nationality_id', (int) $filters['nationality_id']);
        }

        if (array_key_exists('region_id', $filters) && $filters['region_id'] !== null) {
            $regionId = (int) $filters['region_id'];
            $q->leftJoin('nationalities as n_filter', 'n_filter.nationality_id', '=', 's.nationality_id');
            if ($regionId === 0) {
                // Rest of World — null / 0 region on nationality
                $q->where(function ($w): void {
                    $w->whereNull('n_filter.region_id')->orWhere('n_filter.region_id', 0);
                });
            } else {
                $q->where('n_filter.region_id', $regionId);
            }
        }

        foreach ([
            'division_id' => 'sc.division_id',
            'duty_station_id' => 'sc.duty_station_id',
            'funder_id' => 'sc.funder_id',
            'job_id' => 'sc.job_id',
            'grade_id' => 'sc.grade_id',
        ] as $key => $column) {
            $ids = $filters[$key] ?? [];
            if (is_array($ids) && $ids !== []) {
                $q->whereIn($column, $ids);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters): array
    {
        $intList = static function (mixed $value): array {
            if ($value === null || $value === '' || $value === []) {
                return [];
            }
            if (! is_array($value)) {
                $value = [$value];
            }

            return array_values(array_unique(array_filter(
                array_map(static fn ($v) => (int) $v, $value),
                static fn (int $v) => $v > 0
            )));
        };

        $regionRaw = $filters['region_id'] ?? null;
        $regionId = null;
        if ($regionRaw !== null && $regionRaw !== '') {
            $regionId = (int) $regionRaw;
        }

        $nationalityRaw = $filters['nationality_id'] ?? null;

        return [
            'name' => trim((string) ($filters['name'] ?? $filters['lname'] ?? '')),
            'sapno' => trim((string) ($filters['sapno'] ?? $filters['SAPNO'] ?? '')),
            'gender' => in_array(($filters['gender'] ?? ''), ['Male', 'Female'], true)
                ? (string) $filters['gender']
                : '',
            'region_id' => $regionId,
            'nationality_id' => ($nationalityRaw !== null && $nationalityRaw !== '')
                ? (int) $nationalityRaw
                : null,
            'division_id' => $intList($filters['division_id'] ?? null),
            'duty_station_id' => $intList($filters['duty_station_id'] ?? null),
            'funder_id' => $intList($filters['funder_id'] ?? null),
            'job_id' => $intList($filters['job_id'] ?? null),
            'grade_id' => $intList($filters['grade_id'] ?? null),
        ];
    }

    protected function normalizeCategory(string $category): string
    {
        return in_array($category, ['main_staff', 'other_staff', 'all'], true)
            ? $category
            : 'main_staff';
    }
}
