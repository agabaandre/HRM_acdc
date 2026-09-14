<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StaffPortalShareClient
{
    public function isConfigured(): bool
    {
        $u = (string) config('services.staff_api.username', '');
        $p = (string) config('services.staff_api.password', '');

        return $u !== '' && $p !== '';
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
        if ($permissionIds === [] && session()->has('permissions')) {
            $permissionIds = array_map('strval', (array) session('permissions', []));
        }
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
        $base = rtrim((string) config('services.staff_api.base_url'), '/');
        $path = trim((string) config('services.staff_api.endpoints.'.$endpointKey));
        if ($path === '') {
            throw new RuntimeException('Missing staff_api endpoint: '.$endpointKey);
        }
        $token = trim((string) config('services.staff_api.token'));
        if ($token === '') {
            throw new RuntimeException('Missing STAFF_API_TOKEN.');
        }

        return $base.$path.'/'.$token;
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonAssoc(string $url): array
    {
        $response = Http::withBasicAuth(
            (string) config('services.staff_api.username'),
            (string) config('services.staff_api.password')
        )
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
