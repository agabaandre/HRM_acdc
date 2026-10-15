<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApmApiCredentials;
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
        $bag = new RrSettingsBag;

        return response()->json([
            'data' => array_merge(
                StaffApiCredentials::snapshot($bag, 'Finance'),
                ['apm' => ApmApiCredentials::snapshot($bag)],
            ),
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
            'apm_api_base_url' => ['nullable', 'string', 'max:500'],
            'apm_api_email' => ['nullable', 'string', 'max:255'],
            'apm_api_password' => ['nullable', 'string', 'max:255'],
            'clear_apm_password' => ['nullable', 'boolean'],
            'section' => ['nullable', 'string', 'in:staff,apm,both'],
        ]);
        try {
            $bag = new RrSettingsBag;
            $section = (string) ($validated['section'] ?? 'both');
            $messages = [];

            if ($section === 'staff' || $section === 'both') {
                $messages[] = StaffApiCredentials::persist($bag, $validated);
            }
            if ($section === 'apm' || $section === 'both') {
                $messages[] = ApmApiCredentials::persist($bag, $validated);
            }

            return response()->json([
                'success' => true,
                'message' => implode(' ', $messages),
            ]);
        } catch (Throwable $e) {
            Log::error('Finance staff API settings update failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function test(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $target = (string) $request->input('target', 'staff');
        $bag = new RrSettingsBag;

        if ($target === 'apm') {
            $override = [];
            foreach (['apm_api_base_url' => 'base_url', 'apm_api_email' => 'email', 'apm_api_password' => 'password'] as $in => $out) {
                if ($request->filled($in)) {
                    $override[$out] = (string) $request->input($in);
                }
            }
            $result = ApmApiCredentials::probe($bag, $override !== [] ? $override : null);

            return response()->json($result);
        }

        $override = [];
        foreach (['staff_api_base_url' => 'base_url', 'staff_api_username' => 'username', 'staff_api_password' => 'password', 'staff_api_token' => 'token'] as $in => $out) {
            if ($request->filled($in)) {
                $override[$out] = (string) $request->input($in);
            }
        }
        $result = StaffApiCredentials::probe($bag, $override !== [] ? $override : null);

        return response()->json($result);
    }

    private function authorizeManage(Request $request): void
    {
        if (! RiskPermissions::canManage($request->attributes->get('risk_permissions', []))) {
            abort(response()->json(['message' => 'Unauthorized.'], 403));
        }
    }
}
