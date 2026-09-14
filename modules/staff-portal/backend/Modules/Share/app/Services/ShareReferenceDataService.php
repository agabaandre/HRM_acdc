<?php

namespace Modules\Share\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CI3 Share payload builders for APM sync (structure must stay unchanged).
 */
class ShareReferenceDataService
{
    /**
     * GET /share/get_current_staff — JSON array of staff+latest-contract rows.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function currentStaff(array $filters = [], ?int $limit = null, ?int $start = null): array
    {
        if (! Schema::hasTable('staff') || ! Schema::hasTable('staff_contracts')) {
            return [];
        }

        $q = DB::table('staff as s')
            ->leftJoin('staff_contracts as sc', 'sc.staff_id', '=', 's.staff_id')
            ->leftJoin('grades as g', 'g.grade_id', '=', 'sc.grade_id')
            ->leftJoin('nationalities as n', 'n.nationality_id', '=', 's.nationality_id')
            ->leftJoin('regions as reg', 'reg.id', '=', 'n.region_id')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->leftJoin('duty_stations as ds', 'ds.duty_station_id', '=', 'sc.duty_station_id')
            ->leftJoin('funders as f', 'f.funder_id', '=', 'sc.funder_id')
            ->leftJoin('contracting_institutions as ci', 'ci.contracting_institution_id', '=', 'sc.contracting_institution_id')
            ->leftJoin('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id')
            ->leftJoin('jobs as j', 'j.job_id', '=', 'sc.job_id')
            ->leftJoin('jobs_acting as ja', 'ja.job_acting_id', '=', 'sc.job_acting_id')
            ->leftJoin('status as st', 'st.status_id', '=', 'sc.status_id')
            ->whereIn('sc.staff_contract_id', function ($sub): void {
                $sub->selectRaw('MAX(staff_contract_id)')
                    ->from('staff_contracts')
                    ->groupBy('staff_id');
            })
            ->whereIn('sc.status_id', [1, 2, 3, 7])
            ->orderBy('s.fname');

        $select = [
            's.SAPNO', 's.title', 's.fname', 's.lname', 's.oname',
            'sc.grade_id', 'g.grade', 's.date_of_birth', 's.gender',
            'sc.job_id', 'j.job_name', 'sc.job_acting_id', 'ja.job_acting',
            'ci.contracting_institution', 'ci.contracting_institution_id',
            'ct.contract_type', 'n.nationality', 'reg.region_name', 'd.division_name',
            'sc.first_supervisor', 'sc.second_supervisor', 'f.funder', 'f.funder_id',
            'ds.duty_station_name', 's.initiation_date', 'sc.status_id', 'sc.start_date', 'sc.end_date',
            'st.status', 'sc.duty_station_id', 'sc.contract_type_id',
            's.email_status', 's.email_disabled_at', 's.email_disabled_by',
            'sc.division_id', 's.nationality_id', 's.staff_id', 's.tel_1', 's.tel_2', 's.whatsapp',
            's.work_email', 's.photo', 's.signature', 's.private_email', 's.physical_location',
            's.created_at as staff_created_at', 's.updated_at as staff_updated_at',
            'sc.created_at as contract_created_at', 'sc.updated_at as contract_updated_at',
            'sc.other_associated_divisions',
        ];

        if (! empty($filters['division_id'])) {
            $ids = is_array($filters['division_id']) ? $filters['division_id'] : [$filters['division_id']];
            $q->whereIn('sc.division_id', $ids);
        }
        if (! empty($filters['duty_station_id'])) {
            $ids = is_array($filters['duty_station_id']) ? $filters['duty_station_id'] : [$filters['duty_station_id']];
            $q->whereIn('sc.duty_station_id', $ids);
        }
        if (! empty($filters['funder_id'])) {
            $ids = is_array($filters['funder_id']) ? $filters['funder_id'] : [$filters['funder_id']];
            $q->whereIn('sc.funder_id', $ids);
        }
        if (! empty($filters['job_id'])) {
            $ids = is_array($filters['job_id']) ? $filters['job_id'] : [$filters['job_id']];
            $q->whereIn('sc.job_id', $ids);
        }
        if (! empty($filters['grade_id'])) {
            $ids = is_array($filters['grade_id']) ? $filters['grade_id'] : [$filters['grade_id']];
            $q->whereIn('sc.grade_id', $ids);
        }
        if (isset($filters['region_id']) && $filters['region_id'] !== '' && $filters['region_id'] !== null) {
            $q->where('n.region_id', (int) $filters['region_id']);
        }
        if (! empty($filters['staff_id'])) {
            $q->where('s.staff_id', (int) $filters['staff_id']);
        }
        if (! empty($filters['lname'])) {
            $term = (string) $filters['lname'];
            $q->where(function ($w) use ($term): void {
                $w->where('s.lname', 'like', '%'.$term.'%')
                    ->orWhere('s.fname', 'like', '%'.$term.'%');
            });
        }

        if ($limit !== null && $limit > 0) {
            $q->limit($limit)->offset(max(0, (int) ($start ?? 0)));
        }

        $rows = $q->get($select);

        return $rows->map(function ($row) {
            $arr = (array) $row;
            $raw = $arr['other_associated_divisions'] ?? '';
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $arr['associated_divisions'] = is_array($decoded) ? $decoded : [];

            return $arr;
        })->values()->all();
    }

    /**
     * GET /share/divisions — raw divisions table rows (JSON array).
     *
     * @return list<array<string, mixed>>
     */
    public function divisions(): array
    {
        if (! Schema::hasTable('divisions')) {
            return [];
        }

        return DB::table('divisions')->orderBy('division_name')->get()->map(fn ($r) => (array) $r)->values()->all();
    }

    /**
     * GET /share/directorates — directorates + nested director object (CI parity).
     *
     * @return list<array<string, mixed>>
     */
    public function directorates(): array
    {
        if (! Schema::hasTable('directorates')) {
            return [];
        }

        $hasDirectorId = Schema::hasColumn('directorates', 'director_id');
        $q = DB::table('directorates as dir');
        if ($hasDirectorId) {
            $q->leftJoin('staff as s', 's.staff_id', '=', 'dir.director_id')
                ->select([
                    'dir.*',
                    's.fname as director_fname',
                    's.lname as director_lname',
                    's.title as director_title',
                ]);
        } else {
            $q->select('dir.*');
        }
        $q->orderBy('dir.name');

        return $q->get()->map(function ($row) use ($hasDirectorId) {
            $arr = (array) $row;
            if (array_key_exists('director_id', $arr)) {
                $raw = $arr['director_id'];
                $arr['director_id'] = ($raw !== null && $raw !== '' && (int) $raw > 0) ? (int) $raw : null;
            } elseif (! $hasDirectorId) {
                $arr['director_id'] = null;
            }

            $did = $arr['director_id'] ?? null;
            $fname = trim((string) ($arr['director_fname'] ?? ''));
            $lname = trim((string) ($arr['director_lname'] ?? ''));
            $title = trim((string) ($arr['director_title'] ?? ''));
            unset($arr['director_fname'], $arr['director_lname'], $arr['director_title']);

            if ($did) {
                $name = trim(implode(' ', array_filter([$title, $fname, $lname])));
                if ($name === '') {
                    $name = 'Staff '.$did;
                }
                $arr['director'] = [
                    'id' => $did,
                    'fname' => $fname,
                    'lname' => $lname,
                    'title' => $title !== '' ? $title : null,
                    'name' => $name,
                ];
            } else {
                $arr['director'] = null;
            }

            return $arr;
        })->values()->all();
    }
}
