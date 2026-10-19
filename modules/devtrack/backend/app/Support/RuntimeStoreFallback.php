<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * The folder deploy on Apache has no Redis, and a copied Mac .env can point
 * MySQL at a socket that only exists in MAMP. Either one turns every page into
 * a 500 before a controller runs. Keep Redis and the socket when they work.
 */
class RuntimeStoreFallback
{
    public static function apply(): void
    {
        self::ensureAppKey();
        self::useLocalStoresWhenRedisIsDown();
        self::ignoreMissingDatabaseSocket();
    }

    /**
     * Web middleware encrypts cookies. With an empty APP_KEY that throws,
     * and the error page throws again, so the browser shows two 500s.
     */
    private static function ensureAppKey(): void
    {
        $current = (string) config('app.key');
        if ($current !== '') {
            return;
        }

        $path = storage_path('app.key');
        $stored = is_file($path) ? trim((string) file_get_contents($path)) : '';
        if (! str_starts_with($stored, 'base64:')) {
            $stored = 'base64:'.base64_encode(random_bytes(32));
            @file_put_contents($path, $stored.PHP_EOL);
            @chmod($path, 0600);
        }

        Config::set('app.key', $stored);
    }

    private static function useLocalStoresWhenRedisIsDown(): void
    {
        $session = (string) config('session.driver');
        $cache = (string) config('cache.default');
        $queue = (string) config('queue.default');

        if (! in_array('redis', [$session, $cache, $queue], true)) {
            return;
        }

        if (self::redisAcceptsConnections()) {
            return;
        }

        if ($cache === 'redis') {
            Config::set('cache.default', self::writable(storage_path('framework/cache/data')) ? 'file' : 'array');
        }

        if ($session === 'redis') {
            Config::set('session.driver', self::writable(storage_path('framework/sessions')) ? 'file' : 'cookie');
        }

        if ($queue === 'redis') {
            Config::set('queue.default', 'sync');
        }
    }

    private static function ignoreMissingDatabaseSocket(): void
    {
        foreach (['mysql', 'mariadb'] as $connection) {
            $socket = (string) config("database.connections.{$connection}.unix_socket");
            if ($socket !== '' && ! is_file($socket)) {
                Config::set("database.connections.{$connection}.unix_socket", '');
            }
        }
    }

    private static function redisAcceptsConnections(): bool
    {
        $host = (string) config('database.redis.default.host', '127.0.0.1');
        $port = (int) config('database.redis.default.port', 6379);
        if ($host === '' || $port < 1) {
            return false;
        }

        $socket = @stream_socket_client(
            'tcp://'.$host.':'.$port,
            $errno,
            $errstr,
            0.3,
            STREAM_CLIENT_CONNECT,
        );

        if (! is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }

    private static function writable(string $directory): bool
    {
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        return is_dir($directory) && is_writable($directory);
    }
}
