@extends('layouts.app')

@section('title', 'Database Backups')
@section('header', 'Database Backup Management')

@push('styles')
<style>
    .backup-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .backup-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .backup-type-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    .stats-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    .stats-card-success {
        background: linear-gradient(135deg, #119A48 0%, #0d7a3a 100%);
        color: white;
    }
    .stats-card-info {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
    }
    .stats-card-warning {
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        color: white;
    }
    .backup-table tbody tr {
        transition: background-color 0.2s;
    }
    .backup-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    .action-buttons .btn {
        margin-right: 0.25rem;
    }
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    .loading-overlay.show {
        display: flex;
    }
    .spinner-border-lg {
        width: 3rem;
        height: 3rem;
        border-width: 0.3rem;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <!-- Disk Space Alert -->
    @if($diskSpace && $diskSpace['status'] !== 'ok')
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-{{ $diskSpace['status'] === 'critical' ? 'danger' : 'warning' }} alert-dismissible fade show shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-{{ $diskSpace['status'] === 'critical' ? 'exclamation-triangle' : 'exclamation-circle' }} fa-2x me-3"></i>
                    <div class="flex-grow-1">
                        <h5 class="alert-heading mb-1">
                            {{ $diskSpace['status'] === 'critical' ? '🚨 CRITICAL' : '⚠️ WARNING' }}: Disk Space Alert
                        </h5>
                        <p class="mb-0">
                            Server disk usage is at <strong>{{ $diskSpace['usage_percent'] }}%</strong>. 
                            Free space: <strong>{{ $diskSpace['free_formatted'] }}</strong> of {{ $diskSpace['total_formatted'] }}.
                            Please take action to free up space.
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
    @endif

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white mb-1" style="opacity: 0.9; font-weight: 500;">Total Backups</h6>
                            <h3 class="mb-0 text-white" style="font-weight: 700;">{{ $stats['total_files'] ?? 0 }}</h3>
                        </div>
                        <div class="fs-1 opacity-50 text-white">
                            <i class="fas fa-database"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white mb-1" style="opacity: 0.9; font-weight: 500;">Daily Backups</h6>
                            <h3 class="mb-0 text-white" style="font-weight: 700;">{{ $stats['daily_backups'] ?? 0 }}</h3>
                        </div>
                        <div class="fs-1 opacity-50 text-white">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white mb-1" style="opacity: 0.9; font-weight: 500;">Monthly Backups</h6>
                            <h3 class="mb-0 text-white" style="font-weight: 700;">{{ $stats['monthly_backups'] ?? 0 }}</h3>
                        </div>
                        <div class="fs-1 opacity-50 text-white">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white mb-1" style="opacity: 0.9; font-weight: 500;">Annual Backups</h6>
                            <h3 class="mb-0 text-white" style="font-weight: 700;">{{ $stats['annual_backups'] ?? 0 }}</h3>
                        </div>
                        <div class="fs-1 opacity-50 text-white">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm" style="background: linear-gradient(135deg, #fd7e14 0%, #dc6502 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white mb-1" style="opacity: 0.9; font-weight: 500;">Total Size</h6>
                            <h3 class="mb-0 text-white" style="font-weight: 700;">{{ $stats['total_size_formatted'] ?? '0 B' }}</h3>
                        </div>
                        <div class="fs-1 opacity-50 text-white">
                            <i class="fas fa-hdd"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Disk Space Monitoring Card -->
    @if($diskSpace)
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-{{ $diskSpace['status'] === 'critical' ? 'danger' : ($diskSpace['status'] === 'warning' ? 'warning' : 'success') }}">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-server me-2 text-{{ $diskSpace['status'] === 'critical' ? 'danger' : ($diskSpace['status'] === 'warning' ? 'warning' : 'success') }}"></i>
                        Server Disk Space Monitor
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="checkDiskSpace()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted d-block mb-2">Total Space</small>
                                <h4 class="mb-0">{{ $diskSpace['total_formatted'] }}</h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted d-block mb-2">Used Space</small>
                                <h4 class="mb-0 text-{{ $diskSpace['status'] === 'critical' ? 'danger' : ($diskSpace['status'] === 'warning' ? 'warning' : 'success') }}">
                                    {{ $diskSpace['used_formatted'] }}
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted d-block mb-2">Free Space</small>
                                <h4 class="mb-0">{{ $diskSpace['free_formatted'] }}</h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <small class="text-muted d-block mb-2">Usage</small>
                                <h4 class="mb-0">
                                    <span class="badge bg-{{ $diskSpace['status'] === 'critical' ? 'danger' : ($diskSpace['status'] === 'warning' ? 'warning' : 'success') }} fs-6">
                                        {{ $diskSpace['usage_percent'] }}%
                                    </span>
                                </h4>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar 
                                {{ $diskSpace['status'] === 'critical' ? 'bg-danger' : ($diskSpace['status'] === 'warning' ? 'bg-warning' : 'bg-success') }}" 
                                role="progressbar" 
                                style="width: {{ $diskSpace['usage_percent'] }}%"
                                aria-valuenow="{{ $diskSpace['usage_percent'] }}" 
                                aria-valuemin="0" 
                                aria-valuemax="100">
                                {{ $diskSpace['usage_percent'] }}%
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Path: <code>{{ $diskSpace['path'] }}</code>
                            </small>
                            <small class="text-muted">
                                Warning: {{ $config['disk_monitor']['warning_threshold'] ?? 80 }}% | 
                                Critical: {{ $config['disk_monitor']['critical_threshold'] ?? 90 }}%
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Action Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-cog me-2 text-success"></i>Backup Actions</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshStats()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <button type="button" class="btn btn-success w-100" onclick="createBackup('daily')">
                                <i class="fas fa-plus-circle me-2"></i>Create Daily Backup
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-primary w-100" onclick="createBackup('monthly')">
                                <i class="fas fa-calendar-check me-2"></i>Create Monthly Backup
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-info w-100" onclick="createBackup('annual')">
                                <i class="fas fa-calendar me-2"></i>Create Annual Backup
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-warning w-100" onclick="runCleanup()">
                                <i class="fas fa-broom me-2"></i>Run Cleanup
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-secondary w-100" onclick="showDatabaseModal()">
                                <i class="fas fa-database me-2"></i>Manage Databases
                            </button>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check form-switch d-flex align-items-center h-100">
                                <input class="form-check-input me-2" type="checkbox" id="onedriveToggle" 
                                    {{ $config['onedrive']['enabled'] ? 'checked' : '' }} 
                                    onchange="toggleOneDrive(this.checked)">
                                <label class="form-check-label" for="onedriveToggle">
                                    <strong>OneDrive Sync</strong>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Info -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Configuration</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <small class="text-muted">Storage Path</small>
                            <p class="mb-0"><strong>{{ $stats['storage_path'] ?? 'N/A' }}</strong></p>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">Daily Retention</small>
                            <p class="mb-0"><strong>{{ $config['retention']['daily_days'] }} days</strong></p>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">Monthly Retention</small>
                            <p class="mb-0"><strong>{{ $config['retention']['monthly_months'] }} months</strong></p>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">Annual Retention</small>
                            <p class="mb-0"><strong>{{ $config['retention']['annual_years'] ?? 1 }} year(s)</strong></p>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">Daily Schedule</small>
                            <p class="mb-0"><strong>{{ $config['schedule']['daily_time'] }}</strong></p>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">Monthly Schedule</small>
                            <p class="mb-0"><strong>Day {{ $config['schedule']['monthly_day'] }}</strong></p>
                        </div>
                        <div class="col-md-1">
                            <small class="text-muted">Compression</small>
                            <p class="mb-0">
                                <span class="badge bg-{{ $config['compression']['enabled'] ? 'success' : 'secondary' }}">
                                    {{ $config['compression']['enabled'] ? 'ON' : 'OFF' }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup Files List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2 text-success"></i>Backup Files</h5>
                    <div class="input-group" style="width: 300px;">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="searchBackups" placeholder="Search backups...">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover backup-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Filename</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Size</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="backupTableBody">
                                @forelse($backups as $backup)
                                <tr>
                                    <td>
                                        <i class="fas fa-file-{{ $backup['is_compressed'] ? 'archive' : 'code' }} me-2 text-muted"></i>
                                        <code class="small">{{ $backup['filename'] }}</code>
                                    </td>
                                    <td>
                                        @php
                                            // Extract database name from filename (format: backup_type_dbname_timestamp.sql)
                                            $dbName = 'N/A';
                                            if (preg_match('/backup_(daily|monthly|annual)_([^_]+)_/', $backup['filename'], $matches)) {
                                                $dbName = $matches[2];
                                            }
                                        @endphp
                                        <span class="badge bg-secondary">{{ $dbName }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $badgeColor = match($backup['type']) {
                                                'daily' => 'success',
                                                'monthly' => 'primary',
                                                'annual' => 'info',
                                                default => 'secondary'
                                            };
                                        @endphp
                                        <span class="badge backup-type-badge bg-{{ $badgeColor }}">
                                            {{ ucfirst($backup['type']) }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>{{ $backup['modified_at']->format('M d, Y H:i') }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $backup['size_formatted'] }}</strong>
                                    </td>
                                    <td>
                                        @if($backup['is_compressed'])
                                            <span class="badge bg-info">Compressed</span>
                                        @else
                                            <span class="badge bg-secondary">SQL</span>
                                        @endif
                                    </td>
                                    <td class="text-end action-buttons">
                                        <a href="{{ route('backups.download', $backup['filename']) }}" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        No backups found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Database Management Modal -->
<div class="modal fade" id="databaseModal" tabindex="-1" aria-labelledby="databaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="databaseModalLabel">
                    <i class="fas fa-database me-2"></i>Manage Backup Databases
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="mb-0 text-muted">Configure databases to backup on this server</p>
                    <button type="button" class="btn btn-sm btn-success" onclick="showAddDatabaseForm()">
                        <i class="fas fa-plus me-1"></i>Add Database
                    </button>
                </div>
                
                <div id="databaseList">
                    @if($databases && $databases->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Display Name</th>
                                        <th>Database</th>
                                        <th>Host</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($databases as $db)
                                    <tr>
                                        <td>
                                            <strong>{{ $db->display_name }}</strong>
                                            @if($db->is_default)
                                                <span class="badge bg-primary ms-1">Default</span>
                                            @endif
                                        </td>
                                        <td><code>{{ $db->name }}</code></td>
                                        <td>{{ $db->host }}:{{ $db->port }}</td>
                                        <td>{{ $db->priority }}</td>
                                        <td>
                                            <span class="badge bg-{{ $db->is_active ? 'success' : 'secondary' }}">
                                                {{ $db->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editDatabase({{ $db->id }})">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteDatabaseConfig({{ $db->id }})">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-database fa-3x mb-3 d-block"></i>
                            <p>No databases configured. Add your first database to get started.</p>
                            <button type="button" class="btn btn-success" onclick="showAddDatabaseForm()">
                                <i class="fas fa-plus me-1"></i>Add Database
                            </button>
                        </div>
                    @endif
                </div>
                
                <!-- Add/Edit Database Form -->
                <div id="databaseForm" style="display: none;">
                    <hr>
                    <h6 id="formTitle">Add Database</h6>
                    <form id="databaseFormElement">
                        <input type="hidden" id="dbId" name="id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Database Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="dbName" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Display Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="dbDisplayName" name="display_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Host <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="dbHost" name="host" value="127.0.0.1" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Port <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="dbPort" name="port" value="3306" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Priority</label>
                                <input type="number" class="form-control" id="dbPriority" name="priority" value="0">
                                <small class="text-muted">Higher = backup first</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="dbUsername" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="dbPassword" name="password" required>
                                <small class="text-muted" id="passwordHint">Leave blank to keep existing password</small>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" id="dbDescription" name="description" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="dbIsActive" name="is_active" checked>
                                    <label class="form-check-label" for="dbIsActive">Active</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="dbIsDefault" name="is_default">
                                    <label class="form-check-label" for="dbIsDefault">Set as Default</label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" onclick="cancelDatabaseForm()">Cancel</button>
                            <button type="button" class="btn btn-info" onclick="testDatabaseConnection()">
                                <i class="fas fa-plug me-1"></i>Test Connection
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i>Save Database
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="text-center text-white">
        <div class="spinner-border spinner-border-lg mb-3" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mb-0">Processing...</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Create backup
    function createBackup(type) {
        if (!confirm(`Are you sure you want to create a ${type} backup?`)) {
            return;
        }
        
        showLoading();
        
        fetch('{{ route("backups.create") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ type: type })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('danger', data.message || 'Failed to create backup');
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('danger', 'Error: ' + error.message);
        });
    }
    
    // Run cleanup
    function runCleanup() {
        if (!confirm('Are you sure you want to run cleanup? This will delete old backups based on retention policy.')) {
            return;
        }
        
        showLoading();
        
        fetch('{{ route("backups.cleanup") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showAlert('success', `${data.message}. Deleted ${data.deleted_count} files, freed ${data.deleted_size}`);
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert('danger', data.message || 'Cleanup failed');
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('danger', 'Error: ' + error.message);
        });
    }
    
    // Toggle OneDrive
    function toggleOneDrive(enabled) {
        // This would require a backend endpoint to update config
        // For now, just show a message
        showAlert('info', `OneDrive sync ${enabled ? 'enabled' : 'disabled'}. Please update BACKUP_ONEDRIVE_ENABLED in .env file.`);
    }
    
    // Refresh stats
    function refreshStats() {
        showLoading();
        fetch('{{ route("backups.stats") }}')
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('danger', 'Error refreshing stats');
        });
    }
    
    // Check disk space
    function checkDiskSpace() {
        showLoading();
        fetch('{{ route("backups.check-disk-space") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showAlert('success', 'Disk space checked successfully' + (data.notification_sent ? '. Notification sent if needed.' : ''));
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('danger', data.message || 'Failed to check disk space');
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('danger', 'Error: ' + error.message);
        });
    }
    
    // Search functionality
    document.getElementById('searchBackups').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#backupTableBody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
    
    // Utility functions
    function showLoading() {
        document.getElementById('loadingOverlay').classList.add('show');
    }
    
    function hideLoading() {
        document.getElementById('loadingOverlay').classList.remove('show');
    }
    
    function showAlert(type, message) {
        // Create alert element
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
</script>
@endpush

