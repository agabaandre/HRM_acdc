<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate Risk Register SPA via Bearer token (cached at SSO accept).
 */
class AuthenticateRiskSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        $session = null;

        if ($bearer !== null && $bearer !== '') {
            $cached = Cache::get('risk_api_token:'.$bearer);
            if (is_array($cached)) {
                $session = $cached;
            }
        }

        if ($session === null) {
            try {
                $fromSession = $request->session()->get('risk_register');
            } catch (\Throwable) {
                $fromSession = null;
            }
            if (is_array($fromSession)
                && ! empty($fromSession['api_token'])
                && $bearer !== null
                && hash_equals((string) $fromSession['api_token'], (string) $bearer)
            ) {
                $session = $fromSession;
            }
        }

        if (! is_array($session) || empty($session['staff_id'])) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('risk_staff_id', (int) $session['staff_id']);
        $request->attributes->set('risk_division_id', (int) ($session['division_id'] ?? 0));
        $perms = $session['permissions'] ?? [];
        if (! is_array($perms)) {
            $perms = [];
        }
        $request->attributes->set('risk_permissions', array_values($perms));

        return $next($request);
    }
}
