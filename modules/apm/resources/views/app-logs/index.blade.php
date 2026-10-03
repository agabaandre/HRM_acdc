@php
    $dates = $panelData['dates'] ?? [];
    $levels = $panelData['levels'] ?? [];
    $entries = $panelData['entries'] ?? [];
    $date = $panelData['date'] ?? date('Y-m-d');
    $level = $panelData['level'] ?? '';
    $minLevel = $panelData['min_level'] ?? '';
    $q = $panelData['query'] ?? '';
    $page = (int) ($panelData['page'] ?? 1);
    $lastPage = (int) ($panelData['last_page'] ?? 1);
    $total = (int) ($panelData['total'] ?? 0);
    $levelCounts = $panelData['level_counts'] ?? [];
    $files = $panelData['files'] ?? [];
    $telemetry = $panelData['telemetry'] ?? [];
    $tSettings = $telemetry['settings'] ?? [];
    $tResolved = $telemetry['resolved'] ?? [];
    $providers = $telemetry['providers'] ?? ['none', 'datadog', 'sentry', 'newrelic', 'otel'];
    $provider = old('telemetry_provider', $tSettings['telemetry_provider'] ?: ($tResolved['provider'] ?? 'none'));
    $critical = $panelData['critical_alerts'] ?? [];
    $cSettings = $critical['settings'] ?? [];
    $cResolved = $critical['resolved'] ?? [];
    $alertEnabled = old('critical_alert_enabled', $cSettings['critical_alert_enabled'] ?: (!empty($cResolved['enabled']) ? 'true' : 'false'));
    $alertMin = old('critical_alert_min_level', $cSettings['critical_alert_min_level'] ?: ($cResolved['min_level'] ?? 'error'));
@endphp

