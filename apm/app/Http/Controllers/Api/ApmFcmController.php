<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApmApiUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApmFcmController extends Controller
{
    /**
     * Register or update the FCM device token for the authenticated API user.
     * Call this after login (and when the token refreshes on the device).
     * Pass empty string or omit token to clear the stored token.
     */
    public function updateToken(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user instanceof ApmApiUser) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'token' => 'nullable|string|max:512',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $token = $request->input('token');
        $token = is_string($token) ? trim($token) : '';
        if ($token === '') {
            $user->update(['firebase_token' => null]);
            return response()->json([
                'success' => true,
                'message' => 'Firebase token cleared.',
            ]);
        }

        $user->update(['firebase_token' => $token]);
        return response()->json([
            'success' => true,
            'message' => 'Firebase token updated.',
        ]);
    }
}
