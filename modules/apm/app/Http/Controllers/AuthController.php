<?php

namespace App\Http\Controllers;

use App\Support\RuntimeUrl;
use App\Support\StaffSsoLaunchCode;
use App\Support\StaffSsoPolicy;
use App\Support\StaffSsoToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Staff\Shared\SessionCookieClearer;

class AuthController extends Controller
{
    /**
     * Secure SSO: POST one-time code from Staff portal (JWT never in URL).
     */
    public function ssoAccept(Request $request): RedirectResponse
    {
        $jwt = trim((string) ($_POST['staff_sso_jwt'] ?? $request->input('staff_sso_jwt', '')));
        if ($jwt === '') {
            $code = trim((string) ($_POST['sso_code'] ?? $request->input('sso_code', '')));
            if ($code !== '') {
                $record = \App\Support\StaffSsoCodeStore::consume($code, 'approvals_management');
                $jwt = (string) ($record['jwt'] ?? '');
            }
        }
        if ($jwt !== '') {
            try {
                $this->openSessionFromStaffToken($jwt);

                return redirect()->route('home');
            } catch (\Throwable $e) {
                try {
                    Log::warning('APM SSO exchange failed: '.$e->getMessage());
                } catch (\Throwable) {
                }
            }
        }

        return redirect(RuntimeUrl::staffPortalLoginUrl());
    }

    /**
     * Refresh APM web session from a fresh Staff portal SSO JWT (posted by cbp-session-refresh.js).
     */
    public function ssoRefresh(Request $request): JsonResponse
    {
        $jwt = trim((string) $request->input('sso_token', $request->input('staff_sso_jwt', '')));
        if ($jwt === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing SSO token',
            ], 422);
        }

        // Staff portal JWT always reflects the real CI user. While impersonating in APM,
        // do not replace the impersonated web session (cbp-session-refresh.js runs every ~15 min).
        if ($this->impersonationIsActive()) {
            $this->refreshOriginalUserSsoToken($jwt);

            $user = session('user', []);

            return response()->json([
                'success' => true,
                'message' => 'Session refresh skipped while impersonating',
                'impersonating' => true,
                'expires_at' => isset($user['sso_jwt_exp'])
                    ? date('c', (int) $user['sso_jwt_exp'])
                    : now()->addHours(2)->toIso8601String(),
            ]);
        }

        try {
            $this->openSessionFromStaffToken($jwt);

            $user = session('user', []);

            return response()->json([
                'success' => true,
                'message' => 'Session refreshed',
                'expires_at' => isset($user['sso_jwt_exp'])
                    ? date('c', (int) $user['sso_jwt_exp'])
                    : now()->addHours(2)->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('APM SSO refresh failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired SSO token',
            ], 401);
        }
    }

    /**
     * SSO entry point: decode ?token= from Staff portal and open an APM session.
     * @deprecated Prefer POST /sso/accept with one-time code from home/launch_module.
     */
    public function ssoEntry(Request $request): RedirectResponse
    {
        $rawToken = $request->query('token');

        if ($rawToken && StaffSsoPolicy::urlTokenAllowed()) {
            try {
                $this->openSessionFromStaffToken(is_string($rawToken) ? $rawToken : '');

                return redirect()->route('home');
            } catch (\Exception $e) {
                Log::error('Token processing error: '.$e->getMessage());

                return redirect(RuntimeUrl::staffPortalLoginUrl());
            }
        }

        $userSession = session('user', []);
        if (! empty($userSession) && isset($userSession['staff_id'])) {
            return redirect()->route('home');
        }

        return redirect(RuntimeUrl::staffPortalLoginUrl());
    }

    private function impersonationIsActive(): bool
    {
        return session()->has('original_user')
            && (bool) data_get(session('user'), 'is_impersonated', false);
    }

    /**
     * Keep the admin's Staff SSO JWT fresh on original_user while browsing as someone else.
     */
    private function refreshOriginalUserSsoToken(string $jwt): void
    {
        $json = StaffSsoToken::decode($jwt);
        if (! is_array($json)) {
            return;
        }

        $original = session('original_user');
        if (! is_array($original)) {
            return;
        }

        $original['sso_jwt'] = $jwt;
        if (isset($json['exp'])) {
            $original['sso_jwt_exp'] = (int) $json['exp'];
        }

        session(['original_user' => $original]);
        session()->save();
    }

    /**
     * @throws \RuntimeException
     */
    private function openSessionFromStaffToken(string $rawToken): void
    {
        $json = StaffSsoToken::decode($rawToken);
        if (! $json) {
            throw new \RuntimeException('Invalid token format');
        }

        $json['sso_jwt'] = $rawToken;
        if (isset($json['exp'])) {
            $json['sso_jwt_exp'] = (int) $json['exp'];
        }
        if (function_exists('sync_session_staff_id_aliases')) {
            sync_session_staff_id_aliases($json);
        }
        if (class_exists(\App\Support\StaffDivisionContext::class)) {
            \App\Support\StaffDivisionContext::clearActive();
        }

        session([
            'user' => $json,
            'base_url' => $json['base_url'] ?? '',
            'permissions' => $json['permissions'] ?? [],
            'last_activity' => now(),
        ]);
        session()->save();
    }

    /**
     * Clear the APM session, then hand off to Staff Portal web logout
     * (which clears the portal session and lands on SPA /login).
     */
    public function logout(Request $request): RedirectResponse
    {
        try {
            Session::invalidate();
        } catch (\Throwable $e) {
            Log::warning('APM session invalidate during logout failed', [
                'error' => $e->getMessage(),
            ]);
        }

        /** @var RedirectResponse $response */
        $response = redirect()->away(RuntimeUrl::staffPortalLogoutUrl());
        SessionCookieClearer::forgetSessionCookies($response, ['/apm', '/staff/apm']);

        return $response;
    }

    /**
     * API endpoint to destroy Laravel session (called from CodeIgniter logout)
     */
    public function apiLogout(Request $request)
    {
        try {
            $sessionCookieName = (string) config('session.cookie', 'laravel_session');
            $hasSession = Session::has('user');
            $sessionId = Session::getId();

            Log::info('API logout called', [
                'has_session' => $hasSession,
                'session_id' => $sessionId,
                'cookie_name' => $sessionCookieName,
                'cookies_received' => array_keys($request->cookies->all()),
            ]);

            try {
                if ($sessionId) {
                    Session::invalidate();
                } else {
                    Session::flush();
                }
            } catch (\Exception $e) {
                Log::warning('Session invalidation failed, attempting flush', ['error' => $e->getMessage()]);
                try {
                    Session::flush();
                } catch (\Exception $e2) {
                    Log::warning('Session flush also failed', ['error' => $e2->getMessage()]);
                }
            }

            $response = response()->json(['success' => true, 'message' => 'Session destroyed']);
            SessionCookieClearer::forgetSessionCookies($response, ['/apm', '/staff/apm']);

            return $response;
        } catch (\Exception $e) {
            Log::error('API logout error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            try {
                $response = response()->json([
                    'success' => false,
                    'message' => 'Failed to destroy session: '.$e->getMessage(),
                ], 500);
                SessionCookieClearer::forgetSessionCookies($response, ['/apm', '/staff/apm']);

                return $response;
            } catch (\Exception $e2) {
                return response()->json(['success' => false, 'message' => 'Failed to destroy session'], 500);
            }
        }
    }
}

