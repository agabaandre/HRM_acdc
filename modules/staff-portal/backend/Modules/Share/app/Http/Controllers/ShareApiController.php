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
            return response()->json(['success' => false, 'message' => 'No token', 'session_expired' => true], 401);
        }

        $payload = SsoJwt::decode($token);
        if (! $payload || empty($payload['staff_id'])) {
            return response()->json(['success' => false, 'message' => 'Invalid token', 'session_expired' => true], 401);
        }

        $staff = Staff::query()->find((int) $payload['staff_id']);
        if (! $staff) {
            return response()->json(['success' => false, 'message' => 'User not found', 'session_expired' => true], 401);
        }

        return response()->json([
            'success' => true,
            'user' => $payload,
            'staff' => ['staff_id' => $staff->staff_id, 'fname' => $staff->fname, 'lname' => $staff->lname],
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
