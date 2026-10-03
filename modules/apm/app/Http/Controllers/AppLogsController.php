<?php

namespace App\Http\Controllers;

use App\Support\SystemSettingsBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Staff\Shared\LaravelLogReader;
use Staff\Shared\ModuleCriticalErrorAlerts;
use Staff\Shared\ModuleTelemetrySettings;
use Throwable;

class AppLogsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function getIndexData(Request $request): array
    {
        $meta = LaravelLogReader::meta();
        $date = (string) $request->query('date', $meta['default_date']);
        $level = (string) $request->query('level', '');
        $minLevel = (string) $request->query('min_level', '');
        $query = (string) $request->query('q', '');
        $page = max(1, (int) $request->query('page', 1));
        $read = LaravelLogReader::read($date, $level, $query, $page, 100, null, $minLevel);
        $bag = new SystemSettingsBag('telemetry');

        return array_merge($meta, $read, [
            'index_url' => route('system-configs.index', ['tab' => 'app-logs']),
            'update_url' => route('app-logs.telemetry.update'),
            'test_alert_url' => route('app-logs.critical-alert.test'),
            'telemetry' => ModuleTelemetrySettings::snapshot($bag, 'APM', 'apm'),
            'critical_alerts' => ModuleCriticalErrorAlerts::snapshot($bag, 'APM'),
            'flash_ok' => session('success'),
            'flash_error' => session('error'),
        ]);
    }

    public function updateTelemetry(Request $request): RedirectResponse
    {
        if (! in_array(89, user_session('permissions', []), true)) {
            abort(403, 'Unauthorized access to system configuration');
        }

        $validated = $request->validate([
            'telemetry_provider' => ['nullable', 'string', 'max:32'],
            'telemetry_service' => ['nullable', 'string', 'max:255'],
            'telemetry_environment' => ['nullable', 'string', 'max:64'],
            'telemetry_datadog_agent_host' => ['nullable', 'string', 'max:255'],
            'telemetry_datadog_trace_enabled' => ['nullable', 'string', 'max:16'],
            'telemetry_datadog_logs_injection' => ['nullable', 'string', 'max:16'],
            'telemetry_sentry_dsn' => ['nullable', 'string', 'max:500'],
            'telemetry_sentry_traces_sample_rate' => ['nullable', 'string', 'max:16'],
            'telemetry_newrelic_app_name' => ['nullable', 'string', 'max:255'],
            'telemetry_newrelic_license_key' => ['nullable', 'string', 'max:255'],
            'telemetry_otel_endpoint' => ['nullable', 'string', 'max:500'],
            'clear_sentry_dsn' => ['nullable', 'boolean'],
            'clear_newrelic_license' => ['nullable', 'boolean'],
            'critical_alert_enabled' => ['nullable'],
            'critical_alert_emails' => ['nullable', 'string', 'max:2000'],
            'critical_alert_min_level' => ['nullable', 'string', 'max:32'],
            'critical_alert_cooldown_minutes' => ['nullable', 'string', 'max:8'],
        ]);

        $bag = new SystemSettingsBag('telemetry');
        try {
            $result = ModuleTelemetrySettings::persistAndMirrorEnv(
                $bag,
                $validated,
                base_path('.env'),
                null,
                'apm',
            );
            $alertResult = ModuleCriticalErrorAlerts::persistAndMirrorEnv($bag, $validated, base_path('.env'));

            return redirect()
                ->route('system-configs.index', ['tab' => 'app-logs'])
                ->with('success', $result['message'].' '.$alertResult['message']);
        } catch (Throwable $e) {
            Log::error('APM telemetry settings update failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('system-configs.index', ['tab' => 'app-logs'])
                ->with('error', $e->getMessage());
        }
    }

    public function testCriticalAlert(Request $request): RedirectResponse
    {
        if (! in_array(89, user_session('permissions', []), true)) {
            abort(403, 'Unauthorized access to system configuration');
        }

        $result = ModuleCriticalErrorAlerts::sendTest(new SystemSettingsBag('telemetry'), 'APM');

        return redirect()
            ->route('system-configs.index', ['tab' => 'app-logs'])
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
