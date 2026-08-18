<?php

namespace Modules\Staff\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Staff\Shared\StaffStorage;

class StaffProfileService
{
    public function find(int $staffId): ?object
    {
        return DB::table('staff as s')
            ->leftJoin('nationalities as n', 'n.nationality_id', '=', 's.nationality_id')
            ->leftJoin('regions as reg', 'reg.id', '=', 'n.region_id')
            ->where('s.staff_id', $staffId)
            ->select('s.*', 'n.nationality', 'reg.region_name')
            ->first();
    }

    /**
     * Update biodata fields (CI3 parity with staff/update_staff).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateBiodata(int $staffId, array $data): bool
    {
        if ($staffId < 1) {
            return false;
        }

        $old = DB::table('staff')->where('staff_id', $staffId)->first();
        if (! $old) {
            return false;
        }

        // Legacy `staff` columns are NOT NULL — store blanks as empty strings (CI3 parity).
        $payload = [
            'SAPNO' => $this->blankToEmpty($data['SAPNO'] ?? null),
            'title' => trim((string) $data['title']),
            'fname' => trim((string) $data['fname']),
            'lname' => trim((string) $data['lname']),
            'oname' => $this->blankToEmpty($data['oname'] ?? null),
            'date_of_birth' => (string) $data['date_of_birth'],
            'gender' => trim((string) $data['gender']),
            'nationality_id' => (int) $data['nationality_id'],
            'initiation_date' => (string) $data['initiation_date'],
            'tel_1' => $this->blankToEmpty($data['tel_1'] ?? null),
            'tel_2' => $this->blankToEmpty($data['tel_2'] ?? null),
            'whatsapp' => $this->blankToEmpty($data['whatsapp'] ?? null),
            'work_email' => trim((string) $data['work_email']),
            'private_email' => $this->blankToEmpty($data['private_email'] ?? null),
            'physical_location' => $this->blankToEmpty($data['physical_location'] ?? null),
        ];

        if (array_key_exists('next_of_kin', $data) && Schema::hasColumn('staff', 'next_of_kin_json')) {
            $nok = $this->normalizeOptionalNextOfKin((array) ($data['next_of_kin'] ?? []));
            $payload['next_of_kin_json'] = json_encode($nok, JSON_UNESCAPED_UNICODE);
        }

        $updated = DB::table('staff')->where('staff_id', $staffId)->update($payload) !== false;
        if (! $updated) {
            return false;
        }

        $new = DB::table('staff')->where('staff_id', $staffId)->first();
        $audit = app(StaffAuditTrailService::class);
        $snaps = $audit->biodataSnapshots($old, $new, array_keys($payload));
        if ($snaps !== null) {
            [$before, $after] = $snaps;
            $audit->logChange('staff_biodata', 'staff', $staffId, $staffId, $before, $after);
        }

        return true;
    }

    /**
     * Store passport biodata page (image or PDF) for a staff record.
     *
     * @return array{filename: string, passport_url: string|null, passport_is_pdf: bool}
     */
    public function storePassport(int $staffId, UploadedFile $file): array
    {
        if ($staffId < 1 || ! DB::table('staff')->where('staff_id', $staffId)->exists()) {
            throw ValidationException::withMessages(['passport' => ['Staff not found.']]);
        }
        if (! Schema::hasColumn('staff', 'passport_biodata_page')) {
            throw ValidationException::withMessages([
                'passport' => ['Passport biodata is not available on this installation.'],
            ]);
        }

        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        $ok = str_starts_with($mime, 'image/')
            || $mime === 'application/pdf'
            || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'], true);
        if (! $ok || $file->getSize() > 4096 * 1024) {
            throw ValidationException::withMessages([
                'passport' => ['Passport biodata must be an image or PDF up to 4MB.'],
            ]);
        }
        if ($ext === '') {
            $ext = $mime === 'application/pdf' ? 'pdf' : 'jpg';
        }

        $staff = DB::table('staff')->where('staff_id', $staffId)->first(['fname', 'lname']);
        $base = preg_replace(
            '/[^a-zA-Z0-9_\-.]/',
            '',
            str_replace(' ', '_', trim(($staff->lname ?? '').'_'.($staff->fname ?? '')))
        ) ?: 'staff';
        $filename = substr((string) $base, 0, 40).'_passport_'.time().'.'.$ext;
        $dir = StaffStorage::ciPath('staff/passport_biodata');
        $this->storeUploadedFile($file, $dir, $filename);
        DB::table('staff')->where('staff_id', $staffId)->update(['passport_biodata_page' => $filename]);

        return [
            'filename' => $filename,
            'passport_url' => $this->passportUrl($filename),
            'passport_is_pdf' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'pdf',
        ];
    }

