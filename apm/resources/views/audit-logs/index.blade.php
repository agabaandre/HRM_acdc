@extends('layouts.app')

@section('title', 'Audit Logs')

@section('header', 'Audit Logs')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('audit-logs.cleanup') }}" class="btn btn-warning" onclick="return confirm('Are you sure you want to clean up old audit logs?')">
        <i class="bx bx-trash"></i> Cleanup Old Logs
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <!-- Statistics Cards -->
    <div class="col-12 mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="card-title text-primary">Total Logs</h6>
                        <h3 class="text-primary">{{ number_format($stats['total_logs']) }}</h3>
                        <small class="text-muted">All Time</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="card-title text-success">Recent Activity</h6>
                        <h3 class="text-success">{{ number_format($stats['recent_activity']) }}</h3>
                        <small class="text-muted">Last 24 Hours</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="card-title text-info">Top Action</h6>
                        <h3 class="text-info">{{ $stats['actions_count']->keys()->first() ?? 'N/A' }}</h3>
                        <small class="text-muted">{{ $stats['actions_count']->first() ?? 0 }} times</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="card-title text-warning">Top Table</h6>
                        <h3 class="text-warning">{{ $stats['tables_count']->keys()->first() ?? 'N/A' }}</h3>
                        <small class="text-muted">{{ $stats['tables_count']->first() ?? 0 }} records</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bx bx-filter me-2 text-primary"></i>Filters</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('audit-logs.index') }}" id="filterForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="{{ request('search') }}" placeholder="Search logs...">
                        </div>
                        <div class="col-md-2">
                            <label for="action" class="form-label">Action</label>
                            <select class="form-select" id="action" name="action">
                                <option value="">All Actions</option>
                                @foreach($actions as $action)
                                    <option value="{{ $action }}" {{ request('action') == $action ? 'selected' : '' }}>
                                        {{ $action }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="table" class="form-label">Table</label>
                            <select class="form-select" id="table" name="table">
                                <option value="">All Tables</option>
                                @foreach($tables as $table)
                                    <option value="{{ $table }}" {{ request('table') == $table ? 'selected' : '' }}>
                                        {{ $table }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bx bx-search"></i>
                                </button>
                                <a href="{{ route('audit-logs.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="bx bx-x"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bx bx-list-ul me-2 text-primary"></i>Audit Logs</h6>
                <small class="text-muted">Showing {{ $auditLogs->count() }} logs</small>
            </div>
            <div class="card-body p-0">
                @if($auditLogs->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Action</th>
                                    <th>Entity</th>
                                    <th>Table</th>
                                    <th>Causer</th>
                                    <th>Source</th>
                                    <th>Date/Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($auditLogs as $log)
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary">#{{ $log->id }}</span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $log->action == 'created' ? 'bg-success' : ($log->action == 'updated' ? 'bg-warning' : 'bg-danger') }}">
                                                {{ $log->action }}
                                            </span>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-semibold">ID: {{ $log->entity_id ?? 'N/A' }}</div>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="small">{{ $log->source_table }}</code>
                                        </td>
                                        <td>
                                            <div>
                                                @if($log->causer_id)
                                                    <div class="fw-semibold">{{ $log->causer_type }}</div>
                                                    <small class="text-muted">ID: {{ $log->causer_id }}</small>
                                                @else
                                                    <span class="text-muted">System</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $log->source ?? 'Unknown' }}</span>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-semibold">{{ \Carbon\Carbon::parse($log->created_at)->format('M j, Y') }}</div>
                                                <small class="text-muted">{{ \Carbon\Carbon::parse($log->created_at)->format('g:i A') }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#auditLogModal" 
                                                    data-log-id="{{ $log->id }}" 
                                                    data-log-table="{{ $log->source_table }}"
                                                    data-log-action="{{ $log->action }}"
                                                    data-log-entity="{{ $log->entity_id ?? 'N/A' }}"
                                                    data-log-causer-type="{{ $log->causer_type }}"
                                                    data-log-causer-id="{{ $log->causer_id }}"
                                                    data-log-source="{{ $log->source }}"
                                                    data-log-created="{{ $log->created_at }}"
                                                    data-log-old-values="{{ $log->old_values }}"
                                                    data-log-new-values="{{ $log->new_values }}"
                                                    data-log-metadata="{{ $log->metadata }}">
                                                <i class="bx bx-show"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bx bx-clipboard text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-muted">No audit logs found</h5>
                        <p class="text-muted">Try adjusting your filters or check back later.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Audit Log Details Modal -->
<div class="modal fade" id="auditLogModal" tabindex="-1" aria-labelledby="auditLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="auditLogModalLabel">
                    <i class="bx bx-info-circle me-2 text-primary"></i>
                    Audit Log Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <!-- Basic Information -->
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-info-circle me-2"></i>Basic Information
                                </h6>
                                <div class="row g-2">
                                    <div class="col-4"><strong>ID:</strong></div>
                                    <div class="col-8" id="modal-log-id">-</div>
                                    
                                    <div class="col-4"><strong>Action:</strong></div>
                                    <div class="col-8">
                                        <span class="badge" id="modal-log-action">-</span>
                                    </div>
                                    
                                    <div class="col-4"><strong>Entity ID:</strong></div>
                                    <div class="col-8" id="modal-log-entity">-</div>
                                    
                                    <div class="col-4"><strong>Table:</strong></div>
                                    <div class="col-8"><code id="modal-log-table">-</code></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Causer Information -->
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-user me-2"></i>Causer Information
                                </h6>
                                <div class="row g-2">
                                    <div class="col-4"><strong>Causer Type:</strong></div>
                                    <div class="col-8" id="modal-log-causer-type">-</div>
                                    
                                    <div class="col-4"><strong>Causer ID:</strong></div>
                                    <div class="col-8" id="modal-log-causer-id">-</div>
                                    
                                    <div class="col-4"><strong>Source:</strong></div>
                                    <div class="col-8">
                                        <span class="badge bg-info" id="modal-log-source">-</span>
                                    </div>
                                    
                                    <div class="col-4"><strong>Created:</strong></div>
                                    <div class="col-8" id="modal-log-created">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Changes -->
                    <div class="col-12" id="data-changes-section" style="display: none;">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-data me-2"></i>Data Changes
                                </h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-danger">Old Values</h6>
                                        <pre class="bg-white p-3 rounded border" id="modal-log-old-values"><code>-</code></pre>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6 class="text-success">New Values</h6>
                                        <pre class="bg-white p-3 rounded border" id="modal-log-new-values"><code>-</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Metadata -->
                    <div class="col-12" id="metadata-section" style="display: none;">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-cog me-2"></i>Metadata
                                </h6>
                                <pre class="bg-white p-3 rounded border" id="modal-log-metadata"><code>-</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Auto-submit form on filter change
document.getElementById('filterForm').addEventListener('change', function() {
    this.submit();
});

// Handle modal data population
document.getElementById('auditLogModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    
    // Get data attributes
    const logId = button.getAttribute('data-log-id');
    const logTable = button.getAttribute('data-log-table');
    const logAction = button.getAttribute('data-log-action');
    const logEntity = button.getAttribute('data-log-entity');
    const logCauserType = button.getAttribute('data-log-causer-type');
    const logCauserId = button.getAttribute('data-log-causer-id');
    const logSource = button.getAttribute('data-log-source');
    const logCreated = button.getAttribute('data-log-created');
    const logOldValues = button.getAttribute('data-log-old-values');
    const logNewValues = button.getAttribute('data-log-new-values');
    const logMetadata = button.getAttribute('data-log-metadata');
    
    // Populate basic information
    document.getElementById('modal-log-id').textContent = logId;
    document.getElementById('modal-log-entity').textContent = logEntity;
    document.getElementById('modal-log-table').textContent = logTable;
    document.getElementById('modal-log-causer-type').textContent = logCauserType || 'N/A';
    document.getElementById('modal-log-causer-id').textContent = logCauserId || 'N/A';
    document.getElementById('modal-log-source').textContent = logSource || 'Unknown';
    
    // Format and set action badge
    const actionBadge = document.getElementById('modal-log-action');
    actionBadge.textContent = logAction;
    actionBadge.className = 'badge ' + (logAction === 'created' ? 'bg-success' : (logAction === 'updated' ? 'bg-warning' : 'bg-danger'));
    
    // Format and set created date
    if (logCreated) {
        const createdDate = new Date(logCreated);
        document.getElementById('modal-log-created').innerHTML = 
            createdDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) + 
            ' ' + createdDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) +
            '<br><small class="text-muted">(' + getRelativeTime(createdDate) + ')</small>';
    }
    
    // Handle old/new values
    const dataChangesSection = document.getElementById('data-changes-section');
    if (logOldValues || logNewValues) {
        dataChangesSection.style.display = 'block';
        
        if (logOldValues) {
            try {
                const oldValues = JSON.parse(logOldValues);
                document.getElementById('modal-log-old-values').innerHTML = '<code>' + JSON.stringify(oldValues, null, 2) + '</code>';
            } catch (e) {
                document.getElementById('modal-log-old-values').innerHTML = '<code>' + logOldValues + '</code>';
            }
        } else {
            document.getElementById('modal-log-old-values').innerHTML = '<code>No old values</code>';
        }
        
        if (logNewValues) {
            try {
                const newValues = JSON.parse(logNewValues);
                document.getElementById('modal-log-new-values').innerHTML = '<code>' + JSON.stringify(newValues, null, 2) + '</code>';
            } catch (e) {
                document.getElementById('modal-log-new-values').innerHTML = '<code>' + logNewValues + '</code>';
            }
        } else {
            document.getElementById('modal-log-new-values').innerHTML = '<code>No new values</code>';
        }
    } else {
        dataChangesSection.style.display = 'none';
    }
    
    // Handle metadata
    const metadataSection = document.getElementById('metadata-section');
    if (logMetadata) {
        metadataSection.style.display = 'block';
        try {
            const metadata = JSON.parse(logMetadata);
            document.getElementById('modal-log-metadata').innerHTML = '<code>' + JSON.stringify(metadata, null, 2) + '</code>';
        } catch (e) {
            document.getElementById('modal-log-metadata').innerHTML = '<code>' + logMetadata + '</code>';
        }
    } else {
        metadataSection.style.display = 'none';
    }
});

// Helper function for relative time
function getRelativeTime(date) {
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
    return Math.floor(diffInSeconds / 86400) + ' days ago';
}
</script>
@endpush
