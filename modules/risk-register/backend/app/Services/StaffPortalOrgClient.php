<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Read-only Staff Portal Share API client for org mirror (divisions / directorates).
 */
class StaffPortalOrgClient
{
    public function isConfigured(): bool
    {
        return $this->username() !== '' && $this->password() !== '' && $this->token() !== '';
    }

    /**
     * @return array{divisions: list<array<string, mixed>>, directorates: list<array<string, mixed>>}
     */
    public function fetchOrg(): array
    {
        if ($this->isConfigured()) {
            return [
                'divisions' => $this->normalizeDivisions($this->fetchList('divisions')),
                'directorates' => $this->normalizeDirectorates($this->fetchList('directorates')),
            ];
        }

        // Same MySQL host as Staff Portal (dev / co-located DB) — read-only mirror.
        if (Schema::hasTable('divisions')) {
            return $this->fetchOrgFromLocalTables();
        }

        throw new RuntimeException(
            'Staff Portal Share API is not configured (STAFF_API_USERNAME/PASSWORD/TOKEN) and local divisions table is missing.'
        );
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
            ];
        }

        return $out;
    }

    private function buildUrl(string $endpointKey): string
    {
        $base = rtrim((string) config('risk-register.staff_api.base_url', env('STAFF_API_BASE_URL', '')), '/');
        if ($base === '') {
            $base = rtrim((string) env('APP_URL', 'http://localhost'), '/').'/staff/backend';
        }
        $path = match ($endpointKey) {
            'divisions' => (string) config('risk-register.staff_api.endpoints.divisions', '/share/divisions'),
            'directorates' => (string) config('risk-register.staff_api.endpoints.directorates', '/share/directorates'),
            default => throw new RuntimeException('Unknown staff_api endpoint: '.$endpointKey),
        };
        $token = $this->token();
        if ($token === '') {
            throw new RuntimeException('Missing STAFF_API_TOKEN.');
        }

        return $base.rtrim($path, '/').'/'.$token;
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
