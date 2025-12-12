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
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Total Backups</h6>
                            <h3 class="mb-0 text-white">{{ $stats['total_files'] ?? 0 }}</h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-database"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card-success shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Daily Backups</h6>
                            <h3 class="mb-0 text-white">{{ $stats['daily_backups'] ?? 0 }}</h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card-info shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Monthly Backups</h6>
                            <h3 class="mb-0 text-white">{{ $stats['monthly_backups'] ?? 0 }}</h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card-warning shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Total Size</h6>
                            <h3 class="mb-0 text-white">{{ $stats['total_size_formatted'] ?? '0 B' }}</h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-hdd"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                        <div class="col-md-3">
                            <button type="button" class="btn btn-success w-100" onclick="createBackup('daily')">
                                <i class="fas fa-plus-circle me-2"></i>Create Daily Backup
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-primary w-100" onclick="createBackup('monthly')">
                                <i class="fas fa-calendar-check me-2"></i>Create Monthly Backup
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-warning w-100" onclick="runCleanup()">
                                <i class="fas fa-broom me-2"></i>Run Cleanup
                            </button>
                        </div>
                        <div class="col-md-3">
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
                                        <span class="badge backup-type-badge bg-{{ $backup['type'] == 'daily' ? 'success' : 'primary' }}">
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
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteBackup('{{ $backup['filename'] }}')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
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
    
    // Delete backup
    function deleteBackup(filename) {
        if (!confirm(`Are you sure you want to delete "${filename}"?`)) {
            return;
        }
        
        showLoading();
        
        fetch(`{{ url('backups') }}/${encodeURIComponent(filename)}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('danger', data.message || 'Failed to delete backup');
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

