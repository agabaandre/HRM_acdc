<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Tools\AuthorizesHelpdeskTools;
use App\Http\Controllers\Controller;
use App\Models\ItSetting;
use App\Services\LicenseExpiryAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseAlertSettingsController extends Controller
{
    use AuthorizesHelpdeskTools;

    public function show(Request $request): JsonResponse
    {
        $this->ensureLicenseManager($request);

        return response()->json(['data' => $this->payload()]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->ensureLicenseManager($request);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'interval_days' => ['required', 'integer', 'min:1', 'max:365'],
            'intervals' => ['required', 'array', 'min:1', 'max:12'],
            'intervals.*' => ['integer', 'min:1', 'max:365', 'distinct'],
        ]);

        $intervals = array_values(array_unique(array_map('intval', $validated['intervals'])));
        sort($intervals);
        $active = (int) $validated['interval_days'];
        if (! in_array($active, $intervals, true)) {
            abort(422, 'Choose the reminder interval from the intervals you are keeping.');
        }

        ItSetting::putValue(ItSetting::KEY_LICENSE_EXPIRY_ALERT_ENABLED, $validated['enabled'] ? '1' : '0');
        ItSetting::putValue(ItSetting::KEY_LICENSE_EXPIRY_ALERT_INTERVAL_DAYS, (string) $active);
        ItSetting::putValue(ItSetting::KEY_LICENSE_EXPIRY_ALERT_INTERVALS, implode(',', $intervals));

        return response()->json(['data' => $this->payload()]);
    }

    public function sendNow(Request $request, LicenseExpiryAlertService $alerts): JsonResponse
    {
        $this->ensureLicenseManager($request);

        return response()->json(['data' => $alerts->sendDue()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'enabled' => ItSetting::licenseExpiryAlertEnabled(),
            'interval_days' => ItSetting::licenseExpiryAlertIntervalDays(),
            'intervals' => ItSetting::licenseExpiryAlertIntervals(),
            'last_run_at' => ItSetting::getValue(ItSetting::KEY_LICENSE_EXPIRY_ALERT_LAST_RUN_AT),
        ];
    }
}
