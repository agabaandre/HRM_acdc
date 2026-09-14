<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\StaffSsoSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SessionController extends Controller
{
    /**
     * Validate session with CI app
     */
    public function validateSession(Request $request)
    {
        try {
            $userSession = session('user', []);
            
            if (empty($userSession) || !isset($userSession['staff_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No session data found',
                    'session_expired' => true
                ], 401);
            }
            
            $ciToken = StaffSsoSession::bearerToken($userSession);
            
            if (!$ciToken) {
                return response()->json([
                    'success' => true,
                    'message' => 'Session valid (SSO token will refresh from Staff portal)',
                    'session_expired' => false,
                ]);
            }

            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $ciToken,
                    'Accept' => 'application/json',
                ])
                ->get(StaffSsoSession::validateUrl());

            if (!$response->successful() || $response->status() === 401) {
                // CI session expired, log out user
                Auth::logout();
                Session::flush();
                
                return response()->json([
                    'success' => false,
                    'message' => 'CI session expired',
                    'session_expired' => true
                ], 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'Session is valid',
                'session_expired' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Session validation failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Session validation failed',
                'session_expired' => true
            ], 500);
        }
    }

    /**
     * Extend session
     */
    public function extendSession(Request $request)
    {
        try {
            // Check if user has session data
            $userSession = session('user', []);
            
            if (empty($userSession) || !isset($userSession['staff_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No session data found',
                    'session_expired' => true
                ], 401);
            }

            // Validate with CI app first
            $validationResponse = $this->validateSession($request);
            $validationData = $validationResponse->getData(true);
            
            if (!$validationData['success']) {
                return $validationResponse;
            }

            // Update last activity
            session(['last_activity' => now()]);
            
            // Optionally refresh CI token if needed
            $this->refreshCiTokenIfNeeded();

            return response()->json([
                'success' => true,
                'message' => 'Session extended successfully',
                'expires_at' => now()->addMinutes(config('session.lifetime', 120))->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Session extension failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to extend session',
                'session_expired' => true
            ], 500);
        }
    }

    /**
     * Get session status
     */
    public function getSessionStatus(Request $request)
    {
        try {
            // Check if user has session data (even if not fully authenticated)
            $userSession = session('user', []);
            
            // Debug logging
            Log::info('SessionController: getSessionStatus called', [
                'user_session' => $userSession,
                'session_id' => session()->getId(),
                'has_user_session' => !empty($userSession),
                'staff_id' => $userSession['staff_id'] ?? 'not_set'
            ]);
            
            if (empty($userSession) || !isset($userSession['staff_id'])) {
                return response()->json([
                    'success' => false,
                    'authenticated' => false,
                    'session_expired' => true,
                    'message' => 'No session data found',
                    'debug' => [
                        'session_id' => session()->getId(),
                        'user_session_keys' => array_keys($userSession),
                        'has_staff_id' => isset($userSession['staff_id'])
                    ]
                ]);
            }

            $lastActivity = session('last_activity', now());
            $sessionLifetime = config('session.lifetime', 120) * 60; // Convert to seconds
            $timeSinceActivity = now()->diffInSeconds($lastActivity);
            $timeUntilExpiry = $sessionLifetime - $timeSinceActivity;

            return response()->json([
                'success' => true,
                'authenticated' => true,
                'session_expired' => $timeUntilExpiry <= 0,
                'time_until_expiry' => max(0, $timeUntilExpiry),
                'last_activity' => $lastActivity->toISOString(),
                'expires_at' => $lastActivity->addSeconds($sessionLifetime)->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Session status check failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check session status',
                'session_expired' => true
            ], 500);
        }
    }

    /**
     * Get session debug information
     */
    public function getSessionDebug(Request $request)
    {
        try {
            $userSession = session('user', []);
            $allSessionData = session()->all();
            
            return response()->json([
                'success' => true,
                'debug' => [
                    'session_id' => session()->getId(),
                    'user_session' => $userSession,
                    'all_session_keys' => array_keys($allSessionData),
                    'has_user_session' => !empty($userSession),
                    'staff_id' => $userSession['staff_id'] ?? 'not_set',
                    'session_lifetime' => config('session.lifetime', 120),
                    'last_activity' => session('last_activity', 'not_set'),
                    'request_headers' => $request->headers->all(),
                    'cookies' => $request->cookies->all()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Session debug failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get session debug info',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh CI token if needed
     */
    private function refreshCiTokenIfNeeded()
    {
        try {
            $userSession = session('user', []);
            $ciToken = StaffSsoSession::bearerToken($userSession);
            $tokenExpiry = $userSession['sso_jwt_exp'] ?? ($userSession['ci_token_expires_at'] ?? null);
            
            if (!$ciToken) {
                return;
            }

            $shouldRefresh = true;
            if ($tokenExpiry) {
                $expTs = is_numeric($tokenExpiry) ? (int) $tokenExpiry : strtotime((string) $tokenExpiry);
                if ($expTs > 0) {
                    $shouldRefresh = ($expTs - time()) < (30 * 60);
                }
            }

            if (!$shouldRefresh) {
                return;
            }

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $ciToken,
                    'Accept' => 'application/json',
                ])
                ->post(StaffSsoSession::refreshUrl());

            if ($response->successful()) {
                $data = $response->json();
                $newToken = $data['sso_token'] ?? ($data['token'] ?? null);
                if ($data['success'] && is_string($newToken) && $newToken !== '') {
                    $userSession['sso_jwt'] = $newToken;
                    $userSession['ci_token'] = $newToken;
                    $expiresAt = $data['expires_at'] ?? now()->addHours(2)->toISOString();
                    $userSession['sso_jwt_exp'] = is_numeric($expiresAt)
                        ? (int) $expiresAt
                        : strtotime((string) $expiresAt);
                    $userSession['ci_token_expires_at'] = $expiresAt;
                    session(['user' => $userSession]);
                    
                    Log::info('Staff SSO token refreshed successfully');
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to refresh Staff SSO token', ['error' => $e->getMessage()]);
        }
    }
}
