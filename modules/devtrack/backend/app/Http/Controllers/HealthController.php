<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => 'ok',
            'app_key' => $this->checkAppKey(),
            'database' => $this->checkDatabase(),
            'queue' => $this->checkQueue(),
            'cache' => $this->checkCache(),
        ];

        if ($this->usesRedis()) {
            $checks['redis'] = $this->checkRedis();
        }

        $healthy = collect($checks)
            ->except('app')
            ->every(fn (array|string $check) => is_array($check) ? $check['status'] === 'ok' : $check === 'ok');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    /**
     * @return array{status: string, message?: string}
     */
    private function checkAppKey(): array
    {
        try {
            $configKey = (string) config('app.key', '');
            $envKey = (string) env('APP_KEY', '');

            if ($configKey === '' && $envKey === '') {
                return [
                    'status' => 'error',
                    'message' => 'APP_KEY missing — provider secrets cannot be encrypted. Run deploy/fix-app-key.sh (and remove duplicate/empty APP_KEY= lines from backend/.env).',
                ];
            }

            // Real proof — same path as EmailProvider config encryption
            $cipher = encrypt('health-probe');
            if (decrypt($cipher) !== 'health-probe') {
                return ['status' => 'error', 'message' => 'APP_KEY present but encrypt/decrypt failed'];
            }

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            report($e);

            return [
                'status' => 'error',
                'message' => 'Encryption check failed',
            ];
        }
    }

    /**
     * @return array{status: string, message?: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::connection()->select('select 1');

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            report($e);

            $detail = 'Database unreachable';
            if (preg_match('/SQLSTATE\[(\w+)\](?: \[(\d+)\])?/', $e->getMessage(), $match) === 1) {
                $detail .= ' ('.$match[1].(isset($match[2]) && $match[2] !== '' ? ' '.$match[2] : '').')';
            }

            return ['status' => 'error', 'message' => $detail];
        }
    }

    private function usesRedis(): bool
    {
        return in_array('redis', [
            (string) config('cache.default'),
            (string) config('session.driver'),
            (string) config('queue.default'),
        ], true);
    }

    /**
     * @return array{status: string, message?: string, driver?: string}
     */
    private function checkRedis(): array
    {
        try {
            $pong = Redis::connection()->ping();
            $driver = config('database.redis.client', 'phpredis');

            if ($pong === false || $pong === null) {
                return ['status' => 'error', 'message' => 'Redis ping failed', 'driver' => $driver];
            }

            return ['status' => 'ok', 'driver' => $driver];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'error', 'message' => 'Redis unreachable'];
        }
    }

    /**
     * @return array{status: string, connection?: string, message?: string}
     */
    private function checkQueue(): array
    {
        $connection = (string) config('queue.default', 'sync');

        if ($connection === 'sync') {
            return ['status' => 'ok', 'connection' => 'sync', 'message' => 'In-process (dev only)'];
        }

        if ($connection !== 'redis') {
            return ['status' => 'ok', 'connection' => $connection];
        }

        try {
            $size = Queue::connection('redis')->size('emails');

            return [
                'status' => 'ok',
                'connection' => 'redis',
                'emails_queue_depth' => $size,
            ];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'error', 'connection' => 'redis', 'message' => 'Queue unreachable'];
        }
    }

    /**
     * @return array{status: string, store?: string, message?: string}
     */
    private function checkCache(): array
    {
        $store = (string) config('cache.default', 'file');

        try {
            $key = 'health:probe:'.uniqid('', true);
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value !== 'ok') {
                return ['status' => 'error', 'store' => $store, 'message' => 'Cache read/write failed'];
            }

            return ['status' => 'ok', 'store' => $store];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'error', 'store' => $store, 'message' => 'Cache unreachable'];
        }
    }
}
