<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class SystemSettingsController extends Controller
{
    /**
     * Show the form for editing app settings (branding, app, locale, ui).
     */
    public function index(): View
    {
        if (!in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to app settings');
        }
        $settings = SystemSetting::orderBy('group')->orderBy('key')->get();
        $byGroup = $settings->groupBy('group')->map(fn ($items) => $items->pluck('value', 'key')->toArray());
        return view('system-settings.index', [
            'settings' => $byGroup->toArray(),
        ]);
    }

    /**
     * Update app settings from form submission.
     */
    public function update(Request $request): RedirectResponse
    {
        if (!in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to app settings');
        }
        $input = $request->except('_token', '_method');
        foreach ($input as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            $existing = SystemSetting::find($key);
            $group = $existing ? $existing->group : $this->inferGroup($key);
            SystemSetting::set($key, $value === '' ? null : $value, $group);
        }
        return redirect()->route('system-settings.index')
            ->with('msg', 'App settings updated successfully.')
            ->with('type', 'success');
    }

    /**
     * Add a new setting (key, value, group).
     */
    public function store(Request $request): RedirectResponse
    {
        if (!in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to app settings');
        }
        $valid = $request->validate([
            'key'   => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\.\-]+$/', Rule::unique('system_settings', 'key')],
            'value' => ['nullable', 'string'],
            'group' => ['required', 'string', 'max:50', Rule::in(['branding', 'app', 'locale', 'ui', 'general'])],
        ], [
            'key.regex'   => 'Key may only contain letters, numbers, underscores, dots and hyphens.',
            'key.unique'  => 'A setting with this key already exists. Edit it below or choose another key.',
        ]);
        SystemSetting::set($valid['key'], $valid['value'] === '' ? null : $valid['value'], $valid['group']);
        return redirect()->route('system-settings.index')
            ->with('msg', 'Setting "' . $valid['key'] . '" added successfully.')
            ->with('type', 'success');
    }

    /**
     * Delete a setting by key.
     */
    public function destroy(Request $request): RedirectResponse
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
