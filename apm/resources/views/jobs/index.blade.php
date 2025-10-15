@extends('layouts.app')

@section('title', 'Jobs Management')

@section('header', 'Jobs Management')

@section('header-actions')
<div class="d-flex gap-2">
    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#envEditorModal">
        <i class="bx bx-edit"></i> Edit Environment
    </button>
</div>
@endsection

@section('content')
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-tasks me-2 text-primary"></i>System Jobs & Maintenance</h5>
        <div class="text-muted small mt-1">
            <i class="bx bx-info-circle me-1"></i>Execute artisan commands and manage system configuration
        </div>
    </div>
    <div class="card-body p-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- System Status Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="card-title text-primary">Cache Status</h6>
                        <h3 class="text-primary" id="cacheStatus">
                            <i class="bx bx-loader-alt bx-spin"></i>
                        </h3>
                        <small class="text-muted">System Cache</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="card-title text-success">Last Sync</h6>
                        <h3 class="text-success" id="lastSync">
                            <i class="bx bx-time"></i>
                        </h3>
                        <small class="text-muted">Data Synchronization</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="card-title text-info">Environment</h6>
                        <h3 class="text-info" id="envStatus">
                            <span class="badge bg-info">{{ config('app.env') }}</span>
                        </h3>
                        <small class="text-muted">Application Environment</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="card-title text-warning">Debug Mode</h6>
                        <h3 class="text-warning" id="debugStatus">
                            @if(config('app.debug'))
                                <span class="badge bg-warning">ON</span>
                            @else
                                <span class="badge bg-success">OFF</span>
                            @endif
                        </h3>
                        <small class="text-muted">Debug Status</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bx bx-info-circle me-2 text-primary"></i>System Information</h6>
                <div class="text-muted small mt-1">
                    <i class="bx bx-info-circle me-1"></i>Current system status and configuration
                </div>
            </div>
            <div class="card-body">
                <div id="systemInfoContent">
                    <div class="text-center">
                        <i class="bx bx-loader-alt bx-spin" style="font-size: 2rem;"></i>
                        <p class="mt-2">Loading system information...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Laravel Maintenance Commands -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bx bx-cog me-2 text-primary"></i>Laravel Maintenance Commands</h6>
                <div class="text-muted small mt-1">
                    <i class="bx bx-info-circle me-1"></i>Clear caches, optimize application, and manage storage
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Clear Cache Command -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-trash text-danger" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Clear Cache</h6>
                                <p class="card-text small text-muted">Clear application cache and temporary files</p>
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="executeCommand('cache:clear')">
                                    <i class="bx bx-play me-1"></i> Execute
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Clear Config Command -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-cog text-warning" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Clear Config</h6>
                                <p class="card-text small text-muted">Clear configuration cache</p>
                                <button type="button" class="btn btn-outline-warning btn-sm" 
                                        onclick="executeCommand('config:clear')">
                                    <i class="bx bx-play me-1"></i> Execute
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Clear Route Command -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-route text-info" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Clear Routes</h6>
                                <p class="card-text small text-muted">Clear route cache</p>
                                <button type="button" class="btn btn-outline-info btn-sm" 
                                        onclick="executeCommand('route:clear')">
                                    <i class="bx bx-play me-1"></i> Execute
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Clear View Command -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-show text-secondary" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Clear Views</h6>
                                <p class="card-text small text-muted">Clear compiled view files</p>
                                <button type="button" class="btn btn-outline-secondary btn-sm" 
                                        onclick="executeCommand('view:clear')">
                                    <i class="bx bx-play me-1"></i> Execute
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Storage Link Command -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-link text-success" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Storage Link</h6>
                                <p class="card-text small text-muted">Create symbolic link for storage</p>
                                <button type="button" class="btn btn-outline-success btn-sm" 
                                        onclick="executeCommand('storage:link')">
                                    <i class="bx bx-play me-1"></i> Execute
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Storage Unlink Command -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-unlink text-danger" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Storage Unlink</h6>
                                <p class="card-text small text-muted">Remove symbolic link for storage</p>
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="executeCommand('storage:unlink')">
                                    <i class="bx bx-play me-1"></i> Execute
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Optimize Command -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-tachometer text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Optimize</h6>
                                <p class="card-text small text-muted">Cache config, routes, and views</p>
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="executeCommand('optimize')">
                                    <i class="bx bx-play me-1"></i> Execute
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Config Cache Command -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-memory-card text-warning" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Cache Config</h6>
                                <p class="card-text small text-muted">Cache configuration files</p>
                                <button type="button" class="btn btn-outline-warning btn-sm" 
                                        onclick="executeCommand('config:cache')">
                                    <i class="bx bx-play me-1"></i> Execute
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Audit Cleanup Command -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-clipboard text-danger" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Cleanup Audit Logs</h6>
                                <p class="card-text small text-muted">Remove old audit logs based on retention period</p>
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="executeCommand('audit:cleanup')">
                                    <i class="bx bx-play me-1"></i> Execute
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Synchronization Commands -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bx bx-sync me-2 text-primary"></i>Data Synchronization Commands</h6>
                <div class="text-muted small mt-1">
                    <i class="bx bx-info-circle me-1"></i>Synchronize data from external sources
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Divisions Sync Command -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-building text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Sync Divisions</h6>
                                <p class="card-text small text-muted">Synchronize divisions data from external source</p>
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="executeCommand('divisions:sync')">
                                    <i class="bx bx-play me-1"></i> Execute
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Staff Sync Command -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-user text-success" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Sync Staff</h6>
                                <p class="card-text small text-muted">Synchronize staff data from external source</p>
                                <button type="button" class="btn btn-outline-success btn-sm" 
                                        onclick="executeCommand('staff:sync')">
                                    <i class="bx bx-play me-1"></i> Execute
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Directorates Sync Command -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-network-chart text-info" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Sync Directorates</h6>
                                <p class="card-text small text-muted">Synchronize directorates data from external source</p>
                                <button type="button" class="btn btn-outline-info btn-sm" 
                                        onclick="executeCommand('directorates:sync')">
                                    <i class="bx bx-play me-1"></i> Execute
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification & Reminders Commands -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bx bx-bell me-2 text-primary"></i>Notification & Reminders Commands</h6>
                <div class="text-muted small mt-1">
                    <i class="bx bx-info-circle me-1"></i>Manage email notifications and reminder schedules
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Reminders Schedule Command -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-calendar text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Schedule Reminders</h6>
                                <p class="card-text small text-muted">Schedule daily pending approval reminders for all approvers</p>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                            onclick="executeRemindersSchedule(false)">
                                        <i class="bx bx-play me-1"></i> Schedule Now
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm" 
                                            onclick="executeRemindersSchedule(true)">
                                        <i class="bx bx-send me-1"></i> Force Schedule
                                    </button>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="bx bx-info-circle me-1"></i>
                                    Force mode bypasses time restrictions
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Send Instant Reminders Command -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-send text-success" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Send Instant Reminders</h6>
                                <p class="card-text small text-muted">Send immediate reminders to specific staff or all approvers</p>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-success btn-sm" 
                                            onclick="showInstantRemindersModal()">
                                        <i class="bx bx-send me-1"></i> Send Now
                                    </button>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="bx bx-info-circle me-1"></i>
                                    Send to specific staff or all approvers
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Queue Status Command -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bx bx-data text-info" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="card-title">Queue Status</h6>
                                <p class="card-text small text-muted">Check queue status and pending jobs</p>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-info btn-sm" 
                                            onclick="executeCommand('queue:work --once')">
                                        <i class="bx bx-play me-1"></i> Process Queue
                                    </button>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="bx bx-info-circle me-1"></i>
                                    Process one job from the queue
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Counter Management -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bx bx-reset me-2 text-primary"></i>Document Counter Management</h6>
                <div class="text-muted small mt-1">
                    <i class="bx bx-info-circle me-1"></i>Reset document counters for specific years, divisions, or document types
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label for="counterYear" class="form-label">Year</label>
                        <select id="counterYear" class="form-select">
                            <option value="2024">2024</option>
                            <option value="2025" selected>2025</option>
                            <option value="2026">2026</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="counterDivision" class="form-label">Division (Optional)</label>
                        <select id="counterDivision" class="form-select">
                            <option value="">All Divisions</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="counterType" class="form-label">Document Type (Optional)</label>
                        <select id="counterType" class="form-select">
                            <option value="">All Types</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="button" class="btn btn-primary" onclick="loadDocumentCounters()">
                                <i class="bx bx-search me-1"></i> Load Counters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Counters Table -->
                <div id="countersTable" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Division</th>
                                    <th>Document Type</th>
                                    <th>Year</th>
                                    <th>Current Counter</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="countersTableBody">
                                <!-- Counters will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Reset Form -->
                <div class="card mt-4" id="resetForm" style="display: none;">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="bx bx-warning me-2"></i>Reset Document Counters</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="bx bx-error-circle me-2"></i>
                            <strong>Warning:</strong> This will reset the selected counters to 0. This action cannot be undone!
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Year</label>
                                <input type="number" id="resetYear" class="form-control" min="2020" max="2030" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Division</label>
                                <input type="text" id="resetDivision" class="form-control" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Document Type</label>
                                <input type="text" id="resetType" class="form-control" readonly>
                            </div>
                        </div>
                        
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="syncMode">
                            <label class="form-check-label" for="syncMode">
                                Run synchronously (immediate execution)
                            </label>
                        </div>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-danger" onclick="resetDocumentCounters()">
                                <i class="bx bx-reset me-1"></i> Reset Counters
                            </button>
                            <button type="button" class="btn btn-secondary ms-2" onclick="cancelReset()">
                                <i class="bx bx-x me-1"></i> Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Command Output -->
        <div class="card" id="outputCard" style="display: none;">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bx bx-terminal me-2 text-primary"></i>Command Output</h6>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearOutput()">
                    <i class="bx bx-x"></i> Clear
                </button>
            </div>
            <div class="card-body">
                <div id="commandOutput" class="bg-dark text-light p-3 rounded" style="font-family: 'Courier New', monospace; font-size: 0.9rem; max-height: 400px; overflow-y: auto;">
                    <!-- Command output will be displayed here -->
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Environment Editor Modal -->
<div class="modal fade" id="envEditorModal" tabindex="-1" aria-labelledby="envEditorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="envEditorModalLabel">
                    <i class="bx bx-edit me-2 text-warning"></i>Environment File Editor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="envEditorForm">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bx bx-error-circle me-2"></i>
                        <strong>Warning:</strong> Editing the environment file can affect system functionality. A backup will be created automatically.
                    </div>
                    
                    <div class="mb-3">
                        <label for="envContent" class="form-label fw-semibold">
                            <i class="bx bx-file me-1 text-warning"></i>Environment File Content:
                        </label>
                        <textarea id="envContent" name="content" class="form-control" rows="20" 
                                  style="font-family: 'Courier New', monospace; font-size: 0.9rem;" 
                                  placeholder="Loading environment file..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bx bx-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Load system info on page load
    loadSystemInfo();

    // Load environment content when modal is shown
    $('#envEditorModal').on('show.bs.modal', function() {
        loadEnvContent();
    });

    // Handle environment form submission
    $('#envEditorForm').on('submit', function(e) {
        e.preventDefault();
        saveEnvContent();
    });
});