<div class="app-logs-panel">
    @if(!empty($panelData['flash_ok']))
        <div class="alert alert-success border-0 shadow-sm">{{ $panelData['flash_ok'] }}</div>
    @endif
    @if(!empty($panelData['flash_error']))
        <div class="alert alert-danger border-0 shadow-sm">{{ $panelData['flash_error'] }}</div>
    @endif

    <div class="alert alert-info border-0 shadow-sm mb-4">
        <h5 class="alert-heading mb-2"><i class="bx bx-file me-1"></i> Application logs &amp; telemetry</h5>
        <p class="mb-1 small">Built-in reader with opcodesio/log-viewer–style filters (date, level, min severity, search). Daily files: <code>storage/logs/laravel-YYYY-MM-DD.log</code>.</p>
        <p class="mb-0 small text-muted">
            Channel: <code>{{ $panelData['channel'] ?? 'stack' }}</code>
            · stack: <code>{{ $panelData['stack'] ?? 'daily' }}</code>
            · LOG_LEVEL: <code>{{ $panelData['log_level'] ?? 'debug' }}</code>
            · file: <code>{{ $panelData['file'] ?? '—' }}</code>
            {{ !empty($panelData['file_exists']) ? ' · '.$panelData['file_size_human'] : '(missing)' }}
        </p>
    </div>

    <form method="GET" action="{{ $panelData['index_url'] ?? route('system-configs.index', ['tab' => 'app-logs']) }}" class="card border-0 shadow-sm mb-4">
        <input type="hidden" name="tab" value="app-logs">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label" for="log_date">Date</label>
                    <select class="form-select" id="log_date" name="date">
                        @foreach($dates as $d)
                            @php
                                $fileMeta = collect($files)->firstWhere('date', $d);
                                $label = $d.($fileMeta ? ' ('.$fileMeta['size_human'].')' : '');
                            @endphp
                            <option value="{{ $d }}" @selected($d === $date)>{{ $label }}</option>
                        @endforeach
                        @if($dates === [])
                            <option value="{{ $date }}">{{ $date }}</option>
                        @endif
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="log_level">Exact level</label>
                    <select class="form-select" id="log_level" name="level">
                        <option value="">All</option>
                        @foreach($levels as $lv)
                            <option value="{{ $lv }}" @selected($lv === $level)>{{ strtoupper($lv) }}@if(!empty($levelCounts[$lv])) ({{ $levelCounts[$lv] }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="log_min_level">Min severity</label>
                    <select class="form-select" id="log_min_level" name="min_level">
                        <option value="">—</option>
                        @foreach(['error', 'warning', 'info', 'debug'] as $lv)
                            <option value="{{ $lv }}" @selected($lv === $minLevel)>{{ strtoupper($lv) }}+</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="log_q">Contains</label>
                    <input type="text" class="form-control" id="log_q" name="q" value="{{ $q }}" placeholder="Search message…">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0"><i class="bx bx-broadcast me-1"></i> Telemetry / APM</h6>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3">Configure Datadog, Sentry, New Relic, or OpenTelemetry. Saved values override env; agents still need their PHP extension / collector.</p>
            <p class="small mb-3">Resolved provider: <code>{{ $tResolved['provider'] ?? 'none' }}</code>
                · service <code>{{ $tResolved['service'] ?? '—' }}</code>
                · env <code>{{ $tResolved['environment'] ?? '—' }}</code>
            </p>
            <form method="POST" action="{{ $panelData['update_url'] ?? route('app-logs.telemetry.update') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="telemetry_provider">Provider</label>
                        <select class="form-select" id="telemetry_provider" name="telemetry_provider">
                            @foreach($providers as $p)
                                <option value="{{ $p }}" @selected($p === $provider)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="telemetry_service">Service name</label>
                        <input type="text" class="form-control" id="telemetry_service" name="telemetry_service"
                               value="{{ old('telemetry_service', $tSettings['telemetry_service'] ?: ($tResolved['service'] ?? 'apm')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="telemetry_environment">Environment</label>
                        <input type="text" class="form-control" id="telemetry_environment" name="telemetry_environment"
                               value="{{ old('telemetry_environment', $tSettings['telemetry_environment'] ?: ($tResolved['environment'] ?? '')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="telemetry_datadog_agent_host">Datadog agent host</label>
                        <input type="text" class="form-control" id="telemetry_datadog_agent_host" name="telemetry_datadog_agent_host"
                               value="{{ old('telemetry_datadog_agent_host', $tSettings['telemetry_datadog_agent_host'] ?: ($tResolved['datadog_agent_host'] ?? '127.0.0.1')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="telemetry_datadog_trace_enabled">Datadog tracing</label>
                        <select class="form-select" id="telemetry_datadog_trace_enabled" name="telemetry_datadog_trace_enabled">
                            @php $ddTrace = old('telemetry_datadog_trace_enabled', $tSettings['telemetry_datadog_trace_enabled'] ?: (!empty($tResolved['datadog_trace_enabled']) ? 'true' : 'false')); @endphp
                            <option value="true" @selected($ddTrace === 'true')>enabled</option>
                            <option value="false" @selected($ddTrace === 'false')>disabled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="telemetry_datadog_logs_injection">Datadog log injection</label>
                        <select class="form-select" id="telemetry_datadog_logs_injection" name="telemetry_datadog_logs_injection">
                            @php $ddLogs = old('telemetry_datadog_logs_injection', $tSettings['telemetry_datadog_logs_injection'] ?: (!empty($tResolved['datadog_logs_injection']) ? 'true' : 'false')); @endphp
                            <option value="true" @selected($ddLogs === 'true')>enabled</option>
                            <option value="false" @selected($ddLogs === 'false')>disabled</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="telemetry_sentry_dsn">Sentry DSN {{ !empty($tResolved['sentry_dsn_configured']) ? '(configured)' : '' }}</label>
                        <input type="password" class="form-control" id="telemetry_sentry_dsn" name="telemetry_sentry_dsn"
                               value="" placeholder="{{ !empty($tResolved['sentry_dsn_configured']) ? 'Leave blank to keep existing' : 'https://…@sentry.io/…' }}" autocomplete="off">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="clear_sentry_dsn" value="1" id="clear_sentry_dsn">
                            <label class="form-check-label small" for="clear_sentry_dsn">Clear Sentry DSN</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="telemetry_sentry_traces_sample_rate">Sentry traces sample rate</label>
                        <input type="text" class="form-control" id="telemetry_sentry_traces_sample_rate" name="telemetry_sentry_traces_sample_rate"
                               value="{{ old('telemetry_sentry_traces_sample_rate', $tSettings['telemetry_sentry_traces_sample_rate'] ?: ($tResolved['sentry_traces_sample_rate'] ?? '0.0')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="telemetry_newrelic_app_name">New Relic app name</label>
                        <input type="text" class="form-control" id="telemetry_newrelic_app_name" name="telemetry_newrelic_app_name"
                               value="{{ old('telemetry_newrelic_app_name', $tSettings['telemetry_newrelic_app_name'] ?: ($tResolved['newrelic_app_name'] ?? '')) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="telemetry_newrelic_license_key">New Relic license {{ !empty($tResolved['newrelic_license_configured']) ? '(configured)' : '' }}</label>
                        <input type="password" class="form-control" id="telemetry_newrelic_license_key" name="telemetry_newrelic_license_key"
                               value="" placeholder="{{ !empty($tResolved['newrelic_license_configured']) ? 'Leave blank to keep existing' : 'License key' }}" autocomplete="off">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="clear_newrelic_license" value="1" id="clear_newrelic_license">
                            <label class="form-check-label small" for="clear_newrelic_license">Clear New Relic license</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="telemetry_otel_endpoint">OpenTelemetry OTLP endpoint</label>
                        <input type="text" class="form-control" id="telemetry_otel_endpoint" name="telemetry_otel_endpoint"
                               value="{{ old('telemetry_otel_endpoint', $tSettings['telemetry_otel_endpoint'] ?: ($tResolved['otel_endpoint'] ?? '')) }}"
                               placeholder="http://localhost:4318">
                    </div>

                    <div class="col-12"><hr class="my-2"><h6 class="mb-0"><i class="bx bx-envelope me-1"></i> Critical error admin emails</h6>
                        <p class="small text-muted mb-0">Email admins when unhandled exceptions are reported (skips 4xx / validation). Cooldown prevents floods.</p>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="critical_alert_enabled">Alerts</label>
                        <select class="form-select" id="critical_alert_enabled" name="critical_alert_enabled">
                            <option value="true" @selected($alertEnabled === 'true')>enabled</option>
                            <option value="false" @selected($alertEnabled !== 'true')>disabled</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="critical_alert_emails">Admin emails</label>
                        <input type="text" class="form-control" id="critical_alert_emails" name="critical_alert_emails"
                               value="{{ old('critical_alert_emails', $cSettings['critical_alert_emails'] ?: ($cResolved['emails_display'] ?? '')) }}"
                               placeholder="ops@example.org, oncall@example.org">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="critical_alert_min_level">Min level</label>
                        <select class="form-select" id="critical_alert_min_level" name="critical_alert_min_level">
                            @foreach(['error' => 'ERROR+', 'critical' => 'CRITICAL+', 'emergency' => 'EMERGENCY'] as $val => $label)
                                <option value="{{ $val }}" @selected($alertMin === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="critical_alert_cooldown_minutes">Cooldown (min)</label>
                        <input type="number" min="1" max="1440" class="form-control" id="critical_alert_cooldown_minutes" name="critical_alert_cooldown_minutes"
                               value="{{ old('critical_alert_cooldown_minutes', $cSettings['critical_alert_cooldown_minutes'] ?: ($cResolved['cooldown_minutes'] ?? 30)) }}">
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save telemetry &amp; alerts</button>
                    </div>
                </div>
            </form>
            <form method="POST" action="{{ $panelData['test_alert_url'] ?? route('app-logs.critical-alert.test') }}" class="mt-3">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">Send test critical alert</button>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0">{{ $total }} matching {{ \Illuminate\Support\Str::plural('entry', $total) }}</h6>
            <div class="btn-group btn-group-sm">
                @if($page > 1)
                    <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">Prev</a>
                @endif
                <span class="btn btn-light disabled">Page {{ $page }} / {{ $lastPage }}</span>
                @if($page < $lastPage)
                    <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Next</a>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            @forelse($entries as $entry)
                @php
                    $lv = $entry['level'] ?? 'info';
                    $badge = match ($lv) {
                        'error', 'critical', 'alert', 'emergency' => 'danger',
                        'warning' => 'warning',
                        'notice', 'info' => 'info',
                        default => 'secondary',
                    };
                @endphp
                <div class="border-bottom px-3 py-2">
                    <div class="d-flex gap-2 align-items-center mb-1 small">
                        <span class="badge text-bg-{{ $badge }}">{{ strtoupper($lv) }}</span>
                        <code class="text-muted">{{ $entry['timestamp'] ?? '' }}</code>
                        <span class="text-muted">{{ $entry['env'] ?? '' }}</span>
                    </div>
                    <pre class="mb-0 small" style="white-space: pre-wrap; word-break: break-word;">{{ $entry['message'] ?? '' }}</pre>
                </div>
            @empty
                <div class="p-4 text-muted">No log entries for this filter.</div>
            @endforelse
        </div>
    </div>
</div>
