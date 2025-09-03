@extends('layouts.app')

@section('title', 'Jobs Management')

@section('header', 'Jobs Management')

@section('header-actions')
<div class="d-flex gap-2">
    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#systemInfoModal">
        <i class="bx bx-info-circle"></i> System Info
    </button>
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

        <!-- Available Commands -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bx bx-command me-2 text-primary"></i>Available Artisan Commands</h6>
                <div class="text-muted small mt-1">
                    <i class="bx bx-info-circle me-1"></i>Execute system maintenance commands
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

                    <!-- Divisions Sync Command -->
                    <div class="col-md-6 col-lg-3">
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
                    <div class="col-md-6 col-lg-3">
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
                    <div class="col-md-6 col-lg-3">
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

<!-- System Info Modal -->
<div class="modal fade" id="systemInfoModal" tabindex="-1" aria-labelledby="systemInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="systemInfoModalLabel">
                    <i class="bx bx-info-circle me-2 text-primary"></i>System Information
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="systemInfoContent">
                    <div class="text-center">
                        <i class="bx bx-loader-alt bx-spin" style="font-size: 2rem;"></i>
                        <p class="mt-2">Loading system information...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Environment Editor Modal -->
<div class="modal fade" id="envEditorModal" tabindex="-1" aria-labelledby="envEditorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
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
    // Load system info when modal is shown
    $('#systemInfoModal').on('show.bs.modal', function() {
        loadSystemInfo();
    });

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
</script>
@endpush
