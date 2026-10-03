<?php

namespace App\Services;

use App\Support\StaffApiBaseUrl;
use RuntimeException;
use Staff\Shared\StaffShareHttp;

/**
 * Calls Laravel Staff Share API (staff-portal Modules/Share) — same contract as APM
 * staff:sync / divisions:sync. Auth: STAFF_API_USERNAME/PASSWORD → POST /share/token
 * (Bearer JWT), else STAFF_API_TOKEN path/Bearer. Docs: /staff/backend/share/docs
 */
class StaffPortalReferenceClient
{
    private ?StaffShareHttp $http = null;

    public function isConfigured(): bool
    {
        return $this->client()->isConfigured();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchDivisions(): array
    {
        return $this->asList($this->client()->getJson($this->endpoint('divisions')));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchDirectorates(): array
    {
        return $this->asList($this->client()->getJson($this->endpoint('directorates')));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchStaff(int $limit, int $start = 0): array
    {
        return $this->asList($this->client()->getJson($this->endpoint('staff'), [
            'limit' => max(1, min($limit, 20000)),
            'start' => max(0, $start),
        ]));
    }

    /**
     * @param  array<int, int>  $divisionIds
     * @return array<int, array<string, mixed>>
     */
    public function fetchAgentsInDivisions(array $divisionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $divisionIds), fn (int $n) => $n > 0)));
        $query = [];
        if ($ids !== []) {
            $query['division_ids'] = implode(',', $ids);
        }
        $payload = $this->client()->getJson($this->endpoint('agents_in_divisions'), $query);

        return is_array($payload['data'] ?? null) ? $payload['data'] : [];
    }

    /**
     * @param  array<int, int>  $staffIds
     * @return array<string, mixed>
     */
    public function markHelpdeskAgents(array $staffIds, bool $mark): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $staffIds), fn (int $n) => $n > 0)));
        if ($ids === []) {
            throw new RuntimeException('No staff_ids supplied to markHelpdeskAgents.');
        }

        return $this->client()->postJson($this->endpoint('mark_agents'), [
            'staff_ids' => $ids,
            'mark' => $mark,
        ]);
    }

    /**
     * @param  list<string>  $permissionIds
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    public function fetchCbpModules(
        int $staffId,
        string $excludeModuleKey = 'helpdesk_itsm',
        string $activeModuleKey = 'helpdesk_itsm',
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

        $bag = class_exists(\App\Support\HelpdeskSettingsBag::class)
            ? new \App\Support\HelpdeskSettingsBag
            : null;
        $this->http = \Staff\Shared\StaffApiCredentials::client($bag, 120);

        return $this->http;
    }

    private function endpoint(string $key): string
    {
        $path = trim((string) config('helpdesk.staff_api.endpoints.'.$key));
        if ($path === '') {
            throw new RuntimeException('Missing staff_api endpoint: '.$key);
        }

        return $path[0] === '/' ? $path : '/'.$path;
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function asList(array $data): array
    {
        /** @var array<int, array<string, mixed>> $out */
        $out = array_values(array_map(function ($row) {
            return is_array($row) ? $row : (array) $row;
        }, $data));

        return $out;
    }
}
