<?php

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Core\Support\PortalPermission;
use Modules\Settings\Support\PortalKvSettingsBag;
use Staff\Shared\ModuleEmailSettings;
use Staff\Shared\StaffApiCredentials;
use Throwable;

class StaffApiSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        PortalPermission::authorize(15);
        $bag = new PortalKvSettingsBag('staff_api');

        return response()->json([
            'data' => StaffApiCredentials::snapshot($bag, 'Staff Portal'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);
        $validated = $request->validate([
            'staff_api_base_url' => ['nullable', 'string', 'max:500'],
            'staff_api_username' => ['nullable', 'string', 'max:255'],
            'staff_api_password' => ['nullable', 'string', 'max:255'],
            'staff_api_token' => ['nullable', 'string', 'max:255'],
            'clear_password' => ['nullable', 'boolean'],
            'clear_token' => ['nullable', 'boolean'],
        ]);

        $bag = new PortalKvSettingsBag('staff_api');
        try {
            $msg = StaffApiCredentials::persist($bag, $validated);
            $resolved = StaffApiCredentials::resolve($bag);
            // Mirror into env when writable so Share middleware + modules inherit.
            $envPath = base_path('.env');
            $root = dirname(base_path(), 2).'/.env';
            $pairs = [
                'STAFF_API_INTERNAL_BASE_URL' => $resolved['base_url'],
                'STAFF_API_USERNAME' => $resolved['username'],
                'STAFF_API_TOKEN' => $resolved['token'],
            ];
            if ($resolved['password'] !== '') {
                $pairs['STAFF_API_PASSWORD'] = $resolved['password'];
            }
            foreach ([$envPath, $root] as $path) {
                if (is_file($path) && is_writable($path)) {
                    ModuleEmailSettings::upsertEnvKeys($path, $pairs);
                }
            }
            foreach ($pairs as $k => $v) {
                putenv($k.'='.$v);
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }

            return response()->json(['success' => true, 'message' => $msg]);
        } catch (Throwable $e) {
            Log::error('Portal staff API settings update failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function test(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);
        $bag = new PortalKvSettingsBag('staff_api');
        $override = [];
        foreach (['staff_api_base_url' => 'base_url', 'staff_api_username' => 'username', 'staff_api_password' => 'password', 'staff_api_token' => 'token'] as $in => $out) {
            if ($request->filled($in)) {
                $override[$out] = (string) $request->input($in);
            }
        }
        $result = StaffApiCredentials::probe($bag, $override !== [] ? $override : null);

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}
