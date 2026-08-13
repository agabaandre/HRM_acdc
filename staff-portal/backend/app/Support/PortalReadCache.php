<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Short-lived list/read cache with version busting (helpdesk TicketReadCache pattern).
 */
final class PortalReadCache
{
    /** @var list<string> */
    private const SCOPES = ['staff', 'leave', 'permissions', 'dashboard'];

    private static function versionKey(string $scope): string
    {
        return "staff_portal:read_cache_ver:{$scope}";
    }

    public static function version(string $scope): string
    {
        return (string) Cache::get(self::versionKey($scope), '1');
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

        foreach ((array) $scopes as $scope) {
            Cache::put(self::versionKey($scope), (string) microtime(true), 86400 * 7);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function key(string $family, string $name, int $userId, array $query = []): string
    {
        ksort($query);
        $q = http_build_query($query);

        return sprintf(
            'staff_portal:%s:%s:v%s:u%d:%s',
            $family,
            $name,
            self::version($family),
            $userId,
            md5($q)
        );
    }

    public static function remember(string $key, callable $callback): mixed
    {
        if (! config('staff-portal.read_cache_enabled', true)) {
            return $callback();
        }

        $ttl = max(15, (int) config('staff-portal.read_cache_ttl', 60));
        $store = config('staff-portal.read_cache_store');

        if (is_string($store) && $store !== '') {
            return Cache::store($store)->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }
}
