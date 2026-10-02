<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Read-only Staff Portal Share API client for org mirror (divisions / directorates).
 */
class StaffPortalOrgClient
{
    /**
     * Staff Portal style: "Full Division Name (SHORT)".
     */
    public static function formatDivisionLabel(?string $name, ?string $short, ?int $id = null): string
    {
        $name = trim((string) $name);
        $short = trim((string) $short);
        if ($name !== '' && $short !== '' && strcasecmp($name, $short) !== 0) {
            return $name.' ('.$short.')';
        }
        if ($name !== '') {
            return $name;
        }
        if ($short !== '') {
            return $short;
        }

        return $id !== null && $id > 0 ? 'Division '.$id : 'Unassigned';
    }

    /**
     * @return array<int, string> division_id => display label
     */
    public function divisionLabelsById(): array
    {
        $labels = [];
        try {
            $org = $this->fetchOrg();
        } catch (\Throwable) {
            return $labels;
        }
        foreach ($org['divisions'] ?? [] as $div) {
            $id = (int) ($div['division_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $labels[$id] = self::formatDivisionLabel(
                (string) ($div['division_name'] ?? ''),
                (string) ($div['division_short_name'] ?? ''),
                $id
            );
        }

        return $labels;
    }

    public function isConfigured(): bool
    {
        return $this->username() !== '' && $this->password() !== '' && $this->token() !== '';
    }

    /**
     * Org branding from Staff Portal Settings → Branding (Share API).
     *
     * @return array<string, mixed>
     */
    public function fetchBranding(int $timeoutSeconds = 8): array
    {
        $url = $this->buildUrl('branding');
        $response = Http::withBasicAuth($this->username(), $this->password())
            ->timeout(max(1, $timeoutSeconds))
            ->connectTimeout(min(2, max(1, $timeoutSeconds)))
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return [];
        }

        return is_array($payload['data'] ?? null) ? $payload['data'] : [];
    }

    /**
     * @return array{divisions: list<array<string, mixed>>, directorates: list<array<string, mixed>>}
     */
    public function fetchOrg(): array
    {
        return Cache::remember('rr_staff_portal_org_v1', 300, function () {
            if ($this->isConfigured()) {
                return [
                    'divisions' => $this->normalizeDivisions($this->fetchList('divisions')),
                    'directorates' => $this->normalizeDirectorates($this->fetchList('directorates')),
                ];
            }

            if (Schema::hasTable('divisions')) {
                return $this->fetchOrgFromLocalTables();
            }

            throw new RuntimeException(
                'Staff Portal Share API is not configured (STAFF_API_USERNAME/PASSWORD/TOKEN) and local divisions table is missing.'
            );
        });
    }