function executeCommand(command) {
    const button = event.target;
    const originalText = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i>Executing...';
    button.disabled = true;

    // Show output card
    $('#outputCard').show();
    $('#commandOutput').html('<div class="text-info">Executing command: ' + command + '</div>');

    $.ajax({
        url: '{{ route("jobs.execute-command") }}',
        type: 'POST',
        data: {
            command: command,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                $('#commandOutput').html(`
                    <div class="text-success">✓ Command executed successfully</div>
                    <div class="text-light">Execution time: ${response.execution_time}ms</div>
                    <div class="text-light mt-2">Output:</div>
                    <div class="text-light">${response.output || 'No output'}</div>
                `);
                showAlert('Command executed successfully', 'success');
            } else {
                $('#commandOutput').html(`
                    <div class="text-danger">✗ Command failed</div>
                    <div class="text-light">Execution time: ${response.execution_time}ms</div>
                    <div class="text-light mt-2">Error:</div>
                    <div class="text-danger">${response.output || 'Unknown error'}</div>
                `);
                showAlert('Command execution failed', 'danger');
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON;
            $('#commandOutput').html(`
                <div class="text-danger">✗ Command failed</div>
                <div class="text-danger mt-2">Error:</div>
                <div class="text-danger">${response?.message || 'Network error'}</div>
            `);
            showAlert('Command execution failed', 'danger');
        },
        complete: function() {
            // Restore button state
            button.innerHTML = originalText;
            button.disabled = false;
        }
    });
}

