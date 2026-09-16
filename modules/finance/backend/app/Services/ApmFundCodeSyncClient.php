<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class ApmFundCodeSyncClient
{
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
        $email = (string) config('apm.email');
        $password = (string) config('apm.password');
        if ($email === '' || $password === '') {
            throw new RuntimeException('APM_API_EMAIL and APM_API_PASSWORD must be configured.');
        }

        $response = Http::timeout((int) config('apm.timeout', 60))
            ->acceptJson()
            ->post($this->url('auth/login'), [
                'email' => $email,
                'password' => $password,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('APM login failed: HTTP '.$response->status());
        }

        $token = data_get($response->json(), 'data.access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('APM login response missing access_token.');
        }

        return $token;
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
            $response = Http::timeout((int) config('apm.timeout', 60))
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
        $response = Http::timeout((int) config('apm.timeout', 60))
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
        $base = rtrim((string) config('apm.base_url'), '/');
        if ($base === '') {
            throw new RuntimeException('APM_BASE_URL is not configured.');
        }
        $prefix = rtrim((string) config('apm.api_prefix', '/api/apm/v1'), '/');

        return $base.$prefix.'/'.ltrim($path, '/');
    }
}
