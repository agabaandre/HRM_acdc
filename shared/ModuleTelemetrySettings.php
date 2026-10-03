<?php

namespace Staff\Shared;

/**
 * Observability / telemetry settings for CBP modules.
 * Resolve: DB (if set) → env → defaults.
 *
 * Providers: none | datadog | sentry | newrelic | otel
 * Values are stored for ops configuration; Datadog PHP tracer still needs the
 * extension / agent (DD_* at process level) — see Datadog PHP docs.
 */
final class ModuleTelemetrySettings
{
    public const KEY_PROVIDER = 'telemetry_provider';

    public const KEY_SERVICE = 'telemetry_service';

    public const KEY_ENVIRONMENT = 'telemetry_environment';

    public const KEY_DATADOG_AGENT_HOST = 'telemetry_datadog_agent_host';

    public const KEY_DATADOG_TRACE_ENABLED = 'telemetry_datadog_trace_enabled';

    public const KEY_DATADOG_LOGS_INJECTION = 'telemetry_datadog_logs_injection';

    public const KEY_SENTRY_DSN = 'telemetry_sentry_dsn';

    public const KEY_SENTRY_TRACES = 'telemetry_sentry_traces_sample_rate';

    public const KEY_NEWRELIC_APP = 'telemetry_newrelic_app_name';

    public const KEY_NEWRELIC_LICENSE = 'telemetry_newrelic_license_key';

    public const KEY_OTEL_ENDPOINT = 'telemetry_otel_endpoint';

