<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\RiskPermissions;
use App\Support\RrSettingsBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Staff\Shared\LaravelLogReader;
use Staff\Shared\ModuleCriticalErrorAlerts;
use Staff\Shared\ModuleTelemetrySettings;
use Throwable;

class AppLogsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $meta = LaravelLogReader::meta();
        $date = (string) $request->query('date', $meta['default_date']);
        $level = (string) $request->query('level', '');
        $minLevel = (string) $request->query('min_level', '');
        $query = (string) $request->query('q', '');
        $page = max(1, (int) $request->query('page', 1));
        $bag = new RrSettingsBag;

        return response()->json([
            'data' => array_merge(
                $meta,
                LaravelLogReader::read($date, $level, $query, $page, 100, null, $minLevel),
                [
                    'telemetry' => ModuleTelemetrySettings::snapshot($bag, 'Risk Register', 'risk-register'),
                    'critical_alerts' => ModuleCriticalErrorAlerts::snapshot($bag, 'Risk Register'),
                ]
            ),
        ]);
    }

    public function updateTelemetry(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
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

        $bag = new RrSettingsBag;
        try {
            $result = ModuleTelemetrySettings::persistAndMirrorEnv(
                $bag,
                $validated,
                base_path('.env'),
                null,
                'risk-register',
            );
            $alertResult = ModuleCriticalErrorAlerts::persistAndMirrorEnv($bag, $validated, base_path('.env'));

            return response()->json([
                'success' => true,
                'message' => $result['message'].' '.$alertResult['message'],
                'data' => [
                    'telemetry' => ModuleTelemetrySettings::snapshot($bag, 'Risk Register', 'risk-register'),
                    'critical_alerts' => ModuleCriticalErrorAlerts::snapshot($bag, 'Risk Register'),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Risk Register telemetry settings update failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function testCriticalAlert(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $result = ModuleCriticalErrorAlerts::sendTest(new RrSettingsBag, 'Risk Register');

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    private function authorizeManage(Request $request): void
    {
        if (! RiskPermissions::canManage($request->attributes->get('risk_permissions', []))) {
            abort(response()->json(['message' => 'Unauthorized.'], 403));
        }
    }
}
