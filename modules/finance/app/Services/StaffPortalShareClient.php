<?php

namespace App\Services;

use RuntimeException;
use Staff\Shared\StaffApiCredentials;
use Staff\Shared\StaffShareHttp;

class StaffPortalShareClient
{
    private ?StaffShareHttp $http = null;

    public function isConfigured(): bool
    {
        return $this->client()->isConfigured();
    }

    /**
     * @param  list<string|int>  $permissionIds
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    public function fetchCbpModules(
        int $staffId,
        string $excludeModuleKey = 'finance_management',
        string $activeModuleKey = 'finance_management',
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
        if ($permissionIds === [] && function_exists('session') && session()->has('permissions')) {
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

    private function client(): StaffShareHttp
    {
        if ($this->http instanceof StaffShareHttp) {
            return $this->http;
        }

        $bag = class_exists(\App\Support\RrSettingsBag::class)
            ? new \App\Support\RrSettingsBag
            : null;
        $this->http = StaffApiCredentials::client($bag, 60);

        return $this->http;
    }

    private function endpoint(string $key): string
    {
        $path = trim((string) config('services.staff_api.endpoints.'.$key, '/share/'.$key));
        if ($path === '') {
            throw new RuntimeException('Missing staff_api endpoint: '.$key);
        }

        return $path[0] === '/' ? $path : '/'.$path;
    }
}
