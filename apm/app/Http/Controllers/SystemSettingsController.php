<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class SystemSettingsController extends Controller
{
    /**
     * Group labels for display.
     */
    private const GROUP_LABELS = [
        'branding' => 'Branding',
        'app'      => 'Application',
        'locale'   => 'Locale & format',
        'ui'       => 'UI',
        'general'  => 'Other settings',
    ];

    /**
     * Show the form for editing app settings (table layout with types).
     */
    public function index(): View
    {
        if (!in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to app settings');
        }
        $grouped = SystemSetting::getGroupedForEditing();
        $order = ['branding', 'app', 'locale', 'ui', 'general'];
        $groups = [];
        foreach ($order as $g) {
            $groups[$g] = [
                'label' => self::GROUP_LABELS[$g] ?? $g,
                'items' => $grouped[$g] ?? [],
            ];
        }
        foreach (array_keys($grouped) as $g) {
            if (!isset($groups[$g])) {
                $groups[$g] = ['label' => self::GROUP_LABELS[$g] ?? $g, 'items' => $grouped[$g]];
            }
        }
        return view('system-settings.index', [
            'groups' => $groups,
        ]);
    }

    /**
     * Update app settings from form submission. Password fields: empty = keep existing value.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        if (!in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to app settings');
        }
        $input = $request->except('_token', '_method');
        foreach ($input as $key => $value) {
            if (!is_string($value) && $value !== null) {
                continue;
            }
            $existing = SystemSetting::find($key);
            if (!$existing) {
                continue;
            }
            $type = $existing->type ?? 'text';
            if ($type === 'password') {
                if ($value === '' || $value === null || $value === '••••••••') {
                    continue;
                }
            }
            $group = $existing->group ?: $this->inferGroup($key);
            SystemSetting::set($key, $value === '' ? null : $value, $group, $type);
        }
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Settings updated successfully.']);
        }
        return redirect()->route('system-settings.index')
            ->with('msg', 'App settings updated successfully.')
            ->with('type', 'success');
    }

    /**
     * Add a new setting (key, value, group, type). Returns JSON for AJAX with new row data.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        if (!in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to app settings');
        }
        $valid = $request->validate([
            'key'   => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\.\-]+$/', Rule::unique('system_settings', 'key')],
            'value' => ['nullable', 'string'],
            'group' => ['required', 'string', 'max:50', Rule::in(['branding', 'app', 'locale', 'ui', 'general'])],
            'type'  => ['required', 'string', Rule::in(SystemSetting::TYPES)],
        ], [
            'key.regex'   => 'Key may only contain letters, numbers, underscores, dots and hyphens.',
            'key.unique'  => 'A setting with this key already exists. Edit it below or choose another key.',
        ]);
        $key = $valid['key'];
        $type = $valid['type'];
        $group = $valid['group'];
        $value = $valid['value'] === '' ? null : $valid['value'];
        SystemSetting::set($key, $value, $group, $type);
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Setting added.',
                'setting' => [
                    'key'   => $key,
                    'type'  => $type,
                    'group' => $group,
                    'value' => $type === 'password' ? null : $value,
                ],
            ]);
        }
        return redirect()->route('system-settings.index')
            ->with('msg', 'Setting "' . $key . '" added successfully.')
            ->with('type', 'success');
    }

    /**
     * Delete a setting by key. Returns JSON for AJAX.
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        if (!in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to app settings');
        }
        $valid = $request->validate([
            'key' => ['required', 'string', 'max:100', 'exists:system_settings,key'],
        ]);
        $key = $valid['key'];
        SystemSetting::find($key)->delete();
        Cache::forget('system_setting:' . $key);
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Setting removed.']);
        }
        return redirect()->route('system-settings.index')
            ->with('msg', 'Setting "' . $key . '" removed.')
            ->with('type', 'success');
    }

    private function inferGroup(string $key): ?string
    {
        $map = [
            'primary_color' => 'branding', 'secondary_color' => 'branding', 'primary_color_dark' => 'branding',
            'logo' => 'branding', 'logo_dark' => 'branding', 'favicon' => 'branding',
            'application_name' => 'app', 'application_short_name' => 'app', 'tagline' => 'app',
            'support_email' => 'app', 'footer_text' => 'app',
            'default_currency' => 'locale', 'currency_symbol' => 'locale', 'timezone' => 'locale',
            'date_format' => 'locale', 'date_time_format' => 'locale', 'locale' => 'locale',
            'items_per_page' => 'ui', 'pagination_size' => 'ui', 'maintenance_mode' => 'ui',
        ];
        return $map[$key] ?? 'general';
    }
}
