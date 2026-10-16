<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * APM registers Finance SPA Bearer tokens into Finance's own Cache store.
 * Avoids hard dependence on shared Redis prefix/DB between APM and Finance.
 */
class ApmSessionBridgeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! $this->bridgeAuthorized($request)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $validated = $request->validate([
            'token' => ['required', 'string', 'min:16', 'max:191'],
            'payload' => ['required', 'array'],
            'payload.staff_id' => ['required', 'integer', 'min:1'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:86400'],
        ]);

        $token = (string) $validated['token'];
        $payload = $validated['payload'];
        $payload['api_token'] = $token;
        $ttl = (int) ($validated['ttl'] ?? config('services.apm_bridge.token_ttl', 3600));

        try {
            Cache::put('risk_api_token:'.$token, $payload, $ttl);
            $read = Cache::get('risk_api_token:'.$token);
            if (! is_array($read) || (int) ($read['staff_id'] ?? 0) < 1) {
                throw new \RuntimeException('Finance Cache did not retain the APM session token.');
            }
        } catch (Throwable $e) {
            Log::error('APM session bridge cache write failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Could not store session token: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Session token stored.',
            'data' => [
                'staff_id' => (int) $payload['staff_id'],
                'ttl' => $ttl,
                'cache_store' => (string) config('cache.default'),
            ],
        ]);
    }

    private function bridgeAuthorized(Request $request): bool
    {
        $expected = trim((string) config('services.apm_bridge.secret', ''));
        if ($expected === '') {
            return false;
        }

        $provided = trim((string) (
            $request->header('X-Finance-Bridge-Token')
            ?? $request->bearerToken()
            ?? $request->input('bridge_token')
            ?? ''
        ));

        return $provided !== '' && hash_equals($expected, $provided);
    }
}
