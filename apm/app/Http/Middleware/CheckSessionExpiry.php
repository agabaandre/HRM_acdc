<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckSessionExpiry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    /** Routes that do not require authentication (public docs and FAQ). Include both bare and apm-prefixed paths for when app is under /demo_staff/apm/. */
    private const PUBLIC_PATHS = [
        'docs', 'docs/*',
        'faq', 'help', 'help/user-guide', 'help/approvers-guide',
        'documentation', 'documentation/*',
        'apm/docs', 'apm/docs/*',
        'apm/faq', 'apm/help', 'apm/help/user-guide', 'apm/help/approvers-guide',
        'apm/documentation', 'apm/documentation/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Skip session check for API routes and AJAX requests
        if ($request->is('api/*') || $request->ajax()) {
            return $next($request);
        }

        // Skip session check for public docs and FAQ (no login required)
        if ($request->is(self::PUBLIC_PATHS)) {
            return $next($request);
        }

        // Check if a token is provided in the query string (for initial authentication from CI)
        // If token is present, allow the request to proceed so the route handler can process it
        if ($request->has('token') && !empty($request->query('token'))) {
            return $next($request);
        }

        // Check if user is logged in (using CI session data)
        $userSession = session('user', []);
        if (empty($userSession) || !isset($userSession['staff_id'])) {
            // User is not logged in, redirect to login page which checks session and redirects to home if authenticated
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please log in.',
                    'requires_auth' => true
                ], 401);
            }
            
            // Redirect to CodeIgniter login page which will check session and redirect to home if authenticated
            $base_url = env('BASE_URL', 'http://localhost/staff/');
            return redirect($base_url . 'auth/login');
        }
        $lastActivity = session('last_activity', now());
        $sessionTimeout = config('session.lifetime', 120) * 60; // Convert to seconds

        // Check if session has expired
        if (now()->diffInSeconds($lastActivity) > $sessionTimeout) {
            // Session expired, check with CI app
            if ($this->isCiSessionExpired($userSession)) {
                // CI session expired, log out user
                Auth::logout();
                Session::flush();
                
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Session expired. Please log in again.',
                        'session_expired' => true
                    ], 401);
                }
                
                $base_url = env('BASE_URL', 'http://localhost/staff/');
                return redirect($base_url . 'auth/login');
            }
        }

        // Update last activity
        session(['last_activity' => now()]);

        return $next($request);
    }

    /**
     * Check if CI session has expired by making a request to CI app
     *
     * @param array $userSession
     * @return bool
     */
    private function isCiSessionExpired(array $userSession): bool
    {
        try {
            // Get CI app base URL from confi
            $base_url = env('BASE_URL','http://localhost/staff/');
            $ciBaseUrl = config('app.ci_base_url', $base_url.'/auth/login');
            $ciToken = $userSession['ci_token'] ?? null;
            
            if (!$ciToken) {
                return true; // No token means expired
            }

            // Make a request to CI app to validate session
            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $ciToken,
                    'Accept' => 'application/json',
                ])
                ->get($ciBaseUrl . '/api/validate-session');

            // If request fails or returns 401, session is expired
            return !$response->successful() || $response->status() === 401;
            
        } catch (\Exception $e) {
            // If we can't check, assume session is expired for security
            Log::warning('Failed to validate CI session', ['error' => $e->getMessage()]);
            return true;
        }
    }
}
