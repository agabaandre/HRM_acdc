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
    public function handle(Request $request, Closure $next)
    {
        // Skip session check for API routes and AJAX requests
        if ($request->is('api/*') || $request->ajax()) {
            return $next($request);
        }

        // Check if user is logged in (using CI session data)
        $userSession = session('user', []);
        if (empty($userSession)) {
            return $next($request);
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
                
                return redirect()->route('login')->with('error', 'Your session has expired. Please log in again.');
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
            // Get CI app base URL from config
            $ciBaseUrl = config('app.ci_base_url', 'http://localhost/staff');
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
