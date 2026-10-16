<?php

namespace App\Services;

use App\Support\ApmApiCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class ApmFundCodeSyncClient
{
    /** @var array{base_url: string, api_prefix: string, email: string, password: string, timeout: int}|null */
    private ?array $creds = null;

    /**
     * @param  list<array{fund_center: string, total_released_budget: float, released_budget_balance: float}>  $totals
     */
    public function syncTotals(int $year, array $totals): int
    {
        if ($totals === []) {
            return 0;
        }

        $token = $this->login();
        $byCode = $this->fetchFundCodesByCode($token, $year);
        $updated = 0;

        foreach ($totals as $row) {
            $code = trim((string) ($row['fund_center'] ?? ''));
            if ($code === '' || ! isset($byCode[$code])) {
                continue;
            }

            $id = (int) $byCode[$code]['id'];
            $approved = number_format((float) $row['total_released_budget'], 2, '.', '');
            $balance = number_format((float) $row['released_budget_balance'], 2, '.', '');

            $this->patchFundCode($token, $id, [
                'approved_budget' => $approved,
                'uploaded_budget' => $approved,
                'budget_balance' => $balance,
            ]);
            $updated++;
        }

        return $updated;
    }

    private function login(): string
    {
        $auth = ApmApiCredentials::login();
        // Keep sync URL/timeout resolution aligned with whatever candidate succeeded.
        $resolved = ApmApiCredentials::resolve();
        $this->creds = [
            'base_url' => $resolved['base_url'],
            'api_prefix' => $resolved['api_prefix'],
            'email' => $auth['email'],
            'password' => '',
            'timeout' => $resolved['timeout'],
        ];
        Log::info('APM fund-code sync authenticated', [
            'email' => $auth['email'],
            'via' => $auth['label'],
        ]);

        return $auth['access_token'];
    }

    /**
     * @return array{base_url: string, api_prefix: string, email: string, password: string, timeout: int}
     */
    private function credentials(): array
    {
        if ($this->creds !== null) {
            return $this->creds;
        }
        $resolved = ApmApiCredentials::resolve();
        $this->creds = [
            'base_url' => $resolved['base_url'],
            'api_prefix' => $resolved['api_prefix'],
            'email' => $resolved['email'],
            'password' => $resolved['password'],
            'timeout' => $resolved['timeout'],
        ];

        return $this->creds;
    }

    /**
     * @return array<string, array{id: int, code: string}>
     */
    private function fetchFundCodesByCode(string $token, int $year): array
    {
        $byCode = [];
        $page = 1;
        $lastPage = 1;

        do {
            $response = Http::timeout($this->credentials()['timeout'])
                ->withToken($token)
                ->acceptJson()
                ->get($this->url('fund-codes'), [
                    'year' => $year,
                    'is_active' => 1,
                    'per_page' => 100,
                    'page' => $page,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('APM fund-codes list failed: HTTP '.$response->status());
            }

            $json = $response->json();
            foreach ($json['data'] ?? [] as $item) {
                $code = trim((string) ($item['code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $byCode[$code] = [
                    'id' => (int) $item['id'],
                    'code' => $code,
                ];
            }

            $lastPage = (int) data_get($json, 'pagination.last_page', 1);
            $page++;
        } while ($page <= $lastPage);

        return $byCode;
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function patchFundCode(string $token, int $id, array $payload): void
    {
        $response = Http::timeout($this->credentials()['timeout'])
            ->withToken($token)
            ->acceptJson()
            ->patch($this->url('fund-codes/'.$id), $payload);

        if (! $response->successful()) {
            Log::warning('APM fund-code PATCH failed', [
                'id' => $id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('APM fund-code PATCH failed for id '.$id.': HTTP '.$response->status());
        }
    }

    private function url(string $path): string
    {
        $creds = $this->credentials();
        $base = $creds['base_url'];
        if ($base === '') {
            throw new RuntimeException('APM base URL is not configured (Settings → Staff API, or APM_BASE_URL).');
        }
        $prefix = rtrim($creds['api_prefix'], '/');

        return $base.$prefix.'/'.ltrim($path, '/');
    }
}