    public const PROVIDERS = ['none', 'datadog', 'sentry', 'newrelic', 'otel'];

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(
        ?ModuleSettingsBag $bag = null,
        string $moduleLabel = 'Module',
        string $defaultService = 'cbp',
    ): array {
        $r = self::resolve($bag, $defaultService);

        return [
            'module' => $moduleLabel,
            'providers' => self::PROVIDERS,
            'settings' => [
                'telemetry_provider' => $bag?->has(self::KEY_PROVIDER) ? (string) $bag->get(self::KEY_PROVIDER) : '',
                'telemetry_service' => $bag?->has(self::KEY_SERVICE) ? (string) $bag->get(self::KEY_SERVICE) : '',
                'telemetry_environment' => $bag?->has(self::KEY_ENVIRONMENT) ? (string) $bag->get(self::KEY_ENVIRONMENT) : '',
                'telemetry_datadog_agent_host' => $bag?->has(self::KEY_DATADOG_AGENT_HOST) ? (string) $bag->get(self::KEY_DATADOG_AGENT_HOST) : '',
                'telemetry_datadog_trace_enabled' => $bag?->has(self::KEY_DATADOG_TRACE_ENABLED) ? (string) $bag->get(self::KEY_DATADOG_TRACE_ENABLED) : '',
                'telemetry_datadog_logs_injection' => $bag?->has(self::KEY_DATADOG_LOGS_INJECTION) ? (string) $bag->get(self::KEY_DATADOG_LOGS_INJECTION) : '',
                'telemetry_sentry_dsn' => '',
                'telemetry_sentry_dsn_configured' => ($r['sentry_dsn'] ?? '') !== '',
                'telemetry_sentry_traces_sample_rate' => $bag?->has(self::KEY_SENTRY_TRACES) ? (string) $bag->get(self::KEY_SENTRY_TRACES) : '',
                'telemetry_newrelic_app_name' => $bag?->has(self::KEY_NEWRELIC_APP) ? (string) $bag->get(self::KEY_NEWRELIC_APP) : '',
                'telemetry_newrelic_license_key' => '',
                'telemetry_newrelic_license_configured' => ($r['newrelic_license'] ?? '') !== '',
                'telemetry_otel_endpoint' => $bag?->has(self::KEY_OTEL_ENDPOINT) ? (string) $bag->get(self::KEY_OTEL_ENDPOINT) : '',
            ],
            'resolved' => [
                'provider' => $r['provider'],
                'service' => $r['service'],
                'environment' => $r['environment'],
                'datadog_agent_host' => $r['datadog_agent_host'],
                'datadog_trace_enabled' => $r['datadog_trace_enabled'],
                'datadog_logs_injection' => $r['datadog_logs_injection'],
                'sentry_dsn_configured' => $r['sentry_dsn'] !== '',
                'sentry_traces_sample_rate' => $r['sentry_traces'],
                'newrelic_app_name' => $r['newrelic_app'],
                'newrelic_license_configured' => $r['newrelic_license'] !== '',
                'otel_endpoint' => $r['otel_endpoint'],
            ],
            'sources' => $r['sources'],
            'notes' => [
                'datadog' => 'Datadog PHP tracer uses DD_* at the PHP/FPM process level (extension + agent). Saving here writes .env for Docker/setup; ensure the agent is reachable.',
                'sentry' => 'Requires sentry/sentry-laravel. DSN empty disables sending.',
                'newrelic' => 'Requires New Relic PHP agent. License key is write-only in this UI.',
                'otel' => 'OpenTelemetry OTLP endpoint for otel PHP SDK / collector.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolve(?ModuleSettingsBag $bag = null, string $defaultService = 'cbp'): array
    {
        $pick = static function (string $dbKey, array $envKeys, string $default = '') use ($bag): array {
            if ($bag instanceof ModuleSettingsBag && $bag->has($dbKey)) {
                return ['value' => trim((string) $bag->get($dbKey)), 'source' => 'db'];
            }
            foreach ($envKeys as $ek) {
                $v = self::envStr($ek);
                if ($v !== '') {
                    return ['value' => $v, 'source' => 'env'];
                }
            }

            return ['value' => $default, 'source' => $default !== '' ? 'default' : 'unset'];
        };

        $provider = $pick(self::KEY_PROVIDER, ['TELEMETRY_PROVIDER'], 'none');
        $prov = strtolower((string) $provider['value']);
        if (! in_array($prov, self::PROVIDERS, true)) {
            $prov = 'none';
        }

        $service = $pick(self::KEY_SERVICE, ['TELEMETRY_SERVICE', 'DD_SERVICE', 'OTEL_SERVICE_NAME', 'NEW_RELIC_APP_NAME'], $defaultService);
        $environment = $pick(self::KEY_ENVIRONMENT, ['TELEMETRY_ENVIRONMENT', 'DD_ENV', 'SENTRY_ENVIRONMENT', 'APP_ENV'], 'production');
        $ddHost = $pick(self::KEY_DATADOG_AGENT_HOST, ['DD_AGENT_HOST', 'DATADOG_AGENT_HOST'], '127.0.0.1');
        $ddTrace = $pick(self::KEY_DATADOG_TRACE_ENABLED, ['DD_TRACE_ENABLED'], 'true');
        $ddLogs = $pick(self::KEY_DATADOG_LOGS_INJECTION, ['DD_LOGS_INJECTION'], 'true');
        $sentryDsn = $pick(self::KEY_SENTRY_DSN, ['SENTRY_LARAVEL_DSN', 'SENTRY_DSN']);
        $sentryTraces = $pick(self::KEY_SENTRY_TRACES, ['SENTRY_TRACES_SAMPLE_RATE'], '0.0');
        $nrApp = $pick(self::KEY_NEWRELIC_APP, ['NEW_RELIC_APP_NAME'], $defaultService);
        $nrKey = $pick(self::KEY_NEWRELIC_LICENSE, ['NEW_RELIC_LICENSE_KEY']);
        $otel = $pick(self::KEY_OTEL_ENDPOINT, ['OTEL_EXPORTER_OTLP_ENDPOINT', 'OTEL_EXPORTER_OTLP_TRACES_ENDPOINT']);

        return [
            'provider' => $prov,
            'service' => (string) $service['value'],
            'environment' => (string) $environment['value'],
            'datadog_agent_host' => (string) $ddHost['value'],
            'datadog_trace_enabled' => self::boolish($ddTrace['value']),
            'datadog_logs_injection' => self::boolish($ddLogs['value']),
            'sentry_dsn' => (string) $sentryDsn['value'],
            'sentry_traces' => (string) $sentryTraces['value'],
            'newrelic_app' => (string) $nrApp['value'],
            'newrelic_license' => (string) $nrKey['value'],
            'otel_endpoint' => (string) $otel['value'],
            'sources' => [
                'provider' => (string) $provider['source'],
                'service' => (string) $service['source'],
                'environment' => (string) $environment['source'],
                'datadog_agent_host' => (string) $ddHost['source'],
                'sentry_dsn' => (string) $sentryDsn['source'],
                'otel_endpoint' => (string) $otel['source'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function persist(ModuleSettingsBag $bag, array $input): string
    {
        $setOrClear = static function (string $dbKey, string $inputKey) use ($bag, $input): void {
            if (! array_key_exists($inputKey, $input)) {
                return;
            }
            $v = trim((string) ($input[$inputKey] ?? ''));
            $bag->set($dbKey, $v === '' ? null : $v);
        };

        if (array_key_exists('telemetry_provider', $input)) {
            $p = strtolower(trim((string) $input['telemetry_provider']));
            $bag->set(self::KEY_PROVIDER, in_array($p, self::PROVIDERS, true) ? $p : 'none');
        }

        $setOrClear(self::KEY_SERVICE, 'telemetry_service');
        $setOrClear(self::KEY_ENVIRONMENT, 'telemetry_environment');
        $setOrClear(self::KEY_DATADOG_AGENT_HOST, 'telemetry_datadog_agent_host');
        $setOrClear(self::KEY_DATADOG_TRACE_ENABLED, 'telemetry_datadog_trace_enabled');
        $setOrClear(self::KEY_DATADOG_LOGS_INJECTION, 'telemetry_datadog_logs_injection');
        $setOrClear(self::KEY_SENTRY_TRACES, 'telemetry_sentry_traces_sample_rate');
        $setOrClear(self::KEY_NEWRELIC_APP, 'telemetry_newrelic_app_name');
        $setOrClear(self::KEY_OTEL_ENDPOINT, 'telemetry_otel_endpoint');

        if (! empty($input['clear_sentry_dsn'])) {
            $bag->set(self::KEY_SENTRY_DSN, null);
        } elseif (array_key_exists('telemetry_sentry_dsn', $input)) {
            $dsn = trim((string) $input['telemetry_sentry_dsn']);
            if ($dsn !== '') {
                $bag->set(self::KEY_SENTRY_DSN, $dsn);
            }
        }

        if (! empty($input['clear_newrelic_license'])) {
            $bag->set(self::KEY_NEWRELIC_LICENSE, null);
        } elseif (array_key_exists('telemetry_newrelic_license_key', $input)) {
            $key = trim((string) $input['telemetry_newrelic_license_key']);
            if ($key !== '') {
                $bag->set(self::KEY_NEWRELIC_LICENSE, $key);
            }
        }

        return 'Telemetry settings saved. Non-empty DB values override env.';
    }

    /**
     * Persist bag + mirror resolved agent keys into module/root .env when writable.
     *
     * @param  array<string, mixed>  $input
     * @return array{message: string, pairs: array<string, string>}
     */
    public static function persistAndMirrorEnv(
        ModuleSettingsBag $bag,
        array $input,
        string $moduleEnvPath,
        ?string $rootEnvPath = null,
        string $defaultService = 'cbp',
    ): array {
        $message = self::persist($bag, $input);
        $resolved = self::resolve($bag, $defaultService);
        $pairs = self::envPairsFromResolved($resolved);

        if ($pairs !== []) {
            if (is_file($moduleEnvPath) && is_writable($moduleEnvPath)) {
                ModuleEmailSettings::upsertEnvKeys($moduleEnvPath, $pairs);
            }
            $root = $rootEnvPath ?? dirname($moduleEnvPath, 2).'/.env';
            if (! is_file($root)) {
                $alt = dirname($moduleEnvPath, 3).'/.env';
                if (is_file($alt)) {
                    $root = $alt;
                }
            }
            if (is_file($root) && is_writable($root)) {
                ModuleEmailSettings::upsertEnvKeys($root, $pairs);
            }
            foreach ($pairs as $key => $value) {
                putenv($key.'='.$value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        return ['message' => $message, 'pairs' => $pairs];
    }

    /**
     * Map resolved settings to process/.env keys for Docker and agents.
     *
     * @return array<string, string>
     */
    public static function envPairsFromResolved(array $resolved): array
    {
        $pairs = [
            'TELEMETRY_PROVIDER' => (string) ($resolved['provider'] ?? 'none'),
            'TELEMETRY_SERVICE' => (string) ($resolved['service'] ?? ''),
            'TELEMETRY_ENVIRONMENT' => (string) ($resolved['environment'] ?? ''),
        ];
        $provider = $pairs['TELEMETRY_PROVIDER'];
        if ($provider === 'datadog') {
            $pairs['DD_SERVICE'] = $pairs['TELEMETRY_SERVICE'];
            $pairs['DD_ENV'] = $pairs['TELEMETRY_ENVIRONMENT'];
            $pairs['DD_AGENT_HOST'] = (string) ($resolved['datadog_agent_host'] ?? '127.0.0.1');
            $pairs['DD_TRACE_ENABLED'] = ! empty($resolved['datadog_trace_enabled']) ? 'true' : 'false';
            $pairs['DD_LOGS_INJECTION'] = ! empty($resolved['datadog_logs_injection']) ? 'true' : 'false';
        }
        if ($provider === 'sentry') {
            if (($resolved['sentry_dsn'] ?? '') !== '') {
                $pairs['SENTRY_LARAVEL_DSN'] = (string) $resolved['sentry_dsn'];
                $pairs['SENTRY_DSN'] = (string) $resolved['sentry_dsn'];
            }
            $pairs['SENTRY_TRACES_SAMPLE_RATE'] = (string) ($resolved['sentry_traces'] ?? '0.0');
            $pairs['SENTRY_ENVIRONMENT'] = $pairs['TELEMETRY_ENVIRONMENT'];
        }
        if ($provider === 'newrelic') {
            $pairs['NEW_RELIC_APP_NAME'] = (string) ($resolved['newrelic_app'] ?? $pairs['TELEMETRY_SERVICE']);
            if (($resolved['newrelic_license'] ?? '') !== '') {
                $pairs['NEW_RELIC_LICENSE_KEY'] = (string) $resolved['newrelic_license'];
            }
        }
        if ($provider === 'otel') {
            $pairs['OTEL_SERVICE_NAME'] = $pairs['TELEMETRY_SERVICE'];
            if (($resolved['otel_endpoint'] ?? '') !== '') {
                $pairs['OTEL_EXPORTER_OTLP_ENDPOINT'] = (string) $resolved['otel_endpoint'];
            }
        }

        return array_filter($pairs, static fn ($v) => is_string($v) && $v !== '');
    }

    private static function envStr(string $key): string
    {
        if (function_exists('env')) {
            $v = env($key);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }
        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private static function boolish(string $v): bool
    {
        $v = strtolower(trim($v));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
