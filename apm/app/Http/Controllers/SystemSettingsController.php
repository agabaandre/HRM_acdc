<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
