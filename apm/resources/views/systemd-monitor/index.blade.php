@extends('layouts.app')

@section('title', 'Systemd Monitor')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="fas fa-server me-2"></i>Systemd Service Monitor
                    </h4>
                    <p class="card-subtitle text-muted">Monitor and manage Laravel queue workers and scheduler services</p>
                </div>
                <div class="card-body">
                    <!-- Service Status Cards -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avatar avatar-lg bg-{{ $queue_worker_status['is_running'] ? 'success' : 'danger' }} text-white rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="fas fa-tasks"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h5 class="mb-1">Queue Worker</h5>
                                            <p class="text-muted mb-0">
                                                Status: 
                                                <span class="badge bg-{{ $queue_worker_status['is_running'] ? 'success' : 'danger' }}">
                                                    {{ ucfirst($queue_worker_status['status']) }}
                                                </span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <button class="btn btn-sm btn-outline-primary" onclick="executeCommand('restart-queue-worker')">
                                                <i class="fas fa-redo"></i> Restart
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avatar avatar-lg bg-{{ $scheduler_status['is_running'] ? 'success' : 'danger' }} text-white rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h5 class="mb-1">Scheduler</h5>
                                            <p class="text-muted mb-0">
                                                Status: 
                                                <span class="badge bg-{{ $scheduler_status['is_running'] ? 'success' : 'danger' }}">
                                                    {{ ucfirst($scheduler_status['status']) }}
                                                </span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <button class="btn btn-sm btn-outline-primary" onclick="executeCommand('restart-scheduler')">
                                                <i class="fas fa-redo"></i> Restart
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Queue Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="avatar avatar-lg bg-warning text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <h3 class="mb-1">{{ $failed_jobs_count }}</h3>
                                    <p class="text-muted mb-0">Failed Jobs</p>
                                    <button class="btn btn-sm btn-outline-warning mt-2" onclick="executeCommand('retry-failed-jobs')">
                                        <i class="fas fa-redo"></i> Retry All
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="avatar avatar-lg bg-info text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                        <i class="fas fa-list"></i>
                                    </div>
                                    <h3 class="mb-1">{{ $queue_size }}</h3>
                                    <p class="text-muted mb-0">Pending Jobs</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="avatar avatar-lg bg-info text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <h6 class="mb-1">Last Daily Notification</h6>
                                    <p class="text-muted mb-1 small">{{ $last_daily_notification }}</p>
                                    <p class="text-muted mb-2 small">{{ $approver_count }} approvers</p>
                                    <button class="btn btn-sm btn-outline-info mt-2" onclick="executeCommand('send-daily-notifications')">
                                        <i class="fas fa-paper-plane"></i> Send Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Service Control Buttons -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">Service Controls</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Queue Worker</h6>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-success" onclick="executeCommand('start-queue-worker')">
                                                    <i class="fas fa-play"></i> Start
                                                </button>
                                                <button class="btn btn-warning" onclick="executeCommand('restart-queue-worker')">
                                                    <i class="fas fa-redo"></i> Restart
                                                </button>
                                                <button class="btn btn-danger" onclick="executeCommand('stop-queue-worker')">
                                                    <i class="fas fa-stop"></i> Stop
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Scheduler</h6>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-success" onclick="executeCommand('start-scheduler')">
                                                    <i class="fas fa-play"></i> Start
                                                </button>
                                                <button class="btn btn-warning" onclick="executeCommand('restart-scheduler')">
                                                    <i class="fas fa-redo"></i> Restart
                                                </button>
                                                <button class="btn btn-danger" onclick="executeCommand('stop-scheduler')">
                                                    <i class="fas fa-stop"></i> Stop
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Job Controls -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">Job Controls</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Email Notifications</h6>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-info" onclick="executeCommand('send-daily-notifications')">
                                                    <i class="fas fa-envelope"></i> Send Daily Notifications
                                                </button>
                                            </div>
                                            <small class="text-muted d-block mt-2">Manually trigger daily pending approvals email notifications</small>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Queue Management</h6>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-warning" onclick="executeCommand('retry-failed-jobs')">
                                                    <i class="fas fa-redo"></i> Retry Failed Jobs
                                                </button>
                                                <button class="btn btn-danger" onclick="executeCommand('clear-failed-jobs')">
                                                    <i class="fas fa-trash"></i> Clear Failed Jobs
                                                </button>
                                            </div>
                                            <small class="text-muted d-block mt-2">Manage failed jobs in the queue</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Logs Section -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">Queue Worker Logs (Last 5 minutes)</h5>
                                </div>
                                <div class="card-body">
                                    <pre class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto; font-size: 12px;">{{ $recent_queue_logs ?: 'No recent logs available' }}</pre>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">Scheduler Logs (Last 5 minutes)</h5>
                                </div>
                                <div class="card-body">
                                    <pre class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto; font-size: 12px;">{{ $recent_scheduler_logs ?: 'No recent logs available' }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mb-0">Executing command...</p>
            </div>
        </div>
    </div>
</div>

<!-- Result Modal -->
<div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Command Result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="resultContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="location.reload()">Refresh Page</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function executeCommand(command) {
    // Special handling for daily notifications
    if (command === 'send-daily-notifications') {
        if (!confirm('Are you sure you want to send daily notifications to all {{ $approver_count }} approvers? This will send emails to all users with pending approvals.')) {
            return;
        }
    }
    
    // Show loading modal
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    // Execute command via AJAX
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
        
        // Show result modal
        const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
        const resultContent = document.getElementById('resultContent');
        
        if (data.success) {
            resultContent.innerHTML = `
                <div class="alert alert-success">
                    <h6>Command executed successfully!</h6>
                    <pre class="mb-0">${data.output || 'No output'}</pre>
                </div>
            `;
        } else {
            resultContent.innerHTML = `
                <div class="alert alert-danger">
                    <h6>Command failed!</h6>
                    <pre class="mb-0">${data.error || 'Unknown error'}</pre>
                </div>
            `;
        }
        
        resultModal.show();
    })
    .catch(error => {
        loadingModal.hide();
        
        const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
        const resultContent = document.getElementById('resultContent');
        
        resultContent.innerHTML = `
            <div class="alert alert-danger">
                <h6>Network Error!</h6>
                <pre class="mb-0">${error.message}</pre>
            </div>
        `;
        
        resultModal.show();
    });
}

// Auto-refresh every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);
</script>
@endpush
