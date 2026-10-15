<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApmApiUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Finance → APM: mint a JWT for an active apm_api_users row using the shared
 * bridge secret (same FINANCE_APM_BRIDGE_SECRET / STAFF_API_TOKEN used by
 * APM → Finance budget-execution). No password required.
 */
class FinanceSessionBridgeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! $this->bridgeAuthorized($request)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $user = ApmApiUser::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user || ! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'APM API user not found or inactive.',
            ], 404);
        }

        $user->update(['last_used_at' => now()]);
        $token = auth('api')->login($user);

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => (int) config('jwt.ttl') * 60,
                'email' => (string) $user->email,
            ],
        ]);
    }

    private function bridgeAuthorized(Request $request): bool
    {
        $expected = trim((string) config('services.finance_api.bridge_secret', ''));
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
