<?php

namespace App\Http\Controllers;

use App\Support\SystemSettingsBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Staff\Shared\StaffApiCredentials;
use Throwable;

class StaffApiSettingsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function getIndexData(): array
    {
        $data = StaffApiCredentials::snapshot(new SystemSettingsBag('staff_api'), 'APM');
        $data['update_url'] = route('staff-api-settings.update');
        $data['test_url'] = route('staff-api-settings.test');

        return $data;
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'staff_api_base_url' => ['nullable', 'string', 'max:500'],
            'staff_api_username' => ['nullable', 'string', 'max:255'],
            'staff_api_password' => ['nullable', 'string', 'max:255'],
            'staff_api_token' => ['nullable', 'string', 'max:255'],
            'clear_password' => ['nullable', 'boolean'],
            'clear_token' => ['nullable', 'boolean'],
        ]);
        try {
            $msg = StaffApiCredentials::persist(new SystemSettingsBag('staff_api'), $validated);
        } catch (Throwable $e) {
            Log::error('APM staff API settings update failed', ['error' => $e->getMessage()]);
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return redirect()->route('system-configs.index', ['tab' => 'staff-api'])
                ->with('type', 'danger')->with('msg', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->route('system-configs.index', ['tab' => 'staff-api'])
            ->with('type', 'success')->with('msg', $msg);
    }

    public function test(Request $request): JsonResponse
    {
        $override = [];
        foreach (['staff_api_base_url' => 'base_url', 'staff_api_username' => 'username', 'staff_api_password' => 'password', 'staff_api_token' => 'token'] as $in => $out) {
            if ($request->filled($in)) {
                $override[$out] = (string) $request->input($in);
            }
        }
        $result = StaffApiCredentials::probe(new SystemSettingsBag('staff_api'), $override !== [] ? $override : null);

        return response()->json($result);
    }
}
