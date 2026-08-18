<?php

namespace Modules\Staff\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\PortalTable;

class StaffHistoryService
{
    public function __construct(
        protected StaffDirectoryService $directory,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: string}|null
     */
    public function normalizePeriod(array $filters): ?array
    {
        $fromRaw = trim((string) ($filters['period_from'] ?? ''));
        $toRaw = trim((string) ($filters['period_to'] ?? ''));
        if ($fromRaw === '' || $toRaw === '') {
            return null;
        }

        try {
            $from = Carbon::parse($fromRaw)->toDateString();
            $to = Carbon::parse($toRaw)->toDateString();
        } catch (\Throwable) {
            return null;
        }

        if ($from > $to) {
            return null;
        }

        return [$from, $to];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(100, max(10, $perPage));
        $page = max(1, $page);
        $period = $this->normalizePeriod($filters);
        $filters = $this->directory->normalizeFilters($filters);

        if ($period === null) {
            return new LengthAwarePaginator(collect(), 0, $perPage, $page, PortalTable::paginationOptions());
        }

        [$periodFrom, $periodTo] = $period;
        $light = $this->overlappingQuery($filters, $periodFrom, $periodTo, false);
        $total = (int) (clone $light)->count(DB::raw('DISTINCT s.staff_id'));

        if ($total === 0) {
            return new LengthAwarePaginator(collect(), 0, $perPage, $page, PortalTable::paginationOptions());
        }

        $ids = (clone $light)
            ->select('s.staff_id', 's.fname', 's.lname')
            ->groupBy('s.staff_id', 's.fname', 's.lname')
            ->orderBy('s.fname')
            ->orderBy('s.lname')
            ->forPage($page, $perPage)
            ->pluck('s.staff_id');

        if ($ids->isEmpty()) {
            return new LengthAwarePaginator(collect(), $total, $perPage, $page, PortalTable::paginationOptions());
        }

        $items = $this->rowsForStaffIds($filters, $periodFrom, $periodTo, $ids->all());

        return new LengthAwarePaginator($items, $total, $perPage, $page, PortalTable::paginationOptions());
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportRows(array $filters, int $limit = 5000): Collection
    {
        $limit = min(5000, max(1, $limit));
        $period = $this->normalizePeriod($filters);
        $filters = $this->directory->normalizeFilters($filters);
        if ($period === null) {
            return collect();
        }

        [$periodFrom, $periodTo] = $period;
        $ids = $this->overlappingQuery($filters, $periodFrom, $periodTo, false)
            ->select('s.staff_id', 's.fname', 's.lname')
            ->groupBy('s.staff_id', 's.fname', 's.lname')
            ->orderBy('s.fname')
            ->orderBy('s.lname')
            ->limit($limit)
            ->pluck('s.staff_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return collect($this->rowsForStaffIds($filters, $periodFrom, $periodTo, $ids->all()));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $staffIds
     * @return list<object>
     */
    protected function rowsForStaffIds(array $filters, string $periodFrom, string $periodTo, array $staffIds): array
    {
        $rows = $this->overlappingQuery($filters, $periodFrom, $periodTo, true)
            ->whereIn('s.staff_id', $staffIds)
            ->orderBy('s.fname')
            ->orderBy('sc.staff_contract_id', 'desc')
            ->get();

        $picked = $this->pickBestContractPerStaff($rows, $periodFrom, $periodTo);
        $byId = [];
        foreach ($picked as $row) {
            $byId[(int) $row->staff_id] = $row;
        }

        $ordered = [];
        foreach ($staffIds as $staffId) {
            if (isset($byId[$staffId])) {
                $ordered[] = $byId[$staffId];
            }
        }

        return $ordered;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function overlappingQuery(array $filters, string $periodFrom, string $periodTo, bool $detailed): Builder
    {
        $q = DB::table('staff as s')
            ->join('staff_contracts as sc', 'sc.staff_id', '=', 's.staff_id')
            ->leftJoin('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id');

        $this->applyOverlap($q, $periodFrom, $periodTo);
        $this->directory->applyListFilters($q, $filters);

        if ($detailed) {
            $q->leftJoin('grades as g', 'g.grade_id', '=', 'sc.grade_id')
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
                    'sc.staff_contract_id',
                    'sc.start_date',
                    'sc.end_date',
                    'sc.status_id',
                    'st.status as contract_status',
                    'f.funder',
                    DB::raw("TRIM(CONCAT(COALESCE(sup1.fname,''), ' ', COALESCE(sup1.lname,''))) as first_supervisor_name"),
                    DB::raw("TRIM(CONCAT(COALESCE(sup2.fname,''), ' ', COALESCE(sup2.lname,''))) as second_supervisor_name"),
                ]);
        }

        return $q;
    }

    protected function applyOverlap(Builder $q, string $periodFrom, string $periodTo): void
    {
        $q->where('sc.start_date', '<=', $periodTo)
            ->where(function (Builder $w) use ($periodFrom): void {
                $w->whereNull('sc.end_date')
                    ->orWhere('sc.end_date', '>=', $periodFrom)
                    ->orWhere('sc.end_date', '<', '1900-01-01');
            });
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<object>
     */
    protected function pickBestContractPerStaff(Collection $rows, string $periodFrom, string $periodTo): array
    {
        $byStaff = [];
        foreach ($rows as $row) {
            $days = $this->overlapDays($row, $periodFrom, $periodTo);
            if ($days < 0) {
                continue;
            }
            $staffId = (int) $row->staff_id;
            $tie = ($days * 10000000) + (int) ($row->staff_contract_id ?? 0);
            if (! isset($byStaff[$staffId]) || $tie > $byStaff[$staffId]['tie']) {
                $byStaff[$staffId] = ['tie' => $tie, 'row' => $row];
            }
        }

        return array_values(array_map(static fn (array $item): object => $item['row'], $byStaff));
    }

    protected function overlapDays(object $row, string $periodFrom, string $periodTo): int
    {
        $start = trim((string) ($row->start_date ?? ''));
        $end = trim((string) ($row->end_date ?? ''));
        if ($start === '' || $start === '0000-00-00') {
            return -1;
        }
        if ($end === '' || $end === '0000-00-00') {
            $end = '9999-12-31';
        }

        try {
            $contractStart = Carbon::parse($start)->startOfDay();
            $contractEnd = Carbon::parse($end)->startOfDay();
            $windowStart = Carbon::parse($periodFrom)->startOfDay();
            $windowEnd = Carbon::parse($periodTo)->startOfDay();
        } catch (\Throwable) {
            return -1;
        }

        if ($contractStart->gt($windowEnd) || $contractEnd->lt($windowStart)) {
            return -1;
        }

        $from = $contractStart->greaterThan($windowStart) ? $contractStart : $windowStart;
        $to = $contractEnd->lessThan($windowEnd) ? $contractEnd : $windowEnd;
        if ($to->lt($from)) {
            return -1;
        }

        return $from->diffInDays($to) + 1;
    }
}
