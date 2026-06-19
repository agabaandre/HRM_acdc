<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Short-lived Redis (or default) cache for ticket list / report / KB read endpoints.
 * Version keys are bumped on writes so list views stay fresh without tag support.
 */
final class TicketReadCache
{
    /** @var list<string> */
    private const SCOPES = ['tickets', 'reports', 'kb'];

    private static function versionKey(string $scope): string
    {
        return "helpdesk:read_cache_ver:{$scope}";
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
            'helpdesk:%s:%s:v%s:u%d:%s',
            $family,
            $name,
            self::version($family),
            $userId,
            md5($q)
        );
    }

    public static function remember(string $key, callable $callback): mixed
    {
        if (! config('helpdesk.ticket_read_cache_enabled', true)) {
            return $callback();
        }

        $ttl = (int) config('helpdesk.ticket_read_cache_ttl', 60);
        $store = config('helpdesk.ticket_read_cache_store');

        if (is_string($store) && $store !== '') {
            return Cache::store($store)->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }
}
