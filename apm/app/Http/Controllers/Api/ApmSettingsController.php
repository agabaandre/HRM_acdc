<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApmSettingsController extends Controller
{
    /**
     * Return system settings (branding, app name, currency, etc.).
     * Public endpoint so the app can load theme/name before login.
     * GET /apm/v1/settings
     * GET /apm/v1/settings?group=branding
     * GET /apm/v1/settings?group=app,locale
     */
    public function index(Request $request): JsonResponse
    {
        $groupParam = $request->query('group');
        $groups = $groupParam
            ? array_map('trim', explode(',', $groupParam))
            : null;

        $query = SystemSetting::query()->orderBy('group')->orderBy('key');
        if ($groups !== null) {
            $query->whereIn('group', $groups);
        }

        $rows = $query->get(['key', 'value', 'group']);
        $data = [];
        foreach ($rows as $row) {
            $group = $row->group ?? 'general';
            if (!isset($data[$group])) {
                $data[$group] = [];
            }
            $data[$group][$row->key] = $row->value;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
