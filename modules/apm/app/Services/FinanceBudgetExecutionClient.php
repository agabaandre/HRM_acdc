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
     * Org-wide fetch for background warm / snapshot (IT-admin equivalent token).
     *
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    public function budgetExecutionAsSystem(array $query = []): array
    {
        return $this->getJson('/api/v1/budget-execution', $query, $this->mintSystemFinanceApiToken());
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
    private function getJson(string $path, array $query = [], ?string $token = null): array
    {
        $token ??= $this->mintFinanceApiToken();
        $url = rtrim($this->baseUrl(), '/').$path;

        $response = Http::withToken($token)
            ->acceptJson()
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->timeout((int) config('services.finance_api.timeout', 60))
            ->withOptions(['allow_redirects' => false])
            ->get($url, array_filter($query, static fn ($v) => $v !== null && $v !== ''));

        $status = $response->status();
        $contentType = (string) $response->header('Content-Type');
        $body = (string) $response->body();

        if ($status >= 300 && $status < 400) {
            $location = (string) $response->header('Location');
            Log::warning('Finance budget-execution API redirected', [
                'status' => $status,
                'path' => $path,
                'url' => $url,
                'location' => $location,
            ]);

            throw new RuntimeException(
                'Finance API redirected (HTTP '.$status.') — check FINANCE_API_BASE_URL'
                .($location !== '' ? ' (to '.$location.')' : '')
                .'. Expected JSON at '.$url
            );
        }

        if (! $response->successful()) {
            $message = (string) ($response->json('message') ?? $body);
            Log::warning('Finance budget-execution API failed', [
                'status' => $status,
                'path' => $path,
                'url' => $url,
                'content_type' => $contentType,
                'message' => mb_substr($message, 0, 500),
            ]);

            throw new RuntimeException(
                $message !== ''
                    ? 'Finance API error: '.$message
                    : 'Finance API request failed (HTTP '.$status.').'
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            $snippet = mb_substr(trim(preg_replace('/\s+/', ' ', $body) ?? ''), 0, 160);
            Log::warning('Finance budget-execution API returned non-JSON', [
                'status' => $status,
                'path' => $path,
                'url' => $url,
                'content_type' => $contentType,
                'body' => $snippet,
            ]);

            throw new RuntimeException(
                'Finance API returned an invalid response (HTTP '.$status
                .', type '.($contentType !== '' ? $contentType : 'unknown')
                .'). Check FINANCE_API_BASE_URL points at the Finance Laravel backend'
                .' (…/staff/finance/backend), not the SPA. Tried: '.$url
            );
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

        // Org-wide only for IT admin (role 10), Finance module / workflow perms, or finance officers.
        // Do not elevate APM "view my/all division" perms (87/88) to Finance org-wide access.
        if ($this->shouldGrantOrgWideBudgetExecution($roleId, $permissions, $staffId)
            && ! in_array('92', $permissions, true)) {
            $permissions[] = '92';
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

    private function mintSystemFinanceApiToken(): string
    {
        $staffId = max(1, (int) config('services.finance_api.warm_staff_id', 1));
        $token = 'apm_fin_warm_'.$staffId.'_'.Str::random(40);
        $payload = [
            'staff_id' => $staffId,
            'name' => 'APM Finance Warm',
            'email' => 'apm-finance-warm@africacdc.org',
            'permissions' => ['92'],
            'division_id' => 0,
            'role_id' => 10,
            'api_token' => $token,
            'source' => 'apm_finance_budget_execution_warm',
        ];

        $ttl = max(60, (int) config('services.finance_api.token_ttl', 3600));
        $redisPrefix = (string) config('services.finance_api.redis_prefix', 'laravel-database-');
        $cachePrefix = (string) config('services.finance_api.cache_prefix', 'risk_register');
        $fullKey = $redisPrefix.$cachePrefix.'risk_api_token:'.$token;

        try {
            $this->financeRedis()->setex($fullKey, $ttl, serialize($payload));
        } catch (Throwable $e) {
            Log::error('Failed to mint Finance warm token in Redis', ['error' => $e->getMessage()]);
            throw new RuntimeException('Could not prepare Finance warm session (Redis unavailable).');
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

    /**
     * @param  list<string>  $permissions
     */
    private function shouldGrantOrgWideBudgetExecution(int $roleId, array $permissions, int $staffId): bool
    {
        if ($roleId === 10) {
            return true;
        }

        foreach (['92', '93', '124'] as $code) {
            if (in_array($code, $permissions, true)) {
                return true;
            }
        }

        return $this->isFinanceOfficer($staffId);
    }

    private function isFinanceOfficer(int $staffId): bool
    {
        if ($staffId < 1 || ! class_exists(\App\Models\Division::class)) {
            return false;
        }

        try {
            if (\App\Models\Division::query()->where('finance_officer', $staffId)->exists()) {
                return true;
            }

            $today = now()->toDateString();

            return \App\Models\Division::query()
                ->where('finance_officer_oic_id', $staffId)
                ->where(function ($q) use ($today) {
                    $q->whereNull('finance_officer_oic_start_date')
                        ->orWhereDate('finance_officer_oic_start_date', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('finance_officer_oic_end_date')
                        ->orWhereDate('finance_officer_oic_end_date', '>=', $today);
                })
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