    /**
     * Lightweight staff directory for searchable pickers / name resolution.
     *
     * @return list<array{staff_id:int,name:string,email:?string}>
     */
    public function fetchStaffDirectory(): array
    {
        return Cache::remember('rr_staff_directory_v1', 300, function () {
            if (Schema::hasTable('staff')) {
                return $this->staffDirectoryFromLocalTable();
            }

            if ($this->isConfigured()) {
                try {
                    $rows = $this->fetchList('staff');

                    return $this->normalizeStaffDirectory($rows);
                } catch (RuntimeException) {
                    return [];
                }
            }

            return [];
        });
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<int, string>
     */
    public function staffNamesForIds(array $ids): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $wanted[$n] = true;
            }
        }
        if ($wanted === []) {
            return [];
        }

        if (Schema::hasTable('staff')) {
            $rows = DB::table('staff')
                ->select(['staff_id', 'fname', 'lname', 'oname', 'work_email', 'title'])
                ->whereIn('staff_id', array_keys($wanted))
                ->get()
                ->map(static fn ($r) => (array) $r)
                ->all();
            $map = [];
            foreach ($this->normalizeStaffDirectory($rows) as $row) {
                $map[(int) $row['staff_id']] = (string) $row['name'];
            }

            return $map;
        }

        $map = [];
        foreach ($this->fetchStaffDirectory() as $row) {
            $id = (int) $row['staff_id'];
            if (isset($wanted[$id])) {
                $map[$id] = (string) $row['name'];
            }
        }

        return $map;
    }

    /**
     * Display labels like "Jane Doe (Chief Risk Officer)" for report owners.
     *
     * @param  list<int|string>  $ids
     * @return array<int, array{staff_id:int,name:string,job_title:?string,label:string}>
     */
    public function staffWithJobsForIds(array $ids): array
    {
        $names = $this->staffNamesForIds($ids);
        if ($names === []) {
            return [];
        }

        $jobs = [];
        if (
            Schema::hasTable('staff_contracts')
            && Schema::hasTable('jobs')
            && Schema::hasColumn('jobs', 'job_id')
            && Schema::hasColumn('jobs', 'job_name')
        ) {
            $eligible = [1, 2, 3, 7];
            $contractRows = DB::table('staff_contracts as sc')
                ->leftJoin('jobs as j', 'j.job_id', '=', 'sc.job_id')
                ->whereIn('sc.staff_id', array_keys($names))
                ->when(
                    Schema::hasColumn('staff_contracts', 'status_id'),
                    static fn ($q) => $q->whereIn('sc.status_id', $eligible)
                )
                ->orderByDesc('sc.staff_contract_id')
                ->get(['sc.staff_id', 'j.job_name']);
            foreach ($contractRows as $row) {
                $sid = (int) $row->staff_id;
                if (isset($jobs[$sid])) {
                    continue;
                }
                $job = trim((string) ($row->job_name ?? ''));
                if ($job !== '') {
                    $jobs[$sid] = $job;
                }
            }
        }

        $out = [];
        foreach ($names as $sid => $name) {
            $job = $jobs[$sid] ?? null;
            $label = $job ? $name.' ('.$job.')' : $name;
            $out[$sid] = [
                'staff_id' => (int) $sid,
                'name' => $name,
                'job_title' => $job,
                'label' => $label,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, string> staff_id => display name
     */
    public function staffNameMap(): array
    {
        $map = [];
        foreach ($this->fetchStaffDirectory() as $row) {
            $map[(int) $row['staff_id']] = (string) $row['name'];
        }

        return $map;
    }

    /**
     * @return array{divisions: list<array<string, mixed>>, directorates: list<array<string, mixed>>}
     */
    private function fetchOrgFromLocalTables(): array
    {
        $divRows = DB::table('divisions')->get()->map(static fn ($r) => (array) $r)->all();
        $dirRows = Schema::hasTable('directorates')
            ? DB::table('directorates')->get()->map(static fn ($r) => (array) $r)->all()
            : [];

        return [
            'divisions' => $this->normalizeDivisions($divRows),
            'directorates' => $this->normalizeDirectorates($dirRows),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchList(string $endpointKey): array
    {
        $url = $this->buildUrl($endpointKey);
        $response = Http::withBasicAuth($this->username(), $this->password())
            ->timeout(120)
            ->retry(2, 1000, null, false)
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Staff API returned non-array JSON.');
        }

        // Envelope { data: [...] } or bare list
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        return array_values(array_map(
            static fn ($row) => is_array($row) ? $row : (array) $row,
            $data
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonAssoc(string $url): array
    {
        $response = Http::withBasicAuth($this->username(), $this->password())
            ->timeout(60)
            ->retry(2, 1000, null, false)
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Staff API returned non-array JSON.');
        }

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeDivisions(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['division_id'] ?? $row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $out[] = [
                'division_id' => $id,
                'division_short_name' => (string) ($row['division_short_name'] ?? $row['short_name'] ?? ''),
                'division_name' => (string) ($row['division_name'] ?? $row['name'] ?? ''),
                'directorate_id' => isset($row['directorate_id']) && $row['directorate_id'] !== '' && $row['directorate_id'] !== null
                    ? (int) $row['directorate_id']
                    : null,
                'division_head' => isset($row['division_head']) && $row['division_head'] !== '' && $row['division_head'] !== null
                    ? (int) $row['division_head']
                    : null,
                'risk_focal_person' => isset($row['risk_focal_person']) && $row['risk_focal_person'] !== '' && $row['risk_focal_person'] !== null
                    ? (int) $row['risk_focal_person']
                    : null,
                'director_id' => isset($row['director_id']) && $row['director_id'] !== '' && $row['director_id'] !== null
                    ? (int) $row['director_id']
                    : null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeDirectorates(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? $row['directorate_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $name = (string) ($row['name'] ?? $row['directorate_name'] ?? '');
            $aliases = $row['aliases'] ?? [];
            if (! is_array($aliases)) {
                $aliases = [];
            }
            // Common Africa CDC short codes when Share does not send aliases
            $auto = [];
            if (stripos($name, 'Primary Health Care') !== false) {
                $auto[] = 'PHC';
            }
            if (stripos($name, 'Disease Control') !== false || stripos($name, 'CDCD') !== false) {
                $auto[] = 'CDC';
            }
            $aliases = array_values(array_unique(array_merge(
                array_map('strval', $aliases),
                $auto
            )));

            $out[] = [
                'id' => $id,
                'name' => $name,
                'aliases' => $aliases,
                'short_name' => (string) ($row['short_name'] ?? $row['directorate_short_name'] ?? ''),
                'director_id' => isset($row['director_id']) && $row['director_id'] !== '' && $row['director_id'] !== null
                    ? (int) $row['director_id']
                    : null,
            ];
        }

        return $out;
    }

    /**
     * CBP module links for top nav (Staff Portal Share API — same as Helpdesk).
     *
     * @param  list<string|int>  $permissionIds
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    public function fetchCbpModules(
        int $staffId,
        string $excludeModuleKey = 'risk_register',
        string $activeModuleKey = 'risk_register',
        array $permissionIds = [],
    ): array {
        if ($staffId < 1) {
            throw new RuntimeException('staff_id is required for CBP modules.');
        }
        $url = $this->buildUrl('cbp_modules').'?staff_id='.$staffId;
        if ($excludeModuleKey !== '') {
            $url .= '&exclude_module_key='.rawurlencode($excludeModuleKey);
        }
        if ($activeModuleKey !== '') {
            $url .= '&active_module_key='.rawurlencode($activeModuleKey);
        }
        $permissionIds = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $permissionIds
        ), static fn (string $id) => $id !== '')));
        if ($permissionIds !== []) {
            $url .= '&permission_ids='.rawurlencode(implode(',', $permissionIds));
        }

        $payload = $this->getJsonAssoc($url);
        if (empty($payload['success'])) {
            $err = is_string($payload['error'] ?? null) ? $payload['error'] : 'Staff API returned success=false for cbp_modules.';
            throw new RuntimeException($err);
        }
        $data = $payload['data'] ?? null;
        if (! is_array($data) || ! is_array($data['home'] ?? null)) {
            throw new RuntimeException('Staff API cbp_modules response is missing data.home.');
        }

        /** @var array{home: array<string, mixed>, modules: list<array<string, mixed>>} $data */
        return $data;
    }

    private function buildUrl(string $endpointKey): string
    {
        $base = rtrim((string) config('risk-register.staff_api.base_url', env('STAFF_API_BASE_URL', '')), '/');
        if ($base === '') {
            $base = rtrim((string) env('STAFF_API_INTERNAL_BASE_URL', ''), '/');
        }
        if ($base === '') {
            // Prefer staff portal root, not this module's APP_URL (/staff/risk-register/backend).
            $portal = rtrim((string) env('BASE_URL', 'http://localhost/staff'), '/');
            $base = $portal.'/backend';
        }
        $path = match ($endpointKey) {
            'divisions' => (string) config('risk-register.staff_api.endpoints.divisions', '/share/divisions'),
            'directorates' => (string) config('risk-register.staff_api.endpoints.directorates', '/share/directorates'),
            'staff' => (string) config('risk-register.staff_api.endpoints.staff', '/share/get_current_staff'),
            'cbp_modules' => (string) config('risk-register.staff_api.endpoints.cbp_modules', '/share/cbp_modules'),
            'branding' => (string) config('risk-register.staff_api.endpoints.branding', '/share/branding'),
            default => throw new RuntimeException('Unknown staff_api endpoint: '.$endpointKey),
        };
        $token = $this->token();
        if ($token === '') {
            throw new RuntimeException('Missing STAFF_API_TOKEN.');
        }

        return $base.rtrim($path, '/').'/'.$token;
    }

    /**
     * @return list<array{staff_id:int,name:string,email:?string}>
     */
    private function staffDirectoryFromLocalTable(): array
    {
        $q = DB::table('staff')->select(['staff_id', 'fname', 'lname', 'oname', 'work_email', 'title']);
        if (Schema::hasTable('staff_contracts') && Schema::hasColumn('staff_contracts', 'status_id')) {
            $q->whereIn('staff_id', function ($sub): void {
                $sub->select('staff_id')
                    ->from('staff_contracts')
                    ->whereIn('status_id', [1, 2, 3, 7])
                    ->groupBy('staff_id');
            });
        }
        $rows = $q->orderBy('fname')->orderBy('lname')->limit(5000)->get();

        return $this->normalizeStaffDirectory($rows->map(static fn ($r) => (array) $r)->all());
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{staff_id:int,name:string,email:?string}>
     */
    private function normalizeStaffDirectory(array $rows): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $id = (int) ($row['staff_id'] ?? $row['id'] ?? 0);
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $name = $this->formatStaffName($row);
            if ($name === '') {
                $name = 'Staff #'.$id;
            }
            $email = $row['work_email'] ?? $row['email'] ?? null;
            $out[] = [
                'staff_id' => $id,
                'name' => $name,
                'email' => is_string($email) && $email !== '' ? $email : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function formatStaffName(array $row): string
    {
        $parts = array_filter([
            trim((string) ($row['title'] ?? '')),
            trim((string) ($row['fname'] ?? $row['first_name'] ?? '')),
            trim((string) ($row['oname'] ?? $row['other_name'] ?? '')),
            trim((string) ($row['lname'] ?? $row['last_name'] ?? '')),
        ], static fn ($p) => $p !== '');

        if ($parts !== []) {
            return preg_replace('/\s+/', ' ', implode(' ', $parts)) ?: '';
        }

        return trim((string) ($row['name'] ?? $row['staff_name'] ?? ''));
    }

    private function username(): string
    {
        return trim((string) config('risk-register.staff_api.username', env('STAFF_API_USERNAME', '')));
    }

    private function password(): string
    {
        return trim((string) config('risk-register.staff_api.password', env('STAFF_API_PASSWORD', '')));
    }

    private function token(): string
    {
        return trim((string) config('risk-register.staff_api.token', env('STAFF_API_TOKEN', '')));
    }

    private function formatHttpError(Response $response): string
    {
        $status = $response->status();
        $body = $response->json();
        $remote = '';
        if (is_array($body)) {
            $remote = (string) ($body['message'] ?? $body['error'] ?? '');
        }
        $msg = 'Staff Share API HTTP '.$status;
        if ($remote !== '') {
            $msg .= ': '.$remote;
        }

        return $msg;
    }
}
