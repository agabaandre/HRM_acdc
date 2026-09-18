<?php

namespace Modules\Share\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\SsoJwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Staff\Models\Staff;

class ShareApiController extends Controller
{
    public function validateSession(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'No valid authorization token provided',
                'session_expired' => true,
            ], 401);
        }

        $payload = SsoJwt::decode($token);
        if (! $payload || empty($payload['staff_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token format',
                'session_expired' => true,
            ], 401);
        }

        $staff = Staff::query()->find((int) $payload['staff_id']);
        if (! $staff) {
            return response()->json([
                'success' => false,
                'message' => 'User not found or inactive',
                'session_expired' => true,
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Session is valid',
            'session_expired' => false,
            'user' => [
                'staff_id' => $staff->staff_id,
                'name' => trim(($staff->fname ?? '').' '.($staff->lname ?? '')),
                'email' => $staff->work_email,
            ],
        ]);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'No valid authorization token provided',
            ], 401);
        }

        $payload = SsoJwt::decode($token, true);
        if (! $payload || empty($payload['staff_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token format',
            ], 401);
        }

        $staff = Staff::query()->find((int) $payload['staff_id']);
        if (! $staff) {
            return response()->json([
                'success' => false,
                'message' => 'User not found or inactive',
            ], 401);
        }

        $ttl = 7200;
        unset($payload['iat'], $payload['exp']);
        $payload['staff_id'] = (int) $staff->staff_id;
        $payload['base_url'] = rtrim((string) config('staff-portal.base_url', config('app.url')), '/').'/';
        $newToken = SsoJwt::encode($payload, $ttl);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'token' => $newToken,
            'sso_token' => $newToken,
            'expires_at' => date('c', time() + $ttl),
            'expires_in' => $ttl,
        ]);
    }

    public function currentStaff(Request $request): JsonResponse
    {
        $user = $request->user();
        $staffId = $user?->auth_staff_id ?? session('user.staff_id');
        $staff = Staff::query()->find($staffId);
        if (! $staff) {
            return response()->json(['message' => 'Staff not found'], 404);
        }

        return response()->json(['staff' => $staff]);
    }
}
