<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Predis\Client as PredisClient;
use RuntimeException;
use Throwable;

/**
 * Fetches Finance module budget-execution (and related) data over HTTP.
 * Session tokens are minted into Finance's Redis cache so AuthenticateRiskSession accepts them.
 */
final class FinanceBudgetExecutionClient
{
    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    public function budgetExecution(array $query = []): array
    {
        return $this->getJson('/api/v1/budget-execution', $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function fundCenterExpenses(string $fundCenter): array
    {
        $code = rawurlencode(trim($fundCenter));

        return $this->getJson('/api/v1/sap/fund-centers/'.$code.'/expenses');
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    private function getJson(string $path, array $query = []): array
    {
        $token = $this->mintFinanceApiToken();
        $url = rtrim($this->baseUrl(), '/').$path;

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('services.finance_api.timeout', 60))
            ->get($url, array_filter($query, static fn ($v) => $v !== null && $v !== ''));

        if (! $response->successful()) {
            $message = (string) ($response->json('message') ?? $response->body());
            Log::warning('Finance budget-execution API failed', [
                'status' => $response->status(),
                'path' => $path,
                'message' => mb_substr($message, 0, 500),
            ]);

            throw new RuntimeException(
                $message !== ''
                    ? 'Finance API error: '.$message
                    : 'Finance API request failed (HTTP '.$response->status().').'
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Finance API returned an invalid response.');
        }

        return $json;
    }

    private function mintFinanceApiToken(): string
    {
        $session = user_session();
        $staffId = (int) ($session['staff_id'] ?? 0);
        if ($staffId < 1) {
            throw new RuntimeException('No APM staff session available for Finance API.');
        }

        $roleId = (int) ($session['role'] ?? $session['user_role'] ?? $session['role_id'] ?? 0);
        $rawPerms = $session['permissions'] ?? [];
        $permissions = array_values(array_map('strval', is_array($rawPerms) ? $rawPerms : []));

        // Finance treats module permission 92 (or role 10) as org-wide budget execution.
        if ($roleId === 10 || in_array('88', $permissions, true) || in_array('92', $permissions, true)) {
            if (! in_array('92', $permissions, true)) {
                $permissions[] = '92';
            }
        }

        $token = 'apm_fin_'.$staffId.'_'.Str::random(40);
        $payload = [
            'staff_id' => $staffId,
            'name' => (string) ($session['name'] ?? ''),
            'email' => (string) ($session['email'] ?? $session['work_email'] ?? ''),
            'permissions' => $permissions,
            'division_id' => (int) ($session['division_id'] ?? 0),
            'role_id' => $roleId,
            'api_token' => $token,
            'source' => 'apm_finance_budget_execution',
        ];

        $ttl = max(60, (int) config('services.finance_api.token_ttl', 3600));
        $redisPrefix = (string) config('services.finance_api.redis_prefix', 'laravel-database-');
        $cachePrefix = (string) config('services.finance_api.cache_prefix', 'risk_register');
        $fullKey = $redisPrefix.$cachePrefix.'risk_api_token:'.$token;

        try {
            $this->financeRedis()->setex($fullKey, $ttl, serialize($payload));
        } catch (Throwable $e) {
            Log::error('Failed to mint Finance API token in Redis', ['error' => $e->getMessage()]);
            throw new RuntimeException('Could not prepare Finance API session (Redis unavailable).');
        }

        return $token;
    }

    private function financeRedis(): PredisClient
    {
        return new PredisClient([
            'scheme' => 'tcp',
            'host' => (string) config('database.redis.finance_cache.host', '127.0.0.1'),
            'port' => (int) config('database.redis.finance_cache.port', 6379),
            'password' => config('database.redis.finance_cache.password'),
            'database' => (int) config('database.redis.finance_cache.database', 1),
        ]);
    }

    private function baseUrl(): string
    {
        $base = trim((string) config('services.finance_api.base_url', ''));
        if ($base === '') {
            $base = 'http://localhost/staff/finance/backend';
        }

        return rtrim($base, '/');
    }
}
