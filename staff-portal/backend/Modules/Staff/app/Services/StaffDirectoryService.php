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
     */
    public function paginate(
        string $search = '',
        int|array|null $statusId = null,
        int $page = 1,
        int $perPage = 20,
        string $category = 'main_staff'
    ): LengthAwarePaginator {
        $perPage = min(100, max(10, $perPage));
        $page = max(1, $page);
        $category = $this->normalizeCategory($category);

        $light = $this->lightQuery($search, $statusId, $category);
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
     * @return array<string, int>
     */
    public function filterCounts(string $search = '', string $category = 'main_staff'): array
    {
        $category = $this->normalizeCategory($category);
        $cacheKey = 'staff_directory_filter_counts:'.md5($search.'|'.$category);

        return Cache::remember($cacheKey, 60, function () use ($search, $category): array {
            $row = $this->lightQuery($search, null, $category)
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
     * Minimal joins for counts and ID pagination.
     *
     * @param  int|list<int>|null  $statusId
     */
    protected function lightQuery(string $search, int|array|null $statusId, string $category): Builder
    {
        $sub = DB::table('staff_contracts')
            ->selectRaw('staff_id, MAX(staff_contract_id) as cid')
            ->groupBy('staff_id');

        $q = DB::table('staff as s')
            ->joinSub($sub, 'lc', 'lc.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
            ->leftJoin('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id');

        $this->applyStatusFilter($q, $statusId);
        $this->applyCategoryFilter($q, $category);
        $this->applySearchFilter($q, $search);

        return $q;
    }

    /** Full row payload — only for the current page of staff IDs. */
    protected function detailQuery(string $category): Builder
    {
        $sub = DB::table('staff_contracts')
            ->selectRaw('staff_id, MAX(staff_contract_id) as cid')
            ->groupBy('staff_id');

        return DB::table('staff as s')
            ->joinSub($sub, 'lc', 'lc.staff_id', '=', 's.staff_id')
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

    protected function normalizeCategory(string $category): string
    {
        return in_array($category, ['main_staff', 'other_staff', 'all'], true)
            ? $category
            : 'main_staff';
    }
}
