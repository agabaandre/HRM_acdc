<?php

namespace Modules\Staff\Services;

use App\Support\StaffPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NextOfKinReportService
{
    public function __construct(
        protected StaffDirectoryService $directory,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, total: int, kin_relationships: list<array{id: int, name: string}>}
     */
    public function page(array $filters, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(10, $perPage));
        $filters = $this->directory->normalizeFilters($filters);
        $kinMap = $this->kinNameById();
        $base = $this->baseQuery($filters);
        $total = (int) (clone $base)->count();
        $raw = (clone $base)
            ->orderBy('s.lname')
            ->orderBy('s.fname')
            ->forPage($page, $perPage)
            ->get();

        return [
            'rows' => array_values(array_map(fn ($r) => $this->presentRow($r, $kinMap), $raw->all())),
            'total' => $total,
            'kin_relationships' => $this->kinRelationshipsList($kinMap),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters, int $limit = 5000): array
    {
        $filters = $this->directory->normalizeFilters($filters);
        $kinMap = $this->kinNameById();
        $raw = $this->baseQuery($filters)
            ->orderBy('s.lname')
            ->orderBy('s.fname')
            ->limit(min(5000, max(1, $limit)))
            ->get();

        return array_values(array_map(fn ($r) => $this->presentRow($r, $kinMap), $raw->all()));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function baseQuery(array $filters)
    {
        $latest = DB::table('staff_contracts')
            ->selectRaw('staff_id, MAX(staff_contract_id) as cid')
            ->groupBy('staff_id');

        $select = [
            's.staff_id',
            's.SAPNO',
            's.title',
            's.fname',
            's.lname',
            's.oname',
            's.photo',
            's.work_email',
            's.tel_1',
            's.tel_2',
            's.whatsapp',
            's.private_email',
            's.physical_location',
            'j.job_name',
            'ds.duty_station_name',
            'd.division_name',
            'st.status as contract_status_label',
            'g.grade',
        ];

        if (Schema::hasColumn('staff', 'residential_address_duty_station')) {
            $select[] = 's.residential_address_duty_station';
        }
        if (Schema::hasColumn('staff', 'number_of_dependants')) {
            $select[] = 's.number_of_dependants';
        }
        if (Schema::hasColumn('staff', 'next_of_kin_json')) {
            $select[] = 's.next_of_kin_json';
        }

        $q = DB::table('staff as s')
            ->joinSub($latest, 'lc', 'lc.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
            ->leftJoin('funders as f', 'f.funder_id', '=', 'sc.funder_id')
            ->leftJoin('grades as g', 'g.grade_id', '=', 'sc.grade_id')
            ->leftJoin('nationalities as n', 'n.nationality_id', '=', 's.nationality_id')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->leftJoin('duty_stations as ds', 'ds.duty_station_id', '=', 'sc.duty_station_id')
            ->leftJoin('jobs as j', 'j.job_id', '=', 'sc.job_id')
            ->leftJoin('status as st', 'st.status_id', '=', 'sc.status_id')
            ->whereIn('sc.status_id', [1, 2, 7])
            ->select($select);

        if ($filters['division_id'] !== []) {
            $q->whereIn('sc.division_id', $filters['division_id']);
        }
        if ($filters['duty_station_id'] !== []) {
            $q->whereIn('sc.duty_station_id', $filters['duty_station_id']);
        }
        if ($filters['funder_id'] !== []) {
            $q->whereIn('sc.funder_id', $filters['funder_id']);
        }
        if ($filters['job_id'] !== []) {
            $q->whereIn('sc.job_id', $filters['job_id']);
        }
        if ($filters['grade_id'] !== []) {
            $q->whereIn('sc.grade_id', $filters['grade_id']);
        }
        if ($filters['region_id'] !== null) {
            $q->where('n.region_id', $filters['region_id']);
        }
        if ($filters['nationality_id'] !== null) {
            $q->where('s.nationality_id', $filters['nationality_id']);
        }
        if ($filters['gender'] !== '') {
            $q->where('s.gender', $filters['gender']);
        }
        if ($filters['sapno'] !== '') {
            $q->where('s.SAPNO', 'like', '%'.$filters['sapno'].'%');
        }
        if ($filters['name'] !== '') {
            $name = $filters['name'];
            $q->where(function ($w) use ($name): void {
                $w->where('s.lname', 'like', '%'.$name.'%')
                    ->orWhere('s.fname', 'like', '%'.$name.'%')
                    ->orWhere('s.oname', 'like', '%'.$name.'%');
            });
        }

        return $q;
    }

    /**
     * @param  array<int, string>  $kinMap
     * @return array<string, mixed>
     */
    protected function presentRow(object $r, array $kinMap): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
            trim((string) ($r->title ?? '')),
            trim((string) ($r->fname ?? '')),
            trim((string) ($r->lname ?? '')),
            trim((string) ($r->oname ?? '')),
        ]))) ?? '');

        $nok = $this->parseNextOfKin($r->next_of_kin_json ?? null, $kinMap);
        $photo = trim((string) ($r->photo ?? ''));

        return [
            'staff_id' => (int) $r->staff_id,
            'SAPNO' => $r->SAPNO,
            'title' => $r->title,
            'fname' => $r->fname,
            'lname' => $r->lname,
            'oname' => $r->oname,
            'full_name' => $fullName,
            'photo' => $photo !== '' ? $photo : null,
            'photo_url' => StaffPhoto::url($photo !== '' ? $photo : null),
            'work_email' => $r->work_email,
            'tel_1' => $r->tel_1,
            'tel_2' => $r->tel_2,
            'whatsapp' => $r->whatsapp,
            'private_email' => $r->private_email,
            'physical_location' => $r->physical_location,
            'residential_address_duty_station' => $r->residential_address_duty_station ?? null,
            'number_of_dependants' => $r->number_of_dependants ?? null,
            'job_name' => $r->job_name,
            'duty_station_name' => $r->duty_station_name,
            'division_name' => $r->division_name,
            'contract_status_label' => $r->contract_status_label,
            'grade' => $r->grade,
            'next_of_kin' => $nok,
        ];
    }

    /**
     * @param  array<int, string>  $kinMap
     * @return list<array{name: string, relationship_id: int, relationship_name: string, phone: string, email: string}>
     */
    protected function parseNextOfKin(mixed $json, array $kinMap): array
    {
        $raw = json_decode((string) ($json ?? '[]'), true);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $relId = (int) ($row['relationship_id'] ?? 0);
            $phone = trim((string) ($row['phone'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            if ($phone === '' && $email === '' && ! empty($row['contact'])) {
                $c = trim((string) $row['contact']);
                if ($c !== '' && str_contains($c, '@')) {
                    $email = $c;
                } elseif ($c !== '') {
                    $phone = $c;
                }
            }
            if ($name === '' && $phone === '' && $email === '' && $relId < 1) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'relationship_id' => $relId,
                'relationship_name' => $relId > 0 ? ($kinMap[$relId] ?? '') : '',
                'phone' => $phone,
                'email' => $email,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    protected function kinNameById(): array
    {
        if (! Schema::hasTable('kin_relationship_types')) {
            return [];
        }

        $map = [];
        foreach (DB::table('kin_relationship_types')->get() as $kt) {
            $map[(int) $kt->kin_relationship_id] = (string) $kt->relationship_name;
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $kinMap
     * @return list<array{id: int, name: string}>
     */
    protected function kinRelationshipsList(array $kinMap): array
    {
        $list = [];
        foreach ($kinMap as $id => $name) {
            $list[] = ['id' => $id, 'name' => $name];
        }

        return $list;
    }
}
