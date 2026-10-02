<?php

namespace App\Services;

use App\Support\StaffApiBaseUrl;
use RuntimeException;
use Staff\Shared\StaffShareHttp;

/**
 * Staff portal Laravel Share API (same contract as Helpdesk reference sync).
 * Auth: STAFF_API_USERNAME/PASSWORD → POST /share/token, else STAFF_API_TOKEN.
 * Docs: /staff/backend/share/docs
 */
class StaffPortalShareClient
{
    private ?StaffShareHttp $http = null;

    public function isConfigured(): bool
    {
        return $this->client()->isConfigured();
    }

    /**
     * @param  list<string|int>  $permissionIds  Staff portal permission codes from session (optional)
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    public function fetchCbpModules(
        int $staffId,
        string $excludeModuleKey = 'approvals_management',
        string $activeModuleKey = 'approvals_management',
        array $permissionIds = [],
    ): array {
        if ($staffId < 1) {
            throw new RuntimeException('staff_id is required for CBP modules.');
        }

        $query = ['staff_id' => $staffId];
        if ($excludeModuleKey !== '') {
            $query['exclude_module_key'] = $excludeModuleKey;
        }
        if ($activeModuleKey !== '') {
            $query['active_module_key'] = $activeModuleKey;
        }
        $permissionIds = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $permissionIds
        ), static fn (string $id) => $id !== '')));
        if ($permissionIds === [] && session()->has('permissions')) {
            $permissionIds = array_map('strval', (array) session('permissions', []));
        }
        if ($permissionIds !== []) {
            $query['permission_ids'] = implode(',', $permissionIds);
        }

        $payload = $this->client()->getJson($this->endpoint('cbp_modules'), $query);
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

    /**
     * @return non-empty-string|null Base64-encoded image bytes (not a data URI)
     */
    public function fetchStaffSignatureBase64(int $staffId): ?string
    {
        if (! $this->isConfigured() || $staffId < 1) {
            return null;
        }

        try {
            $payload = $this->client()->getJson($this->endpoint('signature'), [
                'staff_id' => $staffId,
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (empty($payload['success'])) {
            return null;
        }

        $data = $payload['signature_data'] ?? null;
        if (! is_string($data) || trim($data) === '') {
            return null;
        }

        return trim($data);
    }

    private function client(): StaffShareHttp
    {
        if ($this->http instanceof StaffShareHttp) {
            return $this->http;
        }

        $base = StaffApiBaseUrl::resolve((string) config('services.staff_api.base_url'));
        $this->http = StaffShareHttp::fromConfig([
            'base_url' => $base,
            'username' => config('services.staff_api.username'),
            'password' => config('services.staff_api.password'),
            'token' => config('services.staff_api.token') ?: 'YWZyY2FjZGNzdGFmZnRyYWNrZXI',
        ], 60);

        return $this->http;
    }

    private function endpoint(string $key): string
    {
        $path = trim((string) config('services.staff_api.endpoints.'.$key));
        if ($path === '') {
            throw new RuntimeException('Missing staff_api endpoint: '.$key);
        }

        return $path[0] === '/' ? $path : '/'.$path;
    }
}