function loadSystemInfo() {
    $.ajax({
        url: '{{ route("jobs.system-info") }}',
        type: 'GET',
        success: function(response) {
            if (response.success) {
                const info = response.info;
                $('#systemInfoContent').html(`
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-body">
                                    <h6 class="card-title text-primary">Application Info</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><strong>PHP Version:</strong> ${info.php_version}</li>
                                        <li><strong>Laravel Version:</strong> ${info.laravel_version}</li>
                                        <li><strong>Environment:</strong> <span class="badge bg-info">${info.environment}</span></li>
                                        <li><strong>Debug Mode:</strong> <span class="badge ${info.debug_mode ? 'bg-warning' : 'bg-success'}">${info.debug_mode ? 'ON' : 'OFF'}</span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-body">
                                    <h6 class="card-title text-success">System Configuration</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><strong>Timezone:</strong> ${info.timezone}</li>
                                        <li><strong>Cache Driver:</strong> ${info.cache_driver}</li>
                                        <li><strong>Queue Driver:</strong> ${info.queue_driver}</li>
                                        <li><strong>Database:</strong> ${info.database_connection}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-info">
                                <div class="card-body">
                                    <h6 class="card-title text-info">Server Resources</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><strong>Memory Limit:</strong> ${info.memory_limit}</li>
                                        <li><strong>Max Execution Time:</strong> ${info.max_execution_time}s</li>
                                        <li><strong>Free Disk Space:</strong> ${info.disk_free_space}</li>
                                        <li><strong>Total Disk Space:</strong> ${info.disk_total_space}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-warning">
                                <div class="card-body">
                                    <h6 class="card-title text-warning">Current Status</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><strong>Server Time:</strong> ${info.server_time}</li>
                                        <li><strong>Status:</strong> <span class="badge bg-success">Online</span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            } else {
                $('#systemInfoContent').html('<div class="alert alert-danger">Failed to load system information</div>');
            }
        },
        error: function() {
            $('#systemInfoContent').html('<div class="alert alert-danger">Failed to load system information</div>');
        }
    });
}

function loadEnvContent() {
    $('#envContent').val('Loading...');
    
    $.ajax({
        url: '{{ route("jobs.env-content") }}',
        type: 'GET',
        success: function(response) {
            if (response.success) {
                $('#envContent').val(response.content);
            } else {
                $('#envContent').val('Error: ' + response.message);
                showAlert('Failed to load environment file', 'danger');
            }
        },
        error: function() {
            $('#envContent').val('Error: Failed to load environment file');
            showAlert('Failed to load environment file', 'danger');
        }
    });
}

function saveEnvContent() {
    const content = $('#envContent').val();
    const submitBtn = $('#envEditorForm button[type="submit"]');
    const originalText = submitBtn.html();
    
    submitBtn.html('<i class="bx bx-loader-alt bx-spin me-1"></i>Saving...');
    submitBtn.prop('disabled', true);
    
    $.ajax({
        url: '{{ route("jobs.update-env-content") }}',
        type: 'POST',
        data: {
            content: content,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                showAlert('Environment file updated successfully', 'success');
                $('#envEditorModal').modal('hide');
            } else {
                showAlert('Failed to update environment file: ' + response.message, 'danger');
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON;
            showAlert('Failed to update environment file: ' + (response?.message || 'Unknown error'), 'danger');
        },
        complete: function() {
            submitBtn.html(originalText);
            submitBtn.prop('disabled', false);
        }
    });
}

function clearOutput() {
    $('#outputCard').hide();
    $('#commandOutput').html('');
}

function showAlert(message, type = 'success') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Remove existing alerts
    $('.alert').remove();
    
    // Add new alert at the top of the card body
    $('.card-body').prepend(alertHtml);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        $('.alert').fadeOut(300, function() {
            $(this).remove();
        });
    }, 5000);
}

// Document Counter Management Functions
let currentFilters = {};

$(document).ready(function() {
    // Load filters for document counters
    loadDocumentCounterFilters();
});

function loadDocumentCounterFilters() {
    $.ajax({
        url: '{{ route("jobs.document-counter-filters") }}',
        type: 'GET',
        success: function(response) {
            if (response.success) {
                // Populate division dropdown
                const divisionSelect = $('#counterDivision');
                divisionSelect.empty().append('<option value="">All Divisions</option>');
                Object.entries(response.divisions).forEach(([id, name]) => {
                    divisionSelect.append(`<option value="${name}">${name}</option>`);
                });

                // Populate document type dropdown
                const typeSelect = $('#counterType');
                typeSelect.empty().append('<option value="">All Types</option>');
                Object.entries(response.document_types).forEach(([code, name]) => {
                    typeSelect.append(`<option value="${code}">${code} - ${name}</option>`);
                });
            }
        },
        error: function() {
            showAlert('Failed to load filters', 'error');
        }
    });
}

function loadDocumentCounters() {
    const year = $('#counterYear').val();
    const division = $('#counterDivision').val();
    const type = $('#counterType').val();

    currentFilters = { year, division, type };

    $.ajax({
        url: '{{ route("jobs.document-counters") }}',
        type: 'GET',
        data: { year, division, type },
        success: function(response) {
            if (response.success) {
                displayDocumentCounters(response.counters);
            } else {
                showAlert('Failed to load document counters', 'error');
            }
        },
        error: function() {
            showAlert('Failed to load document counters', 'error');
        }
    });
}

function displayDocumentCounters(counters) {
    const tbody = $('#countersTableBody');
    tbody.empty();

    if (counters.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="5" class="text-center text-muted">No counters found for the selected criteria</td>
            </tr>
        `);
    } else {
        counters.forEach(counter => {
            tbody.append(`
                <tr>
                    <td>${counter.division_short_name}</td>
                    <td>${counter.document_type}</td>
                    <td>${counter.year}</td>
                    <td><span class="badge bg-primary">${counter.counter}</span></td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="showResetForm('${counter.division_short_name}', '${counter.document_type}', ${counter.year})">
                            <i class="bx bx-reset me-1"></i> Reset
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    $('#countersTable').show();
}

function showResetForm(division, type, year) {
    $('#resetYear').val(year);
    $('#resetDivision').val(division);
    $('#resetType').val(type);
    $('#resetForm').show();
    $('#resetForm')[0].scrollIntoView({ behavior: 'smooth' });
}

function resetDocumentCounters() {
    const year = $('#resetYear').val();
    const division = $('#resetDivision').val();
    const type = $('#resetType').val();
    const sync = $('#syncMode').is(':checked');

    if (!confirm(`Are you sure you want to reset counters for ${division} - ${type} - ${year}?`)) {
        return;
    }

    const button = event.target;
    const originalText = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i>Resetting...';
    button.disabled = true;

    $.ajax({
        url: '{{ route("jobs.reset-document-counters") }}',
        type: 'POST',
        data: {
            year: parseInt(year),
            division: division,
            type: type,
            sync: sync,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                if (response.execution_mode === 'synchronous') {
                    // Reload counters if executed synchronously
                    loadDocumentCounters();
                }
                cancelReset();
            } else {
                showAlert(response.message, 'error');
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON;
            showAlert(response?.message || 'Failed to reset counters', 'error');
        },
        complete: function() {
            // Restore button state
            button.innerHTML = originalText;
            button.disabled = false;
        }
    });
}

function cancelReset() {
    $('#resetForm').hide();
    $('#resetYear').val('');
    $('#resetDivision').val('');
    $('#resetType').val('');
    $('#syncMode').prop('checked', false);
}

// Reminders Schedule Functions
function executeRemindersSchedule(force = false) {
    const button = event.target;
    const originalText = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i>Executing...';
    button.disabled = true;

    // Show output card
    $('#outputCard').show();
    $('#commandOutput').html('<div class="text-info">Executing reminders schedule command' + (force ? ' (force mode)' : '') + '...</div>');

    $.ajax({
        url: '{{ route("jobs.reminders-schedule") }}',
        type: 'POST',
        data: {
            force: force,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                $('#commandOutput').html(`
                    <div class="text-success">✓ Reminders schedule executed successfully</div>
                    <div class="text-light">Execution time: ${response.execution_time}ms</div>
                    <div class="text-light">Force mode: ${response.force_mode ? 'Yes' : 'No'}</div>
                    <div class="text-light mt-2">Output:</div>
                    <div class="text-light">${response.output || 'No output'}</div>
                `);
                showAlert('Reminders schedule executed successfully', 'success');
            } else {
                $('#commandOutput').html(`
                    <div class="text-danger">✗ Reminders schedule failed</div>
                    <div class="text-light">Execution time: ${response.execution_time}ms</div>
                    <div class="text-light">Force mode: ${response.force_mode ? 'Yes' : 'No'}</div>
                    <div class="text-light mt-2">Error:</div>
                    <div class="text-danger">${response.output || 'Unknown error'}</div>
                `);
                showAlert('Reminders schedule execution failed', 'danger');
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON;
            $('#commandOutput').html(`
                <div class="text-danger">✗ Reminders schedule failed</div>
                <div class="text-danger mt-2">Error:</div>
                <div class="text-danger">${response?.message || 'Network error'}</div>
            `);
            showAlert('Reminders schedule execution failed', 'danger');
        },
        complete: function() {
            // Restore button state
            button.innerHTML = originalText;
            button.disabled = false;
        }
    });
}

function showInstantRemindersModal() {
    // For now, just show an alert with instructions
    // In a full implementation, you could create a modal with options
    showAlert('To send instant reminders, use the command line: php artisan reminders:send-instant --all', 'info');
}
</script>
@endpush
