<?php

namespace Modules\Auth\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SsoJwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Models\PortalUser;

/**
 * SSO + Sanctum endpoints for CBP modules (APM, Finance, Helpdesk).
 */
class SsoController extends Controller
{
    public function session(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'user' => $user->toSessionArray(),
            'permissions' => $user->toSessionArray()['permissions'] ?? [],
        ]);
    }

    public function issueToken(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $token = $user->createToken(
            'cbp-module',
            ['*'],
            now()->addHours(2)
        );

        $session = $user->toSessionArray();
        $sso = SsoJwt::encode($session, (int) config('staff-portal.sso.token_ttl', 7200));

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'sso_token' => $sso,
            'expires_in' => config('staff-portal.sso.token_ttl', 7200),
            'user' => $session,
        ]);
    }

    public function validateSsoToken(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);
        $payload = SsoJwt::decode($request->input('token'));
        if (! $payload || empty($payload['staff_id'])) {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired token'], 401);
        }

        return response()->json(['valid' => true, 'user' => $payload]);
    }

    public function acceptSsoRedirect(Request $request)
    {
        $payload = SsoJwt::decode((string) $request->query('token', ''));
        if (! $payload || empty($payload['staff_id'])) {
            return redirect()->route('login')->with('error', 'Invalid or expired sign-in link.');
        }

        $user = PortalUser::query()->where('auth_staff_id', (int) $payload['staff_id'])->where('status', 1)->first();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Account not found or inactive.');
        }

        Auth::login($user);
        session(['user' => $user->toSessionArray(), 'last_activity' => now()]);

        return redirect()->route('core.home');
    }
}
