<?php

/**
 * Helpdesk Redis / cache probe — bootstraps Laravel and runs read-only checks
 * (optional --warm-staff-cache to repopulate staff reference data).
 *
 * Invoked by scripts/production-test-redis.sh; do not run directly on dev unless intended.
 */

declare(strict_types=1);

use App\Services\ReferenceDataSyncService;
use App\Services\StaffPortalReferenceClient;
use App\Support\StaffShareNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

$warmStaffCache = in_array('--warm-staff-cache', $argv, true);
$verbose = in_array('--verbose', $argv, true) || in_array('-v', $argv, true);

$backendRoot = getenv('HELPDESK_BACKEND_ROOT') ?: null;
if ($backendRoot === null || $backendRoot === '') {
    fwrite(STDERR, "error: HELPDESK_BACKEND_ROOT is not set\n");
    exit(2);
}

$backendRoot = rtrim($backendRoot, '/');
if (! is_file("{$backendRoot}/artisan")) {
    fwrite(STDERR, "error: missing artisan in {$backendRoot}\n");
    exit(2);
}

require "{$backendRoot}/vendor/autoload.php";
$app = require "{$backendRoot}/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = 0;

function probe_line(string $label, string $value, bool $ok = true): void
{
    $tag = $ok ? 'OK' : 'FAIL';
    echo sprintf("[%s] %-28s %s\n", $tag, $label, $value);
}

function probe_fail(string $label, string $detail): void
{
    global $failures;
    $failures++;
    probe_line($label, $detail, false);
}

echo "==> Helpdesk cache / Redis probe\n";
echo "    backend: {$backendRoot}\n\n";

$cacheStore = (string) config('cache.default');
$queueConn = (string) config('queue.default');
$redisClient = (string) config('database.redis.client');
$staffLimit = (int) config('helpdesk.staff_api.staff_fetch_limit', 5000);
$staffCacheKey = 'helpdesk_reference_staff_v1_'.$staffLimit;

probe_line('cache.default', $cacheStore);
probe_line('queue.default', $queueConn);
probe_line('redis.client', $redisClient);
probe_line('staff reference key', $staffCacheKey);

echo "\n==> Redis connections\n";

foreach (['default', 'cache'] as $connection) {
    try {
        $pong = Redis::connection($connection)->ping();
        $pongStr = is_string($pong) ? $pong : (is_bool($pong) ? ($pong ? 'PONG' : 'false') : json_encode($pong));
        probe_line("redis.{$connection}.ping", $pongStr);
    } catch (Throwable $e) {
        probe_fail("redis.{$connection}.ping", $e->getMessage());
    }
}

echo "\n==> Laravel cache round-trip (store: {$cacheStore})\n";

$probeKey = 'helpdesk_redis_probe_'.bin2hex(random_bytes(4));
$probeValue = 'ok-'.time();

try {
    Cache::put($probeKey, $probeValue, 60);
    $read = Cache::get($probeKey);
    if ($read === $probeValue) {
        probe_line('cache put/get', 'match');
    } else {
        probe_fail('cache put/get', 'read back '.var_export($read, true));
    }
    Cache::forget($probeKey);
    probe_line('cache forget', Cache::has($probeKey) ? 'key still present' : 'cleared');
} catch (Throwable $e) {
    probe_fail('cache round-trip', $e->getMessage());
}

if ($cacheStore !== 'redis') {
    echo "\n==> Explicit redis cache store (CACHE_STORE is not redis)\n";
    try {
        Cache::store('redis')->put($probeKey.'_redis', $probeValue, 60);
        $read = Cache::store('redis')->get($probeKey.'_redis');
        if ($read === $probeValue) {
            probe_line('redis store put/get', 'match');
        } else {
            probe_fail('redis store put/get', 'read back '.var_export($read, true));
        }
        Cache::store('redis')->forget($probeKey.'_redis');
    } catch (Throwable $e) {
        probe_fail('redis store round-trip', $e->getMessage());
    }
}

echo "\n==> Staff reference cache (duty station lookup)\n";

if ($warmStaffCache) {
    echo "    warming staff reference cache via ReferenceDataSyncService...\n";
    try {
        $client = app(StaffPortalReferenceClient::class);
        if (! $client->isConfigured()) {
            probe_fail('staff cache warm', 'Staff API credentials not configured');
        } else {
            $stats = app(ReferenceDataSyncService::class)->warmCaches($client);
            probe_line('staff cache warm', sprintf(
                'divisions=%d directorates=%d staff_rows=%d',
                $stats['divisions'],
                $stats['directorates'],
                $stats['staff_rows'],
            ));
        }
    } catch (Throwable $e) {
        probe_fail('staff cache warm', $e->getMessage());
    }
}

try {
    $staffRows = Cache::get($staffCacheKey);
    if (! is_array($staffRows) || $staffRows === []) {
        probe_fail('staff reference cache', 'empty or missing — run directory sync or pass --warm-staff-cache');
    } else {
        probe_line('staff reference cache', count($staffRows).' rows');

        $withDuty = 0;
        $sample = [];
        foreach ($staffRows as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $row = StaffShareNormalizer::staff($raw);
            $station = trim((string) ($row['duty_station_name'] ?? ''));
            if ($station !== '') {
                $withDuty++;
                if (count($sample) < 5) {
                    $sample[] = sprintf('#%d %s', (int) $row['id'], $station);
                }
            }
        }
        probe_line('staff with duty station', "{$withDuty} / ".count($staffRows));
        if ($verbose && $sample !== []) {
            echo "    sample: ".implode('; ', $sample)."\n";
        }
        if ($withDuty === 0) {
            probe_fail('duty station data', 'no duty_station_name (or fallbacks) in cached staff rows');
        }
    }
} catch (Throwable $e) {
    probe_fail('staff reference cache', $e->getMessage());
}

echo "\n==> Summary\n";
if ($failures === 0) {
    echo "All checks passed.\n";
    exit(0);
}

echo "{$failures} check(s) failed.\n";
exit(1);
