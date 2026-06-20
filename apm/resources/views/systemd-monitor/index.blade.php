@php
    $healthOverall = $health['overall'] ?? 'healthy';
    $healthChecks = $health['checks'] ?? [];
    $healthBannerClass = match ($healthOverall) {
        'critical' => 'sc-health-banner--critical',
        'warning' => 'sc-health-banner--warning',
        default => 'sc-health-banner--healthy',
    };
    $healthBannerLabel = match ($healthOverall) {
        'critical' => 'Critical issues detected',
        'warning' => 'Some checks need attention',
        default => 'All systems operational',
    };
@endphp

<div class="sys-config-monitor">
    <div class="sc-health-banner {{ $healthBannerClass }}">
        <div class="sc-health-banner-icon">
            <i class="bx {{ $healthOverall === 'healthy' ? 'bx-check-shield' : ($healthOverall === 'critical' ? 'bx-error-circle' : 'bx-error') }}"></i>
        </div>
        <div>
            <div class="sc-health-banner-title">{{ $healthBannerLabel }}</div>
            <div class="sc-health-banner-sub">Live health snapshot · auto-refreshes every 30 seconds</div>
        </div>
        <button type="button" class="btn btn-sm btn-light" onclick="location.reload()">
            <i class="bx bx-revision"></i> Refresh
        </button>
    </div>

    <div class="sc-section">
        <div class="sc-section-head">
            <h6><i class="bx bx-pulse me-2 text-success"></i>System health</h6>
            <p>Runtime stack, infrastructure dependencies and resource usage</p>
        </div>
        <div class="sc-section-body">
            <div class="sc-health-grid">
                @foreach ($healthChecks as $check)
                    @php
                        $statusClass = match ($check['status']) {
                            'critical' => 'sc-health-card--critical',
                            'warning' => 'sc-health-card--warning',
                            default => 'sc-health-card--ok',
                        };
                        $statusLabel = match ($check['status']) {
                            'critical' => 'Critical',
                            'warning' => 'Warning',
                            default => 'Healthy',
                        };
                    @endphp
                    <article class="sc-health-card {{ $statusClass }}">
                        <div class="sc-health-card-top">
                            <span class="sc-health-card-icon"><i class="bx {{ $check['icon'] }}"></i></span>
                            <span class="sc-health-pill">{{ $statusLabel }}</span>
                        </div>
                        <div class="sc-health-card-label">{{ $check['label'] }}</div>
                        <div class="sc-health-card-value">{{ $check['value'] }}</div>
                        @if (!empty($check['detail']))
                            <div class="sc-health-card-detail">{{ $check['detail'] }}</div>
                        @endif
                    </article>
                @endforeach
            </div>

            @if ($disk)
            <div class="sc-disk-meter mt-3">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div>
                        <strong>Application disk</strong>
                        <span class="text-muted small ms-2">{{ $disk['path'] ?? base_path() }}</span>
                    </div>
                    <span class="sc-health-pill sc-health-pill--{{ $disk['status'] ?? 'ok' }}">{{ $disk['usage_percent'] ?? 0 }}% used</span>
                </div>
                <div class="sc-disk-meter-bar">
                    <div class="sc-disk-meter-fill sc-disk-meter-fill--{{ $disk['status'] ?? 'ok' }}" style="width: {{ min(100, $disk['usage_percent'] ?? 0) }}%"></div>
                </div>
                <div class="sc-disk-meter-meta">
                    <span>{{ $disk['used_formatted'] ?? '—' }} used</span>
                    <span>{{ $disk['free_formatted'] ?? '—' }} free</span>
                    <span>{{ $disk['total_formatted'] ?? '—' }} total</span>
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="sc-section">
        <div class="sc-section-head">
            <h6><i class="bx bx-server me-2 text-success"></i>Background services</h6>
            <p>Queue worker and scheduler systemd units</p>
        </div>
        <div class="sc-section-body">
            <div class="sc-stats sc-stats--services">
                <div class="sc-stat-card sc-stat-card--service">
                    <div class="sc-stat-icon sc-stat-icon--{{ $queue_worker_status['is_running'] ? 'success' : 'danger' }}">
                        <i class="bx bx-task"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="sc-stat-label">Queue worker</div>
                        <div class="sc-stat-value">
                            <span class="badge bg-{{ $queue_worker_status['is_running'] ? 'success' : 'danger' }}">
                                {{ ucfirst($queue_worker_status['status']) }}
                            </span>
                        </div>
                        <div class="sc-stat-hint">Processes async jobs</div>
                    </div>
                    <div class="sc-service-actions">
                        <button class="btn btn-sm btn-outline-success" onclick="executeCommand('start-queue-worker')" title="Start"><i class="bx bx-play"></i></button>
                        <button class="btn btn-sm btn-outline-primary" onclick="executeCommand('restart-queue-worker')" title="Restart"><i class="bx bx-revision"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="executeCommand('stop-queue-worker')" title="Stop"><i class="bx bx-stop"></i></button>
                    </div>
                </div>
                <div class="sc-stat-card sc-stat-card--service">
                    <div class="sc-stat-icon sc-stat-icon--{{ $scheduler_status['is_running'] ? 'success' : 'danger' }}">
                        <i class="bx bx-time-five"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="sc-stat-label">Scheduler</div>
                        <div class="sc-stat-value">
                            <span class="badge bg-{{ $scheduler_status['is_running'] ? 'success' : 'danger' }}">
                                {{ ucfirst($scheduler_status['status']) }}
                            </span>
                        </div>
                        <div class="sc-stat-hint">Cron &amp; scheduled tasks</div>
                    </div>
                    <div class="sc-service-actions">
                        <button class="btn btn-sm btn-outline-success" onclick="executeCommand('start-scheduler')" title="Start"><i class="bx bx-play"></i></button>
                        <button class="btn btn-sm btn-outline-primary" onclick="executeCommand('restart-scheduler')" title="Restart"><i class="bx bx-revision"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="executeCommand('stop-scheduler')" title="Stop"><i class="bx bx-stop"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sc-monitor-metrics">
        <div class="sc-metric-tile sc-metric-tile--warning">
            <div class="sc-metric-icon"><i class="bx bx-error"></i></div>
            <div>
                <div class="sc-metric-value">{{ $failed_jobs_count }}</div>
                <div class="sc-metric-label">Failed jobs</div>
            </div>
            <button class="btn btn-sm btn-outline-dark" onclick="executeCommand('retry-failed-jobs')">Retry all</button>
        </div>
        <div class="sc-metric-tile sc-metric-tile--info">
            <div class="sc-metric-icon"><i class="bx bx-list-check"></i></div>
            <div>
                <div class="sc-metric-value">{{ $queue_size }}</div>
                <div class="sc-metric-label">Pending jobs</div>
            </div>
        </div>
        <div class="sc-metric-tile sc-metric-tile--primary">
            <div class="sc-metric-icon"><i class="bx bx-envelope"></i></div>
            <div>
                <div class="sc-metric-value sc-metric-value--sm">{{ $last_daily_notification }}</div>
                <div class="sc-metric-label">Last daily notification · {{ $approver_count }} approvers</div>
            </div>
            <button class="btn btn-sm btn-outline-primary" onclick="executeCommand('send-daily-notifications')">Send now</button>
        </div>
    </div>

    <div class="sc-section">
        <div class="sc-section-head d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6><i class="bx bx-slider me-2 text-success"></i>Queue management</h6>
                <p class="mb-0">Manual actions for failed jobs and notification dispatch</p>
            </div>
        </div>
        <div class="sc-section-body">
            <div class="sc-action-row">
                <button class="btn btn-outline-info" onclick="executeCommand('send-daily-notifications')">
                    <i class="bx bx-mail-send me-1"></i> Send daily notifications
                </button>
                <button class="btn btn-outline-warning" onclick="executeCommand('retry-failed-jobs')">
                    <i class="bx bx-revision me-1"></i> Retry failed jobs
                </button>
                <button class="btn btn-outline-danger" onclick="executeCommand('clear-failed-jobs')">
                    <i class="bx bx-trash me-1"></i> Clear failed jobs
                </button>
            </div>
        </div>
    </div>

    <div class="sc-log-grid">
        <div class="sc-section sc-section--log">
            <div class="sc-section-head">
                <h6><i class="bx bx-terminal me-2"></i>Queue worker logs</h6>
                <p>Last 5 minutes · journalctl</p>
            </div>
            <div class="sc-section-body p-0">
                <pre class="sc-log-view">{{ $recent_queue_logs ?: 'No recent logs available' }}</pre>
            </div>
        </div>
        <div class="sc-section sc-section--log">
            <div class="sc-section-head">
                <h6><i class="bx bx-terminal me-2"></i>Scheduler logs</h6>
                <p>Last 5 minutes · journalctl</p>
            </div>
            <div class="sc-section-body p-0">
                <pre class="sc-log-view">{{ $recent_scheduler_logs ?: 'No recent logs available' }}</pre>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-success mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mb-0 text-muted">Executing command...</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title">Command result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="resultContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="location.reload()">Refresh page</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function executeCommand(command) {
    if (command === 'send-daily-notifications') {
        if (!confirm('Send daily notifications to all {{ $approver_count }} approvers with pending approvals?')) {
            return;
        }
    }

    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();

    fetch('{{ route("systemd-monitor.index") }}/execute', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ command: command })
    })
    .then(response => response.json())
    .then(data => {
        loadingModal.hide();

        const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
        const resultContent = document.getElementById('resultContent');

        if (data.success) {
            resultContent.innerHTML = `
                <div class="alert alert-success mb-0">
                    <h6 class="alert-heading">Command executed successfully</h6>
                    <pre class="mb-0 sc-log-inline">${escapeHtml(data.output || 'No output')}</pre>
                </div>
            `;
        } else {
            resultContent.innerHTML = `
                <div class="alert alert-danger mb-0">
                    <h6 class="alert-heading">Command failed</h6>
                    <pre class="mb-0 sc-log-inline">${escapeHtml(data.error || 'Unknown error')}</pre>
                </div>
            `;
        }

        resultModal.show();
    })
    .catch(error => {
        loadingModal.hide();

        const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
        document.getElementById('resultContent').innerHTML = `
            <div class="alert alert-danger mb-0">
                <h6 class="alert-heading">Network error</h6>
                <pre class="mb-0 sc-log-inline">${escapeHtml(error.message)}</pre>
            </div>
        `;
        resultModal.show();
    });
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

setInterval(function() {
    location.reload();
}, 30000);
</script>
@endpush