    public function passportUrl(?string $filename): ?string
    {
        if ($filename === null || trim($filename) === '') {
            return null;
        }
        $safe = basename(str_replace('\\', '/', $filename));

        return route('staff.media.passport', ['filename' => $safe]);
    }

    /**
     * Optional next-of-kin for HR create/edit: empty rows allowed; partial rows must be complete.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{name: string, relationship_id: int, phone: string, email: string}>
     */
    public function normalizeOptionalNextOfKin(array $rows): array
    {
        $this->assertOptionalNextOfKin($rows);

        $out = [];
        foreach (array_slice(array_values($rows), 0, 2) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $rid = (int) ($row['relationship_id'] ?? 0);
            $phone = trim((string) ($row['phone'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            if ($name === '' && $rid <= 0 && $phone === '' && $email === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'relationship_id' => $rid,
                'phone' => $phone,
                'email' => $email,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function assertOptionalNextOfKin(array $rows): void
    {
        foreach (array_slice(array_values($rows), 0, 2) as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $rid = (int) ($row['relationship_id'] ?? 0);
            $phone = trim((string) ($row['phone'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $any = $name !== '' || $rid > 0 || $phone !== '' || $email !== '';
            if (! $any) {
                continue;
            }
            $label = $i === 0 ? 'primary' : 'secondary';
            if ($name === '' || $rid <= 0) {
                throw ValidationException::withMessages([
                    "next_of_kin.{$i}" => ["Next of kin ({$label}): enter full name and relationship, or clear the row."],
                ]);
            }
            if ($phone === '') {
                throw ValidationException::withMessages([
                    "next_of_kin.{$i}.phone" => ["Next of kin ({$label}): phone is required when the row is used."],
                ]);
            }
            if ($email === '') {
                throw ValidationException::withMessages([
                    "next_of_kin.{$i}.email" => ["Next of kin ({$label}): email is required when the row is used."],
                ]);
            }
        }
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function kinRelationshipTypes(): array
    {
        if (! Schema::hasTable('kin_relationship_types')) {
            return [];
        }

        return DB::table('kin_relationship_types')
            ->orderBy('relationship_name')
            ->get(['kin_relationship_id as id', 'relationship_name as name'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name])
            ->all();
    }

    private function blankToEmpty(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    protected function storeUploadedFile(UploadedFile $file, string $dir, string $filename): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (! is_dir($dir) || ! is_writable($dir)) {
            throw ValidationException::withMessages([
                'passport' => ['Upload directory is not writable. Contact an administrator.'],
            ]);
        }
        $target = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$filename;
        $source = $file->getRealPath();
        if (! is_string($source) || $source === '') {
            throw ValidationException::withMessages([
                'passport' => ['Could not read the uploaded file.'],
            ]);
        }
        if (@copy($source, $target)) {
            @chmod($target, 0644);

            return;
        }
        try {
            $file->move($dir, $filename);
            @chmod($target, 0644);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'passport' => ['Could not save the uploaded file. Please try again.'],
            ]);
        }
    }

    /**
     * @return list<object>
     */
    public function contracts(int $staffId): array
    {
        return DB::table('staff_contracts as sc')
            ->leftJoin('duty_stations as ds', 'ds.duty_station_id', '=', 'sc.duty_station_id')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->leftJoin('jobs as j', 'j.job_id', '=', 'sc.job_id')
            ->leftJoin('jobs_acting as ja', 'ja.job_acting_id', '=', 'sc.job_acting_id')
            ->leftJoin('funders as f', 'f.funder_id', '=', 'sc.funder_id')
            ->leftJoin('contracting_institutions as ci', 'ci.contracting_institution_id', '=', 'sc.contracting_institution_id')
            ->leftJoin('contract_types as ct', 'ct.contract_type_id', '=', 'sc.contract_type_id')
            ->leftJoin('grades as g', 'g.grade_id', '=', 'sc.grade_id')
            ->leftJoin('status as st', 'st.status_id', '=', 'sc.status_id')
            ->leftJoin('staff as sup1', 'sup1.staff_id', '=', 'sc.first_supervisor')
            ->leftJoin('staff as sup2', 'sup2.staff_id', '=', 'sc.second_supervisor')
            ->where('sc.staff_id', $staffId)
            ->orderByDesc('sc.staff_contract_id')
            ->select(
                'sc.*',
                'ds.duty_station_name',
                'd.division_name',
                'j.job_name',
                'ja.job_acting',
                'f.funder',
                'ci.contracting_institution',
                'ct.contract_type',
                'g.grade',
                'st.status as status_label',
                DB::raw("TRIM(CONCAT(COALESCE(sup1.fname,''), ' ', COALESCE(sup1.lname,''))) as first_supervisor_name"),
                DB::raw("TRIM(CONCAT(COALESCE(sup2.fname,''), ' ', COALESCE(sup2.lname,''))) as second_supervisor_name")
            )
            ->get()
            ->all();
    }
}
