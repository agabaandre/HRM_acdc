<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\RiskPermissions;
use App\Support\RrSettingsBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Staff\Shared\StaffApiCredentials;
use Throwable;

class StaffApiSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        return response()->json([
            'data' => StaffApiCredentials::snapshot(new RrSettingsBag, 'Risk Register'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'staff_api_base_url' => ['nullable', 'string', 'max:500'],
            'staff_api_username' => ['nullable', 'string', 'max:255'],
            'staff_api_password' => ['nullable', 'string', 'max:255'],
            'staff_api_token' => ['nullable', 'string', 'max:255'],
            'clear_password' => ['nullable', 'boolean'],
            'clear_token' => ['nullable', 'boolean'],
        ]);
        try {
            $msg = StaffApiCredentials::persist(new RrSettingsBag, $validated);

            return response()->json(['success' => true, 'message' => $msg]);
        } catch (Throwable $e) {
            Log::error('Risk Register staff API settings update failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function test(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $override = [];
        foreach (['staff_api_base_url' => 'base_url', 'staff_api_username' => 'username', 'staff_api_password' => 'password', 'staff_api_token' => 'token'] as $in => $out) {
            if ($request->filled($in)) {
                $override[$out] = (string) $request->input($in);
            }
        }
        $result = StaffApiCredentials::probe(new RrSettingsBag, $override !== [] ? $override : null);

        return response()->json($result);
    }

    private function authorizeManage(Request $request): void
    {
        if (! RiskPermissions::canManage($request->attributes->get('risk_permissions', []))) {
            abort(response()->json(['message' => 'Unauthorized.'], 403));
        }
    }
}
