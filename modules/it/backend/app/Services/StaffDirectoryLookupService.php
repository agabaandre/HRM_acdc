<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Staff names and divisions for the IT module, read from the staff portal database.
 */
class StaffDirectoryLookupService
{
    /**
     * @return array{name:string,work_email:string,division_id:?int,directorate_id:?int,duty_station_name:?string}|null
     */
    public function resolveByStaffId(int $staffId): ?array
    {
        if ($staffId < 1) {
            return null;
        }

        $row = DB::connection('staff')->table('staff')
            ->where('staff_id', $staffId)
            ->first(['staff_id', 'fname', 'lname', 'oname', 'work_email']);

        if ($row === null) {
            return null;
        }

        return [
            'name' => $this->fullName($row),
            'work_email' => trim((string) ($row->work_email ?? '')),
            'division_id' => null,
            'directorate_id' => null,
            'duty_station_name' => null,
        ];
    }

    /**
     * @return list<array{id:int,name:string,work_email:string,duty_station_name:?string}>
     */
    public function searchStaff(string $query, int $limit = 40): array
    {
        $limit = max(1, min($limit, 40));
        $q = trim($query);
        $builder = DB::connection('staff')->table('staff')
            ->select(['staff_id', 'fname', 'lname', 'oname', 'work_email'])
            ->orderBy('fname')
            ->limit($limit);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $builder->where(function ($w) use ($like, $q) {
                $w->where('fname', 'like', $like)
                    ->orWhere('lname', 'like', $like)
                    ->orWhere('work_email', 'like', $like);
                if (ctype_digit($q)) {
                    $w->orWhere('staff_id', (int) $q);
                }
            });
        }

        return array_map(function ($row) {
            return [
                'id' => (int) $row->staff_id,
                'name' => $this->fullName($row),
                'work_email' => trim((string) ($row->work_email ?? '')),
                'duty_station_name' => null,
            ];
        }, $builder->get()->all());
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    public function divisionsForSelect(): array
    {
        return DB::connection('staff')->table('divisions')
            ->orderBy('division_name')
            ->get(['division_id', 'division_name'])
            ->map(fn ($row) => [
                'id' => (int) $row->division_id,
                'name' => (string) $row->division_name,
            ])
            ->all();
    }

    private function fullName(object $row): string
    {
        return trim(implode(' ', array_filter([
            trim((string) ($row->fname ?? '')),
            trim((string) ($row->oname ?? '')),
            trim((string) ($row->lname ?? '')),
        ], fn ($part) => $part !== '')));
    }
}
