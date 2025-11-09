<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Logout user from both Laravel and CodeIgniter sessions
     */
    public function logout(Request $request)
    {
        try {
            // Get CodeIgniter base URL
            $baseUrl = env('BASE_URL', 'http://localhost/staff');
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
            
            $baseUrl = env('BASE_URL', 'http://localhost/staff');
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

