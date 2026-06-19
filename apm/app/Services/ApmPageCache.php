<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived Redis (or default) cache for heavy APM list / report pages.
 * Version keys are bumped on writes so views stay fresh without tag support.
 */
final class ApmPageCache
{
    /** @var list<string> */
    public const SCOPES = [
        'approver_dashboard',
        'reports',
        'weekly_briefing',
        'matrices',
        'activities',
        'change_requests',
        'lookups',
    ];

    private static function versionKey(string $scope): string
    {
        return "apm:page_cache_ver:{$scope}";
    }

    public static function version(string $scope): string
    {
        return (string) self::cache()->get(self::versionKey($scope), '1');
    }

    /**
     * @param  list<string>|string|null  $scopes
     */
    public static function bust(array|string|null $scopes = null): void
    {
        if ($scopes === null) {
            foreach (self::SCOPES as $scope) {
                self::bust($scope);
            }

            return;
        }

        $store = self::cache();

        foreach ((array) $scopes as $scope) {
            $store->put(self::versionKey($scope), (string) microtime(true), 86400 * 7);
        }
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    public static function key(string $scope, array $parts): string
    {
        ksort($parts);

        return sprintf(
            'apm:%s:v%s:%s',
            $scope,
            self::version($scope),
            md5(json_encode($parts, JSON_THROW_ON_ERROR))
        );
    }

    /**
     * @param  list<string>  $queryKeys
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function keyFromRequest(Request $request, array $queryKeys, array $extra = []): array
    {
        $parts = array_merge(self::sessionKeyParts(), $extra);

        foreach ($queryKeys as $key) {
            if ($request->has($key)) {
                $parts[$key] = $request->input($key);
            }
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>
     */
    public static function sessionKeyParts(): array
    {
        $session = user_session();

        return [
            '_staff_id' => (int) ($session['staff_id'] ?? 0),
            '_division_id' => (int) ($session['division_id'] ?? 0),
            '_perms' => implode(',', $session['permissions'] ?? []),
            '_role' => (int) (($session['role'] ?? $session['user_role'] ?? 0)),
        ];
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    public static function remember(string $scope, array $parts, callable $callback, ?int $ttl = null): mixed
    {
        if (! config('apm.page_cache_enabled', true)) {
            return $callback();
        }

        $ttl ??= (int) config("apm.page_cache_ttl_by_scope.{$scope}", config('apm.page_cache_ttl', 120));

        return self::cache()->remember(
            self::key($scope, $parts),
            max(1, $ttl),
            $callback
        );
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    public static function get(string $scope, array $parts): mixed
    {
        if (! config('apm.page_cache_enabled', true)) {
            return null;
        }

        return self::cache()->get(self::key($scope, $parts));
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    public static function put(string $scope, array $parts, mixed $payload, ?int $ttl = null): void
    {
        if (! config('apm.page_cache_enabled', true)) {
            return;
        }

        $ttl ??= (int) config("apm.page_cache_ttl_by_scope.{$scope}", config('apm.page_cache_ttl', 120));

        self::cache()->put(self::key($scope, $parts), $payload, max(1, $ttl));
    }

    public static function rememberLookups(string $name, callable $callback): mixed
    {
        return self::remember('lookups', ['lookup' => $name], $callback);
    }

    private static function cache(): \Illuminate\Contracts\Cache\Repository
    {
        $store = config('apm.page_cache_store');

        if (is_string($store) && $store !== '') {
            return Cache::store($store);
        }

        return Cache::store();
    }
}
