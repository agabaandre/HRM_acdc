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

        session([
            'user' => $json,
            'base_url' => $json['base_url'] ?? '',
            'permissions' => $json['permissions'] ?? [],
            'last_activity' => now(),
        ]);
        session()->save();
    }

    /**
     * Logout user from both Laravel and CodeIgniter sessions
     */
    public function logout(Request $request)
    {
        try {
            $baseUrl = RuntimeUrl::staffPortalBaseUrl();
            $ciLogoutUrl = rtrim($baseUrl, '/') . '/auth/logout';
            
            // Get all cookies from the request to pass to CI logout
            $cookies = $request->cookies->all();
            $cookieString = '';
            foreach ($cookies as $name => $value) {
                $cookieString .= $name . '=' . $value . '; ';
            }
            $cookieString = rtrim($cookieString, '; ');
            
            // Get login URL for redirect
            $loginUrl = rtrim($baseUrl, '/') . '/auth';
            
            // Fully destroy Laravel session
            // Invalidate the session (flushes data, regenerates ID, destroys old session)
            Session::invalidate();
            
            // Prepare redirect response and clear the session cookie
            /** @var RedirectResponse $response */
            $response = redirect($loginUrl);
            if ($response) {
                $response->headers->clearCookie(
                config('session.cookie'),
                config('session.path'),
                config('session.domain'),
                config('session.secure'),
                true, // httpOnly
                false, // raw
                config('session.same_site')
                );
            }
            
            // Try to destroy CodeIgniter session via HTTP request with cookies
            try {
                // Use curl to make request with cookies
                $ch = curl_init($ciLogoutUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_COOKIE => $cookieString,
                    CURLOPT_USERAGENT => $request->userAgent() ?? 'Mozilla/5.0',
                    CURLOPT_SSL_VERIFYPEER => false, // Adjust based on your SSL setup
                    CURLOPT_SSL_VERIFYHOST => false,
                ]);
                
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode >= 400) {
                    Log::warning('CI logout returned error code: ' . $httpCode);
                }
            } catch (\Exception $e) {
                // Log but don't fail if CI logout request fails
                Log::warning('Failed to call CI logout endpoint', [
                    'error' => $e->getMessage(),
                    'url' => $ciLogoutUrl
                ]);
            }
            
            // Return the response with cleared cookie
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            
            // Even if there's an error, fully destroy Laravel session and redirect
            Session::invalidate();
            
            $baseUrl = RuntimeUrl::staffPortalBaseUrl();
            $loginUrl = rtrim($baseUrl, '/') . '/auth';
            
            /** @var RedirectResponse $response */
            $response = redirect($loginUrl);
            // Clear the session cookie
            if ($response) {
                $response->headers->clearCookie(
                    config('session.cookie'),
                    config('session.path'),
                    config('session.domain'),
                    config('session.secure'),
                    true, // httpOnly
                    false, // raw
                    config('session.same_site')
                );
            }
            
            return $response;
        }
    }
    
    /**
     * API endpoint to destroy Laravel session (called from CodeIgniter logout)
     */
    public function apiLogout(Request $request)
    {
        try {
            // Get session cookie name from config
            $sessionCookieName = config('session.cookie', 'laravel_session');
            $sessionPath = config('session.path', '/');
            $sessionDomain = config('session.domain');
            $sessionSecure = config('session.secure', false);
            $sessionSameSite = config('session.same_site', null);
            
            // Log for debugging
            $hasSession = Session::has('user');
            $sessionId = Session::getId();
            
            Log::info('API logout called', [
                'has_session' => $hasSession,
                'session_id' => $sessionId,
                'cookie_name' => $sessionCookieName,
                'cookies_received' => array_keys($request->cookies->all())
            ]);
            
            // Try to invalidate the session if it exists
            try {
                if ($sessionId) {
                    // Invalidate the session (this flushes data, regenerates ID, and destroys old session)
                    Session::invalidate();
                } else {
                    // If no session ID, just flush any existing data
                    Session::flush();
                }
            } catch (\Exception $e) {
                // If session invalidation fails, try to flush
                Log::warning('Session invalidation failed, attempting flush', ['error' => $e->getMessage()]);
                try {
                    Session::flush();
                } catch (\Exception $e2) {
                    Log::warning('Session flush also failed', ['error' => $e2->getMessage()]);
                }
            }
            
            // Create response
            $response = response()->json(['success' => true, 'message' => 'Session destroyed']);
            
            // Always clear the session cookie, even if session didn't exist
            // Clear the session cookie with proper settings for root path
            $response->headers->clearCookie(
                $sessionCookieName,
                $sessionPath,
                $sessionDomain,
                $sessionSecure,
                true, // httpOnly
                false, // raw
                $sessionSameSite
            );
            
            // Also clear cookie for /apm path specifically (in case it was set there)
            $response->headers->clearCookie(
                $sessionCookieName,
                '/apm',
                $sessionDomain,
                $sessionSecure,
                true, // httpOnly
                false, // raw
                $sessionSameSite
            );
            
            // Also try to clear with empty domain (for current domain)
            $response->headers->clearCookie(
                $sessionCookieName,
                $sessionPath,
                null,
                $sessionSecure,
                true,
                false,
                $sessionSameSite
            );
            
            // Also clear with /apm path and null domain
            $response->headers->clearCookie(
                $sessionCookieName,
                '/apm',
                null,
                $sessionSecure,
                true,
                false,
                $sessionSameSite
            );
            
            return $response;
        } catch (\Exception $e) {
            Log::error('API logout error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            // Even on error, try to clear cookies
            try {
                $sessionCookieName = config('session.cookie', 'laravel_session');
                $response = response()->json(['success' => false, 'message' => 'Failed to destroy session: ' . $e->getMessage()], 500);
                
                // Clear cookies anyway
                $response->headers->clearCookie($sessionCookieName, '/', null, false, true);
                $response->headers->clearCookie($sessionCookieName, '/apm', null, false, true);
                
                return $response;
            } catch (\Exception $e2) {
                return response()->json(['success' => false, 'message' => 'Failed to destroy session'], 500);
            }
        }
    }
}

